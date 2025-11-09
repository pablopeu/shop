<?php
/**
 * Admin - System Configuration
 * Configure system-level settings like credentials path
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

// Handle messages
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_path'])) {
        $new_path = sanitize_input($_POST['credentials_path'] ?? '');

        if (empty($new_path)) {
            $error = '❌ El path no puede estar vacío';
        } else {
            // Save the path
            if (file_put_contents($credentials_path_file, $new_path) !== false) {
                $message = '✅ Path de credenciales guardado exitosamente';
                $current_path = $new_path;
                log_admin_action('credentials_path_updated', $_SESSION['username']);
            } else {
                $error = '❌ Error al guardar el path';
            }
        }
    }

    if (isset($_POST['test_path'])) {
        $test_path = sanitize_input($_POST['credentials_path'] ?? $current_path);

        if (file_exists($test_path)) {
            $credentials = @json_decode(file_get_contents($test_path), true);
            if ($credentials && json_last_error() === JSON_ERROR_NONE) {
                $message = '✅ El archivo existe y es un JSON válido';
            } else {
                $error = '❌ El archivo existe pero no es un JSON válido: ' . json_last_error_msg();
            }
        } else {
            $error = '❌ El archivo no existe en ese path';
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
            max-width: 800px;
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
            font-family: 'Courier New', monospace;
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

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .code-block {
            background: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
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
                Las credenciales SMTP y Telegram contienen información sensible (contraseñas, tokens).<br>
                Para máxima seguridad, se guardan en un archivo JSON <strong>fuera del directorio público</strong> del sitio web.<br>
                Este archivo NO debe ser accesible desde internet.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="credentials_path">Path del archivo de credenciales</label>
                    <input type="text" id="credentials_path" name="credentials_path"
                           value="<?php echo htmlspecialchars($current_path); ?>"
                           placeholder="/home/smtp_credentials.json" required>
                    <small>Ruta absoluta al archivo JSON que contiene las credenciales SMTP y Telegram</small>
                </div>

                <div class="info-box">
                    <strong>📝 Ejemplos de paths según tu hosting:</strong><br><br>
                    <strong>Servidor de desarrollo local:</strong><br>
                    <code>/home/smtp_credentials.json</code><br><br>

                    <strong>Hosting compartido (cPanel):</strong><br>
                    <code>/home2/uv0023/smtp_credentials.json</code><br>
                    <code>/home/tu_usuario/smtp_credentials.json</code><br><br>

                    <strong>Nota:</strong> El archivo debe estar <strong>fuera</strong> de <code>public_html</code> o <code>www</code>
                </div>

                <div>
                    <button type="submit" name="save_path" class="btn btn-primary">
                        💾 Guardar Path
                    </button>
                    <button type="submit" name="test_path" class="btn btn-info">
                        🧪 Probar Conexión
                    </button>
                </div>
            </form>

            <div class="info-box" style="margin-top: 30px;">
                <strong>🔧 Instrucciones de configuración:</strong><br><br>

                <strong>1. Crear el archivo de credenciales:</strong><br>
                Copia el archivo de ejemplo a la ubicación segura:<br>
                <div class="code-block">cp smtp_credentials.json.example <?php echo htmlspecialchars($current_path); ?></div>

                <strong>2. Editar las credenciales:</strong><br>
                Edita el archivo y completa tus datos reales:<br>
                <div class="code-block">nano <?php echo htmlspecialchars($current_path); ?></div>

                <strong>3. Establecer permisos correctos:</strong><br>
                <div class="code-block">chmod 600 <?php echo htmlspecialchars($current_path); ?></div>

                <br>
                <strong>Estructura del archivo JSON:</strong>
                <div class="code-block">{
  "smtp": {
    "host": "smtp.gmail.com",
    "port": 587,
    "username": "tu-email@gmail.com",
    "password": "tu-app-password-de-16-caracteres",
    "encryption": "tls"
  },
  "telegram": {
    "bot_token": "123456789:ABC...",
    "chat_id": "123456789"
  }
}</div>
            </div>
        </div>
    </div>
</body>
</html>
