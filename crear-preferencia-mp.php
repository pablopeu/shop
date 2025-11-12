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
        $items[] = [
            'title' => $item['name'],
            'quantity' => $item['quantity'],
            'unit_price' => floatval($item['price']),
            'currency_id' => 'ARS'
        ];
    }

    // Add shipping if exists
    if (isset($order['shipping_cost']) && $order['shipping_cost'] > 0) {
        $items[] = [
            'title' => 'Envío',
            'quantity' => 1,
            'unit_price' => floatval($order['shipping_cost']),
            'currency_id' => 'ARS'
        ];
    }

    // Apply discount if exists
    if (isset($order['discount']) && $order['discount'] > 0) {
        $items[] = [
            'title' => 'Descuento (cupón: ' . ($order['coupon_code'] ?? 'N/A') . ')',
            'quantity' => 1,
            'unit_price' => -floatval($order['discount']),
            'currency_id' => 'ARS'
        ];
    }

    // Prepare preference data
    $preference_data = [
        'items' => $items,
        'payer' => [
            'name' => $order['customer']['name'] ?? '',
            'surname' => '',
            'email' => $order['customer']['email'] ?? '',
            'phone' => [
                'number' => $order['customer']['phone'] ?? ''
            ]
        ],
        'back_urls' => [
            'success' => get_absolute_url('/gracias.php?order=' . $order_id . '&token=' . $tracking_token),
            'failure' => get_absolute_url('/error.php?order=' . $order_id . '&token=' . $tracking_token),
            'pending' => get_absolute_url('/pendiente.php?order=' . $order_id . '&token=' . $tracking_token)
        ],
        'auto_return' => 'approved',
        'external_reference' => $order_id,
        'notification_url' => get_absolute_url('/webhook.php'),
        'statement_descriptor' => substr($site_config['site_name'], 0, 22), // Max 22 chars
        'metadata' => [
            'order_id' => $order_id,
            'tracking_token' => $tracking_token
        ]
    ];

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
        error_log("Error creating preference: " . $response);
        throw new Exception('Error al crear preferencia de pago');
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
