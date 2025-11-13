<?php
/**
 * Diagnóstico del webhook
 * Verifica que el código tenga las correcciones implementadas
 */

$secret_key = 'peu2024secure';

if (php_sapi_name() !== 'cli') {
    $provided_secret = $_GET['secret'] ?? '';
    if ($provided_secret !== $secret_key) {
        http_response_code(403);
        die('Acceso denegado');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><head><meta charset='utf-8'><title>Diagnóstico Webhook</title>";
    echo "<style>
        body{font-family:monospace;padding:20px;background:#f5f5f5;}
        .ok{color:green;font-weight:bold;}
        .error{color:red;font-weight:bold;}
        .warning{color:orange;font-weight:bold;}
        pre{background:#f0f0f0;padding:10px;overflow-x:auto;font-size:12px;}
        .section{background:white;padding:15px;margin:10px 0;border-radius:5px;}
    </style></head><body>";
    echo "<h1>🔍 Diagnóstico de Webhook</h1>";
}

echo "\n================================\n";
echo "DIAGNÓSTICO DE WEBHOOK\n";
echo "================================\n\n";

// 1. Verificar que el archivo webhook.php existe
$webhook_file = __DIR__ . '/webhook.php';
if (!file_exists($webhook_file)) {
    echo "❌ ERROR: webhook.php no existe\n";
    exit;
}

echo "✅ Archivo webhook.php existe\n\n";

// 2. Leer el contenido del webhook.php
$webhook_content = file_get_contents($webhook_file);

// 3. Verificar correcciones implementadas
$checks = [
    'Soporte type/topic' => [
        'pattern' => '/\$notification_type\s*=\s*\$data\[\'type\'\]\s*\?\?\s*\$data\[\'topic\'\]/',
        'line' => 'notification_type = $data[\'type\'] ?? $data[\'topic\']'
    ],
    'Validación type O topic' => [
        'pattern' => '/!\$data\s*\|\|\s*\(.*!isset\(\$data\[\'type\'\]\).*!isset\(\$data\[\'topic\'\]\)/',
        'line' => '!isset($data[\'type\']) && !isset($data[\'topic\'])'
    ],
    'Soporte payment Y payments' => [
        'pattern' => '/\$notification_type\s*===\s*[\'"]payment[\'"].*\|\|\s*\$notification_type\s*===\s*[\'"]payments[\'"]/',
        'line' => '$notification_type === \'payment\' || $notification_type === \'payments\''
    ],
    'Logging de type detection' => [
        'pattern' => '/log_mp_debug\([\'"]WEBHOOK_TYPE_DETECTION[\'"]/',
        'line' => 'log_mp_debug(\'WEBHOOK_TYPE_DETECTION\''
    ],
    'Payment ID nested O flat' => [
        'pattern' => '/\$data\[\'data\'\]\[\'id\'\]\s*\?\?\s*\$data\[\'id\'\]/',
        'line' => '$data[\'data\'][\'id\'] ?? $data[\'id\']'
    ]
];

echo "Verificando correcciones implementadas:\n";
echo "---------------------------------------\n";

$all_ok = true;
foreach ($checks as $name => $check) {
    if (preg_match($check['pattern'], $webhook_content)) {
        echo "✅ $name\n";
        if (php_sapi_name() !== 'cli') {
            echo "<div class='section'><span class='ok'>✅ $name</span><br><code>{$check['line']}</code></div>";
        }
    } else {
        echo "❌ $name - NO ENCONTRADO\n";
        if (php_sapi_name() !== 'cli') {
            echo "<div class='section'><span class='error'>❌ $name - NO ENCONTRADO</span><br>";
            echo "Se esperaba: <code>{$check['line']}</code></div>";
        }
        $all_ok = false;
    }
}

echo "\n";

if (!$all_ok) {
    echo "⚠️  ADVERTENCIA: Faltan algunas correcciones en webhook.php\n";
    echo "El archivo puede estar desactualizado.\n\n";

    if (php_sapi_name() !== 'cli') {
        echo "<div class='section' style='background:#ffebee;'>";
        echo "<span class='error'>⚠️ ADVERTENCIA: webhook.php está DESACTUALIZADO</span><br>";
        echo "Falta alguna corrección crítica. Verificar que GitHub Actions haya desplegado correctamente.";
        echo "</div>";
    }
}

// 4. Buscar líneas críticas
echo "Buscando líneas clave:\n";
echo "----------------------\n";

$lines = explode("\n", $webhook_content);
$important_lines = [];

foreach ($lines as $num => $line) {
    $line_num = $num + 1;

    if (stripos($line, 'notification_type') !== false && stripos($line, '=') !== false) {
        $important_lines[] = "Línea $line_num: " . trim($line);
    }

    if (stripos($line, "if (\$data['type']") !== false || stripos($line, 'if ($notification_type') !== false) {
        $important_lines[] = "Línea $line_num: " . trim($line);
    }
}

foreach ($important_lines as $line) {
    echo "$line\n";
}

// 5. Verificar última modificación
$last_modified = filemtime($webhook_file);
$last_modified_date = date('Y-m-d H:i:s', $last_modified);
$time_ago = time() - $last_modified;
$minutes_ago = round($time_ago / 60);

echo "\n";
echo "Última modificación: $last_modified_date ($minutes_ago minutos atrás)\n";

if ($time_ago > 3600) {
    echo "⚠️  El archivo no se ha modificado en más de 1 hora\n";
    echo "Verificar que GitHub Actions haya desplegado correctamente\n";
}

// 6. Leer últimos logs
echo "\n================================\n";
echo "ÚLTIMOS LOGS DE MP_DEBUG.LOG\n";
echo "================================\n\n";

$mp_debug = __DIR__ . '/mp_debug.log';
if (file_exists($mp_debug)) {
    $log_content = file_get_contents($mp_debug);
    $log_lines = explode("\n", $log_content);
    $recent_logs = array_slice($log_lines, -100);

    // Buscar eventos clave
    $events = [
        'WEBHOOK_RECEIVED' => 0,
        'WEBHOOK_VALIDATION' => 0,
        'WEBHOOK_TYPE_DETECTION' => 0,
        'PAYMENT_WEBHOOK' => 0,
        'PAYMENT_DETAILS' => 0,
        'ORDER_UPDATE' => 0,
        'NOTIFICATION' => 0,
        'WEBHOOK_IGNORED' => 0
    ];

    foreach ($recent_logs as $line) {
        foreach ($events as $event => $count) {
            if (stripos($line, "[$event]") !== false) {
                $events[$event]++;
            }
        }
    }

    echo "Eventos en últimas 100 líneas:\n";
    foreach ($events as $event => $count) {
        $status = $count > 0 ? '✅' : '❌';
        echo "$status $event: $count\n";

        if (php_sapi_name() !== 'cli') {
            $class = $count > 0 ? 'ok' : 'error';
            echo "<div class='section'><span class='$class'>$status $event: $count</span></div>";
        }
    }

    if ($events['WEBHOOK_RECEIVED'] > 0 && $events['WEBHOOK_TYPE_DETECTION'] == 0) {
        echo "\n⚠️  PROBLEMA DETECTADO:\n";
        echo "Webhooks llegan pero NO se detecta el tipo\n";
        echo "El código después de la validación NO se está ejecutando\n\n";

        if (php_sapi_name() !== 'cli') {
            echo "<div class='section' style='background:#fff3cd;'>";
            echo "<span class='warning'>⚠️ PROBLEMA DETECTADO</span><br>";
            echo "Webhooks llegan pero NO se detecta el tipo.<br>";
            echo "El código después de la validación NO se está ejecutando.<br>";
            echo "<strong>Posible causa:</strong> Error en el código que termina el script silenciosamente.";
            echo "</div>";
        }
    }

    // Mostrar últimas líneas del log
    echo "\nÚltimas 30 líneas del log:\n";
    echo "-------------------------\n";
    $last_30 = array_slice($log_lines, -30);
    foreach ($last_30 as $line) {
        echo $line . "\n";
    }

    if (php_sapi_name() !== 'cli') {
        echo "<div class='section'><h3>Últimas 30 líneas:</h3><pre>";
        echo htmlspecialchars(implode("\n", $last_30));
        echo "</pre></div>";
    }
}

echo "\n================================\n";
echo "FIN DEL DIAGNÓSTICO\n";
echo "================================\n";

if (php_sapi_name() !== 'cli') {
    echo "</body></html>";
}
