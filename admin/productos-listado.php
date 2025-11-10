<?php
/**
 * Admin - Products List
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
$page_title = 'Gestión de Productos';
$currency_config = read_json(__DIR__ . '/../config/currency.json');

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'product_updated') {
        $message = 'Producto actualizado exitosamente';
    }
}

// Delete product
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    if (delete_product($product_id)) {
        $message = 'Producto eliminado exitosamente';
        log_admin_action('product_deleted', $_SESSION['username'], ['product_id' => $product_id]);
    } else {
        $error = 'Error al eliminar el producto';
    }
}

// Toggle product active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $product = get_product_by_id($product_id);

    if ($product) {
        $new_status = !$product['active'];
        $updated = update_product($product_id, ['active' => $new_status]);

        if ($updated) {
            $message = $new_status ? 'Producto activado' : 'Producto desactivado';
            log_admin_action('product_toggled', $_SESSION['username'], [
                'product_id' => $product_id,
                'new_status' => $new_status
            ]);
        }
    }
}

// Get all products
$products = get_all_products(false); // Include inactive

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Productos - Admin</title>

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

        .badge.active {
            background: #d4edda;
            color: #155724;
        }

        .badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .badge.no-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .delete-confirm {
            display: none;
            gap: 8px;
            align-items: center;
        }

        .delete-confirm.show {
            display: flex;
        }

        .delete-actions {
            display: flex;
            gap: 8px;
        }

        .delete-actions.hide {
            display: none;
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

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($products); ?></div>
                    <div class="stat-label">Total Productos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php echo count(array_filter($products, fn($p) => $p['active'])); ?>
                    </div>
                    <div class="stat-label">Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php echo count(array_filter($products, fn($p) => $p['stock'] === 0)); ?>
                    </div>
                    <div class="stat-label">Sin Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php echo count(array_filter($products, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_alert'])); ?>
                    </div>
                    <div class="stat-label">Stock Bajo</div>
                </div>
            </div>

            <!-- Products List -->
            <div class="card">
                <div class="card-header">Todos los Productos</div>

                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                    No hay productos.
                                    <a href="<?php echo url('/admin/productos-nuevo.php'); ?>" style="color: #4CAF50;">Crear tu primer producto</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($product['thumbnail']); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="product-thumbnail">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    </td>
                                    <td><?php echo format_product_price($product, 'ARS'); ?></td>
                                    <td>
                                        <?php echo $product['stock']; ?>
                                        <?php if ($product['stock'] === 0): ?>
                                            <span class="badge no-stock">Sin Stock</span>
                                        <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                                            <span class="badge low-stock">Bajo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $product['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $product['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions" id="actions-<?php echo $product['id']; ?>">
                                            <div class="delete-actions">
                                                <a href="<?php echo url('/admin/productos-editar.php?id=' . urlencode($product['id'])); ?>"
                                                   class="btn btn-primary btn-sm">✏️ Editar</a>
                                                <a href="?action=toggle&id=<?php echo urlencode($product['id']); ?>"
                                                   class="btn btn-secondary btn-sm"
                                                   onclick="return confirm('¿Cambiar estado del producto?')">
                                                    <?php echo $product['active'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                                </a>
                                                <button class="btn btn-danger btn-sm"
                                                        onclick="showDeleteConfirm('<?php echo $product['id']; ?>')">
                                                    🗑️ Eliminar
                                                </button>
                                            </div>
                                            <div class="delete-confirm" id="delete-confirm-<?php echo $product['id']; ?>">
                                                <span style="font-size: 13px; color: #dc3545; font-weight: 600;">¿Confirmar eliminación?</span>
                                                <a href="?action=delete&id=<?php echo urlencode($product['id']); ?>"
                                                   class="btn btn-danger btn-sm">✓ Borrar</a>
                                                <button class="btn btn-secondary btn-sm"
                                                        onclick="hideDeleteConfirm('<?php echo $product['id']; ?>')">
                                                    ✗ Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <script>
        function showDeleteConfirm(productId) {
            // Hide action buttons
            const deleteActions = document.querySelector(`#actions-${productId} .delete-actions`);
            deleteActions.classList.add('hide');

            // Show confirm buttons
            const deleteConfirm = document.getElementById(`delete-confirm-${productId}`);
            deleteConfirm.classList.add('show');
        }

        function hideDeleteConfirm(productId) {
            // Show action buttons
            const deleteActions = document.querySelector(`#actions-${productId} .delete-actions`);
            deleteActions.classList.remove('hide');

            // Hide confirm buttons
            const deleteConfirm = document.getElementById(`delete-confirm-${productId}`);
            deleteConfirm.classList.remove('show');
        }
    </script>
</body>
</html>
