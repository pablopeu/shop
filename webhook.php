<?php
/**
 * Mercadopago Webhook
 * Receives payment notifications from Mercadopago
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mercadopago.php';
require_once __DIR__ . '/includes/orders.php';

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

// Handle GET requests (Mercadopago validation)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    log_webhook('GET request received - Mercadopago validation', ['query' => $_GET]);
    http_response_code(200);
    exit('OK');
}

// Get webhook data from POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

log_webhook('Webhook received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'input' => $input,
    'parsed_data' => $data,
    'query' => $_GET
]);

// Validate webhook
if (!$data || !isset($data['type'])) {
    log_webhook('Invalid webhook data', ['data' => $data, 'raw_input' => $input]);
    http_response_code(400);
    exit('Invalid data');
}

// Get payment config
$payment_config = read_json(__DIR__ . '/config/payment.json');
$sandbox_mode = $payment_config['mercadopago']['sandbox_mode'] ?? true;
$access_token = $sandbox_mode ?
    $payment_config['mercadopago']['access_token_sandbox'] :
    $payment_config['mercadopago']['access_token_prod'];

if (empty($access_token)) {
    log_webhook('No access token configured');
    http_response_code(500);
    exit('Not configured');
}

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

            case 'pending':
            case 'in_process':
                $new_order_status = 'pendiente';
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
        }

        if ($new_order_status && $order['status'] !== $new_order_status) {
            // Update order status
            $orders_data['orders'][$order_index]['status'] = $new_order_status;
            $orders_data['orders'][$order_index]['payment_status'] = $payment_status;
            $orders_data['orders'][$order_index]['payment_status_detail'] = $status_detail;
            $orders_data['orders'][$order_index]['payment_id'] = $payment_id;

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

            // TODO: Send email to customer about status change
            // require_once __DIR__ . '/includes/email.php';
            // send_order_status_email($order, $new_order_status);

            http_response_code(200);
            exit('OK');
        }

        http_response_code(200);
        exit('OK - No changes');

    } catch (Exception $e) {
        log_webhook('Error processing webhook', ['error' => $e->getMessage()]);
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
