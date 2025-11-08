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

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .content-header h1 {
            font-size: 22px;
            color: #2c3e50;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div>
            <div class="content-header">
                <h1>📦 Archivo de Ventas</h1>
                <a href="/admin/ventas.php" class="btn btn-secondary">← Volver a Ventas Activas</a>
            </div>

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
                <div style="display: flex; gap: 10px; margin-bottom: 15px; align-items: center;">
                    <select name="bulk_action" id="bulkAction" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">Seleccionar acción...</option>
                        <option value="restore">Restaurar a Ventas Activas</option>
                        <option value="delete">Eliminar Permanentemente</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">Aplicar a Seleccionadas</button>
                    <span id="selectedCount" style="color: #666; font-size: 13px;"></span>
                </div>

            <!-- Archived Orders Table -->
            <div class="card">
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
                                            <a href="?action=restore&id=<?php echo urlencode($order['id']); ?>"
                                               class="btn btn-sm btn-secondary"
                                               onclick="return confirm('¿Restaurar esta orden a ventas activas?')">
                                                ↩️ Restaurar
                                            </a>
                                            <a href="?action=delete&id=<?php echo urlencode($order['id']); ?>"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('⚠️ ¿ELIMINAR PERMANENTEMENTE esta orden? Esta acción no se puede deshacer.')">
                                                🗑️ Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
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
                alert('Por favor selecciona una acción');
                return false;
            }

            if (checkboxes.length === 0) {
                alert('Por favor selecciona al menos una orden');
                return false;
            }

            if (action === 'delete') {
                return confirm(`⚠️ ¿ELIMINAR PERMANENTEMENTE ${checkboxes.length} orden(es)? Esta acción no se puede deshacer.`);
            } else if (action === 'restore') {
                return confirm(`¿Restaurar ${checkboxes.length} orden(es) a ventas activas?`);
            }

            return true;
        }

        // Initialize selected count on page load
        document.addEventListener('DOMContentLoaded', updateSelectedCount);
    </script>
</body>
</html>
