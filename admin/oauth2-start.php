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
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error - OAuth2</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 50px auto;
                padding: 20px;
                background: #f5f7fa;
            }
            .error-box {
                background: #f8d7da;
                border-left: 4px solid #dc3545;
                padding: 20px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            .code-box {
                background: #f4f4f4;
                padding: 15px;
                border-radius: 4px;
                font-family: monospace;
                overflow-x: auto;
                margin: 15px 0;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>❌ Configuración OAuth2 Faltante</h2>
            <p>Para usar OAuth2, debes configurar primero tu Client ID y Client Secret desde el panel de notificaciones.</p>

            <h3>Pasos para configurar:</h3>
            <ol>
                <li>Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Crea un proyecto nuevo o selecciona uno existente</li>
                <li>Habilita "Gmail API" en APIs & Services</li>
                <li>Ve a "Credentials" → "Create Credentials" → "OAuth 2.0 Client IDs"</li>
                <li>Tipo de aplicación: "Web application"</li>
                <li>Authorized JavaScript origins:
                    <div class="code-box"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']; ?></div>
                </li>
                <li>Authorized redirect URIs:
                    <div class="code-box"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/admin/oauth2-callback.php"; ?></div>
                </li>
                <li>Copia el Client ID y Client Secret</li>
                <li>Vuelve a <strong>Admin → Email y Notificaciones</strong></li>
                <li>En la sección OAuth2, pega tu Client ID y Client Secret</li>
                <li>Guarda la configuración y luego haz click en "Autorizar con Google"</li>
            </ol>

            <a href="/admin/notificaciones.php" class="btn">Volver a Notificaciones</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Generate redirect URI
$redirect_uri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                "://" . $_SERVER['HTTP_HOST'] . "/admin/oauth2-callback.php";

// Generate authorization URL
$auth_url = get_gmail_oauth2_url($client_id, $redirect_uri);

// Redirect to Google
header('Location: ' . $auth_url);
exit;
