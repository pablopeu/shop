<?php
/**
 * Script temporal para obtener la URL de reprocesamiento
 * ELIMINAR DESPUÉS DE USAR
 */

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#4ec9b0;}";
echo "h1{font-size:24px;margin-bottom:20px;}";
echo "pre{background:#252526;padding:20px;border-left:3px solid #4ec9b0;font-size:14px;overflow-x:auto;margin:20px 0;}";
echo ".btn{display:inline-block;background:#569cd6;color:#fff;padding:15px 30px;text-decoration:none;border-radius:5px;margin:20px 0;font-size:16px;}";
echo ".btn:hover{background:#4080bf;} .warning{color:#f48771;}</style>";
echo "</head><body>";

echo "<h1>🔑 Reprocesar Pago de MercadoPago</h1>";

// La clave por defecto es esta (calculada con md5 del directorio)
$directory_path = __DIR__;
$secret_key = 'cambiar_esta_clave_secreta_' . md5($directory_path);

$payment_id = '133535068062';
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . '/reprocesar-pago.php';

$full_url = $base_url . '?payment_id=' . $payment_id . '&key=' . urlencode($secret_key);

echo "<p>Haz clic en el botón para reprocesar el pago <strong>133535068062</strong>:</p>";

echo "<a href='" . htmlspecialchars($full_url) . "' class='btn'>→ Reprocesar Pago Ahora</a>";

echo "<hr style='margin:40px 0;border:1px solid #333;'>";

echo "<p>O copia esta URL completa:</p>";
echo "<pre style='font-size:12px;word-break:break-all;'>" . htmlspecialchars($full_url) . "</pre>";

echo "<hr style='margin:40px 0;border:1px solid #333;'>";

echo "<p class='warning'><strong>⚠️ ELIMINAR ESTE ARCHIVO DESPUÉS DE USAR:</strong></p>";
echo "<p>Vía FTP, elimina el archivo:</p>";
echo "<pre>/public_html/shop/obtener-clave-mp.php</pre>";

echo "<hr style='margin:40px 0;border:1px solid #333;'>";

echo "<details style='margin-top:30px;'>";
echo "<summary style='cursor:pointer;color:#569cd6;'>🔍 Información técnica (clic para expandir)</summary>";
echo "<pre style='font-size:12px;'>";
echo "Payment ID: 133535068062\n";
echo "Directorio: " . htmlspecialchars($directory_path) . "\n";
echo "Clave secreta: " . htmlspecialchars($secret_key) . "\n";
echo "URL base: " . htmlspecialchars($base_url) . "\n";
echo "</pre>";
echo "</details>";

echo "</body></html>";
