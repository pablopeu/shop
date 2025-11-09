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
$logo_uploaded = false; // Track if logo was just uploaded

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
        $config['footer_text'] = sanitize_input($_POST['footer_text'] ?? '');

        // WhatsApp configuration (new structure)
        $config['whatsapp'] = [
            'enabled' => isset($_POST['whatsapp_enabled']),
            'number' => sanitize_input($_POST['whatsapp_number'] ?? ''),
            'message' => sanitize_input($_POST['whatsapp_message'] ?? 'Hola! Me interesa un producto de su tienda'),
            'custom_link' => sanitize_input($_POST['whatsapp_custom_link'] ?? ''),
            'display_text' => sanitize_input($_POST['whatsapp_display_text'] ?? '')
        ];

        // Keep old whatsapp_number for backward compatibility
        $config['whatsapp_number'] = $config['whatsapp']['number'];

        // Handle logo upload
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../assets/logos/';

            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (in_array($file_ext, $allowed_exts)) {
                $new_filename = 'logo_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upload_path)) {
                    // Delete old logo if exists
                    if (!empty($config['logo']['path']) && file_exists(__DIR__ . '/..' . $config['logo']['path'])) {
                        unlink(__DIR__ . '/..' . $config['logo']['path']);
                    }

                    $config['logo']['path'] = '/assets/logos/' . $new_filename;
                    $config['logo']['enabled'] = true;
                    $logo_uploaded = true;
                    $message = 'Logo subido exitosamente';
                } else {
                    $error = 'Error al subir el logo. Verifique los permisos del directorio.';
                }
            } else {
                $error = 'Formato de archivo no permitido. Use JPG, PNG, GIF, SVG o WebP';
            }
        }

        // Update logo settings (but keep enabled=true if just uploaded)
        if (!$logo_uploaded) {
            $config['logo']['enabled'] = isset($_POST['logo_enabled']);
        }
        $config['logo']['alt'] = sanitize_input($_POST['logo_alt'] ?? 'Logo');

        if (write_json($config_file, $config)) {
            if (empty($message)) {
                $message = 'Configuración guardada exitosamente';
            }
            log_admin_action('site_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Delete logo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_logo'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/site.json';
        $config = read_json($config_file);

        if (!empty($config['logo']['path']) && file_exists(__DIR__ . '/..' . $config['logo']['path'])) {
            unlink(__DIR__ . '/..' . $config['logo']['path']);
        }

        $config['logo']['path'] = '';
        $config['logo']['enabled'] = false;

        if (write_json($config_file, $config)) {
            $message = 'Logo eliminado exitosamente';
            log_admin_action('logo_deleted', $_SESSION['username'], []);
        } else {
            $error = 'Error al eliminar el logo';
        }
    }
}

$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Información del Sitio';
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
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" id="configForm" enctype="multipart/form-data">
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

                <!-- Logo Section -->
                <div class="form-group" style="border-top: 2px solid #e0e0e0; padding-top: 20px; margin-top: 20px;">
                    <label style="font-size: 16px; margin-bottom: 10px;">🖼️ Logo del Sitio</label>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Imagen recomendada: 170x85px (ratio 2:1). Formatos: JPG, PNG, GIF, SVG, WebP</p>

                    <?php if (!empty($site_config['logo']['path'])): ?>
                        <div style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 6px; display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <img src="<?php echo htmlspecialchars($site_config['logo']['path']); ?>"
                                     alt="Logo actual"
                                     style="max-width: 170px; max-height: 85px; border: 1px solid #ddd; border-radius: 4px;">
                                <div>
                                    <strong>Logo Actual</strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars(basename($site_config['logo']['path'])); ?></small>
                                </div>
                            </div>
                            <button type="submit" name="delete_logo"
                                    onclick="return confirm('¿Está seguro de eliminar el logo?')"
                                    style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                🗑️ Eliminar
                            </button>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 15px; align-items: start;">
                        <div style="flex: 1;">
                            <label for="logo_file" style="display: block; margin-bottom: 5px; font-weight: normal;">Subir Nuevo Logo</label>
                            <input type="file" id="logo_file" name="logo_file" accept="image/*">
                        </div>
                        <div style="flex: 1;">
                            <label for="logo_alt" style="display: block; margin-bottom: 5px; font-weight: normal;">Texto Alternativo</label>
                            <input type="text" id="logo_alt" name="logo_alt"
                                   value="<?php echo htmlspecialchars($site_config['logo']['alt'] ?? 'Logo'); ?>"
                                   placeholder="Logo de Mi Tienda"
                                   style="width: 100%;">
                        </div>
                    </div>

                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 10px; cursor: pointer;">
                        <input type="checkbox" name="logo_enabled" <?php echo ($site_config['logo']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span style="font-weight: normal;">Mostrar logo en el sitio</span>
                    </label>
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

                <!-- WhatsApp Configuration Section -->
                <div class="form-group" style="border-top: 2px solid #e0e0e0; padding-top: 20px; margin-top: 20px;">
                    <label style="font-size: 16px; margin-bottom: 10px;">💬 Configuración de WhatsApp</label>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Configura el botón flotante de WhatsApp que aparecerá en tu sitio</p>

                    <label style="margin-bottom: 15px; cursor: pointer; display: block;">
                        <input type="checkbox" name="whatsapp_enabled" <?php echo ($site_config['whatsapp']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span style="font-weight: normal;">Mostrar botón de WhatsApp en el sitio</span>
                    </label>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label for="whatsapp_number" style="display: block; margin-bottom: 5px; font-weight: normal;">Número de WhatsApp</label>
                            <input type="text" id="whatsapp_number" name="whatsapp_number"
                                   value="<?php echo htmlspecialchars($site_config['whatsapp']['number'] ?? $site_config['whatsapp_number'] ?? ''); ?>"
                                   placeholder="5491112345678"
                                   style="width: 100%;">
                        </div>
                        <div>
                            <label for="whatsapp_message" style="display: block; margin-bottom: 5px; font-weight: normal;">Mensaje predeterminado</label>
                            <input type="text" id="whatsapp_message" name="whatsapp_message"
                                   value="<?php echo htmlspecialchars($site_config['whatsapp']['message'] ?? 'Hola! Me interesa un producto de su tienda'); ?>"
                                   placeholder="Hola! Me interesa un producto de su tienda"
                                   style="width: 100%;">
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <label for="whatsapp_custom_link" style="display: block; margin-bottom: 5px; font-weight: normal;">Link personalizado de WhatsApp (opcional)</label>
                        <input type="text" id="whatsapp_custom_link" name="whatsapp_custom_link"
                               value="<?php echo htmlspecialchars($site_config['whatsapp']['custom_link'] ?? ''); ?>"
                               placeholder="https://api.whatsapp.com/message/XXXXX"
                               style="width: 100%;">
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            Si usas un link personalizado, este tendrá prioridad sobre el número de WhatsApp
                        </small>
                    </div>

                    <div style="margin-top: 15px;">
                        <label for="whatsapp_display_text" style="display: block; margin-bottom: 5px; font-weight: normal;">Texto a mostrar (opcional)</label>
                        <input type="text" id="whatsapp_display_text" name="whatsapp_display_text"
                               value="<?php echo htmlspecialchars($site_config['whatsapp']['display_text'] ?? ''); ?>"
                               placeholder="WhatsApp: +54 9 11 1234-5678"
                               style="width: 100%;">
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                            Texto que se mostrará en lugar del número. Si está vacío, se muestra el número directamente
                        </small>
                    </div>
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
