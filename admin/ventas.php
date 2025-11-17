<?php
/**
 * Admin - Sales/Orders Management
 *
 * Panel principal de gestión de ventas y órdenes.
 * Este archivo es el controlador principal que:
 * - Maneja acciones de actualización/cancelación de órdenes
 * - Aplica filtros y búsqueda
 * - Calcula estadísticas del dashboard
 * - Renderiza las vistas del panel
 *
 * Módulos utilizados:
 * - actions.php: Procesamiento de acciones POST/GET
 * - filters.php: Filtrado y búsqueda de órdenes
 * - stats.php: Cálculo de métricas y estadísticas
 * - views.php: Componentes de vista HTML
 *
 * @package Admin
 * @subpackage Ventas
 */

// ============================================================================
// CONFIGURACIÓN E INICIALIZACIÓN
// ============================================================================

// Define admin access constant for included modules
define('ADMIN_ACCESS', true);

// Core dependencies
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/telegram.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get site configurations
$site_config = read_json(__DIR__ . '/../config/site.json');

// Page title for header
$page_title = 'Gestión de Ventas';

// ============================================================================
// INCLUIR MÓDULOS DE VENTAS
// ============================================================================

require_once __DIR__ . '/includes/ventas/actions.php';  // Manejo de acciones
require_once __DIR__ . '/includes/ventas/filters.php';  // Filtrado de órdenes
require_once __DIR__ . '/includes/ventas/stats.php';    // Estadísticas
require_once __DIR__ . '/includes/ventas/views.php';    // Componentes de vista

// ============================================================================
// PROCESAMIENTO DE DATOS
// ============================================================================

// 1. Handle POST/GET actions (update, cancel, bulk actions)
$action_result = handle_order_actions();
$message = $action_result['message'];
$error = $action_result['error'];

// 2. Get all orders and apply filters
$all_orders = get_all_orders();
$filters = get_filter_params();
$orders = apply_order_filters($all_orders, $filters);

// 3. Calculate dashboard statistics
$stats = calculate_order_stats($all_orders);
extract($stats); // Extract stats variables for backward compatibility

// 4. Generate CSRF token for forms
$csrf_token = generate_csrf_token();

// 5. Get logged user info
$user = get_logged_user();

// 6. Status labels for UI display
$status_labels = [
    'pending' => ['label' => 'Pendiente', 'color' => '#FFA726'],
    'cobrada' => ['label' => 'Cobrada', 'color' => '#4CAF50'],
    'shipped' => ['label' => 'Enviado', 'color' => '#2196F3'],
    'delivered' => ['label' => 'Entregado', 'color' => '#4CAF50'],
    'cancelled' => ['label' => 'Cancelado', 'color' => '#f44336'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#f44336']
];

// ============================================================================
// VISTA HTML
// ============================================================================
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
            <?php render_stats_cards($stats); ?>

            <!-- Advanced Filters -->
            <?php render_advanced_filters($filters); ?>

            <!-- Bulk Actions and Status Filters - Compact Layout -->
            <?php
            render_compact_actions_bar($filters, $csrf_token);
            render_orders_table($orders, $filters, $status_labels);
            ?>
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
