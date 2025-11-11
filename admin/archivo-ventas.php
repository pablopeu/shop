<?php
/**
 * Admin - Archived Sales/Orders
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Archivo de Ventas';

// Handle actions
$message = '';
$error = '';

// Restore order
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $order_id = $_GET['id'];
    if (restore_archived_order($order_id)) {
        $message = 'Orden restaurada exitosamente';
    } else {
        $error = 'Error al restaurar la orden';
    }
}

// Permanently delete order
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $order_id = $_GET['id'];
    if (delete_archived_order($order_id)) {
        $message = 'Orden eliminada permanentemente';
    } else {
        $error = 'Error al eliminar la orden';
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_orders = $_POST['selected_orders'] ?? [];

    if (!empty($selected_orders)) {
        $success_count = 0;
        foreach ($selected_orders as $order_id) {
            if ($action === 'restore') {
                if (restore_archived_order($order_id)) {
                    $success_count++;
                }
            } elseif ($action === 'delete') {
                if (delete_archived_order($order_id)) {
                    $success_count++;
                }
            }
        }

        $message = "$success_count orden(es) procesada(s) exitosamente";
    } else {
        $error = 'No se seleccionaron órdenes';
    }
}

// Get all archived orders
$archived_orders = get_archived_orders();

// Sort by archived date (newest first)
usort($archived_orders, function($a, $b) {
    $date_a = $a['archived_date'] ?? $a['date'];
    $date_b = $b['archived_date'] ?? $b['date'];
    return strtotime($date_b) - strtotime($date_a);
});

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get logged user
$user = get_logged_user();

// Status labels
$status_labels = [
    'pending' => ['label' => 'Pendiente', 'color' => '#FFA726'],
    'confirmed' => ['label' => 'Confirmado', 'color' => '#4CAF50'],
    'shipped' => ['label' => 'Enviado', 'color' => '#2196F3'],
    'delivered' => ['label' => 'Entregado', 'color' => '#4CAF50'],
    'cancelled' => ['label' => 'Cancelado', 'color' => '#f44336'],
    'cobrada' => ['label' => 'Cobrada', 'color' => '#4CAF50'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#f44336'],
    'pendiente' => ['label' => 'Pendiente de Pago', 'color' => '#FFA726']
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archivo de Ventas - Admin</title>

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

        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .info-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 6px;
            color: #856404;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        /* Confirmation Modal */
        .confirm-modal-content {
            max-width: 500px;
            text-align: center;
        }

        .confirm-modal-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .confirm-modal-icon.warning {
            color: #ffc107;
        }

        .confirm-modal-icon.danger {
            color: #dc3545;
        }

        .confirm-modal-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .confirm-modal-description {
            font-size: 15px;
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .confirm-modal-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: left;
        }

        .confirm-modal-details ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .confirm-modal-details li {
            margin: 5px 0;
            font-size: 14px;
        }

        .confirm-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn {
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .modal-btn-cancel:hover {
            background: #5a6268;
        }

        .modal-btn-confirm {
            background: #4CAF50;
            color: white;
        }

        .modal-btn-confirm:hover {
            background: #45a049;
        }

        .modal-btn-danger {
            background: #dc3545;
            color: white;
        }

        .modal-btn-danger:hover {
            background: #c82333;
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
                grid-template-columns: 1fr !important;
            }

            table {
                min-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            table {
                font-size: 12px;
                min-width: 800px;
            }

            table th,
            table td {
                padding: 8px 6px !important;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) !important;
                gap: 8px;
            }

            .actions {
                flex-direction: column !important;
                gap: 5px !important;
            }

            .actions .btn {
                width: 100%;
                padding: 6px 10px;
            }

            .bulk-actions-bar {
                flex-direction: column;
                gap: 8px;
            }

            .bulk-actions-bar select,
            .bulk-actions-bar .btn {
                width: 100%;
            }

            .form-grid {
                grid-template-columns: 1fr !important;
            }

            .form-row {
                flex-direction: column;
            }

            /* Better touch targets */
            .btn {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            input[type="text"],
            input[type="email"],
            input[type="number"],
            input[type="password"],
            select,
            textarea {
                font-size: 16px !important;
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
        <div>
            <?php include __DIR__ . '/includes/header.php'; ?>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="info-banner">
                ⚠️ <strong>Archivo de Ventas:</strong> Las órdenes archivadas no aparecen en el listado principal.
                Puedes restaurarlas o eliminarlas permanentemente.
            </div>

            <!-- Bulk Actions -->
            <form method="POST" id="bulkForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="bulk-actions-bar" style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center;">
                    <select name="bulk_action" id="bulkAction" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Seleccionar acción...</option>
                        <option value="restore">Restaurar a Ventas Activas</option>
                        <option value="delete">Eliminar Permanentemente</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">Aplicar a Seleccionadas</button>
                    <a href="ventas.php" class="btn btn-secondary btn-sm">← Volver a Ventas</a>
                    <span id="selectedCount" style="color: #666; font-size: 13px;"></span>
                </div>

            <!-- Archived Orders Table -->
            <div class="card">
                <div class="table-container">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)">
                            </th>
                            <th>Pedido #</th>
                            <th>Cliente</th>
                            <th>Fecha Original</th>
                            <th>Fecha Archivo</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($archived_orders)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    No hay órdenes archivadas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($archived_orders as $order): ?>
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
                                        <small style="color: #666;"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($order['date'])); ?><br>
                                        <small style="color: #666;"><?php echo date('H:i', strtotime($order['date'])); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $archived_date = $order['archived_date'] ?? $order['date'];
                                        echo date('d/m/Y', strtotime($archived_date));
                                        ?><br>
                                        <small style="color: #666;"><?php echo date('H:i', strtotime($archived_date)); ?></small>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php if ($order['currency'] === 'USD'): ?>
                                                U$D <?php echo number_format($order['total'], 2, ',', '.'); ?>
                                            <?php else: ?>
                                                $ <?php echo number_format($order['total'], 2, ',', '.'); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $order['status'];
                                        $status_info = $status_labels[$status] ?? ['label' => $status, 'color' => '#666'];
                                        ?>
                                        <span class="status-badge" style="background: <?php echo $status_info['color']; ?>">
                                            <?php echo $status_info['label']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="#"
                                               class="btn btn-sm btn-secondary"
                                               onclick="confirmSingleAction('restore', '<?php echo htmlspecialchars($order['id']); ?>', '<?php echo htmlspecialchars($order['order_number']); ?>'); return false;">
                                                ↩️ Restaurar
                                            </a>
                                            <a href="#"
                                               class="btn btn-sm btn-danger"
                                               onclick="confirmSingleAction('delete', '<?php echo htmlspecialchars($order['id']); ?>', '<?php echo htmlspecialchars($order['order_number']); ?>'); return false;">
                                                🗑️ Eliminar
                                            </a>
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
                <?php if (empty($archived_orders)): ?>
                    <div class="card">
                        <p style="text-align: center; color: #999; padding: 20px;">
                            No hay órdenes archivadas.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($archived_orders as $order): ?>
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
                                    <span class="mobile-card-label">Fecha Original:</span>
                                    <span class="mobile-card-value">
                                        <?php echo date('d/m/Y', strtotime($order['date'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($order['date'])); ?></small>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Fecha Archivo:</span>
                                    <span class="mobile-card-value">
                                        <?php
                                        $archived_date = $order['archived_date'] ?? $order['date'];
                                        echo date('d/m/Y', strtotime($archived_date));
                                        ?><br>
                                        <small><?php echo date('H:i', strtotime($archived_date)); ?></small>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Total:</span>
                                    <span class="mobile-card-value">
                                        <strong>
                                            <?php if ($order['currency'] === 'USD'): ?>
                                                U$D <?php echo number_format($order['total'], 2, ',', '.'); ?>
                                            <?php else: ?>
                                                $ <?php echo number_format($order['total'], 2, ',', '.'); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Estado:</span>
                                    <span class="mobile-card-value">
                                        <?php
                                        $status = $order['status'];
                                        $status_info = $status_labels[$status] ?? ['label' => $status, 'color' => '#666'];
                                        ?>
                                        <span class="status-badge" style="background: <?php echo $status_info['color']; ?>">
                                            <?php echo $status_info['label']; ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="mobile-card-actions">
                                <a href="#"
                                   class="btn btn-sm btn-secondary"
                                   onclick="confirmSingleAction('restore', '<?php echo htmlspecialchars($order['id']); ?>', '<?php echo htmlspecialchars($order['order_number']); ?>'); return false;">
                                    Restaurar
                                </a>
                                <a href="#"
                                   class="btn btn-sm btn-danger"
                                   onclick="confirmSingleAction('delete', '<?php echo htmlspecialchars($order['id']); ?>', '<?php echo htmlspecialchars($order['order_number']); ?>'); return false;">
                                    Eliminar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

    <!-- Individual Action Confirmation Modal -->
    <div id="confirmSingleModal" class="modal">
        <div class="modal-content confirm-modal-content">
            <div class="confirm-modal-icon" id="singleConfirmIcon">⚠️</div>
            <h2 class="confirm-modal-title" id="singleConfirmTitle">Confirmar Acción</h2>
            <p class="confirm-modal-description" id="singleConfirmDescription"></p>
            <div class="confirm-modal-details" id="singleConfirmDetails"></div>
            <div class="confirm-modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeSingleConfirmModal()">
                    Cancelar
                </button>
                <a class="modal-btn" id="singleConfirmButton" href="#">
                    Confirmar
                </a>
            </div>
        </div>
    </div>

    <script>
        // Checkbox management
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const count = checkboxes.length;
            const countElement = document.getElementById('selectedCount');

            if (count > 0) {
                countElement.textContent = `${count} orden(es) seleccionada(s)`;
                countElement.style.fontWeight = 'bold';
                countElement.style.color = '#4CAF50';
            } else {
                countElement.textContent = '';
            }

            // Update "select all" checkbox state
            const allCheckboxes = document.querySelectorAll('.order-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            selectAllCheckbox.checked = allCheckboxes.length > 0 && count === allCheckboxes.length;
        }

        function confirmBulkAction() {
            const action = document.getElementById('bulkAction').value;
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');

            if (!action) {
                showNotification('Por favor selecciona una acción', 'warning');
                return false;
            }

            if (checkboxes.length === 0) {
                showNotification('Por favor selecciona al menos una orden', 'warning');
                return false;
            }

            // Show confirmation modal
            showBulkActionModal(action, checkboxes.length);
            return false; // Prevent form submission, will be handled by modal
        }

        function showBulkActionModal(action, count) {
            const modal = document.getElementById('confirmBulkModal');
            const icon = document.getElementById('confirmIcon');
            const title = document.getElementById('confirmTitle');
            const description = document.getElementById('confirmDescription');
            const details = document.getElementById('confirmDetails');
            const confirmBtn = document.getElementById('confirmButton');

            const actionConfig = {
                'restore': {
                    icon: '↩️',
                    iconClass: 'warning',
                    title: 'Restaurar Órdenes',
                    description: 'Las siguientes órdenes serán restauradas al listado activo de ventas:',
                    effects: ['Las órdenes volverán al listado principal de ventas', 'Se eliminarán del archivo', 'Podrán ser gestionadas normalmente'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Restaurar Órdenes'
                },
                'delete': {
                    icon: '🗑️',
                    iconClass: 'danger',
                    title: 'Eliminar Permanentemente',
                    description: '⚠️ ATENCIÓN: Esta acción eliminará permanentemente las órdenes seleccionadas:',
                    effects: ['Las órdenes serán ELIMINADAS PERMANENTEMENTE', '❌ Esta acción NO se puede deshacer', '❌ No podrás recuperar esta información', '⚠️ Solo elimina si estás completamente seguro'],
                    btnClass: 'modal-btn-danger',
                    btnText: 'Eliminar Permanentemente'
                }
            };

            const config = actionConfig[action];

            // Set icon
            icon.textContent = config.icon;
            icon.className = 'confirm-modal-icon ' + config.iconClass;

            // Set title and description
            title.textContent = config.title;
            description.textContent = config.description;

            // Set details
            details.innerHTML = `
                <strong>${count} orden(es) seleccionada(s)</strong>
                <p style="margin: 10px 0; font-size: 13px; color: #666;">${action === 'delete' ? '⚠️ Ten en cuenta que:' : 'Esta acción:'}</p>
                <ul>
                    ${config.effects.map(effect => `<li>${effect}</li>`).join('')}
                </ul>
            `;

            // Configure button
            confirmBtn.className = 'modal-btn ' + config.btnClass;
            confirmBtn.textContent = config.btnText;

            // Show modal
            modal.classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirmBulkModal').classList.remove('active');
        }

        function executeBulkAction() {
            // Close modal
            closeConfirmModal();

            // Submit form
            document.getElementById('bulkForm').submit();
        }

        function showNotification(message, type = 'info') {
            // Simple notification
            const notification = document.createElement('div');
            notification.className = 'message ' + (type === 'warning' ? 'error' : 'success');
            notification.textContent = message;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '10000';
            notification.style.minWidth = '300px';
            notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Show single action confirmation modal
        function confirmSingleAction(action, orderId, orderNumber) {
            const modal = document.getElementById('confirmSingleModal');
            const icon = document.getElementById('singleConfirmIcon');
            const title = document.getElementById('singleConfirmTitle');
            const description = document.getElementById('singleConfirmDescription');
            const details = document.getElementById('singleConfirmDetails');
            const confirmBtn = document.getElementById('singleConfirmButton');

            const actionConfig = {
                'restore': {
                    icon: '↩️',
                    iconClass: 'warning',
                    title: 'Restaurar Orden',
                    description: `¿Deseas restaurar la orden ${orderNumber} al listado activo?`,
                    effects: ['La orden volverá al listado principal de ventas', 'Se eliminará del archivo'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Restaurar',
                    url: `?action=restore&id=${orderId}`
                },
                'delete': {
                    icon: '🗑️',
                    iconClass: 'danger',
                    title: 'Eliminar Permanentemente',
                    description: `⚠️ ¿Estás seguro de eliminar PERMANENTEMENTE la orden ${orderNumber}?`,
                    effects: ['La orden será ELIMINADA PERMANENTEMENTE', '❌ Esta acción NO se puede deshacer', '❌ No podrás recuperar esta información'],
                    btnClass: 'modal-btn-danger',
                    btnText: 'Eliminar Permanentemente',
                    url: `?action=delete&id=${orderId}`
                }
            };

            const config = actionConfig[action];

            // Set icon
            icon.textContent = config.icon;
            icon.className = 'confirm-modal-icon ' + config.iconClass;

            // Set title and description
            title.textContent = config.title;
            description.textContent = config.description;

            // Set details
            details.innerHTML = `
                <p style="margin: 10px 0; font-size: 13px; color: #666;">${action === 'delete' ? '⚠️ Ten en cuenta que:' : 'Esta acción:'}</p>
                <ul>
                    ${config.effects.map(effect => `<li>${effect}</li>`).join('')}
                </ul>
            `;

            // Configure button
            confirmBtn.className = 'modal-btn ' + config.btnClass;
            confirmBtn.textContent = config.btnText;
            confirmBtn.href = config.url;

            // Show modal
            modal.classList.add('active');
        }

        function closeSingleConfirmModal() {
            document.getElementById('confirmSingleModal').classList.remove('active');
        }

        // Close modals when clicking outside
        document.getElementById('confirmBulkModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmModal();
            }
        });

        document.getElementById('confirmSingleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSingleConfirmModal();
            }
        });

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', updateSelectedCount);
    </script>
</body>
</html>
