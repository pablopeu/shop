<?php
/**
 * OAuth2 Start - Generate authorization URL and redirect to Google
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/encryption.php';

session_start();
require_admin();

// Load client credentials from email.json
$config_file = __DIR__ . '/../config/email.json';
$email_config = read_json($config_file);

$client_id = $email_config['oauth2_credentials']['client_id'] ?? '';
$client_secret = $email_config['oauth2_credentials']['client_secret'] ?? '';

// Decrypt if needed
if (!empty($client_id) && is_encrypted($client_id)) {
    $client_id = decrypt_data($client_id);
}
if (!empty($client_secret) && is_encrypted($client_secret)) {
    $client_secret = decrypt_data($client_secret);
}

if (empty($client_id) || empty($client_secret)) {
    // Redirect back to notifications with error modal parameter
    header('Location: /admin/notificaciones.php?oauth2_error=missing_credentials');
    exit;
}

// Generate redirect URI
// Check for HTTPS (including proxies and ngrok)
$is_https = (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) // ngrok always uses HTTPS
);

$protocol = $is_https ? 'https' : 'http';
$redirect_uri = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/admin/oauth2-callback.php";

// Get email hint from query parameter
$email = $_GET['email'] ?? null;

// Generate authorization URL
$auth_url = get_gmail_oauth2_url($client_id, $redirect_uri, $email);

// Log for debugging
error_log("OAuth2: Generated auth URL with redirect_uri: $redirect_uri");

// Redirect to Google
header('Location: ' . $auth_url);
exit;
