<?php
/**
 * Admin - Archived Products List
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Productos Archivados';
$currency_config = read_json(__DIR__ . '/../config/currency.json');

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'product_restored') {
        $message = 'Producto restaurado exitosamente';
    } elseif ($_GET['msg'] === 'product_deleted') {
        $message = 'Producto eliminado permanentemente';
    }
}

// Unarchive product (restore)
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    if (unarchive_product($product_id)) {
        $message = 'Producto restaurado exitosamente';
        log_admin_action('product_restored', $_SESSION['username'], ['product_id' => $product_id]);
    } else {
        $error = 'Error al restaurar el producto';
    }
}

// Delete permanently
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    $result = delete_archived_product($product_id);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get all archived products
$archived_products = get_archived_products();

// Sort by archived date (newest first)
usort($archived_products, function($a, $b) {
    return strtotime($b['archived_date'] ?? '2000-01-01') - strtotime($a['archived_date'] ?? '2000-01-01');
});

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos Archivados - Admin</title>

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
            padding: 15px 20px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .card-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .stat-card {
            background: white;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .stat-label {
            color: #666;
            font-size: 12px;
        }

        /* Table */
        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th,
        .products-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .products-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .products-table td {
            font-size: 14px;
        }

        .products-table tbody tr:hover {
            background: #f8f9fa;
        }

        .product-thumbnail {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.archived {
            background: #e2e3e5;
            color: #383d41;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Warning Box */
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .warning-box strong {
            color: #856404;
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .header-actions .btn {
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .products-table {
                font-size: 13px;
            }

            .products-table th,
            .products-table td {
                padding: 10px;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Header Actions -->
            <div class="header-actions">
                <div>
                    <a href="<?php echo url('/admin/productos-listado.php'); ?>" class="btn btn-secondary">
                        ← Volver a Productos
                    </a>
                </div>
            </div>

            <!-- Warning -->
            <?php if (!empty($archived_products)): ?>
            <div class="warning-box">
                <strong>⚠️ Productos Archivados:</strong> Estos productos han sido archivados y no aparecen en el sitio público. Puedes restaurarlos o eliminarlos permanentemente.
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($archived_products); ?></div>
                    <div class="stat-label">Productos Archivados</div>
                </div>
            </div>

            <!-- Archived Products List -->
            <div class="card">
                <div class="card-header">Productos Archivados</div>

                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Archivado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($archived_products)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                    No hay productos archivados.
                                    <a href="<?php echo url('/admin/productos-listado.php'); ?>" style="color: #4CAF50;">Ir a productos activos</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived_products as $product): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars(url($product['thumbnail'])); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="product-thumbnail">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                                        <small style="color: #999;">ID: <?php echo htmlspecialchars($product['id']); ?></small>
                                    </td>
                                    <td><?php echo format_product_price($product, 'ARS'); ?></td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <span class="badge archived">Archivado</span><br>
                                        <small style="color: #999;">
                                            <?php
                                            $date = new DateTime($product['archived_date'] ?? 'now');
                                            echo $date->format('d/m/Y H:i');
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-primary btn-sm"
                                                    onclick="confirmRestoreProduct('<?php echo urlencode($product['id']); ?>', '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                                ↩️ Restaurar
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="confirmDeleteProduct('<?php echo urlencode($product['id']); ?>', '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- Modal Component -->
    <?php include __DIR__ . '/includes/modal.php'; ?>

    <script>
        /**
         * Confirmar restauración de producto
         */
        function confirmRestoreProduct(productId, productName) {
            showModal({
                title: 'Restaurar Producto',
                message: `¿Estás seguro de que deseas restaurar "${productName}"?`,
                details: 'El producto volverá al listado principal de productos activos y estará disponible en el catálogo público.',
                icon: '↩️',
                confirmText: 'Restaurar',
                confirmType: 'primary',
                onConfirm: function() {
                    window.location.href = `?action=restore&id=${productId}`;
                }
            });
        }

        /**
         * Confirmar eliminación permanente de producto
         */
        function confirmDeleteProduct(productId, productName) {
            showModal({
                title: '⚠️ Eliminar Permanentemente',
                message: `¿Estás COMPLETAMENTE SEGURO de que deseas eliminar "${productName}"?`,
                details: '🚨 ADVERTENCIA: Esta acción es IRREVERSIBLE. Se eliminarán permanentemente todos los datos del producto, incluyendo imágenes y estadísticas. Esta acción NO se puede deshacer.',
                icon: '🗑️',
                confirmText: 'Sí, Eliminar Permanentemente',
                cancelText: 'No, Conservar Producto',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=delete&id=${productId}`;
                }
            });
        }
    </script>
</body>
</html>
