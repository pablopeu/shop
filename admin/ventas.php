<?php
/**
 * Admin - Sales/Orders Management
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

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';

    if (update_order_status($order_id, $new_status, $_SESSION['username'])) {
        $message = 'Estado actualizado exitosamente';
    } else {
        $error = 'Error al actualizar el estado';
    }
}

// Add tracking number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tracking'])) {
    $order_id = $_POST['order_id'] ?? '';
    $tracking_number = sanitize_input($_POST['tracking_number'] ?? '');
    $tracking_url = sanitize_input($_POST['tracking_url'] ?? '');

    if (add_order_tracking($order_id, $tracking_number, $tracking_url)) {
        $message = 'Número de seguimiento agregado';
        log_admin_action('tracking_added', $_SESSION['username'], [
            'order_id' => $order_id,
            'tracking_number' => $tracking_number
        ]);
    } else {
        $error = 'Error al agregar el número de seguimiento';
    }
}

// Cancel order
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $order_id = $_GET['id'];
    if (cancel_order($order_id, 'Cancelado por admin', $_SESSION['username'])) {
        $message = 'Orden cancelada y stock restaurado';
    } else {
        $error = 'Error al cancelar la orden';
    }
}

// Filter orders
$filter_status = $_GET['filter'] ?? 'all';
$all_orders = get_all_orders();

// Apply filter
if ($filter_status === 'all') {
    $orders = $all_orders;
} else {
    $orders = array_filter($all_orders, function($order) use ($filter_status) {
        return $order['status'] === $filter_status;
    });
}

// Sort by date (newest first)
usort($orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Calculate stats
$total_orders = count($all_orders);
$pending_orders = count(array_filter($all_orders, fn($o) => $o['status'] === 'pending'));
$confirmed_orders = count(array_filter($all_orders, fn($o) => $o['status'] === 'confirmed'));
$total_revenue = array_reduce($all_orders, function($sum, $order) {
    if (in_array($order['status'], ['confirmed', 'shipped', 'delivered'])) {
        return $sum + ($order['currency'] === 'ARS' ? $order['total'] : $order['total'] * 1000); // Approx conversion
    }
    return $sum;
}, 0);

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
    'cancelled' => ['label' => 'Cancelado', 'color' => '#f44336']
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ventas - Admin</title>

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

        /* Filters */
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 6px;
            background: white;
            border: 2px solid #e0e0e0;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: #4CAF50;
            border-color: #4CAF50;
            color: white;
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

        .modal-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .modal-close {
            float: right;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .order-items {
            margin-top: 20px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
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
                <h1>Gestión de Ventas</h1>
                <a href="/" class="btn btn-secondary" target="_blank">Ver sitio público</a>
            </div>

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
                    <div class="stat-label">Total Órdenes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $pending_orders; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $confirmed_orders; ?></div>
                    <div class="stat-label">Confirmadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo format_price($total_revenue, 'ARS'); ?></div>
                    <div class="stat-label">Ingresos Totales</div>
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
                    <a href="?filter=confirmed" class="filter-btn <?php echo $filter_status === 'confirmed' ? 'active' : ''; ?>">
                        Confirmadas
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

                <!-- Orders Table -->
                <table class="orders-table">
                    <thead>
                        <tr>
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
                                <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                    No hay órdenes<?php echo $filter_status !== 'all' ? ' con este estado' : ''; ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
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
                                        <?php echo $order['payment_method'] === 'presencial' ? '💵 Presencial' : '💳 Mercadopago'; ?>
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
                                            <button onclick="viewOrder('<?php echo $order['id']; ?>')"
                                                    class="btn btn-primary btn-sm">
                                                👁️ Ver
                                            </button>
                                            <?php if ($order['status'] !== 'cancelled'): ?>
                                                <a href="?action=cancel&id=<?php echo urlencode($order['id']); ?>"
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('¿Cancelar esta orden? Se restaurará el stock.')">
                                                    ❌ Cancelar
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-close" onclick="closeModal()">&times;</span>
                <h2 id="modalOrderNumber">Orden #</h2>
            </div>
            <div id="modalOrderContent">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
    </div>

    <script>
        const orders = <?php echo json_encode($orders); ?>;
        const csrfToken = '<?php echo $csrf_token; ?>';

        function viewOrder(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;

            document.getElementById('modalOrderNumber').textContent = 'Orden ' + order.order_number;

            let html = `
                <div class="form-group">
                    <label><strong>Cliente:</strong></label>
                    <p>${order.customer_name || 'N/A'}<br>
                       ${order.customer_email || ''}<br>
                       ${order.customer_phone || ''}</p>
                </div>

                ${order.shipping_address ? `
                <div class="form-group">
                    <label><strong>Dirección de Envío:</strong></label>
                    <p>${order.shipping_address.address}<br>
                       ${order.shipping_address.city}, CP ${order.shipping_address.postal_code}</p>
                </div>
                ` : ''}

                <div class="form-group">
                    <label><strong>Productos:</strong></label>
                    <div class="order-items">
                        ${order.items.map(item => `
                            <div class="order-item">
                                <span>${item.name} (x${item.quantity})</span>
                                <strong>${formatPrice(item.final_price, order.currency)}</strong>
                            </div>
                        `).join('')}
                        <div class="order-item" style="border-top: 2px solid #ccc; margin-top: 10px; padding-top: 10px;">
                            <span><strong>Total:</strong></span>
                            <strong>${formatPrice(order.total, order.currency)}</strong>
                        </div>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="order_id" value="${order.id}">

                    <div class="form-group">
                        <label for="status"><strong>Cambiar Estado:</strong></label>
                        <select name="status" id="status">
                            <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pendiente</option>
                            <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmado</option>
                            <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Enviado</option>
                            <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Entregado</option>
                        </select>
                    </div>

                    <button type="submit" name="update_status" class="btn btn-primary">
                        💾 Actualizar Estado
                    </button>
                </form>

                <hr style="margin: 20px 0;">

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                    <input type="hidden" name="order_id" value="${order.id}">

                    <div class="form-group">
                        <label for="tracking_number"><strong>Número de Seguimiento:</strong></label>
                        <input type="text" name="tracking_number" id="tracking_number"
                               value="${order.tracking_number || ''}" placeholder="Ej: CA123456789AR">
                    </div>

                    <div class="form-group">
                        <label for="tracking_url"><strong>URL de Seguimiento:</strong></label>
                        <input type="text" name="tracking_url" id="tracking_url"
                               value="${order.tracking_url || ''}" placeholder="https://...">
                    </div>

                    <button type="submit" name="add_tracking" class="btn btn-primary">
                        📦 Guardar Seguimiento
                    </button>
                </form>
            `;

            document.getElementById('modalOrderContent').innerHTML = html;
            document.getElementById('orderModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        function formatPrice(price, currency) {
            const symbols = { 'ARS': '$', 'USD': 'U$D' };
            return (symbols[currency] || currency) + ' ' + price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        // Close modal when clicking outside
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
