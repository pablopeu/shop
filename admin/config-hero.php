<?php
/**
 * Admin - Hero Configuration
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

session_start();
require_admin();

$message = '';
$error = '';

// Handle image upload and config update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/hero.json';
        $config = read_json($config_file);

        // Handle image upload
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = upload_image($_FILES['hero_image'], 'hero');

            if ($upload_result['success']) {
                // Delete old image if exists
                if (!empty($config['image']) && strpos($config['image'], '/images/') === 0) {
                    delete_uploaded_image($config['image']);
                }
                $config['image'] = $upload_result['file_path'];
            } else {
                $error = $upload_result['error'];
            }
        }

        // Update other fields only if no upload error
        if (empty($error)) {
            $config['enabled'] = isset($_POST['enabled']);
            $config['title'] = sanitize_input($_POST['title'] ?? '');
            $config['subtitle'] = sanitize_input($_POST['subtitle'] ?? '');
            $config['background_color'] = sanitize_input($_POST['background_color'] ?? '#667eea');

            // Remove old button fields if they exist
            unset($config['button_text']);
            unset($config['button_link']);

            if (write_json($config_file, $config)) {
                $message = 'Configuración del hero guardada exitosamente';
                log_admin_action('hero_config_updated', $_SESSION['username'], $config);
            } else {
                $error = 'Error al guardar la configuración';
            }
        }
    }
}

// Handle image deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_image') {
    $config_file = __DIR__ . '/../config/hero.json';
    $config = read_json($config_file);

    if (!empty($config['image'])) {
        delete_uploaded_image($config['image']);
        $config['image'] = '';
        write_json($config_file, $config);
        $message = 'Imagen eliminada';
    }
}

$hero_config = read_json(__DIR__ . '/../config/hero.json');
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hero Principal - Admin</title>
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
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: auto; }
        .image-preview { margin-top: 10px; }
        .image-preview img { max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .image-actions { margin-top: 10px; display: flex; gap: 10px; }
        .btn-delete { padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; font-size: 13px; cursor: pointer; }
        .file-input-wrapper { position: relative; display: inline-block; width: 100%; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #e0e0e0; border-radius: 6px; cursor: pointer; }
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
            <h1>🖼️ Hero Principal</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" enctype="multipart/form-data" id="configForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled"
                               <?php echo ($hero_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <label for="enabled">Mostrar Hero en la página principal</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">Título del Hero *</label>
                    <input type="text" id="title" name="title" required
                           value="<?php echo htmlspecialchars($hero_config['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="subtitle">Subtítulo</label>
                    <textarea id="subtitle" name="subtitle"><?php echo htmlspecialchars($hero_config['subtitle'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="background_color">Color de Fondo</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="color" id="background_color" name="background_color"
                               value="<?php echo htmlspecialchars($hero_config['background_color'] ?? '#667eea'); ?>"
                               style="width: 60px; height: 40px; border: none; border-radius: 6px; cursor: pointer;">
                        <input type="text" id="background_color_text"
                               value="<?php echo htmlspecialchars($hero_config['background_color'] ?? '#667eea'); ?>"
                               readonly
                               style="flex: 1; background: #f5f5f5;">
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">Color de fondo cuando no hay imagen</small>
                </div>

                <div class="form-group">
                    <label for="hero_image">Imagen de Fondo del Hero</label>
                    <?php if (!empty($hero_config['image'])): ?>
                        <div class="image-preview">
                            <img src="<?php echo htmlspecialchars($hero_config['image']); ?>" alt="Hero image">
                            <div class="image-actions">
                                <a href="?action=delete_image" class="btn-delete" onclick="return confirm('¿Eliminar esta imagen?')">🗑️ Eliminar Imagen</a>
                            </div>
                        </div>
                        <p style="margin-top: 10px; font-size: 13px; color: #666;">Sube una nueva imagen para reemplazar la actual:</p>
                    <?php endif; ?>
                    <div class="file-input-wrapper">
                        <input type="file" id="hero_image" name="hero_image" accept="image/*">
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">Formatos: JPG, PNG, GIF, WebP. Tamaño máximo: 5MB</small>
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
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]):not([readonly]), textarea');
        const fileInput = document.getElementById('hero_image');
        const colorPicker = document.getElementById('background_color');
        const colorText = document.getElementById('background_color_text');
        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;

        // Sync color picker with text input
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value;
        });

        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }
        });

        inputs.forEach(input => {
            input.addEventListener('input', checkForChanges);
            input.addEventListener('change', checkForChanges);
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                saveBtn.classList.add('changed');
            }
        });

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

            if (hasChanges || fileInput.files.length > 0) {
                saveBtn.classList.add('changed');
                saveBtn.classList.remove('saved');
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        }

        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => saveBtn.classList.remove('saved'), 3000);
        }
    </script>
</body>
</html>
