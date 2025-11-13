<?php
/**
 * Script de verificación de webhooks
 * Muestra el estado de webhooks recibidos y ayuda a debuggear problemas
 *
 * Uso:
 * - Por CLI: php verificar-webhook.php
 * - Por web: https://tu-dominio.com/verificar-webhook.php?secret=TU_CLAVE
 */

require_once __DIR__ . '/includes/functions.php';

// Secret key para acceso web (mismo que otros scripts de admin)
$secret_key = 'peu2024secure';

// Verificar autenticación si se accede por web
if (php_sapi_name() !== 'cli') {
    $provided_secret = $_GET['secret'] ?? '';
    if ($provided_secret !== $secret_key) {
        http_response_code(403);
        die('Acceso denegado. Clave de autenticación inválida.');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><meta charset='utf-8'><title>Verificación de Webhooks</title>";
    echo "<style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
    </style></head><body>";
    echo "<h1>🔍 Verificación de Webhooks - MercadoPago</h1>";
    echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";
}

echo "\n";
echo str_repeat('=', 80) . "\n";
echo "VERIFICACIÓN DE WEBHOOKS - MERCADOPAGO\n";
echo str_repeat('=', 80) . "\n\n";

// 1. Verificar archivos de log
echo "📁 VERIFICACIÓN DE ARCHIVOS DE LOG\n";
echo str_repeat('-', 80) . "\n";

$log_files = [
    'mp_debug.log' => __DIR__ . '/mp_debug.log',
    'webhook_log.json' => __DIR__ . '/data/webhook_log.json',
    'webhook_rate_limit.json' => __DIR__ . '/data/webhook_rate_limit.json'
];

foreach ($log_files as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $modified = date('Y-m-d H:i:s', filemtime($path));
        echo "✅ $name: Existe ($size bytes, modificado: $modified)\n";
    } else {
        echo "❌ $name: No existe\n";
    }
}

// 2. Verificar configuración de payment
echo "\n\n📋 CONFIGURACIÓN DE WEBHOOKS\n";
echo str_repeat('-', 80) . "\n";

$payment_config = read_json(__DIR__ . '/config/payment.json');
$webhook_security = $payment_config['mercadopago']['webhook_security'] ?? [];

echo "Modo: " . ($payment_config['mercadopago']['mode'] ?? 'NO CONFIGURADO') . "\n";
echo "Validación de IP: " . (($webhook_security['validate_ip'] ?? true) ? '✅ Habilitada' : '❌ Deshabilitada') . "\n";
echo "Validación de Firma: " . (($webhook_security['validate_signature'] ?? true) ? '✅ Habilitada' : '❌ Deshabilitada') . "\n";
echo "Validación de Timestamp: " . (($webhook_security['validate_timestamp'] ?? true) ? '✅ Habilitada' : '❌ Deshabilitada') . "\n";
echo "Edad máxima timestamp: " . ($webhook_security['max_timestamp_age_minutes'] ?? 5) . " minutos\n";

// 3. Leer últimos webhooks recibidos
echo "\n\n📨 ÚLTIMOS WEBHOOKS RECIBIDOS\n";
echo str_repeat('-', 80) . "\n";

$webhook_log_file = __DIR__ . '/data/webhook_log.json';
if (file_exists($webhook_log_file)) {
    $webhook_logs = json_decode(file_get_contents($webhook_log_file), true);

    if (!empty($webhook_logs)) {
        $recent_webhooks = array_slice(array_reverse($webhook_logs), 0, 10);

        if (php_sapi_name() !== 'cli') {
            echo "<table>";
            echo "<tr><th>Timestamp</th><th>Mensaje</th><th>Tipo</th><th>Payment ID</th><th>Action</th></tr>";
        }

        foreach ($recent_webhooks as $log) {
            $timestamp = $log['timestamp'] ?? 'N/A';
            $message = $log['message'] ?? 'N/A';
            $data = $log['data'] ?? [];
            $type = $data['parsed_data']['type'] ?? $data['parsed_data']['topic'] ?? 'unknown';
            $payment_id = $data['parsed_data']['data']['id'] ?? $data['parsed_data']['id'] ?? 'N/A';
            $action = $data['parsed_data']['action'] ?? 'N/A';

            if (php_sapi_name() !== 'cli') {
                echo "<tr>";
                echo "<td>$timestamp</td>";
                echo "<td>$message</td>";
                echo "<td>$type</td>";
                echo "<td>$payment_id</td>";
                echo "<td>$action</td>";
                echo "</tr>";
            } else {
                echo "\n[$timestamp]\n";
                echo "Mensaje: $message\n";
                echo "Tipo: $type\n";
                echo "Payment ID: $payment_id\n";
                echo "Action: $action\n";
            }
        }

        if (php_sapi_name() !== 'cli') {
            echo "</table>";
        }
    } else {
        echo "⚠️  No hay webhooks registrados aún.\n";
    }
} else {
    echo "❌ Archivo de log de webhooks no existe.\n";
}

// 4. Leer mp_debug.log (últimas 50 líneas)
echo "\n\n📝 ÚLTIMAS ENTRADAS EN MP_DEBUG.LOG\n";
echo str_repeat('-', 80) . "\n";

$mp_debug_file = __DIR__ . '/mp_debug.log';
if (file_exists($mp_debug_file)) {
    $content = file_get_contents($mp_debug_file);
    $lines = explode("\n", $content);
    $recent_lines = array_slice($lines, -50);

    if (php_sapi_name() !== 'cli') {
        echo "<pre>" . htmlspecialchars(implode("\n", $recent_lines)) . "</pre>";
    } else {
        echo implode("\n", $recent_lines) . "\n";
    }
} else {
    echo "❌ Archivo mp_debug.log no existe o está vacío.\n";
}

// 5. Estadísticas de webhooks
echo "\n\n📊 ESTADÍSTICAS DE WEBHOOKS\n";
echo str_repeat('-', 80) . "\n";

if (file_exists($webhook_log_file)) {
    $webhook_logs = json_decode(file_get_contents($webhook_log_file), true);

    $stats = [
        'total' => count($webhook_logs),
        'by_type' => [],
        'by_message' => []
    ];

    foreach ($webhook_logs as $log) {
        $type = $log['data']['parsed_data']['type'] ?? $log['data']['parsed_data']['topic'] ?? 'unknown';
        $message = $log['message'] ?? 'unknown';

        $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        $stats['by_message'][$message] = ($stats['by_message'][$message] ?? 0) + 1;
    }

    echo "Total de webhooks recibidos: " . $stats['total'] . "\n\n";

    echo "Por tipo:\n";
    foreach ($stats['by_type'] as $type => $count) {
        echo "  - $type: $count\n";
    }

    echo "\nPor mensaje:\n";
    foreach ($stats['by_message'] as $message => $count) {
        echo "  - $message: $count\n";
    }
}

// 6. Verificar órdenes pendientes
echo "\n\n🛒 ÓRDENES PENDIENTES\n";
echo str_repeat('-', 80) . "\n";

$orders_file = __DIR__ . '/data/orders.json';
if (file_exists($orders_file)) {
    $orders_data = read_json($orders_file);
    $pending_orders = array_filter($orders_data['orders'] ?? [], function($order) {
        return in_array($order['status'], ['pending', 'pendiente']);
    });

    if (!empty($pending_orders)) {
        echo "⚠️  Hay " . count($pending_orders) . " órdenes pendientes:\n\n";

        if (php_sapi_name() !== 'cli') {
            echo "<table>";
            echo "<tr><th>Order ID</th><th>Email</th><th>Total</th><th>Fecha</th><th>Payment ID</th></tr>";
        }

        foreach ($pending_orders as $order) {
            $order_id = $order['id'];
            $email = $order['customer_email'];
            $total = number_format($order['total'], 2);
            $date = $order['created_at'] ?? 'N/A';
            $payment_id = $order['payment_id'] ?? $order['mercadopago_data']['payment_id'] ?? 'N/A';

            if (php_sapi_name() !== 'cli') {
                echo "<tr>";
                echo "<td>$order_id</td>";
                echo "<td>$email</td>";
                echo "<td>\$$total</td>";
                echo "<td>$date</td>";
                echo "<td>$payment_id</td>";
                echo "</tr>";
            } else {
                echo "Order: $order_id\n";
                echo "  Email: $email\n";
                echo "  Total: \$$total\n";
                echo "  Fecha: $date\n";
                echo "  Payment ID: $payment_id\n";
                if ($payment_id !== 'N/A') {
                    echo "  🔧 Reprocesar: php reprocesar-pago.php $payment_id\n";
                }
                echo "\n";
            }
        }

        if (php_sapi_name() !== 'cli') {
            echo "</table>";
        }
    } else {
        echo "✅ No hay órdenes pendientes.\n";
    }
} else {
    echo "❌ Archivo de órdenes no existe.\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Verificación completada.\n";
echo str_repeat('=', 80) . "\n";

if (php_sapi_name() !== 'cli') {
    echo "</body></html>";
}
