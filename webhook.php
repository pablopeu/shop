<?php
/**
 * Mercadopago Webhook
 * Receives payment notifications from Mercadopago
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mercadopago.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/telegram.php';

// Log webhook for debugging
function log_webhook($message, $data = []) {
    $log_file = __DIR__ . '/data/webhook_log.json';

    // Ensure data directory exists
    $data_dir = __DIR__ . '/data';
    if (!is_dir($data_dir)) {
        mkdir($data_dir, 0755, true);
    }

    $logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];

    $logs[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'data' => $data
    ];

    // Keep only last 100 logs
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100);
    }

    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT));
}

/**
 * Validate X-Signature header from Mercadopago
 * Protects against fraudulent notifications
 */
function validate_mercadopago_signature($request_data, $headers, $secret_key) {
    // Extract x-signature header
    $signature_header = $headers['x-signature'] ?? $headers['X-Signature'] ?? '';
    $request_id = $headers['x-request-id'] ?? $headers['X-Request-Id'] ?? '';

    if (empty($signature_header) || empty($request_id)) {
        log_webhook('Missing required headers for signature validation', [
            'has_signature' => !empty($signature_header),
            'has_request_id' => !empty($request_id)
        ]);
        return false;
    }

    // Parse signature header: "ts=1234567890,v1=abc123..."
    $signature_parts = [];
    foreach (explode(',', $signature_header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $signature_parts[trim($kv[0])] = trim($kv[1]);
        }
    }

    $ts = $signature_parts['ts'] ?? '';
    $received_hash = $signature_parts['v1'] ?? '';

    if (empty($ts) || empty($received_hash)) {
        log_webhook('Invalid signature header format', ['signature_header' => $signature_header]);
        return false;
    }

    // Build the manifest for validation
    // Format: id:{data_id};request-id:{request_id};ts:{timestamp}
    $data_id = $request_data['data']['id'] ?? '';
    $manifest = "id:{$data_id};request-id:{$request_id};ts:{$ts}";

    // Calculate expected signature
    $expected_hash = hash_hmac('sha256', $manifest, $secret_key);

    // Compare signatures (constant-time comparison to prevent timing attacks)
    $is_valid = hash_equals($expected_hash, $received_hash);

    if (!$is_valid) {
        log_webhook('Signature validation failed', [
            'manifest' => $manifest,
            'expected' => $expected_hash,
            'received' => $received_hash,
            'secret_key_length' => strlen($secret_key)
        ]);
    }

    return $is_valid;
}

/**
 * Validate timestamp to prevent replay attacks
 */
function validate_timestamp($signature_header, $max_age_minutes = 5) {
    // Parse signature header to get timestamp
    $signature_parts = [];
    foreach (explode(',', $signature_header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) === 2) {
            $signature_parts[trim($kv[0])] = trim($kv[1]);
        }
    }

    $ts = $signature_parts['ts'] ?? '';

    if (empty($ts) || !is_numeric($ts)) {
        log_webhook('Invalid or missing timestamp in signature', ['ts' => $ts]);
        return false;
    }

    // Convert to milliseconds
    $current_ts = time() * 1000;
    $max_age_ms = $max_age_minutes * 60 * 1000;

    $age = abs($current_ts - (int)$ts);

    if ($age > $max_age_ms) {
        log_webhook('Timestamp too old or in future', [
            'timestamp' => $ts,
            'current' => $current_ts,
            'age_seconds' => $age / 1000,
            'max_age_minutes' => $max_age_minutes
        ]);
        return false;
    }

    return true;
}

/**
 * Validate IP address is from Mercadopago
 */
function validate_mercadopago_ip($ip) {
    // Official Mercadopago IP ranges
    // Source: https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks
    $allowed_ranges = [
        '209.225.49.0/24',
        '216.33.197.0/24',
        '216.33.196.0/24',
        '52.67.0.0/16',      // AWS South America (São Paulo)
        '54.94.0.0/16',      // AWS South America (São Paulo)
        '54.232.0.0/16',     // AWS South America (São Paulo)
    ];

    foreach ($allowed_ranges as $range) {
        if (ip_in_range($ip, $range)) {
            return true;
        }
    }

    log_webhook('IP not in Mercadopago whitelist', ['ip' => $ip]);
    return false;
}

/**
 * Check if IP is in CIDR range
 */
function ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) {
        $range .= '/32';
    }

    list($subnet, $bits) = explode('/', $range);

    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask = -1 << (32 - (int)$bits);
    $subnet_long &= $mask;

    return ($ip_long & $mask) == $subnet_long;
}

/**
 * Rate limiting to prevent DoS attacks
 */
function check_rate_limit($max_requests = 100, $window_seconds = 60) {
    $rate_limit_file = __DIR__ . '/data/webhook_rate_limit.json';
    $current_time = time();

    // Load rate limit data
    $rate_data = file_exists($rate_limit_file) ?
        json_decode(file_get_contents($rate_limit_file), true) : [];

    // Clean old entries
    $rate_data = array_filter($rate_data, function($timestamp) use ($current_time, $window_seconds) {
        return $timestamp > ($current_time - $window_seconds);
    });

    // Check if limit exceeded
    if (count($rate_data) >= $max_requests) {
        log_webhook('Rate limit exceeded', [
            'requests_in_window' => count($rate_data),
            'max_requests' => $max_requests,
            'window_seconds' => $window_seconds
        ]);
        return false;
    }

    // Add current request
    $rate_data[] = $current_time;

    // Save rate limit data
    file_put_contents($rate_limit_file, json_encode(array_values($rate_data)));

    return true;
}

/**
 * Get all headers (case-insensitive)
 */
function get_request_headers() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$header_name] = $value;
            // Also store lowercase version for case-insensitive access
            $headers[strtolower($header_name)] = $value;
        }
    }
    return $headers;
}

// Handle GET requests (Mercadopago validation)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    log_webhook('GET request received - Mercadopago validation', ['query' => $_GET]);
    http_response_code(200);
    exit('OK');
}

// Get webhook data from POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Get all request headers
$headers = get_request_headers();

// Get client IP
$client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($client_ip, ',') !== false) {
    $client_ip = trim(explode(',', $client_ip)[0]);
}

log_webhook('Webhook received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'input' => $input,
    'parsed_data' => $data,
    'query' => $_GET,
    'client_ip' => $client_ip,
    'has_signature' => !empty($headers['x-signature'] ?? '')
]);

// Validate webhook
if (!$data || !isset($data['type'])) {
    log_webhook('Invalid webhook data', ['data' => $data, 'raw_input' => $input]);
    http_response_code(400);
    exit('Invalid data');
}

// Get payment config
$payment_config = read_json(__DIR__ . '/config/payment.json');
$sandbox_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
$sandbox_mode = ($sandbox_mode === 'sandbox');

$access_token = $sandbox_mode ?
    $payment_config['mercadopago']['access_token_sandbox'] :
    $payment_config['mercadopago']['access_token_prod'];

$webhook_secret = $sandbox_mode ?
    ($payment_config['mercadopago']['webhook_secret_sandbox'] ?? '') :
    ($payment_config['mercadopago']['webhook_secret_prod'] ?? '');

$security_config = $payment_config['mercadopago']['webhook_security'] ?? [
    'validate_signature' => true,
    'validate_timestamp' => true,
    'validate_ip' => true,
    'max_timestamp_age_minutes' => 5
];

if (empty($access_token)) {
    log_webhook('No access token configured');
    http_response_code(500);
    exit('Not configured');
}

// ========== SECURITY VALIDATIONS ==========

// 1. Rate Limiting (always check to prevent DoS)
if (!check_rate_limit(100, 60)) {
    http_response_code(429);
    exit('Too many requests');
}

// 2. IP Validation (if enabled)
if ($security_config['validate_ip'] ?? true) {
    if (!validate_mercadopago_ip($client_ip)) {
        log_webhook('IP validation failed - rejecting webhook', ['ip' => $client_ip]);
        http_response_code(403);
        exit('Forbidden');
    }
}

// 3. X-Signature Validation (if enabled and secret is configured)
if (($security_config['validate_signature'] ?? true) && !empty($webhook_secret)) {
    if (!validate_mercadopago_signature($data, $headers, $webhook_secret)) {
        log_webhook('Signature validation failed - rejecting webhook');
        http_response_code(401);
        exit('Unauthorized');
    }
    log_webhook('Signature validation passed');
} elseif (($security_config['validate_signature'] ?? true) && empty($webhook_secret)) {
    log_webhook('WARNING: Signature validation enabled but webhook secret not configured!', [
        'sandbox_mode' => $sandbox_mode
    ]);
}

// 4. Timestamp Validation (if enabled)
if ($security_config['validate_timestamp'] ?? true) {
    $signature_header = $headers['x-signature'] ?? '';
    if (!empty($signature_header)) {
        $max_age = $security_config['max_timestamp_age_minutes'] ?? 5;
        if (!validate_timestamp($signature_header, $max_age)) {
            log_webhook('Timestamp validation failed - possible replay attack');
            http_response_code(401);
            exit('Unauthorized');
        }
        log_webhook('Timestamp validation passed');
    }
}

log_webhook('All security validations passed - processing webhook');

// Handle payment notification
if ($data['type'] === 'payment') {
    try {
        $payment_id = $data['data']['id'] ?? null;

        if (!$payment_id) {
            log_webhook('No payment ID in webhook');
            http_response_code(400);
            exit('No payment ID');
        }

        // Get payment details from Mercadopago
        $mp = new MercadoPago($access_token, $sandbox_mode);

        try {
            $payment = $mp->getPayment($payment_id);
        } catch (Exception $e) {
            log_webhook('Error getting payment from MP API', [
                'payment_id' => $payment_id,
                'error' => $e->getMessage()
            ]);
            // If it's a test payment that doesn't exist, return 200 to avoid retries
            http_response_code(200);
            exit('Payment not found in MP');
        }

        log_webhook('Payment details retrieved', ['payment' => $payment]);

        // Find order by external_reference (order ID)
        $order_id = $payment['external_reference'] ?? null;

        if (!$order_id) {
            log_webhook('No external reference in payment');
            http_response_code(200); // Acknowledge but don't process
            exit('OK');
        }

        // Load orders
        $orders_file = __DIR__ . '/data/orders.json';

        if (!file_exists($orders_file)) {
            log_webhook('Orders file does not exist');
            http_response_code(200);
            exit('Orders file not found');
        }

        $orders_data = read_json($orders_file);

        if (!isset($orders_data['orders']) || !is_array($orders_data['orders'])) {
            log_webhook('Invalid orders data structure');
            http_response_code(200);
            exit('Invalid orders data');
        }

        $order_index = null;
        foreach ($orders_data['orders'] as $index => $order) {
            if ($order['id'] === $order_id) {
                $order_index = $index;
                break;
            }
        }

        if ($order_index === null) {
            log_webhook('Order not found', ['order_id' => $order_id]);
            http_response_code(200);
            exit('Order not found');
        }

        $order = $orders_data['orders'][$order_index];

        // Update order based on payment status
        $payment_status = $payment['status'];
        $status_detail = $payment['status_detail'];

        log_webhook('Processing payment status', [
            'order_id' => $order_id,
            'payment_status' => $payment_status,
            'status_detail' => $status_detail
        ]);

        // Map Mercadopago status to order status
        $new_order_status = null;
        $restore_stock = false;

        switch ($payment_status) {
            case 'approved':
                $new_order_status = 'cobrada';
                break;

            case 'authorized':
                // Payment authorized but not yet captured - treat as pending
                $new_order_status = 'pendiente';
                log_webhook('Payment authorized - pending capture', ['order_id' => $order_id]);
                break;

            case 'pending':
            case 'in_process':
                $new_order_status = 'pendiente';
                break;

            case 'in_mediation':
                // Payment is being disputed - keep current status but log it
                $new_order_status = 'pendiente';
                log_webhook('Payment in mediation (dispute)', [
                    'order_id' => $order_id,
                    'status_detail' => $status_detail
                ]);
                break;

            case 'rejected':
            case 'cancelled':
                $new_order_status = 'rechazada';
                $restore_stock = true; // Restore stock if payment was rejected
                break;

            case 'refunded':
            case 'charged_back':
                $new_order_status = 'cancelada';
                $restore_stock = true;
                break;

            default:
                // Unknown status - log it and don't change order status
                log_webhook('Unknown payment status received', [
                    'order_id' => $order_id,
                    'payment_status' => $payment_status,
                    'status_detail' => $status_detail
                ]);
                http_response_code(200);
                exit('OK - Unknown status');
        }

        if ($new_order_status && $order['status'] !== $new_order_status) {
            // Update order status
            $orders_data['orders'][$order_index]['status'] = $new_order_status;
            $orders_data['orders'][$order_index]['payment_status'] = $payment_status;
            $orders_data['orders'][$order_index]['payment_status_detail'] = $status_detail;
            $orders_data['orders'][$order_index]['payment_id'] = $payment_id;

            // Update/create complete Mercadopago data
            $orders_data['orders'][$order_index]['mercadopago_data'] = [
                'payment_id' => $payment['id'],
                'status' => $payment['status'],
                'status_detail' => $payment['status_detail'],
                'transaction_amount' => $payment['transaction_amount'] ?? null,
                'currency_id' => $payment['currency_id'] ?? null,
                'date_created' => $payment['date_created'] ?? null,
                'date_approved' => $payment['date_approved'] ?? null,
                'date_last_updated' => $payment['date_last_updated'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'payment_type_id' => $payment['payment_type_id'] ?? null,
                'installments' => $payment['installments'] ?? 1,
                'description' => $payment['description'] ?? null,
                'capture' => $payment['capture'] ?? null,
                'external_reference' => $payment['external_reference'] ?? null,
                'payer_email' => $payment['payer']['email'] ?? null,
                'payer_identification' => $payment['payer']['identification']['number'] ?? null,
                'card_last_four_digits' => $payment['card']['last_four_digits'] ?? null,
                'card_first_six_digits' => $payment['card']['first_six_digits'] ?? null,
                'webhook_received_at' => date('Y-m-d H:i:s'),
            ];

            // Add to status history
            if (!isset($orders_data['orders'][$order_index]['status_history'])) {
                $orders_data['orders'][$order_index]['status_history'] = [];
            }

            $orders_data['orders'][$order_index]['status_history'][] = [
                'status' => $new_order_status,
                'date' => date('Y-m-d H:i:s'),
                'user' => 'mercadopago_webhook',
                'payment_status' => $payment_status
            ];

            // Handle stock
            if ($payment_status === 'approved' && !($order['stock_reduced'] ?? false)) {
                // Reduce stock on approved payment
                foreach ($order['items'] as $item) {
                    reduce_product_stock($item['product_id'], $item['quantity']);
                }
                $orders_data['orders'][$order_index]['stock_reduced'] = true;
                log_webhook('Stock reduced', ['order_id' => $order_id]);
            } elseif ($restore_stock && ($order['stock_reduced'] ?? false)) {
                // Restore stock on rejected/cancelled payment
                foreach ($order['items'] as $item) {
                    restore_product_stock($item['product_id'], $item['quantity']);
                }
                $orders_data['orders'][$order_index]['stock_reduced'] = false;
                log_webhook('Stock restored', ['order_id' => $order_id]);
            }

            // Save orders
            write_json($orders_file, $orders_data);

            log_webhook('Order updated successfully', [
                'order_id' => $order_id,
                'new_status' => $new_order_status,
                'payment_status' => $payment_status
            ]);

            // Send notifications based on new status
            $updated_order = $orders_data['orders'][$order_index];

            if ($new_order_status === 'cobrada') {
                // Payment approved
                send_payment_approved_email($updated_order);
                send_telegram_payment_approved($updated_order);
            } elseif ($new_order_status === 'pendiente' && in_array($payment_status, ['pending', 'in_process', 'authorized', 'in_mediation'])) {
                // Payment pending
                send_payment_pending_email($updated_order);
            } elseif ($new_order_status === 'rechazada') {
                // Payment rejected
                send_payment_rejected_email($updated_order, $status_detail);
                send_telegram_payment_rejected($updated_order);
            } elseif ($new_order_status === 'cancelada' && in_array($payment_status, ['refunded', 'charged_back'])) {
                // Refunded or charged back - don't send regular rejection email
                // Chargeback notification will be sent separately
                log_webhook('Order cancelled due to refund/chargeback - no customer notification sent');
            }

            http_response_code(200);
            exit('OK');
        }

        http_response_code(200);
        exit('OK - No changes');

    } catch (Exception $e) {
        log_webhook('Error processing payment webhook', ['error' => $e->getMessage()]);
        http_response_code(500);
        exit('Error: ' . $e->getMessage());
    }
}

// Handle chargeback notification
if ($data['type'] === 'chargebacks') {
    try {
        $chargeback_id = $data['data']['id'] ?? null;

        if (!$chargeback_id) {
            log_webhook('No chargeback ID in webhook');
            http_response_code(400);
            exit('No chargeback ID');
        }

        log_webhook('Chargeback notification received', [
            'chargeback_id' => $chargeback_id,
            'action' => $data['action'] ?? 'unknown'
        ]);

        // Get payment ID from chargeback data if available
        $payment_id = $data['data']['payment_id'] ?? null;

        if ($payment_id) {
            // Get payment details to find the order
            $mp = new MercadoPago($access_token, $sandbox_mode);

            try {
                $payment = $mp->getPayment($payment_id);
                $order_id = $payment['external_reference'] ?? null;

                if ($order_id) {
                    // Load orders
                    $orders_file = __DIR__ . '/data/orders.json';
                    $orders_data = read_json($orders_file);

                    // Find order
                    $order_index = null;
                    foreach ($orders_data['orders'] as $index => $order) {
                        if ($order['id'] === $order_id) {
                            $order_index = $index;
                            break;
                        }
                    }

                    if ($order_index !== null) {
                        $order = $orders_data['orders'][$order_index];

                        // Update order with chargeback info
                        if (!isset($orders_data['orders'][$order_index]['chargebacks'])) {
                            $orders_data['orders'][$order_index]['chargebacks'] = [];
                        }

                        $orders_data['orders'][$order_index]['chargebacks'][] = [
                            'chargeback_id' => $chargeback_id,
                            'payment_id' => $payment_id,
                            'action' => $data['action'] ?? 'unknown',
                            'date' => date('Y-m-d H:i:s'),
                            'data' => $data
                        ];

                        // If chargeback is created/lost, mark order as charged_back and restore stock
                        if (in_array($data['action'] ?? '', ['created', 'lost'])) {
                            $old_status = $order['status'];
                            $orders_data['orders'][$order_index]['status'] = 'cancelada';

                            // Restore stock if it was reduced
                            if ($order['stock_reduced'] ?? false) {
                                foreach ($order['items'] as $item) {
                                    restore_product_stock($item['product_id'], $item['quantity']);
                                }
                                $orders_data['orders'][$order_index]['stock_reduced'] = false;
                                log_webhook('Stock restored due to chargeback', ['order_id' => $order_id]);
                            }

                            // Add to status history
                            if (!isset($orders_data['orders'][$order_index]['status_history'])) {
                                $orders_data['orders'][$order_index]['status_history'] = [];
                            }

                            $orders_data['orders'][$order_index]['status_history'][] = [
                                'status' => 'cancelada',
                                'date' => date('Y-m-d H:i:s'),
                                'user' => 'mercadopago_webhook',
                                'reason' => 'chargeback_' . ($data['action'] ?? 'unknown'),
                                'previous_status' => $old_status
                            ];

                            log_webhook('Order marked as charged_back', [
                                'order_id' => $order_id,
                                'chargeback_id' => $chargeback_id,
                                'action' => $data['action']
                            ]);
                        }

                        // Save orders
                        write_json($orders_file, $orders_data);

                        // Send chargeback alert notifications
                        $updated_order = $orders_data['orders'][$order_index];
                        $chargeback_data = [
                            'chargeback_id' => $chargeback_id,
                            'payment_id' => $payment_id,
                            'action' => $data['action'] ?? 'unknown',
                            'date' => date('Y-m-d H:i:s')
                        ];

                        send_admin_chargeback_alert($updated_order, $chargeback_data);
                        send_telegram_chargeback_alert($updated_order, $chargeback_data);

                        log_webhook('Chargeback alerts sent', [
                            'order_id' => $order_id,
                            'chargeback_id' => $chargeback_id
                        ]);
                    }
                }
            } catch (Exception $e) {
                log_webhook('Error processing chargeback - payment not found', [
                    'payment_id' => $payment_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        http_response_code(200);
        exit('OK - Chargeback processed');

    } catch (Exception $e) {
        log_webhook('Error processing chargeback webhook', ['error' => $e->getMessage()]);
        http_response_code(500);
        exit('Error: ' . $e->getMessage());
    }
}

// Handle merchant_order notification
if ($data['type'] === 'merchant_order') {
    try {
        $merchant_order_id = $data['data']['id'] ?? null;

        if (!$merchant_order_id) {
            log_webhook('No merchant_order ID in webhook');
            http_response_code(400);
            exit('No merchant_order ID');
        }

        log_webhook('Merchant order notification received', [
            'merchant_order_id' => $merchant_order_id,
            'action' => $data['action'] ?? 'unknown'
        ]);

        // For Checkout Bricks, we primarily rely on payment webhooks
        // Merchant orders are more relevant for Checkout Pro
        // We'll log it but not take specific action unless needed

        http_response_code(200);
        exit('OK - Merchant order logged');

    } catch (Exception $e) {
        log_webhook('Error processing merchant_order webhook', ['error' => $e->getMessage()]);
        http_response_code(500);
        exit('Error: ' . $e->getMessage());
    }
}

// Acknowledge other notification types
log_webhook('Other notification type', ['type' => $data['type']]);
http_response_code(200);
exit('OK');

/**
 * Reduce product stock
 */
function reduce_product_stock($product_id, $quantity) {
    $products_file = __DIR__ . '/data/products.json';
    $products_data = read_json($products_file);

    foreach ($products_data['products'] as &$product) {
        if ($product['id'] === $product_id) {
            $product['stock'] = max(0, ($product['stock'] ?? 0) - $quantity);
            break;
        }
    }

    write_json($products_file, $products_data);
}

/**
 * Restore product stock
 */
function restore_product_stock($product_id, $quantity) {
    $products_file = __DIR__ . '/data/products.json';
    $products_data = read_json($products_file);

    foreach ($products_data['products'] as &$product) {
        if ($product['id'] === $product_id) {
            $product['stock'] = ($product['stock'] ?? 0) + $quantity;
            break;
        }
    }

    write_json($products_file, $products_data);
}
