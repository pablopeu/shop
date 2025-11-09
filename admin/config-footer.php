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

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/footer/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_tmp = $_FILES['logo_upload']['tmp_name'];
    $file_name = $_FILES['logo_upload']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validar extensión
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($file_ext, $allowed_ext)) {
        $new_filename = 'logo-' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($file_tmp, $upload_path)) {
            $_POST['logo_path'] = '/uploads/footer/' . $new_filename;
            $message = 'Logo subido exitosamente';
        } else {
            $error = 'Error al subir el logo';
        }
    } else {
        $error = 'Solo se permiten imágenes (JPG, PNG, GIF, WEBP)';
    }
}

// Update footer config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_footer'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/footer.json';
        $current_config = read_json($config_file);

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

        // Keep existing logo path if not uploaded new one
        $logo_path = $_POST['logo_path'] ?? $current_config['left_column']['logo']['path'] ?? '';

        // Build new config (not merging arrays to avoid preserving deleted items)
        $config = [
            'enabled' => isset($_POST['footer_enabled']),
            'type' => 'advanced',
            'background_color' => $_POST['background_color'] ?? $current_config['background_color'] ?? '#292c2f',
            'text_color' => $_POST['text_color'] ?? $current_config['text_color'] ?? '#ffffff',
            'left_column' => [
                'logo' => [
                    'enabled' => isset($_POST['logo_enabled']),
                    'path' => $logo_path,
                    'alt' => $_POST['logo_alt'] ?? $current_config['left_column']['logo']['alt'] ?? 'Logo',
                    'width' => (int)($_POST['logo_width'] ?? $current_config['left_column']['logo']['width'] ?? 169),
                    'height' => (int)($_POST['logo_height'] ?? $current_config['left_column']['logo']['height'] ?? 83)
                ],
                'links' => $links, // Replace completely, not merge
                'email' => [
                    'enabled' => isset($_POST['email_enabled']),
                    'address' => $_POST['email_address'] ?? $current_config['left_column']['email']['address'] ?? '',
                    'subject' => $_POST['email_subject'] ?? $current_config['left_column']['email']['subject'] ?? 'Consulta desde el sitio web',
                    'body' => $_POST['email_body'] ?? $current_config['left_column']['email']['body'] ?? ''
                ],
                'whatsapp' => [
                    'enabled' => isset($_POST['whatsapp_left_enabled']),
                    'number' => $_POST['whatsapp_left_number'] ?? $current_config['left_column']['whatsapp']['number'] ?? '',
                    'message' => $_POST['whatsapp_left_message'] ?? $current_config['left_column']['whatsapp']['message'] ?? 'Hola, consulta desde el sitio web',
                    'display_text' => $_POST['whatsapp_left_display_text'] ?? $current_config['left_column']['whatsapp']['display_text'] ?? '',
                    'custom_link' => $_POST['whatsapp_left_custom_link'] ?? $current_config['left_column']['whatsapp']['custom_link'] ?? ''
                ]
            ],
            'center_column' => [
                'address' => [
                    'enabled' => isset($_POST['address_enabled']),
                    'street' => $_POST['address_street'] ?? $current_config['center_column']['address']['street'] ?? '',
                    'city' => $_POST['address_city'] ?? $current_config['center_column']['address']['city'] ?? '',
                    'country' => $_POST['address_country'] ?? $current_config['center_column']['address']['country'] ?? '',
                    'map_url' => $_POST['address_map_url'] ?? $current_config['center_column']['address']['map_url'] ?? ''
                ],
                'phones' => $phones, // Replace completely, not merge
                'whatsapp' => [
                    'enabled' => isset($_POST['whatsapp_center_enabled']),
                    'number' => $_POST['whatsapp_center_number'] ?? $current_config['center_column']['whatsapp']['number'] ?? '',
                    'message' => $_POST['whatsapp_center_message'] ?? $current_config['center_column']['whatsapp']['message'] ?? 'Hola, consulta desde el sitio web',
                    'display_text' => $_POST['whatsapp_center_display_text'] ?? $current_config['center_column']['whatsapp']['display_text'] ?? '',
                    'custom_link' => $_POST['whatsapp_center_custom_link'] ?? $current_config['center_column']['whatsapp']['custom_link'] ?? ''
                ],
                'schedule' => [
                    'enabled' => isset($_POST['schedule_enabled']),
                    'days' => $_POST['schedule_days'] ?? $current_config['center_column']['schedule']['days'] ?? 'Lunes a Viernes',
                    'hours' => $_POST['schedule_hours'] ?? $current_config['center_column']['schedule']['hours'] ?? 'de 9 a 18hs'
                ]
            ],
            'right_column' => [
                'about' => [
                    'enabled' => isset($_POST['about_enabled']),
                    'title' => $_POST['about_title'] ?? $current_config['right_column']['about']['title'] ?? 'Acerca de nosotros',
                    'text' => $_POST['about_text'] ?? $current_config['right_column']['about']['text'] ?? ''
                ],
                'social' => [
                    'enabled' => isset($_POST['social_enabled']),
                    'facebook' => $_POST['social_facebook'] ?? $current_config['right_column']['social']['facebook'] ?? '',
                    'twitter' => $_POST['social_twitter'] ?? $current_config['right_column']['social']['twitter'] ?? '',
                    'instagram' => $_POST['social_instagram'] ?? $current_config['right_column']['social']['instagram'] ?? '',
                    'whatsapp' => $_POST['social_whatsapp'] ?? $current_config['right_column']['social']['whatsapp'] ?? '',
                    'telegram' => $_POST['social_telegram'] ?? $current_config['right_column']['social']['telegram'] ?? ''
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
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Configuración del Footer';
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Footer - Admin</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 1400px; }
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
        .form-group input[type="file"],
        .form-group textarea,
        .form-group input[type="color"] { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { resize: vertical; min-height: 80px; font-family: inherit; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: normal; }
        .checkbox-label input[type="checkbox"] { cursor: pointer; width: 18px; height: 18px; }
        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { background: #5568d3; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .btn-add { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 6px; font-size: 13px; cursor: pointer; margin-top: 10px; }
        .btn-remove { padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; }
        .repeater-item { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 10px; position: relative; }
        .repeater-item .btn-remove { position: absolute; top: 10px; right: 10px; }
        .color-input-wrapper { display: flex; gap: 10px; align-items: center; }
        .color-input-wrapper input[type="color"] { width: 60px; height: 40px; cursor: pointer; }
        .color-input-wrapper input[type="text"] { flex: 1; }
        .help-text { font-size: 12px; color: #666; margin-top: 4px; }
        .logo-preview { margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px; text-align: center; }
        .logo-preview img { max-width: 200px; max-height: 100px; }
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

        <form method="POST" action="" id="footerForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="logo_path" id="logo_path_hidden" value="<?php echo htmlspecialchars($footer_config['left_column']['logo']['path'] ?? ''); ?>">

            <!-- General Settings -->
            <div class="card">
                <h2>⚙️ Configuración General</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="footer_enabled" <?php echo ($footer_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                            <span>Activar footer personalizado</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Color de fondo</label>
                        <input type="color" name="background_color" value="<?php echo htmlspecialchars($footer_config['background_color'] ?? '#292c2f'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Color de texto</label>
                        <input type="color" name="text_color" value="<?php echo htmlspecialchars($footer_config['text_color'] ?? '#ffffff'); ?>">
                    </div>
                </div>
            </div>

            <!-- Left Column -->
            <div class="card">
                <h2>📌 Columna Izquierda</h2>

                <!-- Logo Upload -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="logo_enabled" <?php echo ($footer_config['left_column']['logo']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar logo</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Subir logo</label>
                    <input type="file" name="logo_upload" id="logo_upload" accept="image/*" onchange="previewLogo(this)">
                    <div class="help-text">Formatos: JPG, PNG, GIF, WEBP</div>

                    <?php if (!empty($footer_config['left_column']['logo']['path'])): ?>
                    <div class="logo-preview" id="logo_preview">
                        <img src="<?php echo htmlspecialchars($footer_config['left_column']['logo']['path']); ?>" alt="Logo actual">
                        <div class="help-text">Logo actual</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-grid">
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
                <h3 style="margin: 20px 0 10px; font-size: 16px;">Email de Contacto</h3>
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
                <div class="form-group">
                    <label>Cuerpo del mensaje (opcional)</label>
                    <textarea name="email_body" rows="3"><?php echo htmlspecialchars($footer_config['left_column']['email']['body'] ?? ''); ?></textarea>
                    <div class="help-text">Texto que aparecerá pre-escrito en el email</div>
                </div>

                <!-- WhatsApp -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">WhatsApp (sin icono)</h3>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="whatsapp_left_enabled" <?php echo ($footer_config['left_column']['whatsapp']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar WhatsApp</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Link personalizado de WhatsApp (opcional)</label>
                    <input type="url" name="whatsapp_left_custom_link" value="<?php echo htmlspecialchars($footer_config['left_column']['whatsapp']['custom_link'] ?? ''); ?>" placeholder="https://api.whatsapp.com/message/XXXXX">
                    <div class="help-text">Si usas este campo, ignora el número y mensaje. Útil para links sin teléfono.</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Número de WhatsApp</label>
                        <input type="text" name="whatsapp_left_number" value="<?php echo htmlspecialchars($footer_config['left_column']['whatsapp']['number'] ?? ''); ?>" placeholder="+54 9 11 1234-5678">
                        <div class="help-text">Formato internacional (solo si no usas link personalizado)</div>
                    </div>
                    <div class="form-group">
                        <label>Texto a mostrar</label>
                        <input type="text" name="whatsapp_left_display_text" value="<?php echo htmlspecialchars($footer_config['left_column']['whatsapp']['display_text'] ?? ''); ?>" placeholder="WhatsApp: +54 9 11 1234-5678">
                        <div class="help-text">Texto que se mostrará (si está vacío, usa el número)</div>
                    </div>
                    <div class="form-group">
                        <label>Mensaje predeterminado</label>
                        <input type="text" name="whatsapp_left_message" value="<?php echo htmlspecialchars($footer_config['left_column']['whatsapp']['message'] ?? 'Hola, consulta desde el sitio web'); ?>">
                        <div class="help-text">Solo si usas número (no link personalizado)</div>
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
                        <div class="help-text">URL completa de Google Maps para el icono de ubicación</div>
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

                <!-- WhatsApp en columna central -->
                <h3 style="margin: 20px 0 10px; font-size: 16px;">WhatsApp (con icono)</h3>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="whatsapp_center_enabled" <?php echo ($footer_config['center_column']['whatsapp']['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <span>Mostrar WhatsApp</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Link personalizado de WhatsApp (opcional)</label>
                    <input type="url" name="whatsapp_center_custom_link" value="<?php echo htmlspecialchars($footer_config['center_column']['whatsapp']['custom_link'] ?? ''); ?>" placeholder="https://api.whatsapp.com/message/XXXXX">
                    <div class="help-text">Si usas este campo, ignora el número y mensaje. Útil para links sin teléfono.</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Número de WhatsApp</label>
                        <input type="text" name="whatsapp_center_number" value="<?php echo htmlspecialchars($footer_config['center_column']['whatsapp']['number'] ?? ''); ?>" placeholder="+54 9 11 1234-5678">
                        <div class="help-text">Formato internacional (solo si no usas link personalizado)</div>
                    </div>
                    <div class="form-group">
                        <label>Texto a mostrar</label>
                        <input type="text" name="whatsapp_center_display_text" value="<?php echo htmlspecialchars($footer_config['center_column']['whatsapp']['display_text'] ?? ''); ?>" placeholder="Envíenos un WhatsApp">
                        <div class="help-text">Texto que se mostrará (si está vacío, usa el número)</div>
                    </div>
                    <div class="form-group">
                        <label>Mensaje predeterminado</label>
                        <input type="text" name="whatsapp_center_message" value="<?php echo htmlspecialchars($footer_config['center_column']['whatsapp']['message'] ?? 'Hola, consulta desde el sitio web'); ?>">
                        <div class="help-text">Solo si usas número (no link personalizado)</div>
                    </div>
                </div>

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
                        <label><i class="fa fa-facebook"></i> Facebook</label>
                        <input type="url" name="social_facebook" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['facebook'] ?? ''); ?>" placeholder="https://facebook.com/...">
                    </div>
                    <div class="form-group">
                        <label><i class="fa fa-twitter"></i> X (Twitter)</label>
                        <input type="url" name="social_twitter" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['twitter'] ?? ''); ?>" placeholder="https://twitter.com/...">
                    </div>
                    <div class="form-group">
                        <label><i class="fa fa-instagram"></i> Instagram</label>
                        <input type="url" name="social_instagram" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['instagram'] ?? ''); ?>" placeholder="https://instagram.com/...">
                    </div>
                    <div class="form-group">
                        <label><i class="fa fa-whatsapp"></i> WhatsApp</label>
                        <input type="url" name="social_whatsapp" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['whatsapp'] ?? ''); ?>" placeholder="https://wa.me/5491112345678 o https://api.whatsapp.com/...">
                        <div class="help-text">Link directo de WhatsApp (puede ser wa.me, api.whatsapp.com, etc.)</div>
                    </div>
                    <div class="form-group">
                        <label><i class="fa fa-telegram"></i> Telegram</label>
                        <input type="url" name="social_telegram" value="<?php echo htmlspecialchars($footer_config['right_column']['social']['telegram'] ?? ''); ?>" placeholder="https://t.me/...">
                    </div>
                </div>
            </div>

            <button type="submit" name="save_footer" class="btn-save" id="saveBtn">💾 Guardar Configuración</button>
        </form>
    </div>

    <script>
        // Preview logo before upload
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById('logo_preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'logo_preview';
                        preview.className = 'logo-preview';
                        input.parentElement.appendChild(preview);
                    }
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Vista previa"><div class="help-text">Vista previa del logo</div>';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

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

        // Form submit handler
        document.getElementById('footerForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('logo_upload');
            if (fileInput.files.length > 0) {
                // There's a file to upload, submit with file
                return true;
            }
        });

        // Change detection for save button
        const form = document.getElementById('footerForm');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');
        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;

        // Store original values (excluding file inputs and buttons)
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }
        });

        // Detect changes
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                checkForChanges();
            });
            input.addEventListener('change', () => {
                checkForChanges();
            });
        });

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== originalValues[input.name]) {
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

        // Also detect when items are removed
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove')) {
                setTimeout(checkForChanges, 100);
            }
        });

        // Detect when items are added
        const addButtons = document.querySelectorAll('.btn-add');
        addButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                setTimeout(() => {
                    // Re-attach listeners to new inputs
                    const newInputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');
                    newInputs.forEach(input => {
                        if (!originalValues.hasOwnProperty(input.name)) {
                            originalValues[input.name] = input.type === 'checkbox' ? input.checked : input.value;
                            input.addEventListener('input', checkForChanges);
                            input.addEventListener('change', checkForChanges);
                        }
                    });
                    checkForChanges();
                }, 100);
            });
        });
    </script>
</body>
</html>
