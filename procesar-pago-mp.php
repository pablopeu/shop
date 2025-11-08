<?php
/**
 * Process Mercadopago Payment - Backend
 * Procesa el pago usando la API de Mercadopago
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mercadopago.php';
require_once __DIR__ . '/includes/orders.php';

header('Content-Type: application/json');

// Get JSON data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Validate required fields
if (!isset($data['order_id']) || !isset($data['tracking_token']) || !isset($data['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$order_id = $data['order_id'];
$tracking_token = $data['tracking_token'];
$card_token = $data['token']; // Token de la tarjeta generado por MP SDK

// Log received data for debugging
error_log("Payment request received: Order ID: $order_id, Card token present: " . (isset($card_token) ? 'Yes' : 'No'));

// Load order
$orders_file = __DIR__ . '/data/orders.json';
$orders_data = read_json($orders_file);

$order = null;
$order_index = null;
foreach ($orders_data['orders'] as $index => $o) {
    if ($o['id'] === $order_id && $o['tracking_token'] === $tracking_token) {
        $order = $o;
        $order_index = $index;
        break;
    }
}

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Get payment config
$payment_config = read_json(__DIR__ . '/config/payment.json');
$sandbox_mode = $payment_config['mercadopago']['sandbox_mode'] ?? true;
$access_token = $sandbox_mode ?
    $payment_config['mercadopago']['access_token_sandbox'] :
    $payment_config['mercadopago']['access_token_prod'];

if (empty($access_token)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mercadopago not configured']);
    exit;
}

try {
    // Calculate total in ARS
    $currency_config = read_json(__DIR__ . '/config/currency.json');
    $exchange_rate = $currency_config['exchange_rate'] ?? 1500;

    $total_ars = $order['total'];
    if ($order['currency'] === 'USD') {
        $total_ars = $order['total'] * $exchange_rate;
    }

    // Prepare payment data for Mercadopago
    $payment_data = [
        'transaction_amount' => floatval($total_ars),
        'token' => $card_token, // Token de la tarjeta generado por MP SDK
        'description' => 'Orden #' . $order['order_number'],
        'installments' => intval($data['installments'] ?? 1),
        'payment_method_id' => $data['payment_method_id'],
        'issuer_id' => $data['issuer_id'] ?? null,
        'payer' => [
            'email' => $data['payer']['email'],
            'identification' => [
                'type' => $data['payer']['identification']['type'],
                'number' => $data['payer']['identification']['number']
            ]
        ],
        'external_reference' => $order_id,
        'statement_descriptor' => 'ORDEN-' . $order['order_number']
    ];

    // Log payment data for debugging (without sensitive data)
    error_log("Creating payment - Amount: $total_ars ARS, Payment method: {$data['payment_method_id']}, Token length: " . strlen($card_token));

    // Process payment with Mercadopago
    $mp = new MercadoPago($access_token, $sandbox_mode);
    $payment = $mp->createPayment($payment_data);

    // Update order with payment info
    $orders_data['orders'][$order_index]['payment_id'] = $payment['id'];
    $orders_data['orders'][$order_index]['payment_status'] = $payment['status'];
    $orders_data['orders'][$order_index]['payment_status_detail'] = $payment['status_detail'];

    // Save complete Mercadopago data for reference
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
    ];

    // Update order status based on payment status
    if ($payment['status'] === 'approved') {
        $orders_data['orders'][$order_index]['status'] = 'cobrada';

        // Reduce stock
        if (!($order['stock_reduced'] ?? false)) {
            foreach ($order['items'] as $item) {
                update_stock($item['product_id'], -$item['quantity'], "Order {$order['order_number']}");
            }
            $orders_data['orders'][$order_index]['stock_reduced'] = true;
        }

        $redirect_url = '/gracias.php?order=' . $order_id . '&token=' . $tracking_token;
    } elseif ($payment['status'] === 'in_process' ||
              $payment['status'] === 'pending' ||
              $payment['status'] === 'authorized' ||
              $payment['status'] === 'in_mediation') {
        // Pending, in process, authorized, or in mediation
        $orders_data['orders'][$order_index]['status'] = 'pendiente';
        $redirect_url = '/gracias.php?order=' . $order_id . '&token=' . $tracking_token .
                       '&payment_status=' . urlencode($payment['status']) .
                       '&payment_status_detail=' . urlencode($payment['status_detail']);
    } else {
        // Rejected, cancelled, or any other status
        $orders_data['orders'][$order_index]['status'] = 'rechazada';
        $redirect_url = '/error.php?order=' . $order_id . '&token=' . $tracking_token .
                       '&payment_status=' . urlencode($payment['status']) .
                       '&payment_status_detail=' . urlencode($payment['status_detail']);
    }

    // Save order
    write_json($orders_file, $orders_data);

    // Log successful payment
    error_log("Payment processed: Order {$order_id}, Payment ID {$payment['id']}, Status {$payment['status']}");

    echo json_encode([
        'success' => true,
        'payment_id' => $payment['id'],
        'status' => $payment['status'],
        'redirect_url' => $redirect_url
    ]);

} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());

    // Save error info in order for debugging
    if (isset($order_index) && isset($orders_data['orders'][$order_index])) {
        $orders_data['orders'][$order_index]['payment_error'] = [
            'message' => $e->getMessage(),
            'date' => date('Y-m-d H:i:s'),
            'sandbox_mode' => $sandbox_mode ?? true
        ];
        write_json($orders_file, $orders_data);
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar el pago: ' . $e->getMessage()
    ]);
}
