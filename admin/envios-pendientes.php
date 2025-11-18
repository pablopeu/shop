<?php
/**
 * Admin - Envíos Pendientes
 * Listado de envíos con estados: cobrada, enviada, entregada
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Envíos Pendientes';
$currency_config = read_json(__DIR__ . '/../config/currency.json');

// Handle actions
$message = '';
$error = '';

// Check for messages in URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'order_archived') {
        $message = 'Envío archivado exitosamente';
    } elseif ($_GET['msg'] === 'order_updated') {
        $message = 'Envío actualizado exitosamente';
    }
}

// Archive order
if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
    $order_id = $_GET['id'];

    if (archive_order($order_id)) {
        $message = 'Envío archivado exitosamente';
        log_admin_action('order_archived', $_SESSION['username'], ['order_id' => $order_id]);
    } else {
        $error = 'Error al archivar el envío';
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_orders = $_POST['selected_orders'] ?? [];

    if (!empty($selected_orders)) {
        $success_count = 0;
        foreach ($selected_orders as $order_id) {
            if ($action === 'archive') {
                if (archive_order($order_id)) {
                    $success_count++;
                }
            } elseif ($action === 'mark_sent') {
                if (update_order_status($order_id, 'enviada', $_SESSION['username'])) {
                    $success_count++;
                }
            } elseif ($action === 'mark_delivered') {
                if (update_order_status($order_id, 'entregada', $_SESSION['username'])) {
                    $success_count++;
                }
            }
        }

        $message = "$success_count envío(s) procesado(s) exitosamente";
        log_admin_action('bulk_orders_action', $_SESSION['username'], [
            'action' => $action,
            'count' => $success_count
        ]);
    } else {
        $error = 'No se seleccionaron envíos';
    }
}

// Get all orders
$all_orders = get_all_orders();

// Filter only orders with shipping-related statuses (cobrada, enviada, entregada)
$shipping_orders = array_filter($all_orders, function($order) {
    return in_array($order['status'], ['cobrada', 'enviada', 'entregada']);
});

// Apply filters
$filter_status = $_GET['filter'] ?? 'all';
$filter_delivery = $_GET['delivery'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Apply status filter
if ($filter_status === 'all') {
    $orders = $shipping_orders;
} elseif ($filter_status === 'cobrada') {
    $orders = array_filter($shipping_orders, fn($o) => $o['status'] === 'cobrada');
} elseif ($filter_status === 'enviada') {
    $orders = array_filter($shipping_orders, fn($o) => $o['status'] === 'enviada');
} elseif ($filter_status === 'entregada') {
    $orders = array_filter($shipping_orders, fn($o) => $o['status'] === 'entregada');
} else {
    $orders = $shipping_orders;
}

// Apply delivery method filter
if ($filter_delivery === 'pickup') {
    $orders = array_filter($orders, fn($o) => ($o['delivery_method'] ?? 'pickup') === 'pickup');
} elseif ($filter_delivery === 'shipping') {
    $orders = array_filter($orders, fn($o) => ($o['delivery_method'] ?? 'pickup') === 'shipping');
}

// Apply search filter
if (!empty($search_query)) {
    $orders = array_filter($orders, function($order) use ($search_query) {
        $search_lower = mb_strtolower($search_query);
        return stripos($order['order_number'], $search_query) !== false ||
               stripos($order['id'], $search_query) !== false ||
               stripos(mb_strtolower($order['customer_name'] ?? ''), $search_lower) !== false ||
               stripos(mb_strtolower($order['customer_email'] ?? ''), $search_lower) !== false;
    });
}

// Sort orders by date (newest first)
usort($orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate stats
$total_orders = count($shipping_orders);
$cobradas = count(array_filter($shipping_orders, fn($o) => $o['status'] === 'cobrada'));
$enviadas = count(array_filter($shipping_orders, fn($o) => $o['status'] === 'enviada'));
$entregadas = count(array_filter($shipping_orders, fn($o) => $o['status'] === 'entregada'));

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envíos Pendientes - Admin</title>

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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header-actions {
            display: flex;
            gap: 8px;
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
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .orders-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .orders-table td {
            font-size: 14px;
        }

        .orders-table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge.cobrada {
            background: #d4edda;
            color: #155724;
        }

        .badge.enviada {
            background: #cce5ff;
            color: #004085;
        }

        .badge.entregada {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge.pickup {
            background: #fff3cd;
            color: #856404;
        }

        .badge.shipping {
            background: #e2e3e5;
            color: #383d41;
        }

        .actions {
            display: flex;
            gap: 8px;
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
            grid-template-columns: auto 1fr 1fr 1fr auto;
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

        /* Table Container for Mobile Scroll */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -15px;
            padding: 0 15px;
        }

        @media (min-width: 1025px) {
            .table-container {
                overflow-x: visible;
                margin: 0;
                padding: 0;
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .orders-table {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .orders-table {
                font-size: 12px;
                min-width: 800px;
            }

            .orders-table th,
            .orders-table td {
                padding: 8px 6px;
            }

            .actions {
                flex-direction: column;
                gap: 5px;
            }

            .actions .btn {
                width: 100%;
                padding: 6px 10px;
            }

            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions > div {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .header-actions .btn {
                width: 100%;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 8px;
            }

            .bulk-actions-bar {
                flex-direction: column;
                gap: 8px;
            }

            .bulk-actions-bar select,
            .bulk-actions-bar .btn {
                width: 100%;
            }

            /* Better touch targets */
            .btn {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }

        /* Mobile Cards View */
        .mobile-cards {
            display: none;
        }

        @media (max-width: 768px) {
            .table-container {
                display: none !important;
            }

            .mobile-cards {
                display: block;
            }

            .mobile-card {
                background: white;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 12px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                border-left: 4px solid #3498db;
            }

            .mobile-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 1px solid #f0f0f0;
            }

            .mobile-card-title {
                font-weight: 600;
                color: #2c3e50;
                font-size: 15px;
                flex: 1;
            }

            .mobile-card-body {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 12px;
            }

            .mobile-card-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 14px;
            }

            .mobile-card-label {
                color: #666;
                font-weight: 500;
            }

            .mobile-card-value {
                color: #2c3e50;
                text-align: right;
            }

            .mobile-card-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding-top: 10px;
                border-top: 1px solid #f0f0f0;
            }

            .mobile-card-actions .btn {
                width: 100%;
                margin: 0;
            }

            .mobile-card-checkbox {
                margin-right: 10px;
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
                <div class="stat-value"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Envíos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $enviadas; ?></div>
                <div class="stat-label">Enviadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $entregadas; ?></div>
                <div class="stat-label">Entregadas</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-row">
                    <div class="filter-group">
                        <a href="<?php echo url('/admin/envios-archivo.php'); ?>" class="btn btn-secondary" style="height: 38px; display: flex; align-items: center;">
                            📦 Ver Archivados
                        </a>
                    </div>

                    <div class="filter-group">
                        <label for="search">Buscar</label>
                        <input type="text" id="search" name="search"
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Nº Orden, Cliente, Email">
                    </div>

                    <div class="filter-group">
                        <label for="filter">Estado</label>
                        <select id="filter" name="filter">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Todos</option>
                            <option value="cobrada" <?php echo $filter_status === 'cobrada' ? 'selected' : ''; ?>>Cobrada</option>
                            <option value="enviada" <?php echo $filter_status === 'enviada' ? 'selected' : ''; ?>>Enviada</option>
                            <option value="entregada" <?php echo $filter_status === 'entregada' ? 'selected' : ''; ?>>Entregada</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="delivery">Método Entrega</label>
                        <select id="delivery" name="delivery">
                            <option value="all" <?php echo $filter_delivery === 'all' ? 'selected' : ''; ?>>Todos</option>
                            <option value="pickup" <?php echo $filter_delivery === 'pickup' ? 'selected' : ''; ?>>Retiro</option>
                            <option value="shipping" <?php echo $filter_delivery === 'shipping' ? 'selected' : ''; ?>>Envío</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulkForm">
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <span id="selectedCount">0 envíos seleccionados</span>
                <select name="bulk_action" id="bulkAction">
                    <option value="">Seleccionar acción...</option>
                    <option value="mark_sent">Marcar como Enviada</option>
                    <option value="mark_delivered">Marcar como Entregada</option>
                    <option value="archive">Archivar</option>
                </select>
                <button type="button" class="btn btn-sm btn-primary" onclick="confirmBulkAction()">
                    Aplicar
                </button>
            </div>

            <!-- Orders List -->
            <div class="card">
                <div class="card-header">
                    <span>
                        <?php if (empty($orders)): ?>
                            Todos los Envíos
                        <?php else: ?>
                            Mostrando <?php echo count($orders); ?> de <?php echo $total_orders; ?> envíos
                        <?php endif; ?>
                    </span>
                    <div class="card-header-actions">
                        <button type="button" class="btn btn-primary btn-sm" onclick="exportSelected('csv')">
                            📊 Exportar CSV
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="exportSelected('json')">
                            📄 Exportar JSON
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th>Nº Orden</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Total</th>
                                <th>Estado</th>
                                <th>Entrega</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                        No hay envíos que coincidan con los filtros.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr data-id="<?php echo htmlspecialchars($order['id']); ?>">
                                        <td>
                                            <input type="checkbox" name="selected_orders[]"
                                                   value="<?php echo htmlspecialchars($order['id']); ?>"
                                                   class="order-checkbox"
                                                   onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong><br>
                                            <small style="color: #999;">ID: <?php echo htmlspecialchars(substr($order['id'], 0, 10)); ?>...</small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                                            <small style="color: #999;"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($order['date'])); ?><br>
                                            <small style="color: #999;"><?php echo date('H:i', strtotime($order['date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo format_price($order['total']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $order['status']; ?>">
                                                <?php
                                                    $status_labels = [
                                                        'cobrada' => 'Cobrada',
                                                        'enviada' => 'Enviada',
                                                        'entregada' => 'Entregada'
                                                    ];
                                                    echo $status_labels[$order['status']] ?? $order['status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $order['delivery_method'] ?? 'pickup'; ?>">
                                                <?php echo ($order['delivery_method'] ?? 'pickup') === 'pickup' ? 'Retiro' : 'Envío'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button type="button" class="btn btn-primary btn-sm"
                                                        onclick="viewOrder('<?php echo $order['id']; ?>')">
                                                    👁️ Ver
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                        onclick="confirmArchiveOrder('<?php echo $order['id']; ?>', '<?php echo htmlspecialchars(addslashes($order['order_number'])); ?>')">
                                                    📦 Archivar
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
        </form>

        <!-- Mobile Cards View -->
        <div class="mobile-cards">
            <?php if (empty($orders)): ?>
                <div class="card">
                    <p style="text-align: center; color: #999; padding: 20px;">
                        No hay envíos que coincidan con los filtros.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <div style="display: flex; align-items: center; flex: 1;">
                                <input type="checkbox" name="selected_orders[]"
                                       value="<?php echo htmlspecialchars($order['id']); ?>"
                                       class="order-checkbox mobile-card-checkbox"
                                       form="bulkForm"
                                       onchange="updateBulkActions()">
                                <div>
                                    <div class="mobile-card-title"><?php echo htmlspecialchars($order['order_number']); ?></div>
                                    <small style="color: #999;"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-body">
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Fecha:</span>
                                <span class="mobile-card-value"><?php echo date('d/m/Y H:i', strtotime($order['date'])); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Total:</span>
                                <span class="mobile-card-value"><strong><?php echo format_price($order['total']); ?></strong></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Estado:</span>
                                <span class="mobile-card-value">
                                    <span class="badge <?php echo $order['status']; ?>">
                                        <?php
                                            $status_labels = [
                                                'cobrada' => 'Cobrada',
                                                'enviada' => 'Enviada',
                                                'entregada' => 'Entregada'
                                            ];
                                            echo $status_labels[$order['status']] ?? $order['status'];
                                        ?>
                                    </span>
                                </span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Entrega:</span>
                                <span class="mobile-card-value">
                                    <span class="badge <?php echo $order['delivery_method'] ?? 'pickup'; ?>">
                                        <?php echo ($order['delivery_method'] ?? 'pickup') === 'pickup' ? 'Retiro' : 'Envío'; ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="mobile-card-actions">
                            <button type="button" class="btn btn-primary btn-sm"
                                    onclick="viewOrder('<?php echo $order['id']; ?>')">
                                Ver Detalles
                            </button>
                            <button type="button" class="btn btn-danger btn-sm"
                                    onclick="confirmArchiveOrder('<?php echo $order['id']; ?>', '<?php echo htmlspecialchars(addslashes($order['order_number'])); ?>')">
                                Archivar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Component -->
    <?php include __DIR__ . '/includes/modal.php'; ?>

    <script>
        /**
         * Ver detalles de orden en modal
         */
        function viewOrder(orderId) {
            // Fetch order details and show in modal
            fetch('<?php echo url('/api/get-order.php'); ?>?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        let itemsHtml = '<table style="width: 100%; margin-top: 10px; border-collapse: collapse;">';
                        itemsHtml += '<thead><tr style="background: #f8f9fa;"><th style="padding: 8px; text-align: left;">Producto</th><th style="padding: 8px; text-align: center;">Cant.</th><th style="padding: 8px; text-align: right;">Precio</th></tr></thead><tbody>';

                        order.items.forEach(item => {
                            itemsHtml += `<tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 8px;">${item.name}</td>
                                <td style="padding: 8px; text-align: center;">${item.quantity}</td>
                                <td style="padding: 8px; text-align: right;">${item.price_formatted || item.price}</td>
                            </tr>`;
                        });

                        itemsHtml += '</tbody></table>';

                        let detailsHtml = `
                            <p><strong>Nº Orden:</strong> ${order.order_number}</p>
                            <p><strong>Cliente:</strong> ${order.customer_name || 'N/A'}</p>
                            <p><strong>Email:</strong> ${order.customer_email || 'N/A'}</p>
                            <p><strong>Teléfono:</strong> ${order.customer_phone || 'N/A'}</p>
                            <p><strong>Estado:</strong> ${order.status}</p>
                            <p><strong>Método de Entrega:</strong> ${order.delivery_method === 'pickup' ? 'Retiro' : 'Envío'}</p>
                            ${order.shipping_address ? `<p><strong>Dirección:</strong> ${order.shipping_address}</p>` : ''}
                            ${order.tracking_number ? `<p><strong>Nº Seguimiento:</strong> ${order.tracking_number}</p>` : ''}
                            ${order.notes ? `<p><strong>Notas:</strong> ${order.notes}</p>` : ''}
                            <hr style="margin: 15px 0;">
                            <p><strong>Productos:</strong></p>
                            ${itemsHtml}
                            <hr style="margin: 15px 0;">
                            <p style="text-align: right;"><strong>Total: ${order.total_formatted || order.total}</strong></p>
                            <hr style="margin: 15px 0;">
                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <button onclick="exportSingleOrder('${order.id}', 'csv')" class="btn btn-sm btn-primary">📊 Exportar CSV</button>
                                <button onclick="exportSingleOrder('${order.id}', 'json')" class="btn btn-sm btn-primary">📄 Exportar JSON</button>
                            </div>
                        `;

                        showModal({
                            title: 'Detalles de Envío',
                            message: 'Información completa del envío',
                            details: detailsHtml,
                            icon: '📦',
                            iconClass: 'info',
                            confirmText: 'Cerrar',
                            confirmType: 'primary',
                            cancelText: null,
                            onConfirm: function() {}
                        });
                    } else {
                        showModal({
                            title: 'Error',
                            message: 'No se pudo cargar la información del envío.',
                            icon: '❌',
                            iconClass: 'danger',
                            confirmText: 'Cerrar',
                            confirmType: 'danger',
                            cancelText: null,
                            onConfirm: function() {}
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showModal({
                        title: 'Error',
                        message: 'Error de conexión al cargar el envío.',
                        icon: '⚠️',
                        iconClass: 'warning',
                        confirmText: 'Cerrar',
                        confirmType: 'warning',
                        cancelText: null,
                        onConfirm: function() {}
                    });
                });
        }

        /**
         * Confirmar archivo de envío
         */
        function confirmArchiveOrder(orderId, orderNumber) {
            showModal({
                title: 'Archivar Envío',
                message: `¿Estás seguro de que deseas archivar "${orderNumber}"?`,
                details: 'El envío se moverá al archivo y no aparecerá en el listado principal. Podrás restaurarlo desde la sección de Envíos Archivados.',
                icon: '📦',
                iconClass: 'warning',
                confirmText: 'Archivar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=archive&id=${orderId}`;
                }
            });
        }

        /**
         * Confirmar acción masiva
         */
        function confirmBulkAction() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const action = document.getElementById('bulkAction').value;
            const count = checkboxes.length;

            // Validaciones
            if (count === 0) {
                showModal({
                    title: 'Sin Envíos Seleccionados',
                    message: 'Debes seleccionar al menos un envío para realizar una acción masiva.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
                return;
            }

            if (!action) {
                showModal({
                    title: 'Acción No Seleccionada',
                    message: 'Debes seleccionar una acción para aplicar a los envíos seleccionados.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
                return;
            }

            // Configurar modal según la acción
            let title, message, icon, iconClass, confirmType;

            if (action === 'mark_sent') {
                title = 'Marcar como Enviada';
                message = `¿Marcar ${count} envío${count > 1 ? 's' : ''} como enviada${count > 1 ? 's' : ''}?`;
                icon = '📦';
                iconClass = 'info';
                confirmType = 'primary';
            } else if (action === 'mark_delivered') {
                title = 'Marcar como Entregada';
                message = `¿Marcar ${count} envío${count > 1 ? 's' : ''} como entregada${count > 1 ? 's' : ''}?`;
                icon = '✅';
                iconClass = 'success';
                confirmType = 'primary';
            } else if (action === 'archive') {
                title = 'Archivar Envíos';
                message = `¿Archivar ${count} envío${count > 1 ? 's' : ''}?`;
                icon = '📦';
                iconClass = 'danger';
                confirmType = 'danger';
            }

            showModal({
                title: title,
                message: message,
                details: `Esta acción se aplicará a ${count} envío${count > 1 ? 's seleccionados' : ' seleccionado'}.`,
                icon: icon,
                iconClass: iconClass,
                confirmText: 'Confirmar',
                confirmType: confirmType,
                onConfirm: function() {
                    document.getElementById('bulkForm').submit();
                }
            });
        }

        // Handle checkbox selection for bulk actions
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBar.classList.add('show');
                selectedCount.textContent = `${count} envío${count > 1 ? 's' : ''} seleccionado${count > 1 ? 's' : ''}`;
            } else {
                bulkBar.classList.remove('show');
                selectAll.checked = false;
            }
        }

        /**
         * Exportar envíos seleccionados
         */
        function exportSelected(format) {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const orderIds = Array.from(checkboxes).map(cb => cb.value);

            if (orderIds.length === 0) {
                showModal({
                    title: 'Sin Envíos Seleccionados',
                    message: 'Debes seleccionar al menos un envío para exportar.',
                    icon: '⚠️',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
                return;
            }

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo url('/api/export-orders.php'); ?>';
            form.target = '_blank';

            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = format;
            form.appendChild(formatInput);

            orderIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'order_ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        /**
         * Exportar un solo envío desde el modal
         */
        function exportSingleOrder(orderId, format) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo url('/api/export-orders.php'); ?>';
            form.target = '_blank';

            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = format;
            form.appendChild(formatInput);

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'order_ids[]';
            idInput.value = orderId;
            form.appendChild(idInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>
</html>
