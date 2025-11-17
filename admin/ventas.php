<?php
/**
 * Admin - Sales/Orders Management
 */

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

// Handle actions
$message = '';
$error = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';

    if (update_order_status($order_id, $new_status, $_SESSION['username'])) {
        $message = 'Estado actualizado exitosamente';

        // Send notification when order is marked as shipped
        if ($new_status === 'shipped') {
            $updated_order = get_order_by_id($order_id);
            if ($updated_order && !empty($updated_order['customer_email'])) {
                send_order_shipped_email($updated_order);
            }
        }

        // Send notification when order is marked as cobrada (paid)
        if ($new_status === 'cobrada') {
            $updated_order = get_order_by_id($order_id);
            if ($updated_order) {
                // Send notification based on customer's contact preference
                $contact_preference = $updated_order['contact_preference'] ?? 'email';

                error_log("Order {$order_id} marked as cobrada. Contact preference: {$contact_preference}");

                if ($contact_preference === 'telegram' && !empty($updated_order['telegram_chat_id'])) {
                    // Send via Telegram
                    error_log("Sending Telegram notification to chat_id: {$updated_order['telegram_chat_id']}");
                    $telegram_result = send_telegram_order_paid_to_customer($updated_order);
                    error_log("Telegram notification result: " . ($telegram_result ? 'SUCCESS' : 'FAILED'));
                } elseif (!empty($updated_order['customer_email'])) {
                    // Send via Email (default)
                    error_log("Sending email notification to: {$updated_order['customer_email']}");
                    $email_result = send_order_paid_email($updated_order);
                    error_log("Email notification result: " . ($email_result ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log("No valid contact method found for order {$order_id}");
                }
            } else {
                error_log("Could not retrieve updated order {$order_id}");
            }
        }
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
            } elseif ($action === 'cancel') {
                if (cancel_order($order_id, 'Cancelado en masa por admin', $_SESSION['username'])) {
                    $success_count++;
                }
            } elseif (in_array($action, ['pending', 'cobrada', 'shipped', 'delivered'])) {
                if (update_order_status($order_id, $action, $_SESSION['username'])) {
                    $success_count++;

                    // Send email notification when order is marked as shipped
                    if ($action === 'shipped') {
                        $updated_order = get_order_by_id($order_id);
                        if ($updated_order && !empty($updated_order['customer_email'])) {
                            send_order_shipped_email($updated_order);
                        }
                    }
                }
            }
        }

        $message = "$success_count orden(es) procesada(s) exitosamente";
    } else {
        $error = 'No se seleccionaron órdenes';
    }
}

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
            padding: 0;
            max-width: 800px;
            width: 90%;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            font-size: 16px;
            font-weight: 600;
            padding: 20px 20px 0 20px;
            flex-shrink: 0;
        }

        .modal-close {
            float: right;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0 20px;
            min-height: 400px;
        }

        .modal-footer {
            flex-shrink: 0;
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 2px solid #e0e0e0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-save {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-save.has-changes {
            background: #dc3545;
            animation: pulse 1.5s ease-in-out infinite;
        }

        .btn-save:hover {
            opacity: 0.9;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
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

        /* Modal Tabs */
        .modal-tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e0e0e0;
            background: #f8f9fa;
            flex-shrink: 0;
        }

        .modal-tab {
            flex: 1;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            background: #f8f9fa;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
            position: relative;
        }

        .modal-tab:first-child {
            border-radius: 8px 0 0 0;
        }

        .modal-tab:last-child {
            border-radius: 0 8px 0 0;
        }

        .modal-tab:hover {
            background: #e9ecef;
            color: #333;
        }

        .modal-tab.active {
            background: white;
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .modal-tab-content {
            display: none;
        }

        .modal-tab-content.active {
            display: block;
        }

        /* Message History */
        .message-history {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            background: #f8f9fa;
        }

        .message-item {
            background: white;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
        }

        .message-item:last-child {
            margin-bottom: 0;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            color: #666;
        }

        .message-meta {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .message-channel {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            color: white;
        }

        .message-channel.email {
            background: #4CAF50;
        }

        .message-channel.telegram {
            background: #2196F3;
        }

        .message-body {
            color: #333;
            line-height: 1.5;
            white-space: pre-wrap;
        }

        .no-messages {
            text-align: center;
            color: #999;
            padding: 30px;
            font-style: italic;
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

            .orders-table {
                min-width: 1000px;
            }

            .filters-row {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }

            .orders-table {
                font-size: 12px;
                min-width: 900px;
            }

            .orders-table th,
            .orders-table td {
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

        /* Toast animations */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
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

    <script>
        const orders = <?php echo json_encode($orders); ?>;
        const csrfToken = '<?php echo $csrf_token; ?>';

        function viewOrder(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;

            document.getElementById('modalOrderNumber').textContent = 'Orden ' + order.order_number;

            let html = `
                <div class="modal-tabs">
                    <button class="modal-tab active" onclick="switchTab('tab-details')">📋 Detalles</button>
                    <button class="modal-tab" onclick="switchTab('tab-payments')">💳 Pagos</button>
                    <button class="modal-tab" onclick="switchTab('tab-status')">📦 Estado & Tracking</button>
                    <button class="modal-tab" onclick="switchTab('tab-communication')">💬 Comunicación</button>
                </div>

                <!-- TAB 1: Detalles -->
                <div id="tab-details" class="modal-tab-content active">
                    <div class="form-group">
                        <label><strong>Cliente:</strong></label>
                        <p>${order.customer_name || 'N/A'}<br>
                           ${order.customer_email || ''}<br>
                           ${order.customer_phone || ''}</p>
                    </div>

                    <div class="form-group">
                        <label><strong>Preferencia de Contacto:</strong></label>
                        <p>${order.contact_preference === 'telegram' ? '📱 Telegram' : '📧 Email'}</p>
                    </div>

                    ${order.shipping_address ? `
                    <div class="form-group">
                        <label><strong>Dirección de Envío:</strong></label>
                        <p>${order.shipping_address.address}<br>
                           ${order.shipping_address.city}, CP ${order.shipping_address.postal_code}</p>
                    </div>
                    ` : ''}

                    ${order.notes && order.notes.trim() ? `
                    <div class="form-group" style="background: #fff9e6; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107;">
                        <label><strong>💬 Mensaje del Cliente:</strong></label>
                        <p style="margin-top: 10px; white-space: pre-wrap;">${order.notes}</p>
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
                            <div class="order-item" style="margin-top: 10px; padding-top: 10px;">
                                <span><strong>Subtotal:</strong></span>
                                <strong>${formatPrice(order.total, order.currency)}</strong>
                            </div>
                            ${order.mercadopago_data && order.mercadopago_data.total_fees ? `
                            <div class="order-item" style="color: #dc3545;">
                                <span>Comisión MercadoPago:</span>
                                <strong>- ${formatPrice(order.mercadopago_data.total_fees, order.currency)}</strong>
                            </div>
                            <div class="order-item" style="border-top: 2px solid #4CAF50; margin-top: 5px; padding-top: 10px; background: #f0f9f0;">
                                <span><strong>Neto Recibido:</strong></span>
                                <strong style="color: #4CAF50; font-size: 16px;">${formatPrice(order.mercadopago_data.net_received_amount || order.total, order.currency)}</strong>
                            </div>
                            ` : `
                            <div class="order-item" style="border-top: 2px solid #ccc; margin-top: 5px; padding-top: 10px;">
                                <span><strong>Total:</strong></span>
                                <strong>${formatPrice(order.total, order.currency)}</strong>
                            </div>
                            `}
                        </div>
                    </div>
                </div>

                <!-- TAB 2: Pagos -->
                <div id="tab-payments" class="modal-tab-content">
                    <div class="form-group">
                        <label><strong>Método de Pago:</strong></label>
                        <p>${
                            order.payment_method === 'mercadopago' ? '💳 Mercadopago' :
                            order.payment_method === 'arrangement' ? '🤝 Arreglo con vendedor' :
                            order.payment_method === 'pickup_payment' ? '💵 Pago al retirar' :
                            '💵 Presencial'
                        }</p>
                    </div>

                    ${order.mercadopago_data ? `
                    <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea;">
                        <label><strong>📊 Detalles de Mercadopago:</strong></label>
                        <div style="margin-top: 10px; font-size: 13px;">
                            ${order.mercadopago_data.payment_id ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Payment ID:</span>
                                <span style="font-family: monospace;">${order.mercadopago_data.payment_id}</span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.status ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Estado:</span>
                                <span>
                                    <span style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; color: white;
                                          background: ${order.mercadopago_data.status === 'approved' ? '#4CAF50' :
                                                         order.mercadopago_data.status === 'pending' || order.mercadopago_data.status === 'in_process' ? '#FFA726' : '#f44336'};">
                                        ${order.mercadopago_data.status.toUpperCase()}
                                    </span>
                                    ${order.mercadopago_data.status_detail ? `<span style="color: #999; font-size: 11px; margin-left: 8px;">(${order.mercadopago_data.status_detail})</span>` : ''}
                                </span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.transaction_amount ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Monto:</span>
                                <span><strong>${order.mercadopago_data.currency_id || 'ARS'} $${parseFloat(order.mercadopago_data.transaction_amount).toFixed(2)}</strong></span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.payment_method_id ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Método:</span>
                                <span>${order.mercadopago_data.payment_type_id || 'N/A'} - ${order.mercadopago_data.payment_method_id}</span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.installments && order.mercadopago_data.installments > 1 ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Cuotas:</span>
                                <span>${order.mercadopago_data.installments}x</span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.card_last_four_digits ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Tarjeta:</span>
                                <span>**** **** **** ${order.mercadopago_data.card_last_four_digits}</span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.date_created ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Fecha creación:</span>
                                <span>${new Date(order.mercadopago_data.date_created).toLocaleString('es-AR')}</span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.date_approved ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Fecha aprobación:</span>
                                <span style="color: #4CAF50; font-weight: 600;">${new Date(order.mercadopago_data.date_approved).toLocaleString('es-AR')}</span>
                            </div>
                            ` : ''}
                            ${order.mercadopago_data.payer_email ? `
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                                <span style="color: #666; font-weight: 600;">Email pagador:</span>
                                <span>${order.mercadopago_data.payer_email}</span>
                            </div>
                            ` : ''}
                        </div>
                        ${order.mercadopago_data.payment_id ? `
                        <a href="verificar-pago-mp.php?payment_id=${order.mercadopago_data.payment_id}"
                           target="_blank"
                           style="display: inline-block; margin-top: 12px; padding: 6px 12px; background: #667eea; color: white;
                                  text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: 600;">
                            🔍 Ver detalles completos en MP
                        </a>
                        ` : ''}
                    </div>
                    ` : ''}

                    ${order.payment_error ? `
                    <div class="form-group" style="background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #ff9800;">
                        <label><strong>⚠️ Error de Pago:</strong></label>
                        <div style="margin-top: 10px; font-size: 13px;">
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Mensaje:</span>
                                <span style="color: #d32f2f; font-family: monospace; font-size: 12px;">${order.payment_error.message}</span>
                            </div>
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                                <span style="color: #666; font-weight: 600;">Fecha del error:</span>
                                <span>${new Date(order.payment_error.date).toLocaleString('es-AR')}</span>
                            </div>
                            <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                                <span style="color: #666; font-weight: 600;">Modo:</span>
                                <span>${order.payment_error.sandbox_mode ? 'Sandbox (prueba)' : 'Producción'}</span>
                            </div>
                        </div>
                        <small style="display: block; margin-top: 10px; color: #856404;">
                            💡 Este error indica un problema técnico al procesar el pago (error de API, problemas de conexión, etc.)
                        </small>
                    </div>
                    ` : ''}

                    ${order.chargebacks && order.chargebacks.length > 0 ? `
                    <div class="form-group" style="background: #ffebee; padding: 15px; border-radius: 6px; border-left: 4px solid #f44336;">
                        <label><strong>🚨 Contracargos (Chargebacks):</strong></label>
                        <div style="margin-top: 10px;">
                            ${order.chargebacks.map((cb, index) => `
                                <div style="background: white; padding: 12px; border-radius: 4px; margin-bottom: ${index < order.chargebacks.length - 1 ? '10px' : '0'}; border: 1px solid #ffcdd2;">
                                    <div style="font-size: 13px;">
                                        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                            <span style="color: #666; font-weight: 600;">Chargeback ID:</span>
                                            <span style="font-family: monospace; font-size: 12px;">${cb.chargeback_id}</span>
                                        </div>
                                        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                            <span style="color: #666; font-weight: 600;">Acción:</span>
                                            <span>
                                                <span style="padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; color: white;
                                                      background: ${cb.action === 'created' ? '#ff9800' : cb.action === 'lost' ? '#f44336' : cb.action === 'won' ? '#4CAF50' : '#999'};">
                                                    ${cb.action ? cb.action.toUpperCase() : 'UNKNOWN'}
                                                </span>
                                            </span>
                                        </div>
                                        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f5f5f5;">
                                            <span style="color: #666; font-weight: 600;">Payment ID:</span>
                                            <span style="font-family: monospace; font-size: 12px;">${cb.payment_id}</span>
                                        </div>
                                        <div style="display: grid; grid-template-columns: 150px 1fr; gap: 8px; padding: 6px 0;">
                                            <span style="color: #666; font-weight: 600;">Fecha:</span>
                                            <span>${new Date(cb.date).toLocaleString('es-AR')}</span>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <small style="display: block; margin-top: 12px; color: #c62828; font-weight: 600;">
                            ⚠️ Un contracargo indica que el comprador disputó el pago con su banco.
                            ${order.chargebacks.some(cb => cb.action === 'created' || cb.action === 'lost') ? 'El stock fue restaurado automáticamente.' : ''}
                        </small>
                    </div>
                    ` : ''}

                    ${order.payment_link ? `
                    <div class="form-group" style="background: #e3f2fd; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                        <label><strong>🔗 Link de Pago de Mercadopago:</strong></label>
                        <div style="display: flex; gap: 10px; align-items: center; margin-top: 8px;">
                            <input type="text" value="${order.payment_link}" readonly
                                   style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; font-family: monospace; background: white;">
                            <button type="button" onclick="copyPaymentLink('${order.payment_link}')"
                                    style="padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                                📋 Copiar
                            </button>
                        </div>
                        <small style="color: #666; display: block; margin-top: 8px;">
                            ${order.payment_status === 'approved' ? '✅ Pago aprobado' :
                              order.payment_status === 'pending' ? '⏳ Pago pendiente' :
                              order.payment_status === 'rejected' ? '❌ Pago rechazado' :
                              '📝 Esperando pago'}
                        </small>
                    </div>
                    ` : ''}

                    <!-- Historial de Cambios de Estado -->
                    <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea;">
                        <label><strong>📋 Historial de Cambios de Estado:</strong></label>
                        <div style="margin-top: 15px;">
                            ${order.status_history && order.status_history.length > 0 ?
                                order.status_history.map((change, index) => `
                                    <div style="background: white; padding: 12px; margin-bottom: ${index < order.status_history.length - 1 ? '10px' : '0'}; border-radius: 4px; border-left: 3px solid ${
                                        change.status === 'pending' ? '#FFA726' :
                                        change.status === 'cobrada' ? '#4CAF50' :
                                        change.status === 'shipped' ? '#2196F3' :
                                        change.status === 'delivered' ? '#4CAF50' :
                                        change.status === 'cancelled' || change.status === 'rechazada' ? '#f44336' : '#999'
                                    };">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                            <span style="font-weight: 600; color: #333;">
                                                ${change.status === 'pending' ? '⏳ Pendiente' :
                                                  change.status === 'cobrada' ? '💰 Cobrada' :
                                                  change.status === 'shipped' ? '🚚 Enviado' :
                                                  change.status === 'delivered' ? '📦 Entregado' :
                                                  change.status === 'cancelled' ? '❌ Cancelado' :
                                                  change.status === 'rechazada' ? '⛔ Rechazada' :
                                                  change.status}
                                            </span>
                                            <span style="font-size: 12px; color: #666;">
                                                ${new Date(change.date).toLocaleString('es-AR')}
                                            </span>
                                        </div>
                                        ${change.user ? `
                                        <div style="font-size: 12px; color: #999;">
                                            👤 Por: ${change.user}
                                        </div>
                                        ` : ''}
                                    </div>
                                `).join('') :
                                '<div style="text-align: center; color: #999; padding: 20px; font-style: italic;">No hay cambios de estado registrados</div>'
                            }
                        </div>
                    </div>
                </div>

                <!-- TAB 3: Estado & Tracking -->
                <div id="tab-status" class="modal-tab-content">
                    <!-- Current Status Badge -->
                    <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid ${
                        order.status === 'pending' ? '#FFA726' :
                        order.status === 'cobrada' ? '#4CAF50' :
                        order.status === 'shipped' ? '#2196F3' :
                        order.status === 'delivered' ? '#4CAF50' :
                        order.status === 'cancelled' || order.status === 'rechazada' ? '#f44336' : '#999'
                    };">
                        <label style="margin-bottom: 10px; display: block;"><strong>📊 Estado Actual:</strong></label>
                        <div style="display: inline-block;">
                            <span style="padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; color: white; background: ${
                                order.status === 'pending' ? '#FFA726' :
                                order.status === 'cobrada' ? '#4CAF50' :
                                order.status === 'shipped' ? '#2196F3' :
                                order.status === 'delivered' ? '#4CAF50' :
                                order.status === 'cancelled' || order.status === 'rechazada' ? '#f44336' : '#999'
                            };">
                                ${order.status === 'pending' ? '⏳ Pendiente' :
                                  order.status === 'cobrada' ? '💰 Cobrada' :
                                  order.status === 'shipped' ? '🚚 Enviado' :
                                  order.status === 'delivered' ? '📦 Entregado' :
                                  order.status === 'cancelled' ? '❌ Cancelado' :
                                  order.status === 'rechazada' ? '⛔ Rechazada' :
                                  order.status.toUpperCase()}
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="" id="formStatus" onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="order_id" value="${order.id}">
                        <input type="hidden" name="update_status" value="1">

                        <div class="form-group">
                            <label for="status"><strong>Cambiar Estado:</strong></label>
                            <select name="status" id="status" style="font-weight: 600;">
                                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>⏳ Pendiente</option>
                                <option value="cobrada" ${order.status === 'cobrada' ? 'selected' : ''}>💰 Cobrada</option>
                                <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>🚚 Enviado</option>
                                <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>📦 Entregado</option>
                                <option value="rechazada" ${order.status === 'rechazada' ? 'selected' : ''}>⛔ Rechazada</option>
                            </select>
                        </div>
                    </form>

                    <hr style="margin: 20px 0;">

                    <form method="POST" action="" id="formTracking" onsubmit="return false;">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="order_id" value="${order.id}">
                        <input type="hidden" name="add_tracking" value="1">

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
                    </form>
                </div>

                <!-- TAB 4: Comunicación -->
                <div id="tab-communication" class="modal-tab-content">
                    <form onsubmit="sendCustomMessage(event, '${order.id}')">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="order_id" value="${order.id}">

                        <div class="form-group">
                            <label><strong>Enviar Mensaje al Cliente:</strong></label>
                            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                                Medio elegido: <strong>${order.contact_preference === 'telegram' ? '📱 Telegram' : '📧 Email'}</strong>
                                ${order.contact_preference === 'telegram' && !order.telegram_chat_id ? '<br><span style="color: #dc3545;">⚠️ No hay chat_id de Telegram registrado</span>' : ''}
                            </p>
                            <textarea name="custom_message" id="custom_message"
                                      rows="4"
                                      placeholder="Escribe tu mensaje personalizado aquí..."
                                      required
                                      style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; font-family: inherit;"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            📤 Enviar Mensaje
                        </button>
                    </form>

                    <hr style="margin: 30px 0;">

                    <div class="form-group">
                        <label><strong>📋 Historial de Comunicación:</strong></label>
                        <div class="message-history">
                            ${order.messages && order.messages.length > 0 ?
                                order.messages.map(msg => `
                                    <div class="message-item">
                                        <div class="message-header">
                                            <div class="message-meta">
                                                <span class="message-channel ${msg.channel}">${msg.channel === 'email' ? '📧 Email' : '📱 Telegram'}</span>
                                                <span>${new Date(msg.date).toLocaleString('es-AR')}</span>
                                            </div>
                                        </div>
                                        <div class="message-body">${msg.message}</div>
                                    </div>
                                `).join('') :
                                '<div class="no-messages">No hay mensajes enviados aún</div>'
                            }
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('modalOrderContent').innerHTML = html;
            document.getElementById('orderModal').classList.add('active');

            // Setup unsaved changes detection for modal forms
            setupModalChangeDetection();
        }

        function switchTab(tabId) {
            document.querySelectorAll('.modal-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.modal-tab-content').forEach(content => content.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        function sendCustomMessage(event, orderId) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const message = formData.get('custom_message');

            if (!message || message.trim() === '') {
                showNotification('Por favor escribe un mensaje', 'warning');
                return;
            }

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Enviando...';

            // Debug: Log FormData contents
            console.log('=== SENDING MESSAGE ===');
            console.log('Order ID:', formData.get('order_id'));
            console.log('CSRF Token:', formData.get('csrf_token'));
            console.log('Message:', formData.get('custom_message'));
            console.log('=======================');

            // Send message via AJAX
            fetch('api/send-custom-message.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text().then(text => {
                    console.log('Response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.success) {
                    showNotification('Mensaje enviado exitosamente', 'success');

                    // Update local orders array with new message
                    const orderIndex = orders.findIndex(o => o.id === orderId);
                    if (orderIndex !== -1) {
                        const message = formData.get('custom_message');
                        const newMessage = {
                            date: new Date().toISOString().replace('T', ' ').substring(0, 19),
                            channel: data.channel,
                            message: message,
                            sent_by: '<?php echo $_SESSION['username'] ?? 'admin'; ?>'
                        };

                        // Initialize messages array if it doesn't exist
                        if (!orders[orderIndex].messages) {
                            orders[orderIndex].messages = [];
                        }

                        // Add new message to the beginning
                        orders[orderIndex].messages.unshift(newMessage);

                        console.log('Updated order with new message:', newMessage);
                    }

                    form.reset();
                    // Reload order to show new message in history
                    viewOrder(orderId);
                } else {
                    showNotification('Error: ' + (data.message || 'No se pudo enviar el mensaje'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error: ' + error.message, 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        }

        function saveAllChanges() {
            const btnSave = document.getElementById('btnSaveChanges');

            // Get current tab to know which form to submit
            const activeTab = document.querySelector('.modal-tab-content.active');

            if (!activeTab) {
                // No active tab, just close
                closeOrderModal();
                return;
            }

            // Find forms in the active tab
            const forms = activeTab.querySelectorAll('form');

            if (forms.length === 0) {
                // No forms in this tab, just close
                closeOrderModal();
                return;
            }

            // Check if forms have changed
            if (!modalHasUnsavedChanges) {
                // No changes to save, just close
                closeOrderModal();
                return;
            }

            // Submit the first form found (should be the only one per tab that needs backend submission)
            const form = forms[0];

            // Check if this is the sendCustomMessage form (skip it)
            if (form.getAttribute('onsubmit') && form.getAttribute('onsubmit').includes('sendCustomMessage')) {
                showToast('ℹ️ Usa el botón "Enviar Mensaje" para este formulario');
                return;
            }

            // Disable button to prevent double submission
            btnSave.disabled = true;
            btnSave.textContent = '⏳ Guardando...';

            // Remove the onsubmit="return false;" temporarily to allow real submission
            form.removeAttribute('onsubmit');

            // Submit the form
            form.submit();
        }

        function showToast(message) {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #333;
                color: white;
                padding: 15px 20px;
                border-radius: 6px;
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Track unsaved changes in modal
        let modalHasUnsavedChanges = false;
        let modalOriginalValues = {};
        let modalUserHasInteracted = false; // Only set to true after user touches an input

        function setupModalChangeDetection() {
            const modalContent = document.getElementById('modalOrderContent');
            const forms = modalContent.querySelectorAll('form');
            const inputs = modalContent.querySelectorAll('input, select, textarea');
            const globalSaveButton = document.getElementById('btnSaveChanges');

            // Store original values (skip inputs without name or id)
            modalOriginalValues = {};
            inputs.forEach(input => {
                const key = input.name || input.id;
                if (key) {
                    modalOriginalValues[key] = input.type === 'checkbox' ? input.checked : input.value;
                }
            });

            // Reset state
            modalHasUnsavedChanges = false;
            modalUserHasInteracted = false; // Reset interaction flag

            // Reset global save button style
            if (globalSaveButton) {
                globalSaveButton.classList.remove('has-changes');
            }

            // Detect changes - only after a small delay to avoid false positives from browser autocomplete
            setTimeout(() => {
                inputs.forEach(input => {
                    input.addEventListener('input', () => {
                        modalUserHasInteracted = true;
                        checkModalChanges(inputs, globalSaveButton);
                    });
                    input.addEventListener('change', () => {
                        modalUserHasInteracted = true;
                        checkModalChanges(inputs, globalSaveButton);
                    });
                });
            }, 100);

            // Mark as saved when form is submitted
            forms.forEach(form => {
                form.addEventListener('submit', () => {
                    modalHasUnsavedChanges = false;
                    modalUserHasInteracted = false;
                });
            });
        }

        function checkModalChanges(inputs, globalSaveButton) {
            let hasChanges = false;
            inputs.forEach(input => {
                const key = input.name || input.id;
                if (!key) return; // Skip inputs without name or id

                const currentValue = input.type === 'checkbox' ? input.checked : input.value;
                const originalValue = modalOriginalValues[key];

                // Only compare if we have an original value
                if (originalValue !== undefined && currentValue !== originalValue) {
                    hasChanges = true;
                }
            });

            modalHasUnsavedChanges = hasChanges;

            // Update global button class
            if (globalSaveButton) {
                if (hasChanges) {
                    globalSaveButton.classList.add('has-changes');
                } else {
                    globalSaveButton.classList.remove('has-changes');
                }
            }
        }

        function closeOrderModal() {
            // Only show warning if user actually interacted with the form AND there are changes
            if (modalUserHasInteracted && modalHasUnsavedChanges) {
                // Show custom unsaved changes modal
                document.getElementById('unsavedChangesModal').classList.add('active');
            } else {
                // Close directly - no interaction or no changes
                document.getElementById('orderModal').classList.remove('active');
                modalHasUnsavedChanges = false;
                modalUserHasInteracted = false;
            }
        }

        function confirmCloseOrderModal() {
            // User confirmed to leave without saving
            modalHasUnsavedChanges = false;
            modalUserHasInteracted = false;
            document.getElementById('unsavedChangesModal').classList.remove('active');
            document.getElementById('orderModal').classList.remove('active');
        }

        function cancelCloseOrderModal() {
            // User wants to stay and save
            document.getElementById('unsavedChangesModal').classList.remove('active');

            // Focus on the first save button in the modal
            const modalContent = document.getElementById('modalOrderContent');
            const saveButton = modalContent.querySelector('button[type="submit"]');

            if (saveButton) {
                // Scroll to the button
                saveButton.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Wait for scroll to finish, then focus and add highlight
                setTimeout(() => {
                    const originalTransform = saveButton.style.transform || '';
                    const originalBoxShadow = saveButton.style.boxShadow || '';

                    saveButton.focus();
                    saveButton.style.transform = 'scale(1.05)';
                    saveButton.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.4)';

                    // Remove highlight after 1 second
                    setTimeout(() => {
                        saveButton.style.transform = originalTransform;
                        saveButton.style.boxShadow = originalBoxShadow;
                    }, 1000);
                }, 500);
            }
        }

        function showCancelModal(orderId, orderNumber) {
            document.getElementById('cancelOrderNumber').textContent = orderNumber;
            document.getElementById('confirmCancelBtn').href = '?action=cancel&id=' + encodeURIComponent(orderId);
            document.getElementById('cancelModal').classList.add('active');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }

        function copyPaymentLink(link) {
            navigator.clipboard.writeText(link).then(() => {
                showModal({
                    title: 'Link Copiado',
                    message: 'El link de pago se ha copiado al portapapeles exitosamente.',
                    icon: '✅',
                    iconClass: 'success',
                    confirmText: 'Entendido',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
            }).catch(() => {
                showModal({
                    title: 'Copiar Link de Pago',
                    message: 'No se pudo copiar automáticamente. Por favor, copia manualmente el siguiente link:',
                    details: link,
                    icon: '📋',
                    iconClass: 'info',
                    confirmText: 'Cerrar',
                    confirmType: 'primary',
                    cancelText: null,
                    onConfirm: function() {}
                });
            });
        }

        function formatPrice(price, currency) {
            const symbols = { 'ARS': '$', 'USD': 'U$D' };
            return (symbols[currency] || currency) + ' ' + price.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        // Close modal when clicking outside
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });

        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });

        document.getElementById('unsavedChangesModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cancelCloseOrderModal();
            }
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                // Check which modal is open and close accordingly
                const unsavedModal = document.getElementById('unsavedChangesModal');
                const orderModal = document.getElementById('orderModal');
                const cancelModal = document.getElementById('cancelModal');
                const confirmBulkModal = document.getElementById('confirmBulkModal');

                if (unsavedModal.classList.contains('active')) {
                    // Close unsaved changes modal (same as clicking X - stays in edit mode)
                    cancelCloseOrderModal();
                } else if (orderModal.classList.contains('active')) {
                    // Close order detail modal (checks for unsaved changes)
                    closeOrderModal();
                } else if (cancelModal.classList.contains('active')) {
                    // Close cancel order modal
                    closeCancelModal();
                } else if (confirmBulkModal.classList.contains('active')) {
                    // Close bulk action confirmation modal
                    closeConfirmModal();
                }
            }
        });

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
                'pending': {
                    icon: '⏳',
                    iconClass: 'warning',
                    title: 'Marcar como Pendiente',
                    description: 'Las siguientes órdenes cambiarán su estado a "Pendiente":',
                    effects: ['El estado de las órdenes será actualizado', 'No se realizarán cambios en el stock'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Marcar como Pendiente'
                },
                'cobrada': {
                    icon: '💰',
                    iconClass: 'warning',
                    title: 'Marcar como Cobrada',
                    description: 'Las siguientes órdenes cambiarán su estado a "Cobrada":',
                    effects: ['El estado de las órdenes será actualizado', 'Se reducirá el stock si aún no se ha hecho', 'Se considerarán cobradas para reportes'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Marcar como Cobradas'
                },
                'shipped': {
                    icon: '🚚',
                    iconClass: 'warning',
                    title: 'Marcar como Enviada',
                    description: 'Las siguientes órdenes cambiarán su estado a "Enviada":',
                    effects: ['El estado de las órdenes será actualizado', 'Se marcarán como en tránsito'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Marcar como Enviadas'
                },
                'delivered': {
                    icon: '📦',
                    iconClass: 'warning',
                    title: 'Marcar como Entregada',
                    description: 'Las siguientes órdenes cambiarán su estado a "Entregada":',
                    effects: ['El estado de las órdenes será actualizado', 'Se marcarán como completadas'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Marcar como Entregadas'
                },
                'cancel': {
                    icon: '❌',
                    iconClass: 'danger',
                    title: 'Cancelar Órdenes',
                    description: 'Esta acción cancelará las órdenes seleccionadas:',
                    effects: ['Las órdenes serán marcadas como "Canceladas"', '⚠️ El stock de los productos será RESTAURADO', 'Esta acción no se puede deshacer fácilmente'],
                    btnClass: 'modal-btn-danger',
                    btnText: 'Cancelar Órdenes'
                },
                'archive': {
                    icon: '📁',
                    iconClass: 'warning',
                    title: 'Archivar Órdenes',
                    description: 'Las órdenes seleccionadas serán movidas al archivo:',
                    effects: ['Las órdenes NO aparecerán en el listado principal', 'Podrán ser restauradas desde el Archivo de Ventas', 'No se realizarán cambios en el stock'],
                    btnClass: 'modal-btn-confirm',
                    btnText: 'Archivar Órdenes'
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
                <p style="margin: 10px 0; font-size: 13px; color: #666;">Esta acción afectará a:</p>
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
            // Simple notification - could be enhanced with a toast library
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

        // Close modal when clicking outside
        document.getElementById('confirmBulkModal').addEventListener('click', function(e) {
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
