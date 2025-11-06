<?php
/**
 * Admin - Footer Configuration
 * Configuración del footer avanzado de 3 columnas
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

        // Build links array
        $links = [];
        if (!empty($_POST['link_text'])) {
            foreach ($_POST['link_text'] as $index => $text) {
                if (!empty($text) && !empty($_POST['link_url'][$index])) {
                    $links[] = [
                        'text' => $text,
                        'url' => $_POST['link_url'][$index]
                    ];
                }
            }
        }

        // Build phones array
        $phones = [];
        if (!empty($_POST['phone_number'])) {
            foreach ($_POST['phone_number'] as $index => $number) {
                if (!empty($number)) {
                    $phones[] = [
                        'number' => $number,
                        'label' => $_POST['phone_label'][$index] ?? ''
                    ];
                }
            }
        }

        $config = [
            'enabled' => isset($_POST['footer_enabled']),
            'type' => 'advanced',
            'background_color' => $_POST['background_color'] ?? '#292c2f',
            'text_color' => $_POST['text_color'] ?? '#ffffff',
            'left_column' => [
                'logo' => [
                    'enabled' => isset($_POST['logo_enabled']),
                    'path' => $_POST['logo_path'] ?? '',
                    'alt' => $_POST['logo_alt'] ?? 'Logo',
                    'width' => (int)($_POST['logo_width'] ?? 169),
                    'height' => (int)($_POST['logo_height'] ?? 83)
                ],
                'links' => $links,
                'email' => [
                    'enabled' => isset($_POST['email_enabled']),
                    'address' => $_POST['email_address'] ?? '',
                    'subject' => $_POST['email_subject'] ?? 'Consulta desde el sitio web',
                    'icon_color' => $_POST['email_icon_color'] ?? '#4f4f4f'
                ]
            ],
            'center_column' => [
                'address' => [
                    'enabled' => isset($_POST['address_enabled']),
                    'street' => $_POST['address_street'] ?? '',
                    'city' => $_POST['address_city'] ?? '',
                    'country' => $_POST['address_country'] ?? '',
                    'map_url' => $_POST['address_map_url'] ?? '',
                    'icon_color' => $_POST['address_icon_color'] ?? '#4f4f4f'
                ],
                'phones' => $phones,
                'schedule' => [
                    'enabled' => isset($_POST['schedule_enabled']),
                    'days' => $_POST['schedule_days'] ?? 'Lunes a Viernes',
                    'hours' => $_POST['schedule_hours'] ?? 'de 9 a 18hs',
                    'icon_color' => $_POST['schedule_icon_color'] ?? '#4f4f4f'
                ]
            ],
            'right_column' => [
                'about' => [
                    'enabled' => isset($_POST['about_enabled']),
                    'title' => $_POST['about_title'] ?? 'Acerca de nosotros',
                    'text' => $_POST['about_text'] ?? ''
                ],
                'social' => [
                    'enabled' => isset($_POST['social_enabled']),
                    'facebook' => $_POST['social_facebook'] ?? '',
                    'twitter' => $_POST['social_twitter'] ?? '',
                    'instagram' => $_POST['social_instagram'] ?? '',
                    'telegram' => $_POST['social_telegram'] ?? '',
                    'icon_bg_color' => $_POST['social_icon_bg_color'] ?? '#4f4f4f'
                ]
            ]
        ];

        if (write_json($config_file, $config)) {
            $message = 'Configuración del footer guardada exitosamente';
            log_admin_action('footer_config_updated', $_SESSION['username'], [
                'enabled' => $config['enabled']
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1400px; }
        .content-header { margin-bottom: 20px; }
        .content-header h1 { font-size: 24px; color: #2c3e50; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h2 { font-size: 18px; margin-bottom: 15px; color: #2c3e50; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group input[type="color"] { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 80px; font-family: inherit; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal; }
        .checkbox-label input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }
        .btn-save { padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save:hover { background: #5568d3; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .btn-add { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; margin-top: 10px; }
        .btn-remove { padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; }
        .repeater-item { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 10px; position: relative; }
        .repeater-item .btn-remove { position: absolute; top: 10px; right: 10px; }
        .color-input-wrapper { display: flex; gap: 10px; align-items: center; }
        .color-input-wrapper input[type="color"] { width: 60px; height: 40px; cursor: pointer; }
        .color-input-wrapper input[type="text"] { flex: 1; }
        .help-text { font-size: 12px; color: #666; margin-top: 4px; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <h1>🦶 Configuración del Footer Avanzado</h1>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="footerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <!-- General Settings -->
            <div class="card">
                <h2>⚙️ Configuración General</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="footer_enabled" <?php echo ($footer_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                            <span>✅ Activar footer personalizado</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Color de fondo</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="background_color" value="<?php echo htmlspecialchars($footer_config['background_color'] ?? '#292c2f'); ?>">
                            <input type="text" name="background_color_text" value="<?php echo htmlspecialchars($footer_config['background_color'] ?? '#292c2f'); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Color de texto</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="text_color" value="<?php echo htmlspecialchars($footer_config['text_color'] ?? '#ffffff'); ?>">
                            <input type="text" name="text_color_text" value="<?php echo htmlspecialchars($footer_config['text_color'] ?? '#ffffff'); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Left Column -->
            <div class="card">
                <h2>📌 Columna Izquierda</h2>

                <!-- Logo -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="logo_enabled" <?php echo ($footer_config['left_column']['logo']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar logo</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Ruta del logo</label>
                        <input type="text" name="logo_path" value="<?php echo htmlspecialchars($footer_config['left_column']['logo']['path'] ?? ''); ?>" placeholder="/uploads/logo.png">
                        <div class="help-text">Ruta relativa o absoluta de la imagen</div>
                    </div>
                    <div class="form-group">
                        <label>Texto alternativo</label>
                        <input type="text" name="logo_alt" value="<?php echo htmlspecialchars($footer_config['left_column']['logo']['alt'] ?? 'Logo'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Ancho (px)</label>
                        <input type="number" name="logo_width" value="<?php echo (int)($footer_config['left_column']['logo']['width'] ?? 169); ?>">
                    </div>
                    <div class="form-group">
                        <label>Alto (px)</label>
                        <input type="number" name="logo_height" value="<?php echo (int)($footer_config['left_column']['logo']['height'] ?? 83); ?>">
                    </div>
                </div>

                <!-- Links -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">Enlaces</h3>
                <div id="links-container">
                    <?php
                    $links = $footer_config['left_column']['links'] ?? [];
                    foreach ($links as $index => $link):
                    ?>
                    <div class="repeater-item">
                        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Eliminar</button>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Texto del enlace</label>
                                <input type="text" name="link_text[]" value="<?php echo htmlspecialchars($link['text'] ?? ''); ?>" placeholder="Inicio">
                            </div>
                            <div class="form-group">
                                <label>URL</label>
                                <input type="text" name="link_url[]" value="<?php echo htmlspecialchars($link['url'] ?? ''); ?>" placeholder="/">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add" onclick="addLink()">➕ Agregar enlace</button>

                <!-- Email -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">Email</h3>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="email_enabled" <?php echo ($footer_config['left_column']['email']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar email</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Dirección de email</label>
                        <input type="email" name="email_address" value="<?php echo htmlspecialchars($footer_config['left_column']['email']['address'] ?? ''); ?>" placeholder="contacto@ejemplo.com">
                    </div>
                    <div class="form-group">
                        <label>Asunto predeterminado</label>
                        <input type="text" name="email_subject" value="<?php echo htmlspecialchars($footer_config['left_column']['email']['subject'] ?? 'Consulta desde el sitio web'); ?>">
                    </div>
                </div>
            </div>

            <!-- Center Column -->
            <div class="card">
                <h2>📍 Columna Central</h2>

                <!-- Address -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="address_enabled" <?php echo ($footer_config['center_column']['address']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar dirección</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Calle y número</label>
                        <input type="text" name="address_street" value="<?php echo htmlspecialchars($footer_config['center_column']['address']['street'] ?? ''); ?>" placeholder="Av. Principal 123">
                    </div>
                    <div class="form-group">
                        <label>Ciudad</label>
                        <input type="text" name="address_city" value="<?php echo htmlspecialchars($footer_config['center_column']['address']['city'] ?? ''); ?>" placeholder="Buenos Aires">
                    </div>
                    <div class="form-group">
                        <label>País</label>
                        <input type="text" name="address_country" value="<?php echo htmlspecialchars($footer_config['center_column']['address']['country'] ?? ''); ?>" placeholder="Argentina">
                    </div>
                    <div class="form-group">
                        <label>URL de Google Maps</label>
                        <input type="url" name="address_map_url" value="<?php echo htmlspecialchars($footer_config['center_column']['address']['map_url'] ?? ''); ?>" placeholder="https://maps.google.com/...">
                        <div class="help-text">URL completa de Google Maps</div>
                    </div>
                </div>

                <!-- Phones -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">Teléfonos</h3>
                <div id="phones-container">
                    <?php
                    $phones = $footer_config['center_column']['phones'] ?? [];
                    foreach ($phones as $index => $phone):
                    ?>
                    <div class="repeater-item">
                        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Eliminar</button>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Número de teléfono</label>
                                <input type="text" name="phone_number[]" value="<?php echo htmlspecialchars($phone['number'] ?? ''); ?>" placeholder="+54 11 1234-5678">
                            </div>
                            <div class="form-group">
                                <label>Etiqueta (opcional)</label>
                                <input type="text" name="phone_label[]" value="<?php echo htmlspecialchars($phone['label'] ?? ''); ?>" placeholder="Ventas, Soporte, etc.">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add" onclick="addPhone()">➕ Agregar teléfono</button>

                <!-- Schedule -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">Horario de atención</h3>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="schedule_enabled" <?php echo ($footer_config['center_column']['schedule']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar horario</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Días</label>
                        <input type="text" name="schedule_days" value="<?php echo htmlspecialchars($footer_config['center_column']['schedule']['days'] ?? 'Lunes a Viernes'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Horario</label>
                        <input type="text" name="schedule_hours" value="<?php echo htmlspecialchars($footer_config['center_column']['schedule']['hours'] ?? 'de 9 a 18hs'); ?>">
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="card">
                <h2>ℹ️ Columna Derecha</h2>

                <!-- About -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="about_enabled" <?php echo ($footer_config['right_column']['about']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar "Acerca de"</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="about_title" value="<?php echo htmlspecialchars($footer_config['right_column']['about']['title'] ?? 'Acerca de nosotros'); ?>">
                </div>
                <div class="form-group">
                    <label>Texto</label>
                    <textarea name="about_text" rows="4"><?php echo htmlspecialchars($footer_config['right_column']['about']['text'] ?? ''); ?></textarea>
                </div>

                <!-- Social Media -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">Redes Sociales</h3>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="social_enabled" <?php echo ($footer_config['right_column']['social']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar redes sociales</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Facebook</label>
                        <input type="url" name="social_facebook" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['facebook'] ?? ''); ?>" placeholder="https://facebook.com/...">
                    </div>
                    <div class="form-group">
                        <label>Twitter</label>
                        <input type="url" name="social_twitter" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['twitter'] ?? ''); ?>" placeholder="https://twitter.com/...">
                    </div>
                    <div class="form-group">
                        <label>Instagram</label>
                        <input type="url" name="social_instagram" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['instagram'] ?? ''); ?>" placeholder="https://instagram.com/...">
                    </div>
                    <div class="form-group">
                        <label>Telegram</label>
                        <input type="url" name="social_telegram" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['telegram'] ?? ''); ?>" placeholder="https://t.me/...">
                    </div>
                </div>
            </div>

            <button type="submit" name="save_footer" class="btn-save">💾 Guardar Configuración</button>
        </form>
    </div>

    <script>
        // Sync color inputs
        document.querySelectorAll('input[type="color"]').forEach(colorInput => {
            const textInput = colorInput.nextElementSibling;
            colorInput.addEventListener('input', () => {
                textInput.value = colorInput.value;
            });
        });

        // Add link
        function addLink() {
            const container = document.getElementById('links-container');
            const item = document.createElement('div');
            item.className = 'repeater-item';
            item.innerHTML = `
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Eliminar</button>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Texto del enlace</label>
                        <input type="text" name="link_text[]" placeholder="Inicio">
                    </div>
                    <div class="form-group">
                        <label>URL</label>
                        <input type="text" name="link_url[]" placeholder="/">
                    </div>
                </div>
            `;
            container.appendChild(item);
        }

        // Add phone
        function addPhone() {
            const container = document.getElementById('phones-container');
            const item = document.createElement('div');
            item.className = 'repeater-item';
            item.innerHTML = `
                <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Eliminar</button>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Número de teléfono</label>
                        <input type="text" name="phone_number[]" placeholder="+54 11 1234-5678">
                    </div>
                    <div class="form-group">
                        <label>Etiqueta (opcional)</label>
                        <input type="text" name="phone_label[]" placeholder="Ventas, Soporte, etc.">
                    </div>
                </div>
            `;
            container.appendChild(item);
        }
    </script>
</body>
</html>
