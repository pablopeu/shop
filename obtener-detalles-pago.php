<?php
/**
 * Obtener detalles de un pago de MercadoPago
 * Muestra información completa incluyendo email del pagador
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mercadopago.php';

$secret_key = 'peu2024secure';

// Verificar autenticación
if (php_sapi_name() !== 'cli') {
    $provided_secret = $_GET['secret'] ?? '';
    if ($provided_secret !== $secret_key) {
        http_response_code(403);
        die('Acceso denegado');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><meta charset='utf-8'><title>Obtener Detalles de Pago</title>";
    echo "<style>
        body{font-family:monospace;padding:20px;background:#f5f5f5;}
        .result{background:white;padding:20px;margin:10px 0;border-radius:5px;border-left:4px solid #4CAF50;}
        .error{border-left-color:#f44336;}
        table{border-collapse:collapse;width:100%;margin:10px 0;}
        th,td{border:1px solid #ddd;padding:8px;text-align:left;}
        th{background:#333;color:white;}
        .key{font-weight:bold;color:#1976D2;}
        .value{color:#333;}
        h2{color:#1976D2;border-bottom:2px solid #1976D2;padding-bottom:5px;}
        .highlight{background:#fff9c4;padding:2px 5px;border-radius:3px;}
    </style></head><body>";
    echo "<h1>🔍 Obtener Detalles de Pago de MercadoPago</h1>";
}

echo "\n";
echo str_repeat('=', 80) . "\n";
echo "OBTENER DETALLES DE PAGO\n";
echo str_repeat('=', 80) . "\n\n";

// Get payment config
$payment_config = read_json(__DIR__ . '/config/payment.json');
$payment_credentials = get_payment_credentials();

$mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
$sandbox_mode = ($mp_mode === 'sandbox');

$access_token = $sandbox_mode ?
    ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
    ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

if (empty($access_token)) {
    die("❌ Error: No hay access token configurado\n");
}

// Get payment IDs to check (from URL or default)
$payment_ids_str = $_GET['payment_ids'] ?? '132891215537,133535068062,132973453083';
$payment_ids = array_map('trim', explode(',', $payment_ids_str));

echo "🔍 Consultando " . count($payment_ids) . " pagos en MercadoPago...\n";
echo "Modo: " . ($sandbox_mode ? 'SANDBOX' : 'PRODUCTION') . "\n\n";

$mp = new MercadoPago($access_token, $sandbox_mode);

if (php_sapi_name() !== 'cli') {
    echo "<div class='result'>";
    echo "<p>Consultando <strong>" . count($payment_ids) . "</strong> pagos en modo <strong>" . strtoupper($mp_mode) . "</strong></p>";
    echo "</div>";
}

foreach ($payment_ids as $payment_id) {
    if (empty($payment_id)) continue;

    echo "\n" . str_repeat('-', 80) . "\n";
    echo "Payment ID: $payment_id\n";
    echo str_repeat('-', 80) . "\n";

    if (php_sapi_name() !== 'cli') {
        echo "<div class='result'>";
        echo "<h2>💳 Payment ID: $payment_id</h2>";
    }

    try {
        $payment = $mp->getPayment($payment_id);

        // Información clave
        $key_info = [
            'ID' => $payment['id'] ?? 'N/A',
            'Estado' => $payment['status'] ?? 'N/A',
            'Detalle Estado' => $payment['status_detail'] ?? 'N/A',
            'Monto' => ($payment['transaction_amount'] ?? 0) . ' ' . ($payment['currency_id'] ?? ''),
            'Fecha Creación' => $payment['date_created'] ?? 'N/A',
            'Fecha Aprobación' => $payment['date_approved'] ?? 'N/A',
            'Método de Pago' => $payment['payment_method_id'] ?? 'N/A',
            'Tipo de Pago' => $payment['payment_type_id'] ?? 'N/A',
            'External Reference' => $payment['external_reference'] ?? 'N/A',
            'Email Pagador' => $payment['payer']['email'] ?? 'N/A',
            'Nombre Pagador' => ($payment['payer']['first_name'] ?? '') . ' ' . ($payment['payer']['last_name'] ?? ''),
            'Identificación' => $payment['payer']['identification']['number'] ?? 'N/A',
        ];

        // Fee details
        $fee_details = $payment['fee_details'] ?? [];
        $total_fees = 0;
        foreach ($fee_details as $fee) {
            $total_fees += floatval($fee['amount'] ?? 0);
        }

        $transaction_details = $payment['transaction_details'] ?? [];
        $net_amount = floatval($transaction_details['net_received_amount'] ?? 0);
        if ($net_amount == 0 && isset($payment['transaction_amount'])) {
            $net_amount = floatval($payment['transaction_amount']) - $total_fees;
        }

        $key_info['Comisión MP'] = number_format($total_fees, 2) . ' ' . ($payment['currency_id'] ?? '');
        $key_info['Neto Recibido'] = number_format($net_amount, 2) . ' ' . ($payment['currency_id'] ?? '');

        if (php_sapi_name() !== 'cli') {
            echo "<table>";
            echo "<tr><th>Campo</th><th>Valor</th></tr>";
            foreach ($key_info as $key => $value) {
                $highlight = '';
                if (in_array($key, ['Email Pagador', 'External Reference', 'Estado'])) {
                    $highlight = " class='highlight'";
                }
                echo "<tr><td class='key'>$key</td><td$highlight class='value'>$value</td></tr>";
            }
            echo "</table>";

            // Buscar orden correspondiente
            echo "<h3>🔍 Búsqueda de Orden Correspondiente:</h3>";

            $external_ref = $payment['external_reference'] ?? null;
            $payer_email = $payment['payer']['email'] ?? null;

            if ($external_ref) {
                $orders_file = __DIR__ . '/data/orders.json';
                if (file_exists($orders_file)) {
                    $orders_data = json_decode(file_get_contents($orders_file), true);

                    // Buscar por external_reference
                    $order_found = null;
                    foreach ($orders_data['orders'] ?? [] as $order) {
                        if ($order['id'] === $external_ref) {
                            $order_found = $order;
                            break;
                        }
                    }

                    if ($order_found) {
                        echo "<div style='background:#e8f5e9;padding:10px;margin:10px 0;border-radius:5px;'>";
                        echo "✅ <strong>Orden encontrada por external_reference</strong><br>";
                        echo "Order ID: <code>{$order_found['id']}</code><br>";
                        echo "Email: {$order_found['customer_email']}<br>";
                        echo "Estado: <strong>{$order_found['status']}</strong><br>";
                        echo "Total: \$" . number_format($order_found['total'], 2) . "<br>";

                        if ($order_found['status'] === 'pending' || $order_found['status'] === 'pendiente') {
                            echo "<br>🔧 <a href='reprocesar-pago.php?payment_id=$payment_id&key=$secret_key' style='background:#4CAF50;color:white;padding:8px 12px;text-decoration:none;border-radius:4px;'>REPROCESAR ESTE PAGO</a>";
                        }
                        echo "</div>";
                    } else {
                        echo "<div style='background:#ffebee;padding:10px;margin:10px 0;border-radius:5px;'>";
                        echo "❌ <strong>Orden NO encontrada</strong><br>";
                        echo "External Reference: <code>$external_ref</code> no existe en el sistema<br>";

                        // Buscar por email
                        echo "<br>🔍 Buscando órdenes con el mismo email del pagador...<br>";
                        $orders_by_email = array_filter($orders_data['orders'] ?? [], function($o) use ($payer_email) {
                            return strtolower($o['customer_email'] ?? '') === strtolower($payer_email ?? '');
                        });

                        if (!empty($orders_by_email)) {
                            echo "✅ Encontradas " . count($orders_by_email) . " órdenes con email <code>$payer_email</code>:<br><ul>";
                            foreach ($orders_by_email as $o) {
                                $status_color = $o['status'] === 'pending' ? 'orange' : 'green';
                                echo "<li>Order: <code>{$o['id']}</code> - Estado: <span style='color:$status_color'><strong>{$o['status']}</strong></span> - Total: \${$o['total']}</li>";
                            }
                            echo "</ul>";
                            echo "<br>⚠️ <strong>POSIBLE SOLUCIÓN:</strong> El external_reference puede estar equivocado. ";
                            echo "Verificá manualmente cuál de estas órdenes corresponde a este pago.";
                        } else {
                            echo "<br>❌ No se encontraron órdenes con el email <code>$payer_email</code>";
                        }
                        echo "</div>";
                    }
                }
            }

            echo "</div>";
        } else {
            // CLI output
            foreach ($key_info as $key => $value) {
                echo "$key: $value\n";
            }
        }

    } catch (Exception $e) {
        $error_msg = "❌ Error al obtener pago: " . $e->getMessage();
        echo $error_msg . "\n";

        if (php_sapi_name() !== 'cli') {
            echo "<div class='result error'>";
            echo "<p>$error_msg</p>";
            echo "</div>";
        }
    }

    if (php_sapi_name() === 'cli') {
        echo "\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Consulta completada\n";
echo str_repeat('=', 80) . "\n";

if (php_sapi_name() !== 'cli') {
    echo "</body></html>";
}
