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
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        'label' => 'Pendiente de Pago',
        'color' => '#FF9800',
        'description' => 'Tu pedido está esperando la confirmación del pago'
    ],
    'pendiente' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        'label' => 'Pendiente de Pago',
        'color' => '#FF9800',
        'description' => 'Tu pedido está esperando la confirmación del pago'
    ],
    'cobrada' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        'label' => 'Pago Confirmado',
        'color' => '#4CAF50',
        'description' => 'Tu pago fue confirmado y estamos preparando tu pedido'
    ],
    'confirmado' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>',
        'label' => 'Pedido Confirmado',
        'color' => '#2196F3',
        'description' => 'Tu pedido está siendo preparado para el envío'
    ],
    'confirmed' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg>',
        'label' => 'Pedido Confirmado',
        'color' => '#2196F3',
        'description' => 'Tu pedido está siendo preparado para el envío'
    ],
    'shipped' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
        'label' => 'Enviado',
        'color' => '#9C27B0',
        'description' => 'Tu pedido está en camino'
    ],
    'delivered' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'label' => 'Entregado',
        'color' => '#4CAF50',
        'description' => '¡Tu pedido ha sido entregado!'
    ],
    'cancelada' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
        'label' => 'Cancelado',
        'color' => '#f44336',
        'description' => 'Este pedido ha sido cancelado'
    ],
    'cancelled' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
        'label' => 'Cancelado',
        'color' => '#f44336',
        'description' => 'Este pedido ha sido cancelado'
    ],
    'rechazada' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
        'label' => 'Rechazado',
        'color' => '#f44336',
        'description' => 'El pago fue rechazado'
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
    <link rel="stylesheet" href="<?php echo url('/includes/mobile-menu.css'); ?>">

    <!-- Order Tracking Styles -->
    <style>
        /* Timeline Icon Styling */
        .timeline-dot {
            width: 48px !important;
            height: 48px !important;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: white;
            border: 3px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .timeline-dot svg {
            width: 24px;
            height: 24px;
            stroke: #9e9e9e;
            transition: stroke 0.3s ease;
        }

        .timeline-item.completed .timeline-dot {
            background: #4CAF50;
            border-color: #4CAF50;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .timeline-item.completed .timeline-dot svg {
            stroke: white;
        }

        .timeline-item.current .timeline-dot {
            background: white;
            border-color: currentColor;
            border-width: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: pulse 2s ease-in-out infinite;
        }

        .timeline-item.current .timeline-dot svg {
            stroke: currentColor;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            }
        }

        .timeline-item.pending .timeline-dot {
            background: #fafafa;
            border-color: #e0e0e0;
        }

        /* Timeline Content */
        .timeline-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
        }

        .timeline-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 6px;
            line-height: 1.5;
        }

        .timeline-date {
            font-size: 13px;
            color: #999;
            font-weight: 500;
        }

        .timeline-item.completed .timeline-title {
            color: #4CAF50;
        }

        .timeline-item.current .timeline-title {
            color: currentColor;
            font-weight: 700;
        }

        /* Payment Status Icons */
        .payment-status-icon {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 8px;
        }

        /* Order Header Enhancement */
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .order-header h1 {
            font-size: 28px;
            margin: 0 0 10px 0;
            font-weight: 700;
        }

        .order-date {
            font-size: 15px;
            opacity: 0.9;
        }

        /* Timeline Section */
        .timeline {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .timeline h2 {
            font-size: 22px;
            margin: 0 0 25px 0;
            color: #333;
            font-weight: 600;
        }

        /* Payment Status Box Enhancement */
        .payment-status-box {
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .payment-status-box h3 {
            display: flex;
            align-items: center;
            margin: 0 0 12px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .payment-status-box h3 svg {
            width: 24px;
            height: 24px;
            margin-right: 10px;
        }

        /* Sections */
        .order-details, .items-section, .contact-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .order-details h2, .items-section h2 {
            font-size: 20px;
            margin: 0 0 20px 0;
            color: #333;
            font-weight: 600;
        }

        /* Detail rows */
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 500;
            color: #666;
        }

        .detail-value {
            font-weight: 600;
            color: #333;
        }

        /* Items */
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .item-quantity {
            font-size: 14px;
            color: #666;
        }

        .item-price {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        /* Contact Box */
        .contact-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .contact-box h3 {
            margin: 0 0 12px 0;
            font-size: 20px;
        }

        .contact-box p {
            margin: 0;
            line-height: 1.8;
            opacity: 0.95;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .order-header h1 {
                font-size: 24px;
            }

            .timeline, .order-details, .items-section, .contact-box {
                padding: 20px;
            }

            .detail-row {
                flex-direction: column;
                gap: 4px;
            }

            .item {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="<?php echo url('/'); ?>" class="logo"><?php render_site_logo($site_config); ?></a>
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
                <a href="<?php echo url('/'); ?>" class="btn btn-primary">Volver al inicio</a>
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
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px; color: #667eea;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    Estado del Pedido
                </h2>

                <div class="timeline-items">
                    <div class="timeline-line"></div>

                    <?php
                    // Build timeline from status_history
                    // Updated to include both English and Spanish states
                    $all_steps = ['pending', 'pendiente', 'cobrada', 'confirmado', 'confirmed', 'shipped', 'delivered'];
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
                'pending' => [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
                    'label' => 'Pago Pendiente',
                    'color' => '#FF9800',
                    'bg' => '#fff3e0'
                ],
                'approved' => [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
                    'label' => 'Pago Aprobado',
                    'color' => '#4CAF50',
                    'bg' => '#e8f5e9'
                ],
                'rejected' => [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
                    'label' => 'Pago Rechazado',
                    'color' => '#f44336',
                    'bg' => '#ffebee'
                ],
                'cancelled' => [
                    'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4" y1="12" x2="20" y2="12"></line></svg>',
                    'label' => 'Pago Cancelado',
                    'color' => '#9e9e9e',
                    'bg' => '#f5f5f5'
                ]
            ];
            $payment_status = $order['payment_status'] ?? 'pending';
            $payment_info = $payment_status_config[$payment_status] ?? $payment_status_config['pending'];
            ?>
            <div class="payment-status-box" style="background: <?php echo $payment_info['bg']; ?>; border-left: 4px solid <?php echo $payment_info['color']; ?>;">
                <h3 style="color: <?php echo $payment_info['color']; ?>;">
                    <span style="color: <?php echo $payment_info['color']; ?>;"><?php echo $payment_info['icon']; ?></span>
                    Estado del Pago
                </h3>
                <div style="font-size: 18px; font-weight: 600; color: <?php echo $payment_info['color']; ?>; margin-bottom: 10px;">
                    <?php echo $payment_info['label']; ?>
                </div>
                <?php if ($payment_status === 'pending' && $order['payment_link']): ?>
                    <a href="<?php echo htmlspecialchars($order['payment_link']); ?>"
                       class="btn btn-primary"
                       style="display: inline-block; margin-top: 10px; text-decoration: none;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                        Completar Pago
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tracking Info (if exists and not cancelled) -->
            <?php if ($order['tracking_number'] && $order['status'] !== 'cancelled'): ?>
            <div class="tracking-box">
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    Número de Seguimiento
                </h3>
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
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Detalles del Pedido
                </h2>

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

                <?php if (isset($order['notes']) && !empty(trim($order['notes']))): ?>
                <div class="detail-row" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0; display: block;">
                    <div style="margin-bottom: 8px;">
                        <span class="detail-label" style="display: block; font-weight: 600;">💬 Tu mensaje:</span>
                    </div>
                    <div style="background-color: #fff9e6; padding: 12px; border-radius: 6px; border-left: 4px solid #ffc107; white-space: pre-wrap; color: #333; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Items -->
            <div class="items-section">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    Productos
                </h2>

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
                <h3>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 8px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    ¿Necesitas ayuda?
                </h3>
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
                <a href="<?php echo url('/'); ?>" class="btn btn-primary" style="display: inline-block; text-decoration: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    Volver al Inicio
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Mobile Menu -->
    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
