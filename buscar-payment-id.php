<?php
/**
 * Buscar Payment ID de una orden
 * Revisa los logs de webhooks para encontrar el payment_id de una orden específica
 */

require_once __DIR__ . '/includes/functions.php';

$secret_key = 'peu2024secure';

// Verificar autenticación
if (php_sapi_name() !== 'cli') {
    $provided_secret = $_GET['secret'] ?? '';
    if ($provided_secret !== $secret_key) {
        http_response_code(403);
        die('Acceso denegado');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><meta charset='utf-8'><title>Buscar Payment ID</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}
    .result{background:white;padding:15px;margin:10px 0;border-radius:5px;}
    .success{border-left:4px solid green;}
    .error{border-left:4px solid red;}
    pre{background:#f0f0f0;padding:10px;overflow-x:auto;}</style></head><body>";
}

echo "\n";
echo str_repeat('=', 80) . "\n";
echo "BUSCAR PAYMENT ID DE ORDEN\n";
echo str_repeat('=', 80) . "\n\n";

// Buscar en webhook_log.json
$webhook_log_file = __DIR__ . '/data/webhook_log.json';
if (!file_exists($webhook_log_file)) {
    echo "❌ Archivo webhook_log.json no existe\n";
    exit;
}

$webhook_logs = json_decode(file_get_contents($webhook_log_file), true);

// Buscar webhooks de tipo payment
$payment_webhooks = array_filter($webhook_logs, function($log) {
    $type = $log['data']['parsed_data']['type'] ?? $log['data']['parsed_data']['topic'] ?? '';
    return in_array($type, ['payment', 'payments']);
});

echo "📊 Total de webhooks de pago encontrados: " . count($payment_webhooks) . "\n\n";

// Agrupar por payment_id
$payments_found = [];
foreach ($payment_webhooks as $log) {
    $payment_id = $log['data']['parsed_data']['data']['id'] ??
                  $log['data']['parsed_data']['id'] ?? null;

    if ($payment_id && !isset($payments_found[$payment_id])) {
        $payments_found[$payment_id] = [
            'payment_id' => $payment_id,
            'timestamp' => $log['timestamp'] ?? 'N/A',
            'action' => $log['data']['parsed_data']['action'] ?? 'N/A'
        ];
    }
}

echo "💳 Payment IDs únicos encontrados: " . count($payments_found) . "\n\n";

if (php_sapi_name() !== 'cli') {
    echo "<div class='result success'>";
    echo "<h3>Payment IDs encontrados en logs de webhooks:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Payment ID</th><th>Timestamp</th><th>Action</th></tr>";
    foreach ($payments_found as $payment) {
        echo "<tr>";
        echo "<td><strong>" . $payment['payment_id'] . "</strong></td>";
        echo "<td>" . $payment['timestamp'] . "</td>";
        echo "<td>" . $payment['action'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // Mostrar órdenes pendientes
    echo "<div class='result error'>";
    echo "<h3>Órdenes pendientes actuales:</h3>";

    $orders_file = __DIR__ . '/data/orders.json';
    if (file_exists($orders_file)) {
        $orders_data = json_decode(file_get_contents($orders_file), true);
        $pending_orders = array_filter($orders_data['orders'] ?? [], function($order) {
            return in_array($order['status'], ['pending', 'pendiente']);
        });

        if (!empty($pending_orders)) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Order ID</th><th>Email</th><th>Total</th><th>Fecha</th><th>Payment ID (si tiene)</th></tr>";
            foreach ($pending_orders as $order) {
                $payment_id = $order['payment_id'] ??
                             $order['mercadopago_data']['payment_id'] ??
                             'N/A';
                echo "<tr>";
                echo "<td><strong>" . $order['id'] . "</strong></td>";
                echo "<td>" . $order['customer_email'] . "</td>";
                echo "<td>$" . number_format($order['total'], 2) . "</td>";
                echo "<td>" . ($order['created_at'] ?? 'N/A') . "</td>";
                echo "<td>" . $payment_id . "</td>";
                echo "</tr>";

                // Sugerir reprocesamiento si hay match
                foreach ($payments_found as $payment) {
                    if ($payment['payment_id'] == $payment_id) {
                        echo "<tr><td colspan='5' style='background:#e8f5e9;'>";
                        echo "✅ Match encontrado! Payment ID ya está en la orden.";
                        echo "</td></tr>";
                    }
                }
            }
            echo "</table>";

            echo "<h4>🔍 Análisis de correspondencia:</h4>";
            echo "<p>Comparando payment_ids de webhooks con órdenes pendientes...</p>";

            foreach ($pending_orders as $order) {
                $order_payment_id = $order['payment_id'] ??
                                   $order['mercadopago_data']['payment_id'] ??
                                   null;

                echo "<div style='background:#fff3cd;padding:10px;margin:10px 0;'>";
                echo "<strong>Orden: " . $order['id'] . "</strong><br>";
                echo "Email: " . $order['customer_email'] . "<br>";

                if ($order_payment_id && isset($payments_found[$order_payment_id])) {
                    echo "✅ Payment ID en orden: <code>$order_payment_id</code><br>";
                    echo "🔗 <a href='reprocesar-pago.php?payment_id=$order_payment_id&key=$secret_key'>Reprocesar este pago</a>";
                } else {
                    echo "❌ No tiene payment_id registrado<br>";
                    echo "Posibles payment_ids para probar:<br>";

                    // Buscar en mp_debug.log por coincidencia temporal
                    echo "<ul>";
                    foreach ($payments_found as $pid => $pdata) {
                        $time_diff = abs(strtotime($order['created_at'] ?? '2000-01-01') - strtotime($pdata['timestamp']));
                        if ($time_diff < 600) { // 10 minutos de diferencia
                            echo "<li>Payment ID: <code>$pid</code> (diferencia temporal: " . round($time_diff/60, 1) . " min) ";
                            echo "- <a href='reprocesar-pago.php?payment_id=$pid&key=$secret_key'>Probar</a></li>";
                        }
                    }
                    echo "</ul>";
                }
                echo "</div>";
            }
        } else {
            echo "<p>✅ No hay órdenes pendientes</p>";
        }
    } else {
        echo "<p>❌ Archivo orders.json no existe</p>";
    }
    echo "</div>";

} else {
    // CLI output
    foreach ($payments_found as $payment) {
        echo "Payment ID: {$payment['payment_id']}\n";
        echo "  Timestamp: {$payment['timestamp']}\n";
        echo "  Action: {$payment['action']}\n\n";
    }
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Para reprocesar un pago:\n";
echo "https://peu.net/shop/reprocesar-pago.php?payment_id=PAYMENT_ID&key=$secret_key\n";
echo str_repeat('=', 80) . "\n";

if (php_sapi_name() !== 'cli') {
    echo "</body></html>";
}
