<?php
/**
 * Reprocesar Pago de MercadoPago Manualmente
 *
 * Este script permite reprocesar un pago que quedó en estado pendiente
 * debido a un fallo en el webhook de MercadoPago.
 *
 * USO:
 * 1. Acceder desde el navegador: https://peu.net/shop/reprocesar-pago.php?payment_id=PAYMENT_ID&key=TU_CLAVE_SECRETA
 * 2. O ejecutar desde línea de comandos: php reprocesar-pago.php PAYMENT_ID
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mercadopago.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/includes/mp-logger.php';

// Clave secreta para proteger el endpoint
// NOTA: Esta es una clave fija para facilitar el acceso
define('REPROCESS_SECRET_KEY', 'peu2024secure');

// Permitir ejecución desde CLI o navegador con autenticación
$is_cli = php_sapi_name() === 'cli';
$is_authenticated = false;

if ($is_cli) {
    // Modo CLI: el payment_id se pasa como argumento
    $payment_id = $argv[1] ?? '';
    $is_authenticated = true;

    echo "\n=== Reprocesar Pago de MercadoPago ===\n\n";

    if (empty($payment_id)) {
        echo "❌ Error: Debe proporcionar el Payment ID\n";
        echo "Uso: php reprocesar-pago.php PAYMENT_ID\n\n";
        exit(1);
    }
} else {
    // Modo web: requiere autenticación
    header('Content-Type: text/html; charset=utf-8');

    $payment_id = $_GET['payment_id'] ?? '';
    $provided_key = $_GET['key'] ?? '';

    $is_authenticated = hash_equals(REPROCESS_SECRET_KEY, $provided_key);

    if (!$is_authenticated) {
        http_response_code(403);
        echo "<!DOCTYPE html><html><body>";
        echo "<h1>❌ Acceso Denegado</h1>";
        echo "<p>Clave de autenticación inválida.</p>";
        echo "</body></html>";
        exit;
    }

    if (empty($payment_id)) {
        http_response_code(400);
        echo "<!DOCTYPE html><html><body>";
        echo "<h1>❌ Error</h1>";
        echo "<p>Debe proporcionar el Payment ID en la URL.</p>";
        echo "<p>Ejemplo: <code>reprocesar-pago.php?payment_id=133535068062&key=YOUR_KEY</code></p>";
        echo "</body></html>";
        exit;
    }

    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
    echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
    echo "h1{color:#4ec9b0;} .success{color:#4ec9b0;} .error{color:#f48771;} ";
    echo ".info{color:#569cd6;} pre{background:#252526;padding:15px;border-left:3px solid #569cd6;overflow-x:auto;}</style>";
    echo "</head><body>";
    echo "<h1>🔄 Reprocesando Pago de MercadoPago</h1>";
}

try {
    // Log inicio del reprocesamiento
    log_mp_debug('MANUAL_REPROCESS', "Iniciando reprocesamiento manual del pago", [
        'payment_id' => $payment_id,
        'source' => $is_cli ? 'CLI' : 'WEB',
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    if (!$is_cli) echo "<p class='info'>🔍 Payment ID: <strong>$payment_id</strong></p>";
    else echo "🔍 Payment ID: $payment_id\n\n";

    // Get payment config and credentials
    $payment_config = read_json(__DIR__ . '/config/payment.json');
    $payment_credentials = get_payment_credentials();

    $mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
    $sandbox_mode = ($mp_mode === 'sandbox');

    $access_token = $sandbox_mode ?
        ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
        ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

    if (empty($access_token)) {
        throw new Exception('MercadoPago no está configurado correctamente');
    }

    if (!$is_cli) echo "<p class='info'>📡 Obteniendo detalles del pago desde MercadoPago API...</p>";
    else echo "📡 Obteniendo detalles del pago desde MercadoPago API...\n";

    // Get payment details from Mercadopago
    $mp = new MercadoPago($access_token, $sandbox_mode);
    $payment = $mp->getPayment($payment_id);

    // Log payment details
    log_payment_details($payment_id, $payment);

    if (!$is_cli) {
        echo "<pre>";
        echo "Estado del pago: " . ($payment['status'] ?? 'unknown') . "\n";
        echo "Monto: " . ($payment['transaction_amount'] ?? 0) . " " . ($payment['currency_id'] ?? 'ARS') . "\n";
        echo "Método: " . ($payment['payment_method_id'] ?? 'unknown') . "\n";
        echo "External Ref: " . ($payment['external_reference'] ?? 'N/A') . "\n";
        echo "</pre>";
    } else {
        echo "\nDetalles del pago:\n";
        echo "  - Estado: " . ($payment['status'] ?? 'unknown') . "\n";
        echo "  - Monto: " . ($payment['transaction_amount'] ?? 0) . " " . ($payment['currency_id'] ?? 'ARS') . "\n";
        echo "  - Método: " . ($payment['payment_method_id'] ?? 'unknown') . "\n";
        echo "  - External Ref: " . ($payment['external_reference'] ?? 'N/A') . "\n\n";
    }

    // Find order by external_reference (order ID)
    $order_id = $payment['external_reference'] ?? null;

    if (!$order_id) {
        throw new Exception('El pago no tiene external_reference (order ID)');
    }

    if (!$is_cli) echo "<p class='info'>🔍 Buscando orden: <strong>$order_id</strong></p>";
    else echo "🔍 Buscando orden: $order_id...\n";

    // Load orders
    $orders_file = __DIR__ . '/data/orders.json';

    if (!file_exists($orders_file)) {
        throw new Exception('Archivo de órdenes no encontrado');
    }

    $orders_data = read_json($orders_file);

    if (!isset($orders_data['orders']) || !is_array($orders_data['orders'])) {
        throw new Exception('Estructura de órdenes inválida');
    }

    $order_index = null;
    foreach ($orders_data['orders'] as $index => $order) {
        if ($order['id'] === $order_id) {
            $order_index = $index;
            break;
        }
    }

    if ($order_index === null) {
        throw new Exception("Orden no encontrada: $order_id");
    }

    $order = $orders_data['orders'][$order_index];
    $old_status = $order['status'];

    if (!$is_cli) echo "<p class='success'>✅ Orden encontrada - Estado actual: <strong>$old_status</strong></p>";
    else echo "✅ Orden encontrada - Estado actual: $old_status\n\n";

    // Process payment status
    $payment_status = $payment['status'];
    $status_detail = $payment['status_detail'];

    if (!$is_cli) echo "<p class='info'>🔄 Procesando estado: <strong>$payment_status</strong></p>";
    else echo "🔄 Procesando estado: $payment_status...\n";

    // Map Mercadopago status to order status
    $new_order_status = null;
    $restore_stock = false;

    switch ($payment_status) {
        case 'approved':
            $new_order_status = 'cobrada';
            break;
        case 'authorized':
            $new_order_status = 'pendiente';
            break;
        case 'pending':
        case 'in_process':
            $new_order_status = 'pendiente';
            break;
        case 'in_mediation':
            $new_order_status = 'pendiente';
            break;
        case 'rejected':
        case 'cancelled':
            $new_order_status = 'rechazada';
            $restore_stock = true;
            break;
        case 'refunded':
        case 'charged_back':
            $new_order_status = 'cancelada';
            $restore_stock = true;
            break;
        default:
            throw new Exception("Estado de pago desconocido: $payment_status");
    }

    if ($new_order_status && $order['status'] !== $new_order_status) {
        // Update order status
        $orders_data['orders'][$order_index]['status'] = $new_order_status;
        $orders_data['orders'][$order_index]['payment_status'] = $payment_status;
        $orders_data['orders'][$order_index]['payment_status_detail'] = $status_detail;
        $orders_data['orders'][$order_index]['payment_id'] = $payment_id;

        // Extract fee details and net amount
        $fee_details = $payment['fee_details'] ?? [];
        $transaction_details = $payment['transaction_details'] ?? [];

        // Calculate total fees from fee_details
        $total_fees = 0;
        $fee_breakdown = [];
        foreach ($fee_details as $fee) {
            $fee_amount = floatval($fee['amount'] ?? 0);
            $total_fees += $fee_amount;
            $fee_breakdown[] = [
                'type' => $fee['type'] ?? 'unknown',
                'amount' => $fee_amount,
                'fee_payer' => $fee['fee_payer'] ?? 'collector'
            ];
        }

        // Get net amount
        $net_received_amount = floatval($transaction_details['net_received_amount'] ?? 0);
        if ($net_received_amount == 0 && isset($payment['transaction_amount'])) {
            $net_received_amount = floatval($payment['transaction_amount']) - $total_fees;
        }

        // Update mercadopago_data
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
            'total_paid_amount' => floatval($payment['transaction_amount'] ?? 0),
            'fee_details' => $fee_breakdown,
            'total_fees' => $total_fees,
            'net_received_amount' => $net_received_amount,
            'operation_number' => $transaction_details['external_resource_url'] ?? null,
            'payment_method_reference_id' => $transaction_details['payment_method_reference_id'] ?? null,
            'acquirer_reference' => $transaction_details['acquirer_reference'] ?? null,
            'manual_reprocess_at' => date('Y-m-d H:i:s'),
        ];

        // Add to status history
        if (!isset($orders_data['orders'][$order_index]['status_history'])) {
            $orders_data['orders'][$order_index]['status_history'] = [];
        }

        $orders_data['orders'][$order_index]['status_history'][] = [
            'status' => $new_order_status,
            'date' => date('Y-m-d H:i:s'),
            'user' => 'manual_reprocess',
            'payment_status' => $payment_status,
            'note' => 'Reprocesado manualmente debido a fallo en webhook'
        ];

        // Handle stock
        if ($payment_status === 'approved' && !($order['stock_reduced'] ?? false)) {
            if (!$is_cli) echo "<p class='info'>📦 Reduciendo stock...</p>";
            else echo "📦 Reduciendo stock...\n";

            foreach ($order['items'] as $item) {
                reduce_product_stock($item['product_id'], $item['quantity']);
            }
            $orders_data['orders'][$order_index]['stock_reduced'] = true;
        } elseif ($restore_stock && ($order['stock_reduced'] ?? false)) {
            if (!$is_cli) echo "<p class='info'>📦 Restaurando stock...</p>";
            else echo "📦 Restaurando stock...\n";

            foreach ($order['items'] as $item) {
                restore_product_stock($item['product_id'], $item['quantity']);
            }
            $orders_data['orders'][$order_index]['stock_reduced'] = false;
        }

        // Save orders
        write_json($orders_file, $orders_data);

        log_order_update($order_id, $old_status, $new_order_status, $payment_status);

        if (!$is_cli) echo "<p class='success'>✅ Orden actualizada: <strong>$old_status</strong> → <strong>$new_order_status</strong></p>";
        else echo "✅ Orden actualizada: $old_status → $new_order_status\n\n";

        // Send notifications
        $updated_order = $orders_data['orders'][$order_index];

        if (!$is_cli) echo "<p class='info'>📧 Enviando notificaciones...</p>";
        else echo "📧 Enviando notificaciones...\n";

        if ($new_order_status === 'cobrada') {
            // Send to customer based on preference
            $customer_notif_sent = false;
            if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                $customer_notif_sent = send_telegram_payment_approved_to_customer($updated_order);
                log_notification_sent('TELEGRAM_PAYMENT_APPROVED_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
            } else {
                $customer_notif_sent = send_payment_approved_email($updated_order);
                log_notification_sent('EMAIL_PAYMENT_APPROVED', $updated_order['customer_email'], $customer_notif_sent, $order_id);
            }

            // Always send to admin via Telegram
            $telegram_sent = send_telegram_payment_approved($updated_order);
            log_notification_sent('TELEGRAM_PAYMENT_APPROVED', 'admin', $telegram_sent, $order_id);

            if (!$is_cli) {
                echo "<p class='success'>✅ Notificación al cliente: " . ($customer_notif_sent ? 'Sí' : 'No') . "</p>";
                echo "<p class='success'>✅ Telegram admin: " . ($telegram_sent ? 'Sí' : 'No') . "</p>";
            } else {
                echo "  - Cliente: " . ($customer_notif_sent ? '✅ Enviado' : '❌ Fallo') . "\n";
                echo "  - Telegram admin: " . ($telegram_sent ? '✅ Enviado' : '❌ Fallo') . "\n";
            }

            log_mp_debug('PAYMENT_APPROVED', "Pago aprobado (reproceso manual) - Orden: $order_id", [
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'amount' => $payment['transaction_amount'] ?? 0,
                'fees' => $total_fees,
                'net_amount' => $net_received_amount,
                'customer_notif_sent' => $customer_notif_sent,
                'telegram_sent' => $telegram_sent
            ]);
        } elseif ($new_order_status === 'pendiente') {
            // Send to customer based on preference
            $customer_notif_sent = false;
            if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                $customer_notif_sent = send_telegram_payment_pending_to_customer($updated_order);
                log_notification_sent('TELEGRAM_PAYMENT_PENDING_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
            } else {
                $customer_notif_sent = send_payment_pending_email($updated_order);
                log_notification_sent('EMAIL_PAYMENT_PENDING', $updated_order['customer_email'], $customer_notif_sent, $order_id);
            }

            if (!$is_cli) echo "<p class='success'>✅ Notificación pendiente: " . ($customer_notif_sent ? 'Sí' : 'No') . "</p>";
            else echo "  - Cliente: " . ($customer_notif_sent ? '✅ Enviado' : '❌ Fallo') . "\n";
        } elseif ($new_order_status === 'rechazada') {
            // Send to customer based on preference
            $customer_notif_sent = false;
            if (($updated_order['contact_preference'] ?? 'email') === 'telegram') {
                $customer_notif_sent = send_telegram_payment_rejected_to_customer($updated_order, $status_detail);
                log_notification_sent('TELEGRAM_PAYMENT_REJECTED_CUSTOMER', $updated_order['telegram_chat_id'] ?? 'N/A', $customer_notif_sent, $order_id);
            } else {
                $customer_notif_sent = send_payment_rejected_email($updated_order, $status_detail);
                log_notification_sent('EMAIL_PAYMENT_REJECTED', $updated_order['customer_email'], $customer_notif_sent, $order_id);
            }

            // Always send to admin via Telegram
            $telegram_sent = send_telegram_payment_rejected($updated_order);
            log_notification_sent('TELEGRAM_PAYMENT_REJECTED', 'admin', $telegram_sent, $order_id);

            if (!$is_cli) {
                echo "<p class='success'>✅ Notificación al cliente: " . ($customer_notif_sent ? 'Sí' : 'No') . "</p>";
                echo "<p class='success'>✅ Telegram admin: " . ($telegram_sent ? 'Sí' : 'No') . "</p>";
            } else {
                echo "  - Cliente: " . ($customer_notif_sent ? '✅ Enviado' : '❌ Fallo') . "\n";
                echo "  - Telegram admin: " . ($telegram_sent ? '✅ Enviado' : '❌ Fallo') . "\n";
            }
        }

        if (!$is_cli) {
            echo "<br><h2 class='success'>✅ ¡Pago reprocesado exitosamente!</h2>";
            echo "<p>Resumen:</p>";
            echo "<pre>";
            echo "Payment ID: $payment_id\n";
            echo "Order ID: $order_id\n";
            echo "Estado anterior: $old_status\n";
            echo "Estado nuevo: $new_order_status\n";
            echo "Monto: " . ($payment['transaction_amount'] ?? 0) . " " . ($payment['currency_id'] ?? 'ARS') . "\n";
            echo "Comisiones: $total_fees\n";
            echo "Neto acreditado: $net_received_amount\n";
            echo "</pre>";
            echo "<p><a href='javascript:history.back()'>← Volver</a></p>";
        } else {
            echo "\n✅ ¡Pago reprocesado exitosamente!\n";
            echo "\nResumen:\n";
            echo "  Payment ID: $payment_id\n";
            echo "  Order ID: $order_id\n";
            echo "  Estado anterior: $old_status\n";
            echo "  Estado nuevo: $new_order_status\n";
            echo "  Monto: " . ($payment['transaction_amount'] ?? 0) . " " . ($payment['currency_id'] ?? 'ARS') . "\n";
            echo "  Comisiones: $total_fees\n";
            echo "  Neto acreditado: $net_received_amount\n\n";
        }
    } else {
        $message = "El pago ya está en el estado correcto: $old_status";

        if (!$is_cli) {
            echo "<p class='info'>ℹ️ $message</p>";
        } else {
            echo "ℹ️ $message\n\n";
        }

        log_mp_debug('MANUAL_REPROCESS', 'Pago ya procesado, no se requiere acción', [
            'payment_id' => $payment_id,
            'order_id' => $order_id,
            'current_status' => $old_status
        ]);
    }

} catch (Exception $e) {
    $error_msg = $e->getMessage();

    log_mp_error('MANUAL_REPROCESS', 'Error al reprocesar pago', [
        'payment_id' => $payment_id ?? 'unknown',
        'error' => $error_msg
    ]);

    if (!$is_cli) {
        echo "<h2 class='error'>❌ Error</h2>";
        echo "<p class='error'>" . htmlspecialchars($error_msg) . "</p>";
        echo "<p><a href='javascript:history.back()'>← Volver</a></p>";
    } else {
        echo "\n❌ Error: $error_msg\n\n";
        exit(1);
    }
}

if (!$is_cli) {
    echo "</body></html>";
}

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
