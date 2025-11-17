<?php
/**
 * Admin - Sales/Orders Management
 */

// Define admin access constant for included modules
define('ADMIN_ACCESS', true);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/telegram.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');

// Page title for header
$page_title = 'Gestión de Ventas';

// Include actions module and handle POST/GET actions
require_once __DIR__ . '/includes/ventas/actions.php';
$action_result = handle_order_actions();
$message = $action_result['message'];
$error = $action_result['error'];

// Filter orders
$filter_status = $_GET['filter'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$all_orders = get_all_orders();

// Apply status filter
if ($filter_status === 'all') {
    $orders = $all_orders;
} else {
    $orders = array_filter($all_orders, function($order) use ($filter_status) {
        return $order['status'] === $filter_status;
    });
}

// Apply search filter (order number or customer name/email)
if (!empty($search_query)) {
    $orders = array_filter($orders, function($order) use ($search_query) {
        $search_lower = mb_strtolower($search_query);
        return stripos($order['order_number'], $search_query) !== false ||
               stripos(mb_strtolower($order['customer_name'] ?? ''), $search_lower) !== false ||
               stripos(mb_strtolower($order['customer_email'] ?? ''), $search_lower) !== false;
    });
}

// Apply date filter
if (!empty($date_from)) {
    $orders = array_filter($orders, function($order) use ($date_from) {
        return strtotime($order['date']) >= strtotime($date_from . ' 00:00:00');
    });
}

if (!empty($date_to)) {
    $orders = array_filter($orders, function($order) use ($date_to) {
        return strtotime($order['date']) <= strtotime($date_to . ' 23:59:59');
    });
}

// Sort by date (newest first)
usort($orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate stats for non-archived orders
$non_archived_orders = array_filter($all_orders, fn($o) => !($o['archived'] ?? false));

// 1. Total Orders: count + total amount in pesos (all non-archived orders, any status)
$total_orders = count($non_archived_orders);
$total_orders_amount = array_reduce($non_archived_orders, function($sum, $order) {
    return $sum + floatval($order['total']);
}, 0);

// 2. Pending Orders: count + total amount in pesos
$pending_orders_data = array_filter($non_archived_orders, fn($o) => $o['status'] === 'pending' || $o['status'] === 'pendiente');
$pending_orders = count($pending_orders_data);
$pending_amount = array_reduce($pending_orders_data, function($sum, $order) {
    return $sum + floatval($order['total']);
}, 0);

// 3. Cobradas (Confirmed): count + gross amount (without discounting fees)
$cobradas_orders = array_filter($non_archived_orders, fn($o) => $o['status'] === 'cobrada');
$confirmed_orders = count($cobradas_orders);
$cobradas_amount_gross = array_reduce($cobradas_orders, function($sum, $order) {
    return $sum + floatval($order['total']);
}, 0);

// 4. Total Fees: sum of all MP fees from non-archived collected orders
$total_fees = array_reduce($cobradas_orders, function($sum, $order) {
    if (isset($order['mercadopago_data']['total_fees'])) {
        return $sum + floatval($order['mercadopago_data']['total_fees']);
    }
    return $sum;
}, 0);

// 5. Net Revenue: collected amount - fees
$net_revenue = array_reduce($cobradas_orders, function($sum, $order) {
    if (isset($order['mercadopago_data']['net_received_amount'])) {
        return $sum + floatval($order['mercadopago_data']['net_received_amount']);
    } else {
        // For presencial payments or orders without MP data, use full total
        return $sum + floatval($order['total']);
    }
}, 0);

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get logged user
$user = get_logged_user();

// Status labels
$status_labels = [
    'pending' => ['label' => 'Pendiente', 'color' => '#FFA726'],
    'cobrada' => ['label' => 'Cobrada', 'color' => '#4CAF50'],
    'shipped' => ['label' => 'Enviado', 'color' => '#2196F3'],
    'delivered' => ['label' => 'Entregado', 'color' => '#4CAF50'],
    'cancelled' => ['label' => 'Cancelado', 'color' => '#f44336'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#f44336']
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ventas - Admin</title>
    <link rel="stylesheet" href="assets/css/ventas.css">
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
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="stat-card" style="border-left: 4px solid #3498db;">
                    <div class="stat-value">$<?php echo number_format($total_orders_amount, 2, ',', '.'); ?></div>
                    <div class="stat-label">Total Órdenes</div>
                    <div style="font-size: 13px; color: #999; margin-top: 4px;">
                        <?php echo $total_orders; ?> operaciones
                    </div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #FFA726;">
                    <div class="stat-value">$<?php echo number_format($pending_amount, 2, ',', '.'); ?></div>
                    <div class="stat-label">Pendientes</div>
                    <div style="font-size: 13px; color: #999; margin-top: 4px;">
                        <?php echo $pending_orders; ?> operaciones
                    </div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #4CAF50;">
                    <div class="stat-value">$<?php echo number_format($cobradas_amount_gross, 2, ',', '.'); ?></div>
                    <div class="stat-label">Cobradas (Bruto)</div>
                    <div style="font-size: 13px; color: #999; margin-top: 4px;">
                        <?php echo $confirmed_orders; ?> operaciones
                    </div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #dc3545;">
                    <div class="stat-value">$<?php echo number_format($total_fees, 2, ',', '.'); ?></div>
                    <div class="stat-label">Comisiones MP</div>
                    <div style="font-size: 13px; color: #999; margin-top: 4px;">
                        de <?php echo $confirmed_orders; ?> ventas cobradas
                    </div>
                </div>
                <div class="stat-card" style="border-left: 4px solid #27ae60;">
                    <div class="stat-value">$<?php echo number_format($net_revenue, 2, ',', '.'); ?></div>
                    <div class="stat-label">Ingreso Neto</div>
                    <div style="font-size: 13px; color: #999; margin-top: 4px;">
                        Cobrado - Comisiones
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <div class="filters">
                    <a href="?filter=all" class="filter-btn <?php echo $filter_status === 'all' ? 'active' : ''; ?>">
                        Todas
                    </a>
                    <a href="?filter=pending" class="filter-btn <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">
                        Pendientes
                    </a>
                    <a href="?filter=cobrada" class="filter-btn <?php echo $filter_status === 'cobrada' ? 'active' : ''; ?>">
                        Cobradas
                    </a>
                    <a href="?filter=shipped" class="filter-btn <?php echo $filter_status === 'shipped' ? 'active' : ''; ?>">
                        Enviadas
                    </a>
                    <a href="?filter=delivered" class="filter-btn <?php echo $filter_status === 'delivered' ? 'active' : ''; ?>">
                        Entregadas
                    </a>
                    <a href="?filter=cancelled" class="filter-btn <?php echo $filter_status === 'cancelled' ? 'active' : ''; ?>">
                        Canceladas
                    </a>
                </div>

                <!-- Advanced Filters -->
                <div class="card">
                    <div class="card-header">Filtros Avanzados</div>
                    <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: end;">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter_status); ?>">

                        <div class="form-group" style="margin: 0;">
                            <label for="search" style="font-size: 13px; margin-bottom: 5px; display: block;">Buscar (Nro pedido, cliente, email)</label>
                            <input type="text" id="search" name="search" placeholder="Ej: 1001 o Juan Perez"
                                   value="<?php echo htmlspecialchars($search_query); ?>"
                                   style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label for="date_from" style="font-size: 13px; margin-bottom: 5px; display: block;">Desde</label>
                            <input type="date" id="date_from" name="date_from"
                                   value="<?php echo htmlspecialchars($date_from); ?>"
                                   style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                        </div>

                        <div class="form-group" style="margin: 0;">
                            <label for="date_to" style="font-size: 13px; margin-bottom: 5px; display: block;">Hasta</label>
                            <input type="date" id="date_to" name="date_to"
                                   value="<?php echo htmlspecialchars($date_to); ?>"
                                   style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary btn-sm">Aplicar Filtros</button>
                            <a href="?" class="btn btn-secondary btn-sm">Limpiar</a>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="bulk-actions-bar" style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center;">
                        <select name="bulk_action" id="bulkAction" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="">Seleccionar acción...</option>
                            <option value="pending">Marcar como Pendiente</option>
                            <option value="cobrada">Marcar como Cobrada</option>
                            <option value="shipped">Marcar como Enviada</option>
                            <option value="delivered">Marcar como Entregada</option>
                            <option value="cancel">Cancelar</option>
                            <option value="archive">Archivar</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">Aplicar a Seleccionadas</button>
                        <a href="archivo-ventas.php" class="btn btn-secondary btn-sm">Ver Archivo</a>
                        <span id="selectedCount" style="color: #666; font-size: 13px;"></span>
                    </div>

                <!-- Orders Table -->
                <div class="table-container">
                    <table class="orders-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>Pedido #</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Método de Pago</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    No hay órdenes<?php echo $filter_status !== 'all' ? ' con este estado' : ''; ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_orders[]"
                                               value="<?php echo htmlspecialchars($order['id']); ?>"
                                               class="order-checkbox"
                                               onchange="updateSelectedCount()">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                                        <small style="color: #666;">
                                            <?php echo htmlspecialchars($order['customer_email'] ?? ''); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($order['date'])); ?><br>
                                        <small style="color: #666;">
                                            <?php echo date('H:i', strtotime($order['date'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo format_price($order['total'], $order['currency']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        if ($order['payment_method'] === 'mercadopago') {
                                            echo '💳 Mercadopago';
                                        } elseif ($order['payment_method'] === 'arrangement') {
                                            echo '🤝 Arreglo';
                                        } elseif ($order['payment_method'] === 'pickup_payment') {
                                            echo '💵 Pago al retirar';
                                        } else {
                                            echo '💵 Presencial';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_info = $status_labels[$order['status']] ?? ['label' => $order['status'], 'color' => '#999'];
                                        ?>
                                        <span class="status-badge" style="background: <?php echo $status_info['color']; ?>">
                                            <?php echo $status_info['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button type="button" onclick="viewOrder('<?php echo $order['id']; ?>')"
                                                    class="btn btn-primary btn-sm">
                                                👁️ Ver
                                            </button>
                                            <?php if ($order['status'] !== 'cancelled'): ?>
                                                <button type="button" onclick="showCancelModal('<?php echo $order['id']; ?>', '<?php echo $order['order_number']; ?>')"
                                                        class="btn btn-danger btn-sm">
                                                    ❌ Cancelar
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                </form>

                <!-- Mobile Cards View -->
                <div class="mobile-cards">
                    <?php if (empty($orders)): ?>
                        <div class="card">
                            <p style="text-align: center; color: #999; padding: 20px;">
                                No hay órdenes que coincidan con los filtros.
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
                                               onchange="updateSelectedCount()">
                                        <div>
                                            <div class="mobile-card-title">Pedido #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                            <small style="color: #999;"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mobile-card-body">
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Cliente:</span>
                                        <span class="mobile-card-value">
                                            <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?><br>
                                            <small><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                        </span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Fecha:</span>
                                        <span class="mobile-card-value">
                                            <?php echo date('d/m/Y', strtotime($order['date'])); ?><br>
                                            <small><?php echo date('H:i', strtotime($order['date'])); ?></small>
                                        </span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Total:</span>
                                        <span class="mobile-card-value"><strong><?php echo format_price($order['total'], $order['currency']); ?></strong></span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Método de Pago:</span>
                                        <span class="mobile-card-value">
                                            <?php
                                            if ($order['payment_method'] === 'mercadopago') {
                                                echo 'Mercadopago';
                                            } elseif ($order['payment_method'] === 'arrangement') {
                                                echo 'Arreglo';
                                            } elseif ($order['payment_method'] === 'pickup_payment') {
                                                echo 'Pago al retirar';
                                            } else {
                                                echo 'Presencial';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="mobile-card-row">
                                        <span class="mobile-card-label">Estado:</span>
                                        <span class="mobile-card-value">
                                            <?php
                                            $status_info = $status_labels[$order['status']] ?? ['label' => $order['status'], 'color' => '#999'];
                                            ?>
                                            <span class="status-badge" style="background: <?php echo $status_info['color']; ?>">
                                                <?php echo $status_info['label']; ?>
                                            </span>
                                        </span>
                                    </div>
                                </div>

                                <div class="mobile-card-actions">
                                    <button type="button" onclick="viewOrder('<?php echo $order['id']; ?>')"
                                            class="btn btn-primary btn-sm">
                                        Ver Detalles
                                    </button>
                                    <?php if ($order['status'] !== 'cancelled'): ?>
                                        <button type="button" onclick="showCancelModal('<?php echo $order['id']; ?>', '<?php echo $order['order_number']; ?>')"
                                                class="btn btn-danger btn-sm">
                                            Cancelar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" onclick="closeOrderModal()">&times;</span>
                <h2 id="modalOrderNumber">Orden #</h2>
            </div>
            <div id="modalOrderContent" class="modal-body">
                <!-- Content will be loaded via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeOrderModal()">Cancelar</button>
                <button type="button" id="btnSaveChanges" class="btn-save" onclick="saveAllChanges()">
                    💾 Guardar Cambios
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <span class="modal-close" onclick="closeCancelModal()">&times;</span>
                <h2>⚠️ Cancelar Pedido</h2>
            </div>
            <div style="padding: 20px;">
                <p style="margin-bottom: 20px; font-size: 16px; color: #555;">
                    ¿Estás seguro de que deseas cancelar la orden <strong id="cancelOrderNumber"></strong>?
                </p>
                <p style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    Esta acción restaurará el stock de los productos.
                </p>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button onclick="closeCancelModal()" class="btn btn-secondary">
                        Cancelar
                    </button>
                    <a id="confirmCancelBtn" href="#" class="btn btn-danger">
                        Confirmar Cancelación
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Action Confirmation Modal -->
    <div id="confirmBulkModal" class="modal">
        <div class="modal-content confirm-modal-content">
            <div class="confirm-modal-icon" id="confirmIcon">⚠️</div>
            <h2 class="confirm-modal-title" id="confirmTitle">Confirmar Acción</h2>
            <p class="confirm-modal-description" id="confirmDescription"></p>
            <div class="confirm-modal-details" id="confirmDetails"></div>
            <div class="confirm-modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">
                    Cancelar
                </button>
                <button class="modal-btn" id="confirmButton" onclick="executeBulkAction()">
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <!-- Unsaved Changes Modal -->
    <div id="unsavedChangesModal" class="modal" style="z-index: 10001;">
        <div class="modal-content" style="max-width: 500px; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); position: relative;">
            <span class="modal-close" onclick="cancelCloseOrderModal()" style="position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #999; cursor: pointer; line-height: 20px; transition: color 0.3s;">&times;</span>
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="font-size: 48px; margin-bottom: 10px;">⚠️</div>
                <h2 style="margin: 0; color: #333; font-size: 22px;">Cambios sin guardar</h2>
            </div>
            <p style="text-align: center; color: #666; margin-bottom: 25px; line-height: 1.5;">
                Hay cambios sin guardar en este formulario. Si cierras ahora, perderás estos cambios.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="confirmCloseOrderModal()"
                        style="padding: 12px 24px; background: #95a5a6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Salir sin guardar
                </button>
                <button onclick="cancelCloseOrderModal()"
                        style="padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                    Quedarme para guardar
                </button>
            </div>
        </div>
    </div>

    <script type="module">
        // Import utility functions
        import { showToast, copyPaymentLink, formatPrice } from './assets/js/ventas-utils.js';
        import { initModal, viewOrder, switchTab, sendCustomMessage, saveAllChanges,
                 closeOrderModal, confirmCloseOrderModal, cancelCloseOrderModal,
                 showCancelModal, closeCancelModal } from './assets/js/ventas-modal.js';
        import { toggleAllCheckboxes, updateSelectedCount, confirmBulkAction,
                 showBulkActionModal, closeConfirmModal, executeBulkAction } from './assets/js/ventas-bulk-actions.js';

        // Expose utility functions immediately (don't need data)
        window.showToast = showToast;
        window.copyPaymentLink = copyPaymentLink;
        window.formatPrice = formatPrice;

        // Initialize modal module and expose functions when page loads
        const ordersData = <?php echo json_encode($orders); ?>;
        const token = '<?php echo $csrf_token; ?>';

        // Initialize immediately (before DOMContentLoaded)
        initModal(ordersData, token);

        // Expose modal functions globally
        window.viewOrder = viewOrder;
        window.switchTab = switchTab;
        window.sendCustomMessage = sendCustomMessage;
        window.saveAllChanges = saveAllChanges;
        window.closeOrderModal = closeOrderModal;
        window.confirmCloseOrderModal = confirmCloseOrderModal;
        window.cancelCloseOrderModal = cancelCloseOrderModal;
        window.showCancelModal = showCancelModal;
        window.closeCancelModal = closeCancelModal;

        // Expose bulk actions functions globally
        window.toggleAllCheckboxes = toggleAllCheckboxes;
        window.updateSelectedCount = updateSelectedCount;
        window.confirmBulkAction = confirmBulkAction;
        window.showBulkActionModal = showBulkActionModal;
        window.closeConfirmModal = closeConfirmModal;
        window.executeBulkAction = executeBulkAction;

        // Setup event listeners
        document.getElementById('confirmBulkModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', updateSelectedCount);
    </script>

    <!-- Modal Component -->
    <?php include __DIR__ . '/includes/modal.php'; ?>
</body>
</html>
