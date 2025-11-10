<?php
/**
 * Admin - System Configuration
 * Configure and manage secure credentials
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');

// Page title for header
$page_title = '⚙️ Configuración del Sistema';

// Credentials path file
$credentials_path_file = __DIR__ . '/../.credentials_path';

// Get current credentials path
$current_path = file_exists($credentials_path_file)
    ? trim(file_get_contents($credentials_path_file))
    : '/home/smtp_credentials.json';

// Load current credentials
$current_credentials = [
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls'
    ],
    'telegram' => [
        'bot_token' => '',
        'chat_id' => ''
    ]
];

if (file_exists($current_path)) {
    $loaded = @json_decode(file_get_contents($current_path), true);
    if ($loaded && json_last_error() === JSON_ERROR_NONE) {
        $current_credentials = array_merge($current_credentials, $loaded);
    }
}

// Handle messages
$message = '';
$error = '';

// Helper function to check if path is outside webroot
function is_outside_webroot($path) {
    $webroot_indicators = ['public_html', 'www', 'htdocs', 'web'];
    $path_lower = strtolower($path);

    // Check if path contains webroot indicators
    foreach ($webroot_indicators as $indicator) {
        if (strpos($path_lower, '/' . $indicator . '/') !== false) {
            return false;
        }
    }

    return true;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle credentials save
    if (isset($_POST['save_credentials'])) {
        $new_path = sanitize_input($_POST['credentials_path'] ?? '');

        if (empty($new_path)) {
            $error = '❌ El path no puede estar vacío';
        } elseif (!is_outside_webroot($new_path)) {
            $error = '❌ El path debe estar FUERA del directorio público (public_html, www, htdocs, etc.)';
        } else {
            // Prepare credentials data
            // Remove spaces from SMTP password (Gmail App Passwords are shown with spaces: "abcd efgh ijkl mnop")
            $smtp_password = sanitize_input($_POST['smtp_password'] ?? '');
            $smtp_password = str_replace(' ', '', $smtp_password);

            $credentials = [
                'smtp' => [
                    'host' => sanitize_input($_POST['smtp_host'] ?? 'smtp.gmail.com'),
                    'port' => (int)($_POST['smtp_port'] ?? 587),
                    'username' => sanitize_input($_POST['smtp_username'] ?? ''),
                    'password' => $smtp_password,
                    'encryption' => sanitize_input($_POST['smtp_encryption'] ?? 'tls')
                ],
                'telegram' => [
                    'bot_token' => sanitize_input($_POST['telegram_bot_token'] ?? ''),
                    'chat_id' => sanitize_input($_POST['telegram_chat_id'] ?? '')
                ]
            ];

            // Prepare feedback messages
            $actions_performed = [];
            $file_existed = file_exists($new_path);

            // Ensure directory exists
            $dir = dirname($new_path);
            if (!file_exists($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $error = '❌ No se pudo crear el directorio: ' . $dir . ' (verifica permisos)';
                } else {
                    $actions_performed[] = 'Directorio creado';
                }
            }

            // Verify directory is writable
            if (empty($error) && !is_writable($dir)) {
                $error = '❌ El directorio no es escribible: ' . $dir . ' (verifica permisos)';
            }

            if (empty($error)) {
                // Save credentials JSON
                $json_content = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if (file_put_contents($new_path, $json_content) !== false) {
                    // Track if file was created or updated
                    if (!$file_existed) {
                        $actions_performed[] = 'Archivo creado';
                    } else {
                        $actions_performed[] = 'Archivo actualizado';
                    }

                    // Set restrictive permissions (only owner can read/write)
                    @chmod($new_path, 0600);
                    $actions_performed[] = 'Permisos establecidos (600)';

                    // Save path reference
                    file_put_contents($credentials_path_file, $new_path);
                    $current_path = $new_path;
                    $current_credentials = $credentials;

                    $action_details = implode(' • ', $actions_performed);
                    $message = '✅ Credenciales guardadas exitosamente en: ' . $new_path . '<br><small>' . $action_details . '</small>';
                    log_admin_action('credentials_updated', $_SESSION['username']);
                } else {
                    $error = '❌ Error al escribir el archivo de credenciales (verifica permisos del directorio)';
                }
            }
        }
    }

    // Handle password change
    elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = '❌ Todos los campos son requeridos';
        } elseif ($new_password !== $confirm_password) {
            $error = '❌ Las nuevas contraseñas no coinciden';
        } elseif (strlen($new_password) < 8) {
            $error = '❌ La nueva contraseña debe tener al menos 8 caracteres';
        } else {
            $result = change_admin_password($_SESSION['user_id'], $current_password, $new_password);

            if ($result['success']) {
                $message = '✅ Contraseña actualizada exitosamente';
            } else {
                $error = '❌ ' . $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema - Admin</title>
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

        .main-content {
            margin-left: 260px;
            padding: 15px 20px;
        }

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

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            max-width: 900px;
            margin-bottom: 20px;
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
        }

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
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="number"]:focus,
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

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 13px;
        }

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
            margin-right: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .form-section h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 15px;
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

        <div class="card">
            <div class="card-header">
                <h2>🔐 Configuración de Credenciales Seguras</h2>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante - Seguridad:</strong><br>
                Las credenciales (contraseñas SMTP, tokens de Telegram) se guardan en un archivo JSON <strong>fuera del directorio público</strong> del sitio web.<br>
                Esto garantiza que NO sean accesibles desde internet.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="credentials_path">Path del archivo de credenciales</label>
                    <input type="text" id="credentials_path" name="credentials_path"
                           value="<?php echo htmlspecialchars($current_path); ?>"
                           placeholder="/home/smtp_credentials.json" required>
                    <small>Ruta absoluta donde se guardarán las credenciales (debe estar FUERA de public_html/www)</small>
                </div>

                <div class="info-box">
                    <strong>📝 Ejemplos de paths seguros:</strong><br><br>
                    <strong>Desarrollo local:</strong> <code>/home/smtp_credentials.json</code><br>
                    <strong>Hosting cPanel:</strong> <code>/home2/uv0023/smtp_credentials.json</code> (fuera de public_html)<br>
                    <strong>VPS:</strong> <code>/home/usuario/credentials.json</code><br><br>
                    <strong>❌ NUNCA uses:</strong> <code>/var/www/html/...</code>, <code>/public_html/...</code>, <code>/www/...</code>
                </div>

                <!-- SMTP Credentials -->
                <div class="form-section">
                    <h3>📧 Credenciales SMTP (Gmail)</h3>

                    <div class="grid-2">
                        <div class="form-group">
                            <label for="smtp_host">Host SMTP</label>
                            <input type="text" id="smtp_host" name="smtp_host"
                                   value="<?php echo htmlspecialchars($current_credentials['smtp']['host']); ?>"
                                   placeholder="smtp.gmail.com">
                        </div>

                        <div class="form-group">
                            <label for="smtp_port">Puerto</label>
                            <input type="number" id="smtp_port" name="smtp_port"
                                   value="<?php echo $current_credentials['smtp']['port']; ?>"
                                   placeholder="587">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="smtp_username">Usuario SMTP (tu email de Gmail)</label>
                        <input type="text" id="smtp_username" name="smtp_username"
                               value="<?php echo htmlspecialchars($current_credentials['smtp']['username']); ?>"
                               placeholder="tu-email@gmail.com">
                    </div>

                    <div class="form-group">
                        <label for="smtp_password">Contraseña SMTP (App Password)</label>
                        <input type="password" id="smtp_password" name="smtp_password"
                               value="<?php echo htmlspecialchars($current_credentials['smtp']['password']); ?>"
                               placeholder="••••••••••••••••">
                        <small>Para Gmail, usa una <a href="https://myaccount.google.com/apppasswords" target="_blank">App Password</a> en lugar de tu contraseña normal</small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_encryption">Encriptación</label>
                        <select id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?php echo $current_credentials['smtp']['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (recomendado para puerto 587)</option>
                            <option value="ssl" <?php echo $current_credentials['smtp']['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (para puerto 465)</option>
                        </select>
                    </div>
                </div>

                <!-- Telegram Credentials -->
                <div class="form-section">
                    <h3>📱 Credenciales de Telegram</h3>

                    <div class="form-group">
                        <label for="telegram_bot_token">Bot Token</label>
                        <input type="text" id="telegram_bot_token" name="telegram_bot_token"
                               value="<?php echo htmlspecialchars($current_credentials['telegram']['bot_token']); ?>"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                        <small>Obtén tu token de <a href="https://t.me/BotFather" target="_blank">@BotFather</a></small>
                    </div>

                    <div class="form-group">
                        <label for="telegram_chat_id">Chat ID</label>
                        <input type="text" id="telegram_chat_id" name="telegram_chat_id"
                               value="<?php echo htmlspecialchars($current_credentials['telegram']['chat_id']); ?>"
                               placeholder="123456789">
                        <small>ID del chat/canal donde recibirás mensajes</small>
                    </div>
                </div>

                <div class="info-box">
                    <strong>🔒 Al guardar:</strong><br>
                    1. Se creará automáticamente el archivo JSON en el path especificado<br>
                    2. Se establecerán permisos restrictivos (600 - solo owner puede leer)<br>
                    3. Las credenciales estarán protegidas fuera del alcance web
                </div>

                <button type="submit" name="save_credentials" class="btn btn-primary">
                    💾 Guardar Credenciales
                </button>
            </form>
        </div>

        <!-- Password Change Section -->
        <div class="card">
            <div class="card-header">
                <h2>🔑 Cambiar Contraseña de Administrador</h2>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante - Seguridad:</strong><br>
                Asegúrate de usar una contraseña segura con al menos 8 caracteres. Se recomienda usar una combinación de letras mayúsculas, minúsculas, números y símbolos.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Contraseña Actual</label>
                    <input type="password" id="current_password" name="current_password"
                           placeholder="Tu contraseña actual" required autocomplete="current-password">
                    <small>Ingresa tu contraseña actual para confirmar tu identidad</small>
                </div>

                <div class="form-group">
                    <label for="new_password">Nueva Contraseña</label>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                    <small>Debe tener al menos 8 caracteres</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Nueva Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repite la nueva contraseña" required autocomplete="new-password">
                    <small>Vuelve a ingresar la nueva contraseña para confirmar</small>
                </div>

                <div class="info-box">
                    <strong>🔒 Al cambiar la contraseña:</strong><br>
                    1. Deberás usar la nueva contraseña en tu próximo inicio de sesión<br>
                    2. Se registrará un log de este cambio por seguridad<br>
                    3. Guarda tu nueva contraseña en un lugar seguro
                </div>

                <button type="submit" name="change_password" class="btn btn-primary">
                    🔐 Cambiar Contraseña
                </button>
            </form>
        </div>
    </div>

    <!-- Unsaved Changes Warning -->
    <script src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>
</body>
</html>
