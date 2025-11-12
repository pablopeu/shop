<?php
/**
 * Admin - Coupons List
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Gestión de Cupones';

// Handle actions
$message = '';
$error = '';

// Delete coupon
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $coupon_id = $_GET['id'];
    $coupons_data = read_json(__DIR__ . '/../data/coupons.json');

    $coupons_data['coupons'] = array_filter($coupons_data['coupons'], function($c) use ($coupon_id) {
        return $c['id'] !== $coupon_id;
    });
    $coupons_data['coupons'] = array_values($coupons_data['coupons']);

    if (write_json(__DIR__ . '/../data/coupons.json', $coupons_data)) {
        $message = 'Cupón eliminado exitosamente';
        log_admin_action('coupon_deleted', $_SESSION['username'], ['coupon_id' => $coupon_id]);
    } else {
        $error = 'Error al eliminar el cupón';
    }
}

// Toggle coupon active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $coupon_id = $_GET['id'];
    $coupons_data = read_json(__DIR__ . '/../data/coupons.json');

    foreach ($coupons_data['coupons'] as &$coupon) {
        if ($coupon['id'] === $coupon_id) {
            $coupon['active'] = !$coupon['active'];
            $new_status = $coupon['active'];
            break;
        }
    }

    if (write_json(__DIR__ . '/../data/coupons.json', $coupons_data)) {
        $message = $new_status ? 'Cupón activado' : 'Cupón desactivado';
        log_admin_action('coupon_toggled', $_SESSION['username'], [
            'coupon_id' => $coupon_id,
            'new_status' => $new_status
        ]);
    }
}

// Get all coupons
$coupons_data = read_json(__DIR__ . '/../data/coupons.json');
$coupons = $coupons_data['coupons'] ?? [];

// Calculate stats
$now = time();
$total_coupons = count($coupons);
$active_coupons = count(array_filter($coupons, fn($c) => $c['active']));
$expired_coupons = count(array_filter($coupons, function($c) use ($now) {
    return strtotime($c['end_date']) < $now;
}));
$total_uses = array_sum(array_column($coupons, 'uses_count'));

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cupones - Admin</title>

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
        .coupons-table {
            width: 100%;
            border-collapse: collapse;
        }

        .coupons-table th,
        .coupons-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .coupons-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }

        .coupons-table td {
            font-size: 14px;
        }

        .coupons-table tbody tr:hover {
            background: #f8f9fa;
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

        .badge.percentage {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge.fixed {
            background: #fff3cd;
            color: #856404;
        }

        .badge.expired {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.valid {
            background: #d4edda;
            color: #155724;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .coupon-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
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
            }
            .coupons-table {
                min-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .coupons-table {
                font-size: 12px;
                min-width: 700px;
            }

            .coupons-table th,
            .coupons-table td {
                padding: 8px 6px;
            }

            .actions {
                flex-direction: column;
            }

            .actions .btn {
                width: 100%;
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
                <div class="stat-value"><?php echo $total_coupons; ?></div>
                <div class="stat-label">Total Cupones</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_coupons; ?></div>
                <div class="stat-label">Activos</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $expired_coupons; ?></div>
                <div class="stat-label">Expirados</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_uses; ?></div>
                <div class="stat-label">Usos Totales</div>
            </div>
        </div>

        <!-- Coupons List -->
        <div class="card">
            <div class="card-header">Todos los Cupones</div>

            <div class="table-container">
                <table class="coupons-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Tipo / Descuento</th>
                            <th>Vigencia</th>
                            <th>Usos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                    No hay cupones.
                                    <a href="/admin/cupones-nuevo.php" style="color: #4CAF50;">Crear tu primer cupón</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon):
                                $is_expired = strtotime($coupon['end_date']) < $now;
                                $is_maxed = $coupon['max_uses'] > 0 && $coupon['uses_count'] >= $coupon['max_uses'];
                            ?>
                                <tr>
                                    <td>
                                        <span class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $coupon['type']; ?>">
                                            <?php echo $coupon['type'] === 'percentage' ? '%' : '$'; ?>
                                        </span>
                                        <strong>
                                            <?php
                                            echo $coupon['type'] === 'percentage'
                                                ? $coupon['value'] . '% OFF'
                                                : format_price($coupon['value'], 'ARS') . ' OFF';
                                            ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($coupon['start_date'])); ?> -
                                        <?php echo date('d/m/Y', strtotime($coupon['end_date'])); ?>
                                        <br>
                                        <?php if ($is_expired): ?>
                                            <span class="badge expired">Expirado</span>
                                        <?php else: ?>
                                            <span class="badge valid">Vigente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $coupon['uses_count']; ?> /
                                        <?php echo $coupon['max_uses'] > 0 ? $coupon['max_uses'] : '∞'; ?>
                                        <?php if ($is_maxed): ?>
                                            <br><span class="badge inactive">Límite alcanzado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $coupon['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $coupon['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="<?php echo url('/admin/cupones-editar.php?id=' . urlencode($coupon['id'])); ?>"
                                               class="btn btn-primary btn-sm">✏️ Editar</a>
                                            <a href="javascript:void(0)"
                                               class="btn btn-secondary btn-sm"
                                               onclick="confirmToggleCoupon('<?php echo urlencode($coupon['id']); ?>', <?php echo $coupon['active'] ? 'true' : 'false'; ?>)">
                                                <?php echo $coupon['active'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                            </a>
                                            <a href="javascript:void(0)"
                                               class="btn btn-danger btn-sm"
                                               onclick="confirmDeleteCoupon('<?php echo urlencode($coupon['id']); ?>', '<?php echo htmlspecialchars($coupon['code'], ENT_QUOTES); ?>')">
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

            <!-- Mobile Cards View -->
            <div class="mobile-cards">
                <?php if (empty($coupons)): ?>
                    <div class="card">
                        <p style="text-align: center; color: #999; padding: 20px;">
                            No hay cupones.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($coupons as $coupon):
                        $is_expired = strtotime($coupon['end_date']) < $now;
                        $is_maxed = $coupon['max_uses'] > 0 && $coupon['uses_count'] >= $coupon['max_uses'];
                    ?>
                        <div class="mobile-card">
                            <div class="mobile-card-header">
                                <div class="mobile-card-title">
                                    <span class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                </div>
                            </div>

                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Descuento:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $coupon['type']; ?>">
                                            <?php echo $coupon['type'] === 'percentage' ? '%' : '$'; ?>
                                        </span>
                                        <strong>
                                            <?php
                                            echo $coupon['type'] === 'percentage'
                                                ? $coupon['value'] . '% OFF'
                                                : format_price($coupon['value'], 'ARS') . ' OFF';
                                            ?>
                                        </strong>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Vigencia:</span>
                                    <span class="mobile-card-value">
                                        <?php echo date('d/m/Y', strtotime($coupon['start_date'])); ?> -
                                        <?php echo date('d/m/Y', strtotime($coupon['end_date'])); ?><br>
                                        <?php if ($is_expired): ?>
                                            <span class="badge expired">Expirado</span>
                                        <?php else: ?>
                                            <span class="badge valid">Vigente</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Usos:</span>
                                    <span class="mobile-card-value">
                                        <?php echo $coupon['uses_count']; ?> /
                                        <?php echo $coupon['max_uses'] > 0 ? $coupon['max_uses'] : '∞'; ?>
                                        <?php if ($is_maxed): ?>
                                            <br><span class="badge inactive">Límite alcanzado</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Estado:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $coupon['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $coupon['active'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="mobile-card-actions">
                                <a href="<?php echo url('/admin/cupones-editar.php?id=' . urlencode($coupon['id'])); ?>"
                                   class="btn btn-primary btn-sm">Editar</a>
                                <a href="javascript:void(0)"
                                   class="btn btn-secondary btn-sm"
                                   onclick="confirmToggleCoupon('<?php echo urlencode($coupon['id']); ?>', <?php echo $coupon['active'] ? 'true' : 'false'; ?>)">
                                    <?php echo $coupon['active'] ? 'Desactivar' : 'Activar'; ?>
                                </a>
                                <a href="javascript:void(0)"
                                   class="btn btn-danger btn-sm"
                                   onclick="confirmDeleteCoupon('<?php echo urlencode($coupon['id']); ?>', '<?php echo htmlspecialchars($coupon['code'], ENT_QUOTES); ?>')">
                                    Eliminar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmToggleCoupon(id, isActive) {
            const action = isActive ? 'desactivar' : 'activar';
            showModal({
                title: isActive ? 'Desactivar Cupón' : 'Activar Cupón',
                message: `¿Estás seguro de que deseas ${action} este cupón?`,
                icon: isActive ? '❌' : '✅',
                confirmText: isActive ? 'Desactivar' : 'Activar',
                confirmType: 'warning',
                onConfirm: function() {
                    window.location.href = `?action=toggle&id=${id}`;
                }
            });
        }

        function confirmDeleteCoupon(id, code) {
            showModal({
                title: 'Eliminar Cupón',
                message: `¿Estás seguro de que deseas eliminar el cupón "${code}"?`,
                details: 'Esta acción no se puede deshacer.',
                icon: '🗑️',
                confirmText: 'Eliminar',
                confirmType: 'danger',
                onConfirm: function() {
                    window.location.href = `?action=delete&id=${id}`;
                }
            });
        }
    </script>

    <!-- Modal Component -->
    <?php include __DIR__ . '/includes/modal.php'; ?>
</body>
</html>
