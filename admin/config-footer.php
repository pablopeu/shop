<?php
/**
 * Admin - Footer Configuration
 * Configuración del footer HTML personalizado
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();
require_admin();

$message = '';
$error = '';

// Update footer config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_footer'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/footer.json';
        $config = read_json($config_file);

        $config['enabled'] = isset($_POST['footer_enabled']);
        $config['html'] = $_POST['footer_html'] ?? '';
        $config['use_default'] = isset($_POST['use_default']);

        if (write_json($config_file, $config)) {
            $message = 'Configuración del footer guardada exitosamente';
            log_admin_action('footer_config_updated', $_SESSION['username'], [
                'enabled' => $config['enabled'],
                'use_default' => $config['use_default'],
                'html_length' => strlen($config['html'])
            ]);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

$footer_config = read_json(__DIR__ . '/../config/footer.json');
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Footer - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1200px; }
        .content-header { margin-bottom: 20px; }
        .content-header h1 { font-size: 24px; color: #2c3e50; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 13px; font-family: 'Courier New', monospace; transition: border-color 0.3s; resize: vertical; }
        .form-group textarea:focus { outline: none; border-color: #667eea; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal; }
        .checkbox-label input[type="checkbox"] { cursor: pointer; }
        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .help-box { background: #e7f3ff; border-left: 4px solid #2196f3; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .help-box h3 { font-size: 15px; margin-bottom: 8px; color: #1976d2; }
        .help-box p { font-size: 13px; color: #555; line-height: 1.6; margin-bottom: 8px; }
        .help-box code { background: #fff; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 12px; }
        .preview-box { background: #f8f9fa; border: 2px dashed #ddd; border-radius: 6px; padding: 20px; margin-top: 15px; }
        .preview-box h4 { font-size: 14px; margin-bottom: 10px; color: #666; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h1>🦶 Configuración del Footer</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="help-box">
            <h3>💡 Ayuda - Footer Personalizado</h3>
            <p>Puedes crear un footer HTML completamente personalizado para tu tienda.</p>
            <p><strong>Consejos:</strong></p>
            <p>• Usa clases CSS del theme actual para que se adapte a los estilos</p>
            <p>• Incluye información de contacto, redes sociales, links importantes</p>
            <p>• El HTML se insertará dentro de <code>&lt;footer class="footer"&gt;</code></p>
            <p>• Si desmarcas "Usar footer personalizado", se mostrará el footer por defecto</p>
        </div>

        <div class="card">
            <form method="POST" action="" id="footerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="footer_enabled" id="footer_enabled"
                               <?php echo ($footer_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>✅ Usar footer personalizado</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="use_default" id="use_default"
                               <?php echo ($footer_config['use_default'] ?? true) ? 'checked' : ''; ?>>
                        <span>Usar footer por defecto si no hay HTML personalizado</span>
                    </label>
                </div>

                <div class="form-group">
                    <label for="footer_html">HTML del Footer Personalizado</label>
                    <textarea id="footer_html" name="footer_html" rows="20" placeholder="Ingresa tu HTML personalizado aquí..."><?php echo htmlspecialchars($footer_config['html'] ?? ''); ?></textarea>
                </div>

                <?php if (!empty($footer_config['html'])): ?>
                <div class="preview-box">
                    <h4>📋 Vista Previa (HTML renderizado):</h4>
                    <?php echo $footer_config['html']; ?>
                </div>
                <?php endif; ?>

                <button type="submit" name="save_footer" class="btn-save" id="saveBtn">
                    💾 Guardar Configuración
                </button>
            </form>
        </div>

        <!-- Example Section -->
        <div class="card">
            <h3 style="margin-bottom: 15px; font-size: 16px;">📝 Ejemplo de Footer HTML</h3>
            <textarea readonly rows="20" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 12px; background: #f8f9fa;"><div class="container">
    <div class="footer-content">
        <div class="footer-section">
            <h3>Mi Tienda</h3>
            <p>Tu tienda online de confianza</p>
            <p>Productos de calidad al mejor precio</p>
        </div>
        <div class="footer-section">
            <h4>Contacto</h4>
            <p>📧 Email: contacto@mitienda.com</p>
            <p>📱 WhatsApp: +54 9 11 1234-5678</p>
            <p>📍 Dirección: Av. Principal 123, CABA</p>
        </div>
        <div class="footer-section">
            <h4>Enlaces Rápidos</h4>
            <a href="/">Inicio</a><br>
            <a href="/buscar.php">Buscar Productos</a><br>
            <a href="/favoritos.php">Favoritos</a><br>
            <a href="/track.php">Rastrear Pedido</a>
        </div>
        <div class="footer-section">
            <h4>Síguenos</h4>
            <p>🔵 Facebook: /mitienda</p>
            <p>📸 Instagram: @mitienda</p>
            <p>🐦 Twitter: @mitienda</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 Mi Tienda. Todos los derechos reservados.</p>
        <p>Diseñado con ❤️ en Argentina</p>
    </div>
</div></textarea>
        </div>
    </div>

    <script>
        const form = document.getElementById('footerForm');
        const saveBtn = document.getElementById('saveBtn');
        const textarea = document.getElementById('footer_html');
        let originalValue = textarea.value;
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;

        // Detect changes
        textarea.addEventListener('input', () => {
            if (textarea.value !== originalValue) {
                saveBtn.classList.add('changed');
                saveBtn.classList.remove('saved');
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        });

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
