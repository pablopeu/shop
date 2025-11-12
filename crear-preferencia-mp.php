<?php
/**
 * Crear Preferencia de Pago en Mercadopago
 * Para usar con Wallet Brick
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mercadopago.php';

header('Content-Type: application/json');

session_start();

/**
 * Generate absolute URL with protocol, domain and BASE_PATH
 * Required for Mercadopago back_urls which need full URLs
 */
function get_absolute_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $relative_url = url($path);
    return $protocol . '://' . $host . $relative_url;
}

try {
    // Get order ID and token from request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['order_id']) || !isset($data['tracking_token'])) {
        throw new Exception('Faltan parámetros requeridos');
    }

    $order_id = sanitize_input($data['order_id']);
    $tracking_token = sanitize_input($data['tracking_token']);

    // Load order
    $orders_file = __DIR__ . '/data/orders.json';
    $orders_data = read_json($orders_file);

    $order = null;
    foreach ($orders_data['orders'] as $o) {
        if ($o['id'] === $order_id && $o['tracking_token'] === $tracking_token) {
            $order = $o;
            break;
        }
    }

    if (!$order) {
        throw new Exception('Orden no encontrada');
    }

    // Get payment config
    $payment_config = read_json(__DIR__ . '/config/payment.json');
    $payment_credentials = get_payment_credentials();
    $site_config = read_json(__DIR__ . '/config/site.json');

    // Determine mode
    $mode = $payment_config['mercadopago']['mode'] ?? ($payment_config['mercadopago']['sandbox_mode'] ?? true ? 'sandbox' : 'production');
    $sandbox_mode = ($mode === 'sandbox');

    $access_token = $sandbox_mode ?
        ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
        ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

    if (empty($access_token)) {
        throw new Exception('Mercadopago no configurado');
    }

    // Calculate total in ARS (MP only accepts ARS)
    $currency_config = read_json(__DIR__ . '/config/currency.json');
    $exchange_rate = $currency_config['exchange_rate'] ?? 1500;

    $total_ars = $order['total'];
    if ($order['currency'] === 'USD') {
        $total_ars = $order['total'] * $exchange_rate;
    }

    // Prepare items for preference
    $items = [];
    foreach ($order['items'] as $item) {
        // Convert price to ARS if order is in USD
        $item_price_ars = floatval($item['price']);
        if ($order['currency'] === 'USD') {
            $item_price_ars = $item_price_ars * $exchange_rate;
        }

        // Validate unit price (must be > 0)
        if ($item_price_ars <= 0) {
            throw new Exception("Item '{$item['name']}' tiene precio inválido: {$item_price_ars}");
        }

        // Sanitize and truncate title (max 256 chars for Mercadopago)
        $title = strip_tags($item['name']);
        $title = mb_substr($title, 0, 256);

        $items[] = [
            'title' => $title,
            'quantity' => intval($item['quantity']),
            'unit_price' => round($item_price_ars, 2),
            'currency_id' => 'ARS'
        ];
    }

    // Add shipping if exists
    if (isset($order['shipping_cost']) && $order['shipping_cost'] > 0) {
        $shipping_ars = floatval($order['shipping_cost']);
        if ($order['currency'] === 'USD') {
            $shipping_ars = $shipping_ars * $exchange_rate;
        }

        $items[] = [
            'title' => 'Envío',
            'quantity' => 1,
            'unit_price' => round($shipping_ars, 2),
            'currency_id' => 'ARS'
        ];
    }

    // Apply discount if exists
    if (isset($order['discount']) && $order['discount'] > 0) {
        $discount_ars = floatval($order['discount']);
        if ($order['currency'] === 'USD') {
            $discount_ars = $discount_ars * $exchange_rate;
        }

        $items[] = [
            'title' => 'Descuento (cupón: ' . ($order['coupon_code'] ?? 'N/A') . ')',
            'quantity' => 1,
            'unit_price' => -round($discount_ars, 2),
            'currency_id' => 'ARS'
        ];
    }

    // Validate total items value
    $calculated_total = 0;
    foreach ($items as $item) {
        $calculated_total += $item['unit_price'] * $item['quantity'];
    }
    $calculated_total = round($calculated_total, 2);

    // Validate email (Mercadopago requirement)
    $customer_email = $order['customer_email'] ?? '';
    if (empty($customer_email) || !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Email inválido: " . $customer_email);
    }

    // Validate customer name
    $customer_name = trim($order['customer_name'] ?? '');
    if (empty($customer_name)) {
        throw new Exception("Nombre de cliente requerido");
    }
    // Limit name to 256 chars
    $customer_name = mb_substr($customer_name, 0, 256);

    // Clean phone number (remove non-numeric characters except +)
    $phone = $order['customer_phone'] ?? '';
    $phone_cleaned = preg_replace('/[^0-9+]/', '', $phone);

    // Extract area code and number
    // Example: "+54 1154099160" -> area_code: "11", number: "54099160"
    $area_code = '';
    $phone_number = $phone_cleaned;

    // If phone starts with +, extract country code
    if (strpos($phone_cleaned, '+') === 0) {
        // Remove the + and country code
        $phone_cleaned = ltrim($phone_cleaned, '+');
        // For Argentina (+54), extract area code from remaining digits
        if (strlen($phone_cleaned) >= 10) {
            // Skip country code (first 2 digits for Argentina)
            $phone_without_country = substr($phone_cleaned, 2);
            if (strlen($phone_without_country) >= 10) {
                $area_code = substr($phone_without_country, 0, 2);
                $phone_number = substr($phone_without_country, 2);
            } else {
                $phone_number = $phone_without_country;
            }
        }
    }

    // Prepare preference data
    $preference_data = [
        'items' => $items,
        'payer' => [
            'name' => $customer_name,
            'surname' => '',
            'email' => $customer_email
        ],
        'back_urls' => [
            'success' => get_absolute_url('/gracias.php?order=' . $order_id . '&token=' . $tracking_token),
            'failure' => get_absolute_url('/error.php?order=' . $order_id . '&token=' . $tracking_token),
            'pending' => get_absolute_url('/pendiente.php?order=' . $order_id . '&token=' . $tracking_token)
        ],
        'auto_return' => 'approved',
        'external_reference' => $order_id,
        'notification_url' => get_absolute_url('/webhook.php'),
        'statement_descriptor' => mb_substr($site_config['site_name'], 0, 22), // Max 22 chars
        'metadata' => [
            'order_id' => $order_id,
            'tracking_token' => $tracking_token
        ]
    ];

    // Only add phone if we have valid data
    if (!empty($phone_number)) {
        $preference_data['payer']['phone'] = [
            'area_code' => $area_code ?: '',
            'number' => $phone_number
        ];
    }

    // Log preference data being sent (for debugging)
    error_log("Creating MP preference for order " . $order_id . " - Items: " . count($items) . ", Total ARS: " . $total_ars);
    error_log("Preference data: " . json_encode($preference_data, JSON_PRETTY_PRINT));

    // Also save to data directory for easy access
    $log_file = __DIR__ . '/data/mp_preference_log.json';
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'order_id' => $order_id,
        'total_ars' => $total_ars,
        'calculated_total' => $calculated_total,
        'preference_data' => $preference_data
    ];

    $existing_logs = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
    if (!is_array($existing_logs)) $existing_logs = [];

    $existing_logs[] = $log_data;
    // Keep only last 20 logs
    if (count($existing_logs) > 20) {
        $existing_logs = array_slice($existing_logs, -20);
    }

    file_put_contents($log_file, json_encode($existing_logs, JSON_PRETTY_PRINT));

    // Create preference in Mercadopago
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/checkout/preferences');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 201) {
        $error_details = json_decode($response, true);
        error_log("MP API Error (HTTP $http_code): " . $response);
        $error_message = 'Error al crear preferencia de pago';
        if (isset($error_details['message'])) {
            $error_message .= ': ' . $error_details['message'];
        }
        throw new Exception($error_message);
    }

    $preference = json_decode($response, true);

    if (!isset($preference['id'])) {
        throw new Exception('Respuesta inválida de Mercadopago');
    }

    // Log preference creation
    error_log("Preference created: " . $preference['id'] . " for order " . $order_id);

    echo json_encode([
        'success' => true,
        'preference_id' => $preference['id'],
        'init_point' => $preference['init_point'] ?? null,
        'sandbox_init_point' => $preference['sandbox_init_point'] ?? null
    ]);

} catch (Exception $e) {
    error_log("Error in crear-preferencia-mp.php: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
