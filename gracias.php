<?php
/**
 * Thank You Page - Order Confirmation
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
    header('Location: /');
    exit;
}

// Get order by token
$order = get_order_by_token($token);

if (!$order || $order['id'] !== $order_id) {
    header('Location: /');
    exit;
}

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Gracias por tu compra! - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }

        .order-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .order-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .order-label {
            color: #666;
            font-weight: 500;
        }

        .order-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .order-number {
            font-size: 24px;
            color: #4CAF50;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }

        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .info-box p {
            color: #555;
            line-height: 1.6;
        }

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            min-width: 200px;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
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
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .items-list {
            margin-top: 20px;
            text-align: left;
        }

        .item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-name {
            color: #555;
        }

        .item-price {
            color: #2c3e50;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            .success-container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 24px;
            }

            .buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>

        <h1>¡Pedido Confirmado!</h1>
        <p class="subtitle">Gracias por tu compra, <?php echo htmlspecialchars($order['customer_name']); ?></p>

        <div class="order-info">
            <div class="order-row">
                <span class="order-label">Número de pedido:</span>
                <span class="order-value order-number"><?php echo htmlspecialchars($order['order_number']); ?></span>
            </div>

            <div class="order-row">
                <span class="order-label">Fecha:</span>
                <span class="order-value"><?php echo date('d/m/Y H:i', strtotime($order['date'])); ?></span>
            </div>

            <div class="order-row">
                <span class="order-label">Total:</span>
                <span class="order-value"><?php echo format_price($order['total'], $order['currency']); ?></span>
            </div>

            <div class="order-row">
                <span class="order-label">Método de pago:</span>
                <span class="order-value">
                    <?php
                    echo $order['payment_method'] === 'presencial' ? '💵 Pago Presencial' : '💳 Mercadopago';
                    ?>
                </span>
            </div>

            <div class="order-row">
                <span class="order-label">Estado:</span>
                <span class="order-value">
                    <?php
                    $status_labels = [
                        'pending' => '📦 Pendiente',
                        'confirmed' => '✅ Confirmado',
                        'shipped' => '🚚 Enviado',
                        'delivered' => '🏠 Entregado'
                    ];
                    echo $status_labels[$order['status']] ?? '📦 Pendiente';
                    ?>
                </span>
            </div>
        </div>

        <?php if (!empty($order['items'])): ?>
        <div class="items-list">
            <h3 style="margin-bottom: 15px; color: #2c3e50;">Productos:</h3>
            <?php foreach ($order['items'] as $item): ?>
            <div class="item">
                <span class="item-name">
                    <?php echo htmlspecialchars($item['name']); ?>
                    (x<?php echo $item['quantity']; ?>)
                </span>
                <span class="item-price">
                    <?php echo format_price($item['final_price'], $order['currency']); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($order['payment_method'] === 'presencial'): ?>
        <div class="info-box">
            <h3>ℹ️ Instrucciones para el retiro</h3>
            <p>
                <strong>Tu pedido está confirmado.</strong><br>
                <?php if ($order['shipping_address']): ?>
                    Recibirás tu pedido en la dirección indicada. Te contactaremos pronto para coordinar la entrega.
                <?php else: ?>
                    Puedes retirar tu pedido en nuestro local. Te contactaremos pronto para coordinar el retiro y el pago.
                <?php endif; ?>
            </p>
            <p style="margin-top: 10px;">
                <strong>Hemos enviado un email de confirmación a:</strong><br>
                <?php echo htmlspecialchars($order['customer_email']); ?>
            </p>
        </div>
        <?php else: ?>
        <div class="info-box">
            <h3>ℹ️ Próximos pasos</h3>
            <p>
                Una vez que se confirme el pago, comenzaremos a procesar tu pedido.
                Te mantendremos informado por email sobre el estado de tu compra.
            </p>
        </div>
        <?php endif; ?>

        <div class="buttons">
            <a href="/pedido.php?order=<?php echo urlencode($order['id']); ?>&token=<?php echo urlencode($order['tracking_token']); ?>"
               class="btn btn-primary">
                📦 Seguir mi pedido
            </a>
            <a href="/" class="btn btn-secondary">
                🏠 Volver al inicio
            </a>
        </div>

        <p style="margin-top: 30px; color: #999; font-size: 14px;">
            Guarda este enlace para hacer seguimiento de tu pedido.<br>
            También puedes rastrearlo en cualquier momento desde <a href="/track.php" style="color: #667eea;">aquí</a> usando tu email y número de pedido.
        </p>
    </div>
</body>
</html>
