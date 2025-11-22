<?php
/**
 * Admin - Carousel Configuration
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

session_start();
require_admin();

$message = '';
$error = '';

// Handle slide deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_slide' && isset($_GET['index'])) {
    $index = intval($_GET['index']);
    $config = read_json(__DIR__ . '/../config/carousel.json');

    if (isset($config['slides'][$index])) {
        $image_path = $config['slides'][$index]['image'];

        // Delete physical file
        if (strpos($image_path, '/images/') === 0) {
            delete_uploaded_image($image_path);
        }

        // Remove from array
        array_splice($config['slides'], $index, 1);

        // Save
        if (write_json(__DIR__ . '/../config/carousel.json', $config)) {
            header('Location: ' . url('/admin/config-carrusel.php?msg=slide_deleted'));
            exit;
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config = read_json(__DIR__ . '/../config/carousel.json');

        // Get current slides
        $slides = $config['slides'] ?? [];

        // Handle slide reordering
        if (isset($_POST['slides_order']) && !empty($_POST['slides_order'])) {
            $order = json_decode($_POST['slides_order'], true);
            if (is_array($order)) {
                $reordered_slides = [];
                foreach ($order as $index) {
                    if (isset($slides[$index])) {
                        $reordered_slides[] = $slides[$index];
                    }
                }
                $slides = $reordered_slides;
            }
        }

        // Handle new slide image uploads
        if (isset($_FILES['carousel_images']) && !empty($_FILES['carousel_images']['name'][0])) {
            $upload_result = upload_multiple_images($_FILES['carousel_images'], 'carousel');

            if (!empty($upload_result['errors'])) {
                $error = 'Errores al subir imágenes: ' . implode(', ', $upload_result['errors']);
            }

            if (!empty($upload_result['files'])) {
                foreach ($upload_result['files'] as $file_path) {
                    $slides[] = [
                        'image' => $file_path,
                        'title' => '',
                        'subtitle' => '',
                        'link' => ''
                    ];
                }
            }
        }

        // Update slide texts if provided
        if (isset($_POST['slide_titles'])) {
            foreach ($_POST['slide_titles'] as $index => $title) {
                if (isset($slides[$index])) {
                    $slides[$index]['title'] = sanitize_input($title);
                    $slides[$index]['subtitle'] = sanitize_input($_POST['slide_subtitles'][$index] ?? '');
                    $slides[$index]['link'] = sanitize_input($_POST['slide_links'][$index] ?? '');
                }
            }
        }

        // Update config
        $config['enabled'] = isset($_POST['enabled']);
        $config['alignment'] = sanitize_input($_POST['alignment'] ?? 'center');
        $config['auto_advance_time'] = intval($_POST['auto_advance_time'] ?? 5000);
        $config['slides'] = $slides;

        if (empty($error)) {
            if (write_json(__DIR__ . '/../config/carousel.json', $config)) {
                $message = 'Configuración del carrusel guardada exitosamente';
                log_admin_action('carousel_config_updated', $_SESSION['username'], $config);
            } else {
                $error = 'Error al guardar la configuración';
            }
        }
    }
}

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'slide_deleted') {
        $message = 'Slide eliminado exitosamente';
    }
}

$carousel_config = read_json(__DIR__ . '/../config/carousel.json');
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Configuración del Carrusel';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Load all visible products for the link selector
require_once __DIR__ . '/../includes/products.php';
$all_products = get_all_products();
$visible_products = array_filter($all_products, function($product) {
    $hide_when_no_stock = $product['hide_when_out_of_stock'] ?? false;
    if ($hide_when_no_stock && $product['stock'] <= 0) {
        return false;
    }
    return true;
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrusel - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 900px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #667eea; }
        .form-group textarea { min-height: 60px; resize: vertical; font-family: inherit; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: auto; }

        /* Slides Gallery */
        .slides-gallery { display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; }
        .slide-item { position: relative; background: #f8f9fa; border-radius: 8px; padding: 15px; border: 2px solid transparent; transition: all 0.3s; cursor: move; }
        .slide-item:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2); }
        .slide-item.sortable-ghost { opacity: 0.4; }
        .slide-content { display: grid; grid-template-columns: 200px 1fr; gap: 15px; align-items: start; }
        .slide-image { position: relative; }
        .slide-image img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; }
        .drag-handle { position: absolute; top: 5px; left: 5px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: grab; }
        .btn-delete-slide { position: absolute; top: 5px; right: 5px; background: #dc3545; color: white; border: none; width: 28px; height: 28px; border-radius: 50%; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .btn-delete-slide:hover { background: #c82333; transform: scale(1.1); }
        .slide-fields { display: flex; flex-direction: column; gap: 10px; }
        .slide-fields input, .slide-fields textarea, .slide-fields select { padding: 8px 10px; font-size: 13px; border: 2px solid #e0e0e0; border-radius: 6px; transition: border-color 0.3s; }
        .slide-fields input:focus, .slide-fields textarea:focus, .slide-fields select:focus { outline: none; border-color: #667eea; }

        .file-input-wrapper { position: relative; display: inline-block; width: 100%; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 12px; border: 2px dashed #e0e0e0; border-radius: 6px; cursor: pointer; transition: all 0.3s; }
        .file-input-wrapper input[type="file"]:hover { border-color: #667eea; background: #f8f9fa; }

        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } .slide-content { grid-template-columns: 1fr; } }
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
            <form method="POST" action="" enctype="multipart/form-data" id="configForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="slides_order" id="slides_order">

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled"
                               <?php echo ($carousel_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <label for="enabled">Mostrar Carrusel en la página principal</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="alignment">Alineación del Carrusel</label>
                    <select id="alignment" name="alignment" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                        <option value="center" <?php echo ($carousel_config['alignment'] ?? 'center') === 'center' ? 'selected' : ''; ?>>Centrado</option>
                        <option value="left" <?php echo ($carousel_config['alignment'] ?? 'center') === 'left' ? 'selected' : ''; ?>>Izquierda (con margen)</option>
                        <option value="right" <?php echo ($carousel_config['alignment'] ?? 'center') === 'right' ? 'selected' : ''; ?>>Derecha (con margen)</option>
                    </select>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Selecciona cómo se alinea el carrusel en la página.
                    </small>
                </div>

                <div class="form-group">
                    <label for="auto_advance_time">Tiempo de Auto-Avance (milisegundos)</label>
                    <input type="number" id="auto_advance_time" name="auto_advance_time" min="1000" max="30000" step="500"
                           value="<?php echo intval($carousel_config['auto_advance_time'] ?? 5000); ?>">
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Tiempo en milisegundos entre slides. 1000ms = 1 segundo. Recomendado: 3000-7000ms
                    </small>
                </div>

                <?php if (!empty($carousel_config['slides'])): ?>
                    <div class="form-group">
                        <label>Slides Actuales (arrastra para reordenar)</label>
                        <div class="slides-gallery" id="slides-gallery">
                            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                                <div class="slide-item" data-index="<?php echo $index; ?>">
                                    <div class="slide-content">
                                        <div class="slide-image">
                                            <span class="drag-handle">⋮⋮</span>
                                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>" alt="Slide <?php echo $index + 1; ?>">
                                            <a href="?action=delete_slide&index=<?php echo $index; ?>"
                                               class="btn-delete-slide"
                                               onclick="return confirm('¿Eliminar este slide?')">✕</a>
                                        </div>
                                        <div class="slide-fields">
                                            <input type="text" name="slide_titles[<?php echo $index; ?>]"
                                                   placeholder="Título del slide (opcional)"
                                                   value="<?php echo htmlspecialchars($slide['title'] ?? ''); ?>">
                                            <textarea name="slide_subtitles[<?php echo $index; ?>]"
                                                      placeholder="Subtítulo (opcional)"><?php echo htmlspecialchars($slide['subtitle'] ?? ''); ?></textarea>
                                            <select name="slide_links[<?php echo $index; ?>]">
                                                <option value="">-- Sin enlace --</option>
                                                <?php foreach ($visible_products as $product): ?>
                                                    <?php
                                                        $product_link = '/producto.php?slug=' . $product['slug'];
                                                        $selected = ($slide['link'] ?? '') === $product_link ? 'selected' : '';
                                                    ?>
                                                    <option value="<?php echo htmlspecialchars($product_link); ?>" <?php echo $selected; ?>>
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                        (Stock: <?php echo $product['stock']; ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="carousel_images">Agregar Nuevos Slides</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="carousel_images" name="carousel_images[]" accept="image/*" multiple>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Formatos: JPG, PNG, GIF, WebP. Tamaño máximo: 5MB por imagen. Puedes seleccionar múltiples imágenes.
                    </small>
                </div>

                <button type="submit" name="save_config" class="btn-save" id="saveBtn">
                    💾 Guardar Configuración
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const gallery = document.getElementById('slides-gallery');
        const fileInput = document.getElementById('carousel_images');
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');

        let originalValues = {};
        let saveSuccess = <?php echo $message ? 'true' : 'false'; ?>;

        // Store original values
        inputs.forEach(input => {
            if (input.type === 'checkbox') {
                originalValues[input.name] = input.checked;
            } else {
                originalValues[input.name] = input.value;
            }
        });

        // Initialize SortableJS
        if (gallery) {
            Sortable.create(gallery, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                onEnd: () => markChanged()
            });
        }

        function markChanged() {
            saveBtn.classList.add('changed');
            saveBtn.classList.remove('saved');
        }

        // Detect changes
        inputs.forEach(input => {
            input.addEventListener('input', checkForChanges);
            input.addEventListener('change', checkForChanges);
        });

        if (fileInput) {
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    markChanged();
                }
            });
        }

        function checkForChanges() {
            let hasChanges = false;
            inputs.forEach(input => {
                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                if (currentValue !== originalValues[input.name]) {
                    hasChanges = true;
                }
            });

            if (hasChanges || (fileInput && fileInput.files.length > 0)) {
                markChanged();
            } else {
                saveBtn.classList.remove('changed');
                if (saveSuccess) {
                    saveBtn.classList.add('saved');
                }
            }
        }

        // Save order before submit
        form.addEventListener('submit', () => {
            if (gallery) {
                const items = Array.from(gallery.children);
                const order = items.map(item => parseInt(item.dataset.index));
                document.getElementById('slides_order').value = JSON.stringify(order);
            }
        });

        // Show saved state
        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => saveBtn.classList.remove('saved'), 3000);
        }
    </script>
</body>
</html>
