<?php
/**
 * Order Tracking Page
 * URL: /pedido.php?order={order-id}&token={secure-token}
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme-loader.php';
require_once __DIR__ . '/includes/orders.php';

// Set security headers
set_security_headers();

// Start session
session_start();

// Get order info from URL
$order_id = $_GET['order'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($order_id) || empty($token)) {
    $error = 'Link de seguimiento inválido';
    $order = null;
} else {
    // Get order by token
    $order = get_order_by_token($token);

    if (!$order || $order['id'] !== $order_id) {
        $error = 'Pedido no encontrado';
        $order = null;
    } else {
        $error = null;
    }
}

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Status configuration
$status_config = [
    'pending' => [
        'icon' => '📦',
        'label' => 'Pendiente',
        'color' => '#FFA726',
        'description' => 'Tu pedido ha sido recibido y está pendiente de confirmación'
    ],
    'confirmed' => [
        'icon' => '✅',
        'label' => 'Confirmado',
        'color' => '#4CAF50',
        'description' => 'Tu pedido ha sido confirmado y está siendo preparado'
    ],
    'shipped' => [
        'icon' => '🚚',
        'label' => 'Enviado',
        'color' => '#2196F3',
        'description' => 'Tu pedido está en camino'
    ],
    'delivered' => [
        'icon' => '🏠',
        'label' => 'Entregado',
        'color' => '#4CAF50',
        'description' => '¡Tu pedido ha sido entregado!'
    ],
    'cancelled' => [
        'icon' => '❌',
        'label' => 'Cancelado',
        'color' => '#f44336',
        'description' => 'Este pedido ha sido cancelado'
    ]
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $order ? "Pedido {$order['order_number']}" : 'Seguimiento de Pedido'; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">
    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="/" class="logo"><?php render_site_logo($site_config); ?></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <?php if ($error): ?>
            <!-- Error State -->
            <div class="error-container">
                <div class="error-icon">🔍</div>
                <h2><?php echo htmlspecialchars($error); ?></h2>
                <p style="color: #666; margin-top: 10px;">
                    Por favor verifica el link de seguimiento que recibiste por email.
                </p>
                <a href="/" class="btn btn-primary">Volver al inicio</a>
            </div>

        <?php else: ?>
            <!-- Order Header -->
            <div class="order-header">
                <h1>Pedido #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                <div class="order-date">
                    Realizado el <?php echo date('d/m/Y', strtotime($order['date'])); ?> a las <?php echo date('H:i', strtotime($order['date'])); ?>
                </div>
            </div>

            <!-- Timeline -->
            <div class="timeline">
                <h2>📍 Estado del Pedido</h2>

                <div class="timeline-items">
                    <div class="timeline-line"></div>

                    <?php
                    // Build timeline from status_history
                    $all_steps = ['pending', 'confirmed', 'shipped', 'delivered'];
                    $current_status = $order['status'];
                    $history = $order['status_history'];
                    $last_index = count($history) - 1;

                    // Get unique statuses (keep only the LAST occurrence of each)
                    $unique_statuses = [];
                    foreach ($history as $idx => $item) {
                        if (isset($status_config[$item['status']])) {
                            // Overwrite previous occurrence with the latest one
                            $unique_statuses[$item['status']] = [
                                'status' => $item['status'],
                                'date' => $item['date'],
                                'index' => $idx
                            ];
                        }
                    }

                    // Convert to array and mark current/completed
                    $statuses_to_show = [];
                    foreach ($unique_statuses as $status_data) {
                        $is_last = ($status_data['index'] === $last_index);
                        $statuses_to_show[] = [
                            'status' => $status_data['status'],
                            'date' => $status_data['date'],
                            'is_current' => $is_last,
                            'is_completed' => !$is_last
                        ];
                    }

                    // Add next step if order is active (not cancelled/delivered)
                    if ($current_status !== 'cancelled' && $current_status !== 'delivered') {
                        $current_index = array_search($current_status, $all_steps);
                        if ($current_index !== false && isset($all_steps[$current_index + 1])) {
                            $next_status = $all_steps[$current_index + 1];
                            // Only add if not already in history
                            $already_shown = false;
                            foreach ($statuses_to_show as $shown) {
                                if ($shown['status'] === $next_status) {
                                    $already_shown = true;
                                    break;
                                }
                            }
                            if (!$already_shown && isset($status_config[$next_status])) {
                                $statuses_to_show[] = [
                                    'status' => $next_status,
                                    'date' => null,
                                    'is_current' => false,
                                    'is_completed' => false,
                                    'is_future' => true
                                ];
                            }
                        }
                    }

                    foreach ($statuses_to_show as $item):
                        $status = $item['status'];
                        if (!isset($status_config[$status])) continue;

                        $config = $status_config[$status];
                        $class = '';
                        if ($item['is_completed']) {
                            $class = 'completed';
                        } elseif ($item['is_current']) {
                            $class = 'current';
                        } elseif (isset($item['is_future']) && $item['is_future']) {
                            $class = 'pending';
                        }
                    ?>
                        <div class="timeline-item <?php echo $class; ?>">
                            <div class="timeline-dot"><?php echo $config['icon']; ?></div>
                            <div class="timeline-content">
                                <div class="timeline-title"><?php echo $config['label']; ?></div>
                                <div class="timeline-description"><?php echo $config['description']; ?></div>
                                <?php if ($item['date']): ?>
                                    <div class="timeline-date">
                                        <?php echo date('d/m/Y H:i', strtotime($item['date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cancellation Notice (if cancelled) -->
            <?php if ($order['status'] === 'cancelled'): ?>
            <div style="background: #ffebee; border-left: 4px solid #f44336; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                <h3 style="color: #c62828; margin-bottom: 10px;">
                    ❌ Pedido Cancelado
                </h3>
                <div style="font-size: 16px; color: #555; margin-bottom: 10px;">
                    Este pedido fue cancelado y no será procesado.
                </div>
                <div style="font-size: 14px; color: #777;">
                    <?php
                    // Get cancellation date from history
                    $cancel_date = null;
                    foreach ($order['status_history'] as $history_item) {
                        if ($history_item['status'] === 'cancelled') {
                            $cancel_date = $history_item['date'];
                            break;
                        }
                    }
                    if ($cancel_date): ?>
                        Cancelado el <?php echo date('d/m/Y', strtotime($cancel_date)); ?> a las <?php echo date('H:i', strtotime($cancel_date)); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Payment Status Box (only for active orders) -->
            <?php
            $payment_status_config = [
                'pending' => ['icon' => '⏳', 'label' => 'Pago Pendiente', 'color' => '#FF9800', 'bg' => '#fff3e0'],
                'approved' => ['icon' => '✅', 'label' => 'Pago Aprobado', 'color' => '#4CAF50', 'bg' => '#e8f5e9'],
                'rejected' => ['icon' => '❌', 'label' => 'Pago Rechazado', 'color' => '#f44336', 'bg' => '#ffebee'],
                'cancelled' => ['icon' => '🚫', 'label' => 'Pago Cancelado', 'color' => '#9e9e9e', 'bg' => '#f5f5f5']
            ];
            $payment_status = $order['payment_status'] ?? 'pending';
            $payment_info = $payment_status_config[$payment_status] ?? $payment_status_config['pending'];
            ?>
            <div style="background: <?php echo $payment_info['bg']; ?>; border-left: 4px solid <?php echo $payment_info['color']; ?>; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                <h3 style="color: <?php echo $payment_info['color']; ?>; margin-bottom: 10px;">
                    <?php echo $payment_info['icon']; ?> Estado del Pago
                </h3>
                <div style="font-size: 18px; font-weight: 600; color: <?php echo $payment_info['color']; ?>; margin-bottom: 10px;">
                    <?php echo $payment_info['label']; ?>
                </div>
                <?php if ($payment_status === 'pending' && $order['payment_link']): ?>
                    <a href="<?php echo htmlspecialchars($order['payment_link']); ?>"
                       class="btn btn-primary"
                       style="display: inline-block; margin-top: 10px;">
                        💳 Completar Pago
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tracking Info (if exists and not cancelled) -->
            <?php if ($order['tracking_number'] && $order['status'] !== 'cancelled'): ?>
            <div class="tracking-box">
                <h3>🚚 Número de Seguimiento</h3>
                <div class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                <?php if ($order['tracking_url']): ?>
                    <a href="<?php echo htmlspecialchars($order['tracking_url']); ?>"
                       class="tracking-link" target="_blank" rel="noopener">
                        Hacer seguimiento externo →
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Order Details -->
            <div class="order-details">
                <h2>📋 Detalles del Pedido</h2>

                <div class="detail-row">
                    <span class="detail-label">Método de pago:</span>
                    <span class="detail-value">
                        <?php echo $order['payment_method'] === 'presencial' ? '💵 Pago Presencial' : '💳 Mercadopago'; ?>
                    </span>
                </div>

                <?php if ($order['shipping_address']): ?>
                <div class="detail-row">
                    <span class="detail-label">Dirección de envío:</span>
                    <span class="detail-value">
                        <?php
                        $addr = $order['shipping_address'];
                        echo htmlspecialchars("{$addr['address']}, {$addr['city']}, CP {$addr['postal_code']}");
                        ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Teléfono:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                </div>
            </div>

            <!-- Items -->
            <div class="items-section">
                <h2>📦 Productos</h2>

                <?php foreach ($order['items'] as $item): ?>
                <div class="item">
                    <div class="item-info">
                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="item-quantity">Cantidad: <?php echo $item['quantity']; ?></div>
                    </div>
                    <div class="item-price">
                        <?php echo format_price($item['final_price'], $order['currency']); ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="detail-row" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <span class="detail-label">Subtotal:</span>
                    <span class="detail-value"><?php echo format_price($order['subtotal'], $order['currency']); ?></span>
                </div>

                <?php if ($order['discount_coupon'] > 0): ?>
                <div class="detail-row" style="color: #4CAF50;">
                    <span class="detail-label">Descuento (<?php echo htmlspecialchars($order['coupon_code']); ?>):</span>
                    <span class="detail-value">-<?php echo format_price($order['discount_coupon'], $order['currency']); ?></span>
                </div>
                <?php endif; ?>

                <div class="detail-row" style="font-size: 20px; font-weight: bold;">
                    <span class="detail-label">Total:</span>
                    <span class="detail-value"><?php echo format_price($order['total'], $order['currency']); ?></span>
                </div>
            </div>

            <!-- Contact -->
            <div class="contact-box">
                <h3>💬 ¿Necesitas ayuda?</h3>
                <p>
                    Si tienes alguna consulta sobre tu pedido, no dudes en contactarnos:
                    <br>
                    Email: <?php echo htmlspecialchars($site_config['contact_email'] ?? 'contacto@tienda.com'); ?>
                    <br>
                    Teléfono: <?php echo htmlspecialchars($site_config['contact_phone'] ?? '+54 9 11 1234-5678'); ?>
                </p>
            </div>

            <!-- Back to Home -->
            <div style="margin-top: 30px; text-align: center;">
                <a href="/" class="btn btn-primary" style="display: inline-block; text-decoration: none;">
                    🏠 Volver al Inicio
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
