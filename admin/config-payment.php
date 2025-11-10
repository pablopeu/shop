<?php
/**
 * Admin - Payment Configuration
 * Configuración de medios de pago (Mercadopago, presencial)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();
require_admin();

$message = '';
$error = '';

// Update payment config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/payment.json';

        // Save configuration (without sensitive credentials)
        $config = [
            'mercadopago' => [
                'enabled' => isset($_POST['mp_enabled']),
                'mode' => isset($_POST['mp_sandbox']) ? 'sandbox' : 'production',
                'webhook_url' => sanitize_input($_POST['mp_webhook_url'] ?? ''),
                'webhook_security' => [
                    'validate_signature' => isset($_POST['mp_validate_signature']),
                    'validate_timestamp' => isset($_POST['mp_validate_timestamp']),
                    'validate_ip' => isset($_POST['mp_validate_ip']),
                    'max_timestamp_age_minutes' => (int)($_POST['mp_max_timestamp_age'] ?? 5)
                ]
            ],
            'presencial' => [
                'enabled' => isset($_POST['presencial_enabled']),
                'instructions' => sanitize_input($_POST['presencial_instructions'] ?? '')
            ]
        ];

        // Save credentials to secure file (outside webroot)
        $payment_credentials = [
            'mercadopago' => [
                'access_token_sandbox' => sanitize_input($_POST['mp_token_sandbox'] ?? ''),
                'access_token_prod' => sanitize_input($_POST['mp_token_prod'] ?? ''),
                'public_key_sandbox' => sanitize_input($_POST['mp_public_sandbox'] ?? ''),
                'public_key_prod' => sanitize_input($_POST['mp_public_prod'] ?? ''),
                'webhook_secret_sandbox' => sanitize_input($_POST['mp_webhook_secret_sandbox'] ?? ''),
                'webhook_secret_prod' => sanitize_input($_POST['mp_webhook_secret_prod'] ?? '')
            ]
        ];

        // Get credentials path
        $credentials_path_file = __DIR__ . '/../.payment_credentials_path';
        $credentials_path = file_exists($credentials_path_file)
            ? trim(file_get_contents($credentials_path_file))
            : '/home/payment_credentials.json';

        // Save credentials
        $json_content = json_encode($payment_credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($credentials_path, $json_content) !== false) {
            @chmod($credentials_path, 0600);
        }

        if (write_json($config_file, $config)) {
            $message = 'Configuración de pagos y credenciales guardadas exitosamente';
            log_admin_action('payment_config_updated', $_SESSION['username']);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

$payment_config = read_json(__DIR__ . '/../config/payment.json');
$payment_credentials = get_payment_credentials();
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Configuración de Pagos';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Get webhook URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$webhook_url = $protocol . $_SERVER['HTTP_HOST'] . '/webhook.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Pagos - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1200px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h2 { font-size: 18px; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; font-family: 'Courier New', monospace; }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal; padding: 10px; background: #f8f9fa; border-radius: 6px; }
        .checkbox-label input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }
        .btn-save { padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save:hover { background: #5568d3; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .help-text { font-size: 12px; color: #666; margin-top: 4px; line-height: 1.4; }
        .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .alert-box strong { color: #856404; }
        .alert-box p { color: #856404; margin: 5px 0; font-size: 13px; }
        .webhook-url { background: #f8f9fa; padding: 10px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 13px; word-break: break-all; border: 1px solid #dee2e6; }
        .copy-btn { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-top: 8px; }
        .copy-btn:hover { background: #218838; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="alert-box">
            <strong>⚠️ Información Importante</strong>
            <p>Para obtener tus credenciales de Mercadopago:</p>
            <p>1. Ingresá a <a href="https://www.mercadopago.com.ar/developers/panel" target="_blank">Mercadopago Developers</a></p>
            <p>2. Creá una aplicación o usá una existente</p>
            <p>3. Copiá el Access Token (Producción y Sandbox)</p>
            <p>4. Configurá la URL del Webhook en tu panel de Mercadopago</p>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <!-- Mercadopago -->
            <div class="card">
                <h2>🔵 Mercadopago</h2>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="mp_enabled" <?php echo ($payment_config['mercadopago']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Habilitar pagos con Mercadopago</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="mp_sandbox" <?php echo ($payment_config['mercadopago']['mode'] ?? 'sandbox') === 'sandbox' ? 'checked' : ''; ?>>
                        <span>Modo Sandbox (testing)</span>
                    </label>
                    <div class="help-text">En modo sandbox, los pagos son simulados y no se cobran realmente.</div>
                </div>

                <div class="alert-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; border-radius: 4px; font-size: 12px;">
                    <strong>🔒 Seguridad:</strong> Las credenciales se guardan en un archivo fuera del directorio público.
                    <a href="/admin/payment-secretos-path.php" style="color: #856404; text-decoration: underline;">Configurar ubicación →</a>
                </div>

                <div class="form-group">
                    <label>Access Token - Sandbox (Testing)</label>
                    <input type="password" name="mp_token_sandbox"
                           value="<?php echo htmlspecialchars($payment_credentials['mercadopago']['access_token_sandbox'] ?? ''); ?>"
                           placeholder="TEST-XXXX-XXXX-XXXX">
                    <div class="help-text">Token de prueba para modo sandbox. Comienza con "TEST-"</div>
                </div>

                <div class="form-group">
                    <label>Public Key - Sandbox</label>
                    <input type="text" name="mp_public_sandbox"
                           value="<?php echo htmlspecialchars($payment_credentials['mercadopago']['public_key_sandbox'] ?? ''); ?>"
                           placeholder="TEST-XXXX-XXXX-XXXX">
                    <div class="help-text">Public key de prueba</div>
                </div>

                <div class="form-group">
                    <label>Access Token - Producción</label>
                    <input type="password" name="mp_token_prod"
                           value="<?php echo htmlspecialchars($payment_credentials['mercadopago']['access_token_prod'] ?? ''); ?>"
                           placeholder="APP_USR-XXXX-XXXX-XXXX">
                    <div class="help-text">Token de producción para cobros reales. Comienza con "APP_USR-"</div>
                </div>

                <div class="form-group">
                    <label>Public Key - Producción</label>
                    <input type="text" name="mp_public_prod"
                           value="<?php echo htmlspecialchars($payment_credentials['mercadopago']['public_key_prod'] ?? ''); ?>"
                           placeholder="APP_USR-XXXX-XXXX-XXXX">
                    <div class="help-text">Public key de producción</div>
                </div>

                <hr style="margin: 30px 0; border: none; border-top: 2px solid #e0e0e0;">

                <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">🔒 Seguridad del Webhook</h3>

                <div class="alert-box" style="background: #e7f3ff; border-left-color: #2196F3;">
                    <strong>🔐 Secret Key del Webhook</strong>
                    <p>Para obtener tu Secret Key:</p>
                    <p>1. Ve a <a href="https://www.mercadopago.com.ar/developers/panel" target="_blank">Mercadopago Developers</a></p>
                    <p>2. Selecciona tu aplicación → Webhooks</p>
                    <p>3. Haz clic en "Revelar Secret Key" y cópiala aquí</p>
                    <p><strong>⚠️ Esta clave es CRÍTICA para prevenir fraude. Sin ella, tu webhook es vulnerable.</strong></p>
                </div>

                <div class="form-group">
                    <label>Webhook Secret - Sandbox (Testing)</label>
                    <input type="password" name="mp_webhook_secret_sandbox"
                           value="<?php echo htmlspecialchars($payment_credentials['mercadopago']['webhook_secret_sandbox'] ?? ''); ?>"
                           placeholder="secret_key_de_sandbox">
                    <div class="help-text">Secret key de tu aplicación de prueba en Mercadopago</div>
                </div>

                <div class="form-group">
                    <label>Webhook Secret - Producción</label>
                    <input type="password" name="mp_webhook_secret_prod"
                           value="<?php echo htmlspecialchars($payment_credentials['mercadopago']['webhook_secret_prod'] ?? ''); ?>"
                           placeholder="secret_key_de_produccion">
                    <div class="help-text">Secret key de tu aplicación de producción en Mercadopago</div>
                </div>

                <div class="form-group">
                    <label>URL del Webhook (copiar y pegar en Mercadopago)</label>
                    <div class="webhook-url"><?php echo htmlspecialchars($webhook_url); ?></div>
                    <button type="button" class="copy-btn" onclick="copyWebhookUrl()">📋 Copiar URL</button>
                    <div class="help-text">Configurá esta URL en tu panel de Mercadopago → Tu aplicación → Webhooks</div>
                </div>

                <hr style="margin: 30px 0; border: none; border-top: 2px solid #e0e0e0;">

                <h3 style="font-size: 16px; margin-bottom: 15px; color: #2c3e50;">⚙️ Opciones de Seguridad Avanzadas</h3>

                <div class="form-group">
                    <label class="checkbox-label" style="background: #e8f5e9;">
                        <input type="checkbox" name="mp_validate_signature" <?php echo ($payment_config['mercadopago']['webhook_security']['validate_signature'] ?? true) ? 'checked' : ''; ?>>
                        <span><strong>Validar X-Signature</strong> (Muy recomendado)</span>
                    </label>
                    <div class="help-text">Valida que la notificación realmente proviene de Mercadopago usando criptografía. <strong>Previene fraude.</strong></div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label" style="background: #fff3e0;">
                        <input type="checkbox" name="mp_validate_timestamp" <?php echo ($payment_config['mercadopago']['webhook_security']['validate_timestamp'] ?? true) ? 'checked' : ''; ?>>
                        <span><strong>Validar Timestamp</strong> (Recomendado)</span>
                    </label>
                    <div class="help-text">Previene ataques de "replay" - rechaza notificaciones antiguas que alguien podría reenviar.</div>
                </div>

                <div class="form-group">
                    <label>Edad máxima del timestamp (minutos)</label>
                    <input type="number" name="mp_max_timestamp_age" min="1" max="60"
                           value="<?php echo (int)($payment_config['mercadopago']['webhook_security']['max_timestamp_age_minutes'] ?? 5); ?>"
                           style="width: 100px;">
                    <div class="help-text">Las notificaciones más antiguas que este tiempo serán rechazadas (recomendado: 5 minutos)</div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label" style="background: #e3f2fd;">
                        <input type="checkbox" name="mp_validate_ip" <?php echo ($payment_config['mercadopago']['webhook_security']['validate_ip'] ?? true) ? 'checked' : ''; ?>>
                        <span><strong>Validar IP de Mercadopago</strong> (Opcional)</span>
                    </label>
                    <div class="help-text">Solo acepta notificaciones desde IPs oficiales de Mercadopago. Capa adicional de seguridad.</div>
                </div>
            </div>

            <!-- Pago Presencial -->
            <div class="card">
                <h2>🏪 Pago Presencial / Retiro en Local</h2>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="presencial_enabled" <?php echo ($payment_config['presencial']['enabled'] ?? true) ? 'checked' : ''; ?>>
                        <span>Habilitar retiro en local / pago presencial</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Instrucciones para el cliente</label>
                    <textarea name="presencial_instructions" rows="5"><?php echo htmlspecialchars($payment_config['presencial']['instructions'] ?? ''); ?></textarea>
                    <div class="help-text">Este texto se mostrará al cliente cuando elija pago presencial</div>
                </div>
            </div>

            <button type="submit" name="save_payment" class="btn-save">💾 Guardar Configuración</button>
        </form>
    </div>

    <script>
        function copyWebhookUrl() {
            const url = '<?php echo $webhook_url; ?>';
            navigator.clipboard.writeText(url).then(() => {
                alert('✅ URL del webhook copiada al portapapeles');
            }).catch(() => {
                prompt('Copia esta URL:', url);
            });
        }
    </script>

    <!-- Unsaved Changes Warning -->
    <script src="/admin/includes/unsaved-changes-warning.js"></script>
</body>
</html>
