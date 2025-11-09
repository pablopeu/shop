<?php
/**
 * Admin - Notification Settings
 * Configure email and Telegram notifications
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/telegram.php';
require_once __DIR__ . '/../includes/encryption.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');

// Page title for header
$page_title = '🔔 Configuración de Notificaciones';

// File paths
$email_config_file = __DIR__ . '/../config/email.json';
$telegram_config_file = __DIR__ . '/../config/telegram.json';

// Default configurations
$default_email_config = [
    'enabled' => true,
    'method' => 'mail',
    'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'tienda.com'),
    'from_name' => $site_config['site_name'] ?? 'Mi Tienda',
    'admin_email' => '',
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls'
    ],
    'notifications' => [
        'customer' => [
            'order_created' => true,
            'payment_approved' => true,
            'payment_rejected' => true,
            'payment_pending' => true,
            'order_shipped' => true,
            'chargeback_notice' => true
        ],
        'admin' => [
            'new_order' => true,
            'payment_approved' => true,
            'chargeback_alert' => true,
            'low_stock_alert' => true
        ]
    ]
];

$default_telegram_config = [
    'enabled' => false,
    'bot_token' => '',
    'chat_id' => '',
    'notifications' => [
        'new_order' => true,
        'payment_approved' => true,
        'payment_rejected' => false,
        'chargeback_alert' => true,
        'low_stock_alert' => true,
        'high_value_order' => true,
        'high_value_threshold' => 50000
    ]
];

// Load current config or use defaults
$email_config = file_exists($email_config_file)
    ? read_json($email_config_file)
    : $default_email_config;

$telegram_config = file_exists($telegram_config_file)
    ? read_json($telegram_config_file)
    : $default_telegram_config;

// Decrypt sensitive fields for display in form
if (isset($email_config['smtp']['password']) && is_encrypted($email_config['smtp']['password'])) {
    $email_config['smtp']['password'] = decrypt_data($email_config['smtp']['password']);
}
if (isset($email_config['oauth2_credentials']['client_id']) && is_encrypted($email_config['oauth2_credentials']['client_id'])) {
    $email_config['oauth2_credentials']['client_id'] = decrypt_data($email_config['oauth2_credentials']['client_id']);
}
if (isset($email_config['oauth2_credentials']['client_secret']) && is_encrypted($email_config['oauth2_credentials']['client_secret'])) {
    $email_config['oauth2_credentials']['client_secret'] = decrypt_data($email_config['oauth2_credentials']['client_secret']);
}

// Handle messages
$message = '';
$error = '';
$test_result = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save Email Configuration
    if (isset($_POST['save_email'])) {
        // Load existing config to preserve OAuth2 tokens
        $existing_config = file_exists($email_config_file) ? read_json($email_config_file) : [];

        $email_config = [
            'enabled' => isset($_POST['email_enabled']),
            'method' => sanitize_input($_POST['email_method'] ?? 'mail'),
            'from_email' => sanitize_input($_POST['from_email'] ?? ''),
            'from_name' => sanitize_input($_POST['from_name'] ?? ''),
            'admin_email' => sanitize_input($_POST['admin_email'] ?? ''),
            'smtp' => [
                'host' => sanitize_input($_POST['smtp_host'] ?? ''),
                'port' => (int)($_POST['smtp_port'] ?? 587),
                'username' => sanitize_input($_POST['smtp_username'] ?? ''),
                'password' => !empty($_POST['smtp_password']) ? encrypt_data(sanitize_input($_POST['smtp_password'])) : ($existing_config['smtp']['password'] ?? ''),
                'encryption' => sanitize_input($_POST['smtp_encryption'] ?? 'tls'),
                'auth_method' => sanitize_input($_POST['smtp_auth_method'] ?? 'password')
            ],
            'oauth2_credentials' => [
                'client_id' => !empty($_POST['oauth2_client_id']) ? encrypt_data(sanitize_input($_POST['oauth2_client_id'])) : ($existing_config['oauth2_credentials']['client_id'] ?? ''),
                'client_secret' => !empty($_POST['oauth2_client_secret']) ? encrypt_data(sanitize_input($_POST['oauth2_client_secret'])) : ($existing_config['oauth2_credentials']['client_secret'] ?? '')
            ],
            'notifications' => [
                'customer' => [
                    'order_created' => isset($_POST['email_customer_order_created']),
                    'payment_approved' => isset($_POST['email_customer_payment_approved']),
                    'payment_rejected' => isset($_POST['email_customer_payment_rejected']),
                    'payment_pending' => isset($_POST['email_customer_payment_pending']),
                    'order_shipped' => isset($_POST['email_customer_order_shipped']),
                    'chargeback_notice' => isset($_POST['email_customer_chargeback_notice'])
                ],
                'admin' => [
                    'new_order' => isset($_POST['email_admin_new_order']),
                    'payment_approved' => isset($_POST['email_admin_payment_approved']),
                    'chargeback_alert' => isset($_POST['email_admin_chargeback_alert']),
                    'low_stock_alert' => isset($_POST['email_admin_low_stock_alert'])
                ]
            ]
        ];

        // Preserve existing OAuth2 tokens
        if (isset($existing_config['oauth2'])) {
            $email_config['oauth2'] = $existing_config['oauth2'];
        }

        if (write_json($email_config_file, $email_config)) {
            $message = '✅ Configuración de email guardada exitosamente';
        } else {
            $error = '❌ Error al guardar la configuración de email';
        }
    }

    // Revoke OAuth2 Authorization
    if (isset($_POST['revoke_oauth2'])) {
        $config = read_json($email_config_file);
        unset($config['oauth2']);
        if (write_json($email_config_file, $config)) {
            $message = '✅ Autorización OAuth2 revocada exitosamente';
            log_admin_action('oauth2_revoked', $_SESSION['username']);
        } else {
            $error = '❌ Error al revocar OAuth2';
        }
    }

    // Save Telegram Configuration
    if (isset($_POST['save_telegram'])) {
        $telegram_config = [
            'enabled' => isset($_POST['telegram_enabled']),
            'bot_token' => sanitize_input($_POST['bot_token'] ?? ''),
            'chat_id' => sanitize_input($_POST['chat_id'] ?? ''),
            'notifications' => [
                'new_order' => isset($_POST['telegram_new_order']),
                'payment_approved' => isset($_POST['telegram_payment_approved']),
                'payment_rejected' => isset($_POST['telegram_payment_rejected']),
                'chargeback_alert' => isset($_POST['telegram_chargeback_alert']),
                'low_stock_alert' => isset($_POST['telegram_low_stock_alert']),
                'high_value_order' => isset($_POST['telegram_high_value_order']),
                'high_value_threshold' => (float)($_POST['high_value_threshold'] ?? 50000)
            ]
        ];

        if (write_json($telegram_config_file, $telegram_config)) {
            $message = '✅ Configuración de Telegram guardada exitosamente';
        } else {
            $error = '❌ Error al guardar la configuración de Telegram';
        }
    }

    // Test Email
    if (isset($_POST['test_email'])) {
        $test_email = sanitize_input($_POST['test_email_address'] ?? '');
        if (!empty($test_email)) {
            $result = send_email(
                $test_email,
                'Email de Prueba - ' . $site_config['site_name'],
                '<h1>✅ Email de Prueba</h1><p>Si estás leyendo esto, tu configuración de email funciona correctamente.</p><p><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>',
                'Email de Prueba. Si estás leyendo esto, tu configuración funciona correctamente. Fecha: ' . date('d/m/Y H:i:s')
            );

            if ($result) {
                $test_result = '✅ Email de prueba enviado correctamente a ' . htmlspecialchars($test_email);
            } else {
                $test_result = '❌ Error al enviar email de prueba. Revisa los logs del servidor.';
            }
        } else {
            $test_result = '❌ Debes ingresar una dirección de email';
        }
    }

    // Test Telegram
    if (isset($_POST['test_telegram'])) {
        $result = send_telegram_test();

        if ($result) {
            $test_result = '✅ Mensaje de prueba enviado correctamente a Telegram';
        } else {
            $test_result = '❌ Error al enviar mensaje de prueba. Verifica tu bot_token y chat_id.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 15px 20px;
        }


        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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

        .message.info {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        /* Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .card-header h2 {
            font-size: 20px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header .icon {
            font-size: 24px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="password"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #7f8c8d;
            font-size: 12px;
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: normal;
        }

        /* Section */
        .form-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .form-section h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* SMTP Fields */
        .smtp-fields {
            display: none;
            margin-top: 15px;
        }

        .smtp-fields.show {
            display: block;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: auto;
        }

        .status-badge.enabled {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.disabled {
            background: #f8d7da;
            color: #721c24;
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Test Section */
        .test-section {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }

        .test-section h4 {
            font-size: 14px;
            color: #856404;
            margin-bottom: 10px;
        }

        .test-input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .test-input-group .form-group {
            flex: 1;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if ($message): ?>
        <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($test_result): ?>
        <div class="message <?php echo strpos($test_result, '✅') !== false ? 'success' : 'error'; ?>">
            <?php echo $test_result; ?>
        </div>
        <?php endif; ?>

        <div class="cards-grid">
            <!-- EMAIL CONFIGURATION -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <span class="icon">📧</span>
                        Configuración de Email
                    </h2>
                    <span class="status-badge <?php echo $email_config['enabled'] ? 'enabled' : 'disabled'; ?>">
                        <?php echo $email_config['enabled'] ? 'Activado' : 'Desactivado'; ?>
                    </span>
                </div>

                <form method="POST">
                    <!-- Enable/Disable -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="email_enabled" name="email_enabled"
                               <?php echo $email_config['enabled'] ? 'checked' : ''; ?>>
                        <label for="email_enabled">Activar sistema de emails</label>
                    </div>

                    <!-- Basic Settings -->
                    <div class="form-group">
                        <label for="email_method">Método de Envío</label>
                        <select id="email_method" name="email_method" onchange="toggleSmtpFields()">
                            <option value="mail" <?php echo $email_config['method'] === 'mail' ? 'selected' : ''; ?>>
                                PHP mail() - Nativo
                            </option>
                            <option value="smtp" <?php echo $email_config['method'] === 'smtp' ? 'selected' : ''; ?>>
                                SMTP - Externo
                            </option>
                        </select>
                        <small>mail() usa el servidor local, SMTP permite Gmail, etc.</small>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="from_email">Email Remitente</label>
                            <input type="email" id="from_email" name="from_email"
                                   value="<?php echo htmlspecialchars($email_config['from_email']); ?>" required>
                            <small>Email que aparece como remitente</small>
                        </div>

                        <div class="form-group">
                            <label for="from_name">Nombre Remitente</label>
                            <input type="text" id="from_name" name="from_name"
                                   value="<?php echo htmlspecialchars($email_config['from_name']); ?>" required>
                            <small>Nombre que aparece como remitente</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="admin_email">Email del Administrador</label>
                        <input type="email" id="admin_email" name="admin_email"
                               value="<?php echo htmlspecialchars($email_config['admin_email']); ?>" required>
                        <small>Recibirás notificaciones de órdenes, chargebacks, etc.</small>
                    </div>

                    <!-- SMTP Settings -->
                    <div class="smtp-fields <?php echo $email_config['method'] === 'smtp' ? 'show' : ''; ?>" id="smtp-fields">
                        <div class="form-section">
                            <h3>⚙️ Configuración SMTP</h3>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label for="smtp_host">Host SMTP</label>
                                    <input type="text" id="smtp_host" name="smtp_host"
                                           value="<?php echo htmlspecialchars($email_config['smtp']['host']); ?>"
                                           placeholder="smtp.gmail.com">
                                </div>

                                <div class="form-group">
                                    <label for="smtp_port">Puerto</label>
                                    <input type="number" id="smtp_port" name="smtp_port"
                                           value="<?php echo $email_config['smtp']['port']; ?>"
                                           placeholder="587">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="smtp_username">Usuario SMTP</label>
                                <input type="text" id="smtp_username" name="smtp_username"
                                       value="<?php echo htmlspecialchars($email_config['smtp']['username']); ?>"
                                       placeholder="tu-email@gmail.com">
                            </div>

                            <div class="form-group">
                                <label for="smtp_password">Contraseña SMTP</label>
                                <input type="password" id="smtp_password" name="smtp_password"
                                       value="<?php echo htmlspecialchars($email_config['smtp']['password']); ?>"
                                       placeholder="••••••••">
                                <small>Para Gmail, usa una "App Password" en lugar de tu contraseña normal</small>
                            </div>

                            <div class="form-group">
                                <label for="smtp_encryption">Encriptación</label>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo $email_config['smtp']['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo $email_config['smtp']['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>

                            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">

                            <div class="form-group">
                                <label for="smtp_auth_method">Método de Autenticación</label>
                                <select id="smtp_auth_method" name="smtp_auth_method">
                                    <option value="password" <?php echo ($email_config['smtp']['auth_method'] ?? 'password') === 'password' ? 'selected' : ''; ?>>
                                        App Password (Menos seguro)
                                    </option>
                                    <option value="oauth2" <?php echo ($email_config['smtp']['auth_method'] ?? 'password') === 'oauth2' ? 'selected' : ''; ?>>
                                        OAuth2 (Más seguro - Recomendado)
                                    </option>
                                </select>
                                <small>OAuth2 es más seguro ya que no almacena contraseñas y los tokens son revocables</small>
                            </div>

                            <!-- OAuth2 Section -->
                            <div id="oauth2_section" style="display: <?php echo ($email_config['smtp']['auth_method'] ?? 'password') === 'oauth2' ? 'block' : 'none'; ?>;">
                                <div class="info-box" style="background: #e7f3ff; border-left: 4px solid #007bff; padding: 12px; margin: 15px 0; border-radius: 4px; font-size: 13px;">
                                    <strong>🔐 Configuración OAuth2 para Gmail:</strong><br>
                                    1. Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a><br>
                                    2. Crea un proyecto nuevo o selecciona uno existente<br>
                                    3. Habilita "Gmail API" en APIs & Services<br>
                                    4. Ve a "Credentials" → "Create Credentials" → "OAuth 2.0 Client IDs"<br>
                                    5. Tipo: "Web application"<br>
                                    6. Authorized JavaScript origins: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']; ?></code><br>
                                    7. Authorized redirect URIs: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/admin/oauth2-callback.php"; ?></code><br>
                                    8. Copia el Client ID y Client Secret en los campos de abajo
                                </div>

                                <div class="form-group">
                                    <label for="oauth2_client_id">Client ID</label>
                                    <input type="text" id="oauth2_client_id" name="oauth2_client_id"
                                           value="<?php echo htmlspecialchars($email_config['oauth2_credentials']['client_id'] ?? ''); ?>"
                                           placeholder="123456789-abc123.apps.googleusercontent.com">
                                    <small>Tu OAuth 2.0 Client ID de Google Cloud Console</small>
                                </div>

                                <div class="form-group">
                                    <label for="oauth2_client_secret">Client Secret</label>
                                    <input type="password" id="oauth2_client_secret" name="oauth2_client_secret"
                                           value="<?php echo htmlspecialchars($email_config['oauth2_credentials']['client_secret'] ?? ''); ?>"
                                           placeholder="GOCSPX-...">
                                    <small>Tu OAuth 2.0 Client Secret de Google Cloud Console</small>
                                </div>

                                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; border-radius: 4px; font-size: 13px;">
                                    ⚠️ <strong>Importante:</strong> Guarda la configuración después de ingresar Client ID y Client Secret, luego haz click en "Autorizar con Google"
                                </div>

                                <?php if (isset($email_config['oauth2']['access_token'])): ?>
                                    <div class="success-box" style="background: #d4edda; border-left: 4px solid #28a745; padding: 12px; margin: 15px 0; border-radius: 4px;">
                                        ✅ <strong>OAuth2 Autorizado</strong><br>
                                        Email: <strong><?php echo htmlspecialchars($email_config['smtp']['username'] ?? ''); ?></strong><br>
                                        Token expira: <?php
                                            if (isset($email_config['oauth2']['expires_at'])) {
                                                $expires = $email_config['oauth2']['expires_at'] - time();
                                                if ($expires > 0) {
                                                    echo "en " . floor($expires / 60) . " minutos";
                                                } else {
                                                    echo "<span style='color: #dc3545;'>Token expirado (se refrescará automáticamente)</span>";
                                                }
                                            }
                                        ?>
                                        <form method="POST" style="margin-top: 10px; display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <button type="submit" name="revoke_oauth2" class="btn-danger" style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                                🗑️ Revocar Autorización
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="warning-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; border-radius: 4px;">
                                        ⚠️ <strong>OAuth2 no autorizado</strong><br>
                                        Debes autorizar tu cuenta de Gmail antes de poder enviar emails con OAuth2.
                                    </div>

                                    <button type="button" id="authorize_oauth2_btn" class="btn-primary" style="padding: 12px 24px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                        🔑 Autorizar con Google
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Notifications -->
                    <div class="form-section">
                        <h3>👤 Notificaciones al Cliente</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_order_created" name="email_customer_order_created"
                                   <?php echo $email_config['notifications']['customer']['order_created'] ? 'checked' : ''; ?>>
                            <label for="email_customer_order_created">Confirmación de orden creada</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_payment_approved" name="email_customer_payment_approved"
                                   <?php echo $email_config['notifications']['customer']['payment_approved'] ? 'checked' : ''; ?>>
                            <label for="email_customer_payment_approved">Pago aprobado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_payment_rejected" name="email_customer_payment_rejected"
                                   <?php echo $email_config['notifications']['customer']['payment_rejected'] ? 'checked' : ''; ?>>
                            <label for="email_customer_payment_rejected">Pago rechazado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_payment_pending" name="email_customer_payment_pending"
                                   <?php echo $email_config['notifications']['customer']['payment_pending'] ? 'checked' : ''; ?>>
                            <label for="email_customer_payment_pending">Pago pendiente</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_customer_order_shipped" name="email_customer_order_shipped"
                                   <?php echo $email_config['notifications']['customer']['order_shipped'] ? 'checked' : ''; ?>>
                            <label for="email_customer_order_shipped">Orden enviada</label>
                        </div>
                    </div>

                    <!-- Admin Notifications -->
                    <div class="form-section">
                        <h3>👨‍💼 Notificaciones al Administrador</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_new_order" name="email_admin_new_order"
                                   <?php echo $email_config['notifications']['admin']['new_order'] ? 'checked' : ''; ?>>
                            <label for="email_admin_new_order">Nueva orden recibida</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_payment_approved" name="email_admin_payment_approved"
                                   <?php echo $email_config['notifications']['admin']['payment_approved'] ? 'checked' : ''; ?>>
                            <label for="email_admin_payment_approved">Pago aprobado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_chargeback_alert" name="email_admin_chargeback_alert"
                                   <?php echo $email_config['notifications']['admin']['chargeback_alert'] ? 'checked' : ''; ?>>
                            <label for="email_admin_chargeback_alert">Alerta de contracargo (crítico)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="email_admin_low_stock_alert" name="email_admin_low_stock_alert"
                                   <?php echo $email_config['notifications']['admin']['low_stock_alert'] ? 'checked' : ''; ?>>
                            <label for="email_admin_low_stock_alert">Alerta de stock bajo</label>
                        </div>
                    </div>

                    <!-- Test Section -->
                    <div class="test-section">
                        <h4>🧪 Probar Configuración de Email</h4>
                        <div class="test-input-group">
                            <div class="form-group">
                                <label for="test_email_address">Email de Prueba</label>
                                <input type="email" id="test_email_address" name="test_email_address"
                                       placeholder="tu-email@example.com">
                            </div>
                            <button type="submit" name="test_email" class="btn btn-info">
                                📤 Enviar Test
                            </button>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_email" class="btn btn-primary">
                            💾 Guardar Configuración de Email
                        </button>
                    </div>
                </form>
            </div>

            <!-- TELEGRAM CONFIGURATION -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <span class="icon">📱</span>
                        Configuración de Telegram
                    </h2>
                    <span class="status-badge <?php echo $telegram_config['enabled'] ? 'enabled' : 'disabled'; ?>">
                        <?php echo $telegram_config['enabled'] ? 'Activado' : 'Desactivado'; ?>
                    </span>
                </div>

                <form method="POST">
                    <!-- Enable/Disable -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="telegram_enabled" name="telegram_enabled"
                               <?php echo $telegram_config['enabled'] ? 'checked' : ''; ?>>
                        <label for="telegram_enabled">Activar notificaciones por Telegram</label>
                    </div>

                    <!-- Bot Configuration -->
                    <div class="form-group">
                        <label for="bot_token">Bot Token</label>
                        <input type="text" id="bot_token" name="bot_token"
                               value="<?php echo htmlspecialchars($telegram_config['bot_token']); ?>"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                        <small>Obtén tu token de <a href="https://t.me/BotFather" target="_blank">@BotFather</a></small>
                    </div>

                    <div class="form-group">
                        <label for="chat_id">Chat ID</label>
                        <input type="text" id="chat_id" name="chat_id"
                               value="<?php echo htmlspecialchars($telegram_config['chat_id']); ?>"
                               placeholder="123456789">
                        <small>ID del chat/canal donde recibirás mensajes</small>
                    </div>

                    <!-- Info Box -->
                    <div class="message info" style="margin: 20px 0; font-size: 13px;">
                        <strong>ℹ️ Cómo obtener Bot Token y Chat ID:</strong><br><br>
                        <strong>1. Bot Token:</strong><br>
                        • Abre Telegram y busca <a href="https://t.me/BotFather" target="_blank" style="color: #0c5460;">@BotFather</a><br>
                        • Envía <code>/newbot</code> y sigue las instrucciones<br>
                        • Copia el token que te da<br><br>
                        <strong>2. Chat ID:</strong><br>
                        • Busca tu bot en Telegram y envía <code>/start</code><br>
                        • Visita: <code>https://api.telegram.org/bot&lt;TU_TOKEN&gt;/getUpdates</code><br>
                        • Busca el número en <code>"chat":{"id":123456789}</code>
                    </div>

                    <!-- Notifications -->
                    <div class="form-section">
                        <h3>🔔 Tipos de Notificaciones</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_new_order" name="telegram_new_order"
                                   <?php echo $telegram_config['notifications']['new_order'] ? 'checked' : ''; ?>>
                            <label for="telegram_new_order">Nueva orden</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_payment_approved" name="telegram_payment_approved"
                                   <?php echo $telegram_config['notifications']['payment_approved'] ? 'checked' : ''; ?>>
                            <label for="telegram_payment_approved">Pago aprobado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_payment_rejected" name="telegram_payment_rejected"
                                   <?php echo $telegram_config['notifications']['payment_rejected'] ? 'checked' : ''; ?>>
                            <label for="telegram_payment_rejected">Pago rechazado</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_chargeback_alert" name="telegram_chargeback_alert"
                                   <?php echo $telegram_config['notifications']['chargeback_alert'] ? 'checked' : ''; ?>>
                            <label for="telegram_chargeback_alert">🚨 Alerta de contracargo (crítico)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_low_stock_alert" name="telegram_low_stock_alert"
                                   <?php echo $telegram_config['notifications']['low_stock_alert'] ? 'checked' : ''; ?>>
                            <label for="telegram_low_stock_alert">Alerta de stock bajo</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="telegram_high_value_order" name="telegram_high_value_order"
                                   <?php echo $telegram_config['notifications']['high_value_order'] ? 'checked' : ''; ?>>
                            <label for="telegram_high_value_order">Destacar órdenes de alto valor 🌟</label>
                        </div>
                    </div>

                    <!-- High Value Threshold -->
                    <div class="form-group">
                        <label for="high_value_threshold">Umbral para Órdenes de Alto Valor</label>
                        <input type="number" id="high_value_threshold" name="high_value_threshold"
                               value="<?php echo $telegram_config['notifications']['high_value_threshold']; ?>"
                               step="0.01" min="0">
                        <small>Órdenes iguales o mayores a este monto se destacan con 🌟</small>
                    </div>

                    <!-- Test Section -->
                    <div class="test-section">
                        <h4>🧪 Probar Conexión con Telegram</h4>
                        <p style="font-size: 13px; color: #856404; margin-bottom: 10px;">
                            Envía un mensaje de prueba a tu bot para verificar la configuración
                        </p>
                        <button type="submit" name="test_telegram" class="btn btn-info">
                            📤 Enviar Mensaje de Prueba
                        </button>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="save_telegram" class="btn btn-success">
                            💾 Guardar Configuración de Telegram
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSmtpFields() {
            const method = document.getElementById('email_method').value;
            const smtpFields = document.getElementById('smtp-fields');

            if (method === 'smtp') {
                smtpFields.classList.add('show');
            } else {
                smtpFields.classList.remove('show');
            }
        }

        function toggleOAuth2Section() {
            const authMethod = document.getElementById('smtp_auth_method');
            const oauth2Section = document.getElementById('oauth2_section');

            if (!authMethod || !oauth2Section) return;

            if (authMethod.value === 'oauth2') {
                oauth2Section.style.display = 'block';
            } else {
                oauth2Section.style.display = 'none';
            }
        }

        // OAuth2 Authorization - Simple implementation
        function authorizeOAuth2() {
            const username = document.getElementById('smtp_username');

            if (!username || !username.value) {
                alert('Por favor ingresa tu email de Gmail primero en el campo "Usuario SMTP"');
                return;
            }

            // Redirect to start OAuth2 flow
            // The callback page will handle the authorization
            const redirectUri = encodeURIComponent(window.location.origin + '/admin/oauth2-callback.php');
            const state = encodeURIComponent(Math.random().toString(36).substring(7));

            // Note: This requires credentials.php to be configured with Client ID
            // The actual authorization URL will be generated by the server
            window.location.href = `/admin/oauth2-start.php?email=${encodeURIComponent(username.value)}`;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleSmtpFields();
            toggleOAuth2Section();

            // Add event listeners
            const emailMethod = document.getElementById('email_method');
            if (emailMethod) {
                emailMethod.addEventListener('change', toggleSmtpFields);
            }

            const authMethod = document.getElementById('smtp_auth_method');
            if (authMethod) {
                authMethod.addEventListener('change', toggleOAuth2Section);
            }

            const authorizeBtn = document.getElementById('authorize_oauth2_btn');
            if (authorizeBtn) {
                authorizeBtn.addEventListener('click', authorizeOAuth2);
            }
        });
    </script>
</body>
</html>
