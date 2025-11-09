<?php
/**
 * OAuth2 Callback Handler for Gmail
 * This page handles the OAuth2 authorization callback from Google
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/encryption.php';

session_start();
require_admin();

$message = '';
$error = '';

// Check if we have an authorization code
if (isset($_GET['code'])) {
    $code = $_GET['code'];

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

    // Check for HTTPS (including proxies and ngrok)
    $is_https = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
        (strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) // ngrok always uses HTTPS
    );

    $protocol = $is_https ? 'https' : 'http';
    $redirect_uri = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/admin/oauth2-callback.php";

    if (empty($client_id) || empty($client_secret)) {
        $error = 'Client ID y Client Secret no están configurados. Por favor, configúralos en Admin → Email y Notificaciones';
    } else {
        // Exchange code for tokens
        $tokens = exchange_oauth2_code($code, $client_id, $client_secret, $redirect_uri);

        if ($tokens && isset($tokens['access_token'])) {
            // Save tokens to email config
            $config_file = __DIR__ . '/../config/email.json';
            $email_config = read_json($config_file);

            $email_config['oauth2'] = [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? '',
                'expires_at' => time() + ($tokens['expires_in'] ?? 3600),
                'scope' => $tokens['scope'] ?? '',
                'token_type' => $tokens['token_type'] ?? 'Bearer'
            ];

            if (write_json($config_file, $email_config)) {
                $message = '✅ Autorización OAuth2 exitosa! Los tokens han sido guardados.';
                log_admin_action('oauth2_authorized', $_SESSION['username']);
            } else {
                $error = '❌ Error al guardar los tokens OAuth2';
            }
        } else {
            $error = '❌ Error al intercambiar el código de autorización por tokens';
        }
    }
} elseif (isset($_GET['error'])) {
    $error = '❌ Autorización cancelada o fallida: ' . htmlspecialchars($_GET['error']);
} else {
    $error = '❌ No se recibió código de autorización';
}

$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'OAuth2 Callback';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>OAuth2 Callback - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            max-width: 600px;
            width: 100%;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }
        .message {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .message.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php if ($message): ?>
                <div class="icon">✅</div>
                <div class="message success"><?= $message ?></div>
                <p>Ya puedes cerrar esta ventana y volver a la configuración de notificaciones.</p>
                <p>La autenticación OAuth2 está lista para enviar emails.</p>
                <a href="/admin/notificaciones.php" class="btn">Volver a Notificaciones</a>
            <?php elseif ($error): ?>
                <div class="icon">❌</div>
                <div class="message error"><?= $error ?></div>
                <p>Por favor, intenta nuevamente o verifica tu configuración.</p>
                <a href="/admin/notificaciones.php" class="btn">Volver a Notificaciones</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-close and refresh parent after successful auth
        <?php if ($message): ?>
        setTimeout(function() {
            if (window.opener) {
                window.opener.location.reload();
                window.close();
            } else {
                window.location.href = '/admin/notificaciones.php';
            }
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
