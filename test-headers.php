<?php
/**
 * Test file to debug headers and URL construction
 * Access this at: https://your-ngrok-url.ngrok-free.dev/test-headers.php
 */

header('Content-Type: text/plain');

echo "=== SERVER HEADERS ===\n\n";

$important_vars = [
    'HTTP_HOST',
    'HTTP_X_FORWARDED_PROTO',
    'HTTP_X_FORWARDED_SSL',
    'HTTPS',
    'SERVER_PORT',
    'REQUEST_URI',
    'REQUEST_SCHEME'
];

foreach ($important_vars as $var) {
    $value = $_SERVER[$var] ?? 'NOT SET';
    echo "$var: $value\n";
}

echo "\n=== CONSTRUCTED URL ===\n\n";

// Same logic as checkout.php
$protocol = 'https://';

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $protocol = $_SERVER['HTTP_X_FORWARDED_PROTO'] . '://';
    echo "Using HTTP_X_FORWARDED_PROTO\n";
} elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $protocol = 'https://';
    echo "Using HTTP_X_FORWARDED_SSL\n";
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $protocol = 'https://';
    echo "Using HTTPS\n";
} elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
    $protocol = 'https://';
    echo "Using SERVER_PORT\n";
} else {
    echo "Using default (https://)\n";
}

$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host;

echo "\nProtocol: $protocol\n";
echo "Host: $host\n";
echo "Base URL: $base_url\n";
echo "Webhook URL: {$base_url}/webhook.php\n";

echo "\n=== ALL SERVER VARS ===\n\n";
foreach ($_SERVER as $key => $value) {
    if (is_string($value)) {
        echo "$key: $value\n";
    }
}
