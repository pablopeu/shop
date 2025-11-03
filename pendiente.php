<?php
/**
 * Payment Pending Page
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

$order = null;
if (!empty($order_id) && !empty($token)) {
    $order = get_order_by_token($token);
    if ($order && $order['id'] !== $order_id) {
        $order = null;
    }
}

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Pendiente - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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
            background: linear-gradient(135deg, #FFA726 0%, #FB8C00 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .pending-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }

        .pending-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: rotate 2s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        h1 {
            font-size: 32px;
            color: #F57C00;
            margin-bottom: 15px;
        }

        .subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .order-box {
            background: #fff3e0;
            border-left: 4px solid #FF9800;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }

        .order-box h3 {
            color: #F57C00;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ffe0b2;
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

        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
            color: #555;
        }

        .info-box li {
            margin: 5px 0;
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

        @media (max-width: 600px) {
            .pending-container {
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

            .order-row {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="pending-container">
        <div class="pending-icon">⏳</div>

        <h1>Pago Pendiente</h1>
        <p class="subtitle">
            Tu pedido ha sido registrado y está esperando la confirmación del pago.
        </p>

        <?php if ($order): ?>
        <div class="order-box">
            <h3>📋 Información del Pedido</h3>

            <div class="order-row">
                <span class="order-label">Número de pedido:</span>
                <span class="order-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
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
                <span class="order-label">Email:</span>
                <span class="order-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>ℹ️ ¿Qué significa esto?</h3>
            <p>
                Tu pago está siendo procesado por Mercadopago. Esto puede deberse a:
            </p>
            <ul>
                <li>El pago está siendo verificado por tu banco</li>
                <li>Se requiere autorización adicional</li>
                <li>El procesamiento puede tomar unos minutos</li>
            </ul>
            <p style="margin-top: 15px;">
                <strong>Te notificaremos por email cuando se confirme el pago.</strong>
            </p>
        </div>

        <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
            <h3 style="color: #856404;">💳 Mientras tanto...</h3>
            <p style="color: #555;">
                - No realices un nuevo pago por el mismo pedido<br>
                - Revisa tu email para actualizaciones<br>
                - Si tienes dudas, contáctanos con tu número de pedido
            </p>
        </div>

        <div class="buttons">
            <?php if ($order): ?>
            <a href="/pedido.php?order=<?php echo urlencode($order['id']); ?>&token=<?php echo urlencode($order['tracking_token']); ?>"
               class="btn btn-primary">
                📦 Ver estado del pedido
            </a>
            <?php endif; ?>
            <a href="/" class="btn btn-secondary">
                🏠 Volver al inicio
            </a>
        </div>

        <p style="margin-top: 30px; color: #999; font-size: 14px;">
            Contacto: <?php echo htmlspecialchars($site_config['contact_email'] ?? 'contacto@tienda.com'); ?>
        </p>
    </div>
</body>
</html>
