<?php
/**
 * OAuth2 Start - Generate authorization URL and redirect to Google
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

session_start();
require_admin();

// Load client credentials
$credentials = file_exists(__DIR__ . '/../config/credentials.php')
    ? require __DIR__ . '/../config/credentials.php'
    : [];

$client_id = $credentials['gmail_oauth2']['client_id'] ?? '';
$client_secret = $credentials['gmail_oauth2']['client_secret'] ?? '';

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
            <p>Para usar OAuth2, debes configurar las credenciales de Google Cloud en <code>config/credentials.php</code></p>

            <h3>Pasos para configurar:</h3>
            <ol>
                <li>Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                <li>Crea un proyecto nuevo o selecciona uno existente</li>
                <li>Habilita "Gmail API" en APIs & Services</li>
                <li>Ve a "Credentials" → "Create Credentials" → "OAuth 2.0 Client IDs"</li>
                <li>Tipo de aplicación: "Web application"</li>
                <li>Authorized redirect URIs:
                    <div class="code-box"><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/admin/oauth2-callback.php"; ?></div>
                </li>
                <li>Copia el Client ID y Client Secret</li>
                <li>Edita <code>config/credentials.php</code> y agrega:
                    <div class="code-box">'gmail_oauth2' => [
    'client_id' => 'TU_CLIENT_ID_AQUI',
    'client_secret' => 'TU_CLIENT_SECRET_AQUI'
],</div>
                </li>
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
