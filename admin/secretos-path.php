<?php
/**
 * Admin - Secure Credentials Path Configuration
 * Configure where sensitive credentials are stored (outside webroot)
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
$page_title = '🔐 Ubicación de Secretos';

// Credentials path file
$credentials_path_file = __DIR__ . '/../.credentials_path';

// Get current credentials path
$current_path = file_exists($credentials_path_file)
    ? trim(file_get_contents($credentials_path_file))
    : '/home/smtp_credentials.json';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_path'])) {
        $new_path = sanitize_input($_POST['credentials_path'] ?? '');

        if (empty($new_path)) {
            $error = '❌ El path no puede estar vacío';
        } elseif (!is_outside_webroot($new_path)) {
            $error = '❌ El path debe estar FUERA del directorio público (public_html, www, htdocs, etc.)';
        } else {
            // Ensure directory exists
            $dir = dirname($new_path);
            if (!file_exists($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $error = '❌ No se pudo crear el directorio: ' . $dir . ' (verifica permisos)';
                }
            }

            // Verify directory is writable
            if (empty($error) && !is_writable($dir)) {
                $error = '❌ El directorio no es escribible: ' . $dir . ' (verifica permisos)';
            }

            if (empty($error)) {
                // Save path reference
                if (file_put_contents($credentials_path_file, $new_path) !== false) {
                    $current_path = $new_path;
                    $message = '✅ Path de credenciales guardado exitosamente: ' . $new_path;
                    log_admin_action('credentials_path_updated', $_SESSION['username']);
                } else {
                    $error = '❌ Error al guardar el path de credenciales';
                }
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
    <title>Ubicación de Secretos - Admin</title>
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

        .form-group input[type="text"] {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input[type="text"]:focus {
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
                <h2>🔐 Ubicación del Archivo de Secretos</h2>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante - Seguridad:</strong><br>
                Este archivo contiene credenciales sensibles (contraseñas SMTP, tokens de Telegram).<br>
                Debe estar almacenado <strong>fuera del directorio público</strong> del sitio web para que NO sea accesible desde internet.
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

                <div class="info-box">
                    <strong>ℹ️ Nota:</strong> Solo necesitas configurar el path aquí. Las credenciales (usuario, contraseña, tokens)
                    se editan desde <strong>"Email y Notificaciones → Configuración"</strong>.
                </div>

                <button type="submit" name="save_path" class="btn btn-primary">
                    💾 Guardar Path
                </button>
                <a href="/admin/notificaciones.php" class="btn btn-secondary">
                    ← Volver a Configuración
                </a>
            </form>
        </div>
    </div>
</body>
</html>
