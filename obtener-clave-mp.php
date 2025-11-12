<?php
/**
 * Script temporal para obtener la clave de reprocesar-pago.php
 * ELIMINAR DESPUÉS DE USAR
 */

require_once __DIR__ . '/reprocesar-pago.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#4ec9b0;}";
echo "pre{background:#252526;padding:20px;border-left:3px solid #4ec9b0;font-size:16px;}";
echo "a{color:#569cd6;text-decoration:none;} a:hover{text-decoration:underline;}</style>";
echo "</head><body>";

echo "<h1>🔑 Clave para Reprocesar Pago</h1>";
echo "<p>Usa esta URL para reprocesar el pago 133535068062:</p>";

$payment_id = '133535068062';
$base_url = 'https://peu.net/shop/reprocesar-pago.php';
$key = REPROCESS_SECRET_KEY;

$full_url = $base_url . '?payment_id=' . $payment_id . '&key=' . urlencode($key);

echo "<pre>" . htmlspecialchars($full_url) . "</pre>";

echo "<p><a href='" . htmlspecialchars($full_url) . "' target='_blank'>→ Hacer clic aquí para reprocesar el pago</a></p>";

echo "<hr>";
echo "<p style='color:#f48771;'><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo después de usarlo:</p>";
echo "<pre>rm /home2/uv0023/public_html/shop/obtener-clave-mp.php</pre>";

echo "</body></html>";
