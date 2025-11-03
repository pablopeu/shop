<?php
/**
 * Admin - Add New Product
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Validate required fields first
        if (empty($_POST['name'])) {
            $error = 'El nombre es requerido';
        } elseif ((floatval($_POST['price_ars'] ?? 0) <= 0) && (floatval($_POST['price_usd'] ?? 0) <= 0)) {
            $error = 'Debe ingresar al menos un precio (ARS o USD) mayor a 0';
        } elseif (!isset($_FILES['product_images']) || empty($_FILES['product_images']['name'][0])) {
            $error = 'Debe subir al menos una imagen del producto';
        } else {
            // Generate product ID FIRST
            $product_id = generate_id('prod-');

            // Create product directory BEFORE uploading images
            $product_image_dir = __DIR__ . "/../images/products/{$product_id}";
            if (!is_dir($product_image_dir)) {
                mkdir($product_image_dir, 0755, true);
            }

            // Handle image uploads to the product's directory
            $upload_result = upload_multiple_images($_FILES['product_images'], "products/{$product_id}");

            if (!empty($upload_result['errors'])) {
                $error = 'Errores al subir imágenes: ' . implode(', ', $upload_result['errors']);
            } elseif (empty($upload_result['files'])) {
                $error = 'No se pudo subir ninguna imagen';
            } else {
                $images = $upload_result['files'];

                // Get form data
                $product_data = [
                    'id' => $product_id, // Pass the pre-generated ID
                    'name' => sanitize_input($_POST['name'] ?? ''),
                    'slug' => generate_slug($_POST['name'] ?? ''),
                    'description' => sanitize_input($_POST['description'] ?? ''),
                    'price_ars' => floatval($_POST['price_ars'] ?? 0),
                    'price_usd' => floatval($_POST['price_usd'] ?? 0),
                    'stock' => intval($_POST['stock'] ?? 0),
                    'stock_alert' => intval($_POST['stock_alert'] ?? 5),
                    'active' => isset($_POST['active']) ? true : false,
                    'images' => $images,
                    'thumbnail' => $images[0], // First image is thumbnail
                    'seo' => [
                        'title' => sanitize_input($_POST['seo_title'] ?? ''),
                        'description' => sanitize_input($_POST['seo_description'] ?? ''),
                        'keywords' => sanitize_input($_POST['seo_keywords'] ?? '')
                    ]
                ];

                // Create new product
                $result = create_product($product_data);

                if (isset($result['success']) && $result['success']) {
                    $message = 'Producto creado exitosamente';
                    log_admin_action('product_created', $_SESSION['username'], [
                        'product_id' => $product_id,
                        'name' => $product_data['name']
                    ]);

                    // Redirect to list after 2 seconds
                    header("refresh:2;url=/admin/productos-listado.php");
                } else {
                    $error = $result['error'] ?? 'Error al crear el producto';

                    // Clean up images if product creation failed
                    foreach ($images as $img) {
                        delete_uploaded_image($img);
                    }
                    if (is_dir($product_image_dir)) {
                        rmdir($product_image_dir);
                    }
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - Admin</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            max-width: 1200px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .content-header h1 {
            font-size: 24px;
            color: #2c3e50;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 12px;
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

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-save {
            padding: 12px 30px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save.changed {
            background: #dc3545;
            animation: pulse 1.5s infinite;
        }

        .btn-save.saved {
            background: #28a745;
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 8px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 70px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .section-divider {
            margin: 20px 0 15px 0;
            padding: 10px 0;
            border-top: 2px solid #f0f0f0;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .section-divider:first-of-type {
            margin-top: 0;
            border-top: none;
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-input-wrapper input[type="file"]:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }

        /* Image Gallery */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .image-item {
            position: relative;
            background: #f8f9fa;
            border-radius: 6px;
            overflow: hidden;
            cursor: move;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .image-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .image-item.sortable-ghost {
            opacity: 0.4;
        }

        .image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .image-item .drag-handle {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: grab;
        }

        .image-item .drag-handle:active {
            cursor: grabbing;
        }

        .image-item .btn-delete-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3545;
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .image-item .btn-delete-image:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        .image-item .image-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>➕ Agregar Nuevo Producto</h1>
            <div>
                <a href="/admin/productos-listado.php" class="btn btn-secondary" id="backBtn">← Volver al listado</a>
                <span id="unsavedWarning" style="display: none; color: #dc3545; font-weight: 600; font-size: 14px;">
                    ⚠ Hay cambios sin guardar
                </span>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Product Form -->
        <div class="card">
            <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Basic Information -->
                <div class="section-divider">📝 Información Básica</div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="name">
                            Nombre del Producto <span class="required">*</span>
                        </label>
                        <input type="text" id="name" name="name" required
                               placeholder="Ej: Remera Deportiva Premium">
                    </div>

                    <div class="form-group full-width">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description"
                                  placeholder="Descripción detallada del producto..."></textarea>
                    </div>
                </div>

                <!-- Images -->
                <div class="section-divider">🖼️ Imágenes del Producto</div>

                <div class="form-group">
                    <label for="product_images">Subir Imágenes <span class="required">*</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" id="product_images" name="product_images[]" accept="image/*" multiple required>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Formatos: JPG, PNG, GIF, WebP. Tamaño máximo: 5MB por imagen. Puedes seleccionar múltiples imágenes.
                    </small>
                </div>

                <!-- Image Preview Gallery -->
                <div class="form-group" id="previewContainer" style="display: none;">
                    <label>Previsualización (arrastra para reordenar, la primera será la principal)</label>
                    <div class="image-gallery" id="image-gallery"></div>
                    <input type="hidden" id="images_order" name="images_order" value="">
                </div>

                <!-- Pricing -->
                <div class="section-divider">💰 Precios</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="price_ars">
                            Precio en Pesos (ARS)
                        </label>
                        <input type="number" id="price_ars" name="price_ars" step="0.01"
                               placeholder="0.00">
                        <small style="color: #666;">Al menos un precio debe estar completo</small>
                    </div>

                    <div class="form-group">
                        <label for="price_usd">
                            Precio en Dólares (USD)
                        </label>
                        <input type="number" id="price_usd" name="price_usd" step="0.01"
                               placeholder="0.00">
                        <small style="color: #666;">Puede dejarse vacío si solo usas ARS</small>
                    </div>
                </div>

                <!-- Stock -->
                <div class="section-divider">📦 Inventario</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="stock">
                            Stock Disponible <span class="required">*</span>
                        </label>
                        <input type="number" id="stock" name="stock" required
                               value="0" min="0">
                        <small style="color: #666;">Cantidad de unidades disponibles</small>
                    </div>

                    <div class="form-group">
                        <label for="stock_alert">Alerta de Stock Bajo</label>
                        <input type="number" id="stock_alert" name="stock_alert"
                               value="0" min="0">
                        <small style="color: #666;">Te avisaremos cuando el stock llegue a este número</small>
                    </div>
                </div>

                <!-- Status -->
                <div class="section-divider">⚙️ Estado</div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="active" name="active" checked>
                        <label for="active">
                            Producto Activo (visible en el sitio público)
                        </label>
                    </div>
                </div>

                <!-- SEO -->
                <div class="section-divider">🔍 SEO (Opcional)</div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="seo_title">Título SEO</label>
                        <input type="text" id="seo_title" name="seo_title"
                               maxlength="60"
                               placeholder="Título para motores de búsqueda (máx 60 caracteres)">
                    </div>

                    <div class="form-group full-width">
                        <label for="seo_description">Descripción SEO</label>
                        <textarea id="seo_description" name="seo_description"
                                  maxlength="160"
                                  placeholder="Descripción para motores de búsqueda (máx 160 caracteres)"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="seo_keywords">Keywords (separadas por comas)</label>
                        <input type="text" id="seo_keywords" name="seo_keywords"
                               placeholder="Ej: remera, deportiva, algodón, premium">
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" name="save_product" class="btn-save" id="saveBtn">
                        💾 Crear Producto
                    </button>
                    <a href="/admin/productos-listado.php" class="btn btn-secondary">
                        ❌ Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        const form = document.getElementById('productForm');
        const saveBtn = document.getElementById('saveBtn');
        const fileInput = document.getElementById('product_images');
        const inputs = form.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select');
        const priceArs = document.getElementById('price_ars');
        const priceUsd = document.getElementById('price_usd');
        const backBtn = document.getElementById('backBtn');
        const unsavedWarning = document.getElementById('unsavedWarning');
        const gallery = document.getElementById('image-gallery');
        const previewContainer = document.getElementById('previewContainer');
        const imagesOrderInput = document.getElementById('images_order');

        let hasChanges = false;
        let selectedFiles = [];
        let sortableInstance = null;

        function updateUIForChanges() {
            if (hasChanges) {
                saveBtn.classList.add('changed');
                backBtn.style.display = 'none';
                unsavedWarning.style.display = 'inline';
            } else {
                saveBtn.classList.remove('changed');
                backBtn.style.display = 'inline-block';
                unsavedWarning.style.display = 'none';
            }
        }

        // Sort files by name alphabetically
        function sortFilesByName(files) {
            return Array.from(files).sort((a, b) => {
                return a.name.localeCompare(b.name, undefined, {numeric: true, sensitivity: 'base'});
            });
        }

        // Render image preview gallery
        async function renderImageGallery() {
            if (selectedFiles.length === 0) {
                previewContainer.style.display = 'none';
                gallery.innerHTML = '';
                return;
            }

            previewContainer.style.display = 'block';
            gallery.innerHTML = '';

            // Read all files in order using Promise.all to maintain correct sequence
            const readPromises = selectedFiles.map((file, index) => {
                return new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        resolve({
                            index: index,
                            dataUrl: e.target.result,
                            name: file.name
                        });
                    };
                    reader.readAsDataURL(file);
                });
            });

            // Wait for all files to be read, then render in correct order
            const readFiles = await Promise.all(readPromises);

            readFiles.forEach(({index, dataUrl, name}) => {
                const div = document.createElement('div');
                div.className = 'image-item';
                div.dataset.index = index;
                div.innerHTML = `
                    <span class="drag-handle">⋮⋮</span>
                    <img src="${dataUrl}" alt="${name}">
                    ${index === 0 ? '<span class="image-badge">PRINCIPAL</span>' : ''}
                    <button type="button" class="btn-delete-image" onclick="removeImage(${index})">✕</button>
                `;
                gallery.appendChild(div);
            });

            // Initialize Sortable after all images are added to DOM
            initializeSortable();
        }

        // Initialize SortableJS
        function initializeSortable() {
            if (sortableInstance) {
                sortableInstance.destroy();
            }

            if (gallery && selectedFiles.length > 0) {
                sortableInstance = Sortable.create(gallery, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    onEnd: function() {
                        reorderFiles();
                        updateBadges();
                        hasChanges = true;
                        updateUIForChanges();
                    }
                });
            }
        }

        // Reorder files array based on DOM order
        function reorderFiles() {
            const items = Array.from(gallery.children);
            const newOrder = items.map(item => parseInt(item.dataset.index));
            const reorderedFiles = newOrder.map(i => selectedFiles[i]);
            selectedFiles = reorderedFiles;

            // Update data-index attributes
            items.forEach((item, index) => {
                item.dataset.index = index;
            });

            // Save order to hidden input
            imagesOrderInput.value = JSON.stringify(newOrder);
        }

        // Update "PRINCIPAL" badge
        function updateBadges() {
            const items = gallery.querySelectorAll('.image-item');
            items.forEach((item, index) => {
                const existingBadge = item.querySelector('.image-badge');
                if (existingBadge) {
                    existingBadge.remove();
                }
                if (index === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'image-badge';
                    badge.textContent = 'PRINCIPAL';
                    item.appendChild(badge);
                }
            });
        }

        // Remove image from preview
        window.removeImage = function(index) {
            selectedFiles.splice(index, 1);
            renderImageGallery();
            updateDataTransfer();
            hasChanges = true;
            updateUIForChanges();
        };

        // Update FileList in input (DataTransfer trick)
        function updateDataTransfer() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        // Handle file selection
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // Sort files alphabetically by name
                selectedFiles = sortFilesByName(this.files);
                renderImageGallery();
                updateDataTransfer();
                hasChanges = true;
                updateUIForChanges();
            }
        });

        // Detect changes in inputs
        inputs.forEach(input => {
            input.addEventListener('input', () => {
                hasChanges = true;
                updateUIForChanges();
            });
            input.addEventListener('change', () => {
                hasChanges = true;
                updateUIForChanges();
            });
        });

        // Warn before leaving page with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = 'Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?';
                return e.returnValue;
            }
        });

        // Allow form submission (don't warn when saving)
        form.addEventListener('submit', (e) => {
            const arsValue = parseFloat(priceArs.value) || 0;
            const usdValue = parseFloat(priceUsd.value) || 0;

            if (arsValue <= 0 && usdValue <= 0) {
                e.preventDefault();
                alert('Debe ingresar al menos un precio (ARS o USD) mayor a 0');
                return false;
            }

            // Update DataTransfer one more time before submit to ensure order
            updateDataTransfer();

            // Remove beforeunload listener when saving
            hasChanges = false;
        });

        // Initial UI state
        updateUIForChanges();
    </script>
</body>
</html>
