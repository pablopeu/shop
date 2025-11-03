<?php
/**
 * Admin - Site Information Configuration
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();
require_admin();

$message = '';
$error = '';

// Update site config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/site.json';
        $config = read_json($config_file);

        $config['site_name'] = sanitize_input($_POST['site_name'] ?? '');
        $config['site_description'] = sanitize_input($_POST['site_description'] ?? '');
        $config['site_keywords'] = sanitize_input($_POST['site_keywords'] ?? '');
        $config['contact_email'] = sanitize_input($_POST['contact_email'] ?? '');
        $config['contact_phone'] = sanitize_input($_POST['contact_phone'] ?? '');
        $config['whatsapp_number'] = sanitize_input($_POST['whatsapp_number'] ?? '');
        $config['footer_text'] = sanitize_input($_POST['footer_text'] ?? '');

        if (write_json($config_file, $config)) {
            $message = 'Configuración guardada exitosamente';
            log_admin_action('site_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

$site_config = read_json(__DIR__ . '/../config/site.json');
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información del Sitio - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 900px; }
        .content-header { margin-bottom: 20px; }
        .content-header h1 { font-size: 24px; color: #2c3e50; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { min-height: 80px; resize: vertical; font-family: inherit; }
        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h1>📄 Información del Sitio</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" id="configForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="site_name">Nombre del Sitio *</label>
                    <input type="text" id="site_name" name="site_name" required
                           value="<?php echo htmlspecialchars($site_config['site_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="site_description">Descripción del Sitio (SEO)</label>
                    <textarea id="site_description" name="site_description"><?php echo htmlspecialchars($site_config['site_description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="site_keywords">Palabras Clave (SEO, separadas por comas)</label>
                    <input type="text" id="site_keywords" name="site_keywords"
                           value="<?php echo htmlspecialchars($site_config['site_keywords'] ?? ''); ?>"
                           placeholder="ecommerce, tienda, productos">
                </div>

                <div class="form-group">
                    <label for="contact_email">Email de Contacto</label>
                    <input type="email" id="contact_email" name="contact_email"
                           value="<?php echo htmlspecialchars($site_config['contact_email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="contact_phone">Teléfono de Contacto</label>
                    <input type="text" id="contact_phone" name="contact_phone"
                           value="<?php echo htmlspecialchars($site_config['contact_phone'] ?? ''); ?>"
                           placeholder="+54 9 11 1234-5678">
                </div>

                <div class="form-group">
                    <label for="whatsapp_number">Número de WhatsApp</label>
                    <input type="text" id="whatsapp_number" name="whatsapp_number"
                           value="<?php echo htmlspecialchars($site_config['whatsapp_number'] ?? ''); ?>"
                           placeholder="5491112345678">
                </div>

                <div class="form-group">
                    <label for="footer_text">Texto del Footer</label>
                    <input type="text" id="footer_text" name="footer_text"
                           value="<?php echo htmlspecialchars($site_config['footer_text'] ?? ''); ?>"
                           placeholder="© 2025 Mi Tienda. Todos los derechos reservados.">
                </div>

                <button type="submit" name="save_config" class="btn-save" id="saveBtn">
                    💾 Guardar Configuración
                </button>
            </form>
        </div>
    </div>

    <script>
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = form.querySelectorAll('input, textarea');
        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;

        // Store original values
        inputs.forEach(input => {
            originalValues[input.name] = input.value;
        });

        // Detect changes
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                checkForChanges();
            });
        });

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                if (input.value !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

            if (hasChanges) {
                saveBtn.classList.add('changed');
                saveBtn.classList.remove('saved');
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        }

        // Show saved state
        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => {
                saveBtn.classList.remove('saved');
            }, 3000);
        }
    </script>
</body>
</html>
