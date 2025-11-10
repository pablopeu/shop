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
    } elseif ($_GET['msg'] === 'product_created') {
        $message = 'Producto creado exitosamente';
    } elseif ($_GET['msg'] === 'product_archived') {
        $message = 'Producto archivado exitosamente';
    }
}

// Archive product
if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    if (archive_product($product_id)) {
        $message = 'Producto archivado exitosamente';
        log_admin_action('product_archived', $_SESSION['username'], ['product_id' => $product_id]);
    } else {
        $error = 'Error al archivar el producto';
    }
}

// Toggle product active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $product = get_product_by_id($product_id);

    if ($product) {
        $new_status = !$product['active'];
        $updated = update_product($product_id, ['active' => $new_status]);

        if ($updated['success']) {
            $message = $new_status ? 'Producto activado' : 'Producto desactivado';
            log_admin_action('product_toggled', $_SESSION['username'], [
                'product_id' => $product_id,
                'new_status' => $new_status
            ]);
        }
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_products = $_POST['selected_products'] ?? [];

    if (!empty($selected_products)) {
        $success_count = 0;
        foreach ($selected_products as $product_id) {
            if ($action === 'archive') {
                if (archive_product($product_id)) {
                    $success_count++;
                }
            } elseif ($action === 'activate') {
                if (update_product($product_id, ['active' => true])) {
                    $success_count++;
                }
            } elseif ($action === 'deactivate') {
                if (update_product($product_id, ['active' => false])) {
                    $success_count++;
                }
            }
        }

        $message = "$success_count producto(s) procesado(s) exitosamente";
        log_admin_action('bulk_products_action', $_SESSION['username'], [
            'action' => $action,
            'count' => $success_count
        ]);
    } else {
        $error = 'No se seleccionaron productos';
    }
}

// Get all products
$all_products = get_all_products(false); // Include inactive

// Apply filters
$filter_status = $_GET['filter'] ?? 'all';
$filter_stock = $_GET['stock'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Apply status filter
if ($filter_status === 'all') {
    $products = $all_products;
} elseif ($filter_status === 'active') {
    $products = array_filter($all_products, fn($p) => $p['active']);
} elseif ($filter_status === 'inactive') {
    $products = array_filter($all_products, fn($p) => !$p['active']);
} else {
    $products = $all_products;
}

// Apply stock filter
if ($filter_stock === 'in_stock') {
    $products = array_filter($products, fn($p) => $p['stock'] > 0);
} elseif ($filter_stock === 'out_of_stock') {
    $products = array_filter($products, fn($p) => $p['stock'] === 0);
} elseif ($filter_stock === 'low_stock') {
    $products = array_filter($products, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_alert']);
}

// Apply search filter
if (!empty($search_query)) {
    $products = array_filter($products, function($product) use ($search_query) {
        $search_lower = mb_strtolower($search_query);
        return stripos($product['id'], $search_query) !== false ||
               stripos(mb_strtolower($product['name']), $search_lower) !== false;
    });
}

// Sort products (newest first)
usort($products, function($a, $b) {
    return strtotime($b['created_at'] ?? '2000-01-01') - strtotime($a['created_at'] ?? '2000-01-01');
});

// Calculate stats
$total_products = count($all_products);
$active_products = count(array_filter($all_products, fn($p) => $p['active']));
$out_of_stock = count(array_filter($all_products, fn($p) => $p['stock'] === 0));
$low_stock = count(array_filter($all_products, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_alert']));

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

        /* Filters */
        .filters-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        /* Bulk Actions Bar */
        .bulk-actions-bar {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 15px;
            display: none;
            align-items: center;
            gap: 12px;
        }

        .bulk-actions-bar.show {
            display: flex;
        }

        .bulk-actions-bar select {
            padding: 6px 12px;
            border: 1px solid #ffc107;
            border-radius: 4px;
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

            .filters-row {
                grid-template-columns: 1fr;
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
                    <a href="<?php echo url('/admin/productos-nuevo.php'); ?>" class="btn btn-primary">
                        ➕ Nuevo Producto
                    </a>
                    <a href="<?php echo url('/admin/productos-archivados.php'); ?>" class="btn btn-secondary">
                        📦 Ver Archivados
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_products; ?></div>
                    <div class="stat-label">Total Productos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $active_products; ?></div>
                    <div class="stat-label">Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $out_of_stock; ?></div>
                    <div class="stat-label">Sin Stock</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $low_stock; ?></div>
                    <div class="stat-label">Stock Bajo</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" action="">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="search">Buscar</label>
                            <input type="text" id="search" name="search"
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   placeholder="Nombre o ID del producto">
                        </div>

                        <div class="filter-group">
                            <label for="filter">Estado</label>
                            <select id="filter" name="filter">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Activos</option>
                                <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="stock">Stock</label>
                            <select id="stock" name="stock">
                                <option value="all" <?php echo $filter_stock === 'all' ? 'selected' : ''; ?>>Todos</option>
                                <option value="in_stock" <?php echo $filter_stock === 'in_stock' ? 'selected' : ''; ?>>Con Stock</option>
                                <option value="out_of_stock" <?php echo $filter_stock === 'out_of_stock' ? 'selected' : ''; ?>>Sin Stock</option>
                                <option value="low_stock" <?php echo $filter_stock === 'low_stock' ? 'selected' : ''; ?>>Stock Bajo</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions Bar -->
            <form method="POST" id="bulkForm">
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <span id="selectedCount">0 productos seleccionados</span>
                    <select name="bulk_action" id="bulkAction">
                        <option value="">Seleccionar acción...</option>
                        <option value="activate">Activar</option>
                        <option value="deactivate">Desactivar</option>
                        <option value="archive">Archivar</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('¿Confirmar acción en masa?')">
                        Aplicar
                    </button>
                </div>

                <!-- Products List -->
                <div class="card">
                    <div class="card-header">
                        <?php if (empty($products)): ?>
                            Todos los Productos
                        <?php else: ?>
                            Mostrando <?php echo count($products); ?> de <?php echo $total_products; ?> productos
                        <?php endif; ?>
                    </div>

                    <table class="products-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
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
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                        No hay productos que coincidan con los filtros.
                                        <a href="<?php echo url('/admin/productos-nuevo.php'); ?>" style="color: #4CAF50;">Crear nuevo producto</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_products[]"
                                                   value="<?php echo htmlspecialchars($product['id']); ?>"
                                                   class="product-checkbox"
                                                   onchange="updateBulkActions()">
                                        </td>
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
                                                            onclick="showArchiveConfirm('<?php echo $product['id']; ?>')">
                                                        📦 Archivar
                                                    </button>
                                                </div>
                                                <div class="delete-confirm" id="archive-confirm-<?php echo $product['id']; ?>">
                                                    <span style="font-size: 13px; color: #dc3545; font-weight: 600;">¿Archivar producto?</span>
                                                    <a href="?action=archive&id=<?php echo urlencode($product['id']); ?>"
                                                       class="btn btn-danger btn-sm">✓ Archivar</a>
                                                    <button class="btn btn-secondary btn-sm"
                                                            onclick="hideArchiveConfirm('<?php echo $product['id']; ?>')">
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
            </form>
        </div>

    <script>
        function showArchiveConfirm(productId) {
            // Hide action buttons
            const deleteActions = document.querySelector(`#actions-${productId} .delete-actions`);
            deleteActions.classList.add('hide');

            // Show confirm buttons
            const archiveConfirm = document.getElementById(`archive-confirm-${productId}`);
            archiveConfirm.classList.add('show');
        }

        function hideArchiveConfirm(productId) {
            // Show action buttons
            const deleteActions = document.querySelector(`#actions-${productId} .delete-actions`);
            deleteActions.classList.remove('hide');

            // Hide confirm buttons
            const archiveConfirm = document.getElementById(`archive-confirm-${productId}`);
            archiveConfirm.classList.remove('show');
        }

        // Handle checkbox selection for bulk actions
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBar.classList.add('show');
                selectedCount.textContent = `${count} producto${count > 1 ? 's' : ''} seleccionado${count > 1 ? 's' : ''}`;
            } else {
                bulkBar.classList.remove('show');
                selectAll.checked = false;
            }
        }

        // Ensure at least one checkbox is selected and an action is chosen
        document.getElementById('bulkForm')?.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            const action = document.getElementById('bulkAction').value;

            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Selecciona al menos un producto');
                return false;
            }

            if (!action) {
                e.preventDefault();
                alert('Selecciona una acción');
                return false;
            }
        });
    </script>
</body>
</html>
