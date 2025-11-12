<?php
/**
 * Admin - Promotions List
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/promotions.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();
require_admin();

$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Gestión de Promociones';
$message = '';
$error = '';

// Delete promotion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $promotion_id = $_GET['id'];
    if (delete_promotion($promotion_id)) {
        $message = 'Promoción eliminada exitosamente';
        log_admin_action('promotion_deleted', $_SESSION['username'], ['promotion_id' => $promotion_id]);
    } else {
        $error = 'Error al eliminar la promoción';
    }
}

// Toggle promotion active status
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $promotion_id = $_GET['id'];
    $promotion = get_promotion_by_id($promotion_id);
    if ($promotion) {
        $new_status = !$promotion['active'];
        if (update_promotion($promotion_id, array_merge($promotion, ['active' => $new_status]))) {
            $message = $new_status ? 'Promoción activada' : 'Promoción desactivada';
            log_admin_action('promotion_toggled', $_SESSION['username'], [
                'promotion_id' => $promotion_id,
                'new_status' => $new_status
            ]);
        }
    }
}

// Get all promotions
$promotions = get_all_promotions();

// Calculate stats
$now = time();
$total_promotions = count($promotions);
$active_promotions = count(array_filter($promotions, fn($p) => $p['active']));
$expired_promotions = count(array_filter($promotions, function($p) use ($now) {
    return $p['period_type'] === 'limited' && strtotime($p['end_date']) < $now;
}));

$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Promociones - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 15px 20px; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; border: none; transition: all 0.3s; }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-primary:hover { background: #45a049; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .card { background: white; border-radius: 8px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 15px; }
        .card-header { font-size: 16px; font-weight: 600; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 15px; }
        .stat-card { background: white; padding: 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .stat-value { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 2px; }
        .stat-label { color: #666; font-size: 12px; }
        .promotions-table { width: 100%; border-collapse: collapse; }
        .promotions-table th, .promotions-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e0e0e0; }
        .promotions-table th { background: #f8f9fa; font-weight: 600; color: #2c3e50; font-size: 13px; }
        .promotions-table td { font-size: 14px; }
        .promotions-table tbody tr:hover { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge.active { background: #d4edda; color: #155724; }
        .badge.inactive { background: #f8d7da; color: #721c24; }
        .badge.percentage { background: #d1ecf1; color: #0c5460; }
        .badge.fixed { background: #fff3cd; color: #856404; }
        .badge.expired { background: #f8d7da; color: #721c24; }
        .badge.valid { background: #d4edda; color: #155724; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }

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

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
            .promotions-table { min-width: 800px; }
        }

        @media (max-width: 768px) {
            .promotions-table {
                font-size: 12px;
                min-width: 700px;
            }
            .promotions-table th,
            .promotions-table td {
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
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
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
            <div class="stat-card"><div class="stat-value"><?php echo $total_promotions; ?></div><div class="stat-label">Total Promociones</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $active_promotions; ?></div><div class="stat-label">Activas</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $expired_promotions; ?></div><div class="stat-label">Expiradas</div></div>
        </div>

        <!-- Promotions List -->
        <div class="card">
            <div class="card-header">Todas las Promociones</div>
            <div class="table-container">
                <table class="promotions-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo / Descuento</th>
                            <th>Aplicación</th>
                            <th>Período</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($promotions)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: #999;">
                                    No hay promociones.
                                    <a href="/admin/promociones-nuevo.php" style="color: #4CAF50;">Crear tu primera promoción</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($promotions as $promo):
                                $is_expired = $promo['period_type'] === 'limited' && strtotime($promo['end_date']) < $now;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($promo['name']); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo $promo['type']; ?>">
                                            <?php echo $promo['type'] === 'percentage' ? '%' : '$'; ?>
                                        </span>
                                        <strong>
                                            <?php echo $promo['type'] === 'percentage'
                                                ? $promo['value'] . '% OFF'
                                                : format_price($promo['value'], 'ARS') . ' OFF'; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $promo['application'] === 'all' ? 'Todo el sitio' : count($promo['products']) . ' productos'; ?></td>
                                    <td>
                                        <?php if ($promo['period_type'] === 'permanent'): ?>
                                            Permanente
                                        <?php else: ?>
                                            <?php echo date('d/m/Y', strtotime($promo['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($promo['end_date'])); ?>
                                            <br>
                                            <?php if ($is_expired): ?>
                                                <span class="badge expired">Expirado</span>
                                            <?php else: ?>
                                                <span class="badge valid">Vigente</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $promo['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $promo['active'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="/admin/promociones-editar.php?id=<?php echo urlencode($promo['id']); ?>" class="btn btn-primary btn-sm">✏️ Editar</a>
                                            <a href="javascript:void(0)" class="btn btn-secondary btn-sm" onclick="confirmTogglePromotion('<?php echo urlencode($promo['id']); ?>', <?php echo $promo['active'] ? 'true' : 'false'; ?>)">
                                                <?php echo $promo['active'] ? '❌ Desactivar' : '✅ Activar'; ?>
                                            </a>
                                            <a href="javascript:void(0)" class="btn btn-danger btn-sm" onclick="confirmDeletePromotion('<?php echo urlencode($promo['id']); ?>', '<?php echo htmlspecialchars($promo['name'], ENT_QUOTES); ?>')">🗑️ Eliminar</a>
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
                <?php if (empty($promotions)): ?>
                    <div class="card">
                        <p style="text-align: center; color: #999; padding: 20px;">
                            No hay promociones.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($promotions as $promo):
                        $is_expired = $promo['period_type'] === 'limited' && strtotime($promo['end_date']) < $now;
                    ?>
                        <div class="mobile-card">
                            <div class="mobile-card-header">
                                <div class="mobile-card-title"><?php echo htmlspecialchars($promo['name']); ?></div>
                            </div>

                            <div class="mobile-card-body">
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Descuento:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $promo['type']; ?>">
                                            <?php echo $promo['type'] === 'percentage' ? '%' : '$'; ?>
                                        </span>
                                        <strong>
                                            <?php echo $promo['type'] === 'percentage'
                                                ? $promo['value'] . '% OFF'
                                                : format_price($promo['value'], 'ARS') . ' OFF'; ?>
                                        </strong>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Aplicación:</span>
                                    <span class="mobile-card-value">
                                        <?php echo $promo['application'] === 'all' ? 'Todo el sitio' : count($promo['products']) . ' productos'; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Período:</span>
                                    <span class="mobile-card-value">
                                        <?php if ($promo['period_type'] === 'permanent'): ?>
                                            Permanente
                                        <?php else: ?>
                                            <?php echo date('d/m/Y', strtotime($promo['start_date'])); ?> -
                                            <?php echo date('d/m/Y', strtotime($promo['end_date'])); ?><br>
                                            <?php if ($is_expired): ?>
                                                <span class="badge expired">Expirado</span>
                                            <?php else: ?>
                                                <span class="badge valid">Vigente</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mobile-card-row">
                                    <span class="mobile-card-label">Estado:</span>
                                    <span class="mobile-card-value">
                                        <span class="badge <?php echo $promo['active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $promo['active'] ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </span>
                                </div>
                            </div>

                            <div class="mobile-card-actions">
                                <a href="/admin/promociones-editar.php?id=<?php echo urlencode($promo['id']); ?>"
                                   class="btn btn-primary btn-sm">Editar</a>
                                <a href="javascript:void(0)"
                                   class="btn btn-secondary btn-sm"
                                   onclick="confirmTogglePromotion('<?php echo urlencode($promo['id']); ?>', <?php echo $promo['active'] ? 'true' : 'false'; ?>)">
                                    <?php echo $promo['active'] ? 'Desactivar' : 'Activar'; ?>
                                </a>
                                <a href="javascript:void(0)"
                                   class="btn btn-danger btn-sm"
                                   onclick="confirmDeletePromotion('<?php echo urlencode($promo['id']); ?>', '<?php echo htmlspecialchars($promo['name'], ENT_QUOTES); ?>')">Eliminar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmTogglePromotion(id, isActive) {
            const action = isActive ? 'desactivar' : 'activar';
            showModal({
                title: isActive ? 'Desactivar Promoción' : 'Activar Promoción',
                message: `¿Estás seguro de que deseas ${action} esta promoción?`,
                icon: isActive ? '❌' : '✅',
                confirmText: isActive ? 'Desactivar' : 'Activar',
                confirmType: 'warning',
                onConfirm: function() {
                    window.location.href = `?action=toggle&id=${id}`;
                }
            });
        }

        function confirmDeletePromotion(id, name) {
            showModal({
                title: 'Eliminar Promoción',
                message: `¿Estás seguro de que deseas eliminar la promoción "${name}"?`,
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
