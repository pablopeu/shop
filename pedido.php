<?php
/**
 * Order Tracking Page
 * URL: /pedido.php?order={order-id}&token={secure-token}
 */

require_once __DIR__ . '/includes/functions.php';
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

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px 0;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        /* Container */
        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Error State */
        .error-container {
            background: white;
            border-radius: 12px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .error-icon {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .error-container h2 {
            color: #f44336;
            margin-bottom: 15px;
        }

        /* Order Header */
        .order-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .order-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .order-date {
            color: #666;
            font-size: 14px;
        }

        /* Timeline */
        .timeline {
            background: white;
            border-radius: 12px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .timeline h2 {
            font-size: 20px;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        .timeline-items {
            position: relative;
            padding-left: 60px;
        }

        .timeline-line {
            position: absolute;
            left: 24px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 40px;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -46px;
            top: 0;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: white;
            border: 3px solid #e0e0e0;
            z-index: 1;
        }

        .timeline-item.completed .timeline-dot {
            border-color: #4CAF50;
            background: #e8f5e9;
        }

        .timeline-item.current .timeline-dot {
            border-color: #2196F3;
            background: #e3f2fd;
            animation: pulse 2s infinite;
        }

        .timeline-item.pending .timeline-dot {
            border-color: #e0e0e0;
            background: #fafafa;
            opacity: 0.5;
        }

        .timeline-item.pending .timeline-content {
            opacity: 0.5;
        }

        .timeline-item.pending .timeline-title {
            color: #999;
        }

        .timeline-item.pending .timeline-description {
            color: #bbb;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .timeline-content {
            padding-left: 20px;
        }

        .timeline-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .timeline-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .timeline-date {
            color: #999;
            font-size: 13px;
        }

        /* Order Details */
        .order-details {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .order-details h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 600;
            text-align: right;
        }

        /* Items List */
        .items-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .items-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .item-quantity {
            color: #666;
            font-size: 14px;
        }

        .item-price {
            font-weight: 600;
            color: #2c3e50;
            text-align: right;
        }

        /* Tracking Box */
        .tracking-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .tracking-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }

        .tracking-number {
            font-size: 24px;
            font-weight: bold;
            color: #1976D2;
            margin: 10px 0;
        }

        .tracking-link {
            display: inline-block;
            margin-top: 10px;
            color: #2196F3;
            text-decoration: none;
            font-weight: 500;
        }

        .tracking-link:hover {
            text-decoration: underline;
        }

        /* Contact Info */
        .contact-box {
            background: #fff3e0;
            border-left: 4px solid #FF9800;
            border-radius: 8px;
            padding: 20px;
        }

        .contact-box h3 {
            color: #F57C00;
            margin-bottom: 10px;
        }

        .contact-box p {
            color: #555;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            margin-top: 20px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        @media (max-width: 768px) {
            .timeline-items {
                padding-left: 50px;
            }

            .detail-row,
            .item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .detail-value,
            .item-price {
                text-align: left;
            }
        }
    </style>
    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="/" class="logo"><?php echo htmlspecialchars($site_config['site_name']); ?></a>
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

                    // Show all history items
                    $statuses_to_show = [];
                    foreach ($history as $idx => $item) {
                        if (isset($status_config[$item['status']])) {
                            $is_last = ($idx === $last_index);
                            $statuses_to_show[] = [
                                'status' => $item['status'],
                                'date' => $item['date'],
                                'is_current' => $is_last,
                                'is_completed' => !$is_last
                            ];
                        }
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

            <!-- Payment Status Box -->
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

            <!-- Tracking Info (if exists) -->
            <?php if ($order['tracking_number']): ?>
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
</body>
</html>
