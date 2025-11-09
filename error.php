<?php
/**
 * Payment Error Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme-loader.php';

// Set security headers
set_security_headers();

// Start session
session_start();

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

// Get payment status from URL parameters
$payment_status = $_GET['payment_status'] ?? 'rejected';
$payment_status_detail = $_GET['payment_status_detail'] ?? '';

// Get user-friendly message based on payment status
$payment_message = get_payment_message($payment_status, $payment_status_detail);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error en el Pago - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">

    <style>
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            max-width: 700px;
            width: 100%;
            margin: 50px auto;
            text-align: center;
        }

        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error-container h1 {
            font-size: 32px;
            color: #d32f2f;
            margin-bottom: 15px;
        }

        .error-container .subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .error-container .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }

        .error-container .info-box h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .error-container .info-box ul {
            margin-left: 20px;
            color: #555;
        }

        .error-container .info-box li {
            margin: 5px 0;
        }

        .error-container .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .error-container .btn {
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
            display: inline-block;
        }

        .error-container .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .error-container .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }

        .error-container .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .error-container .btn-secondary:hover {
            background: #e0e0e0;
        }

        @media (max-width: 600px) {
            .error-container {
                padding: 30px 20px;
                margin: 20px;
            }

            .error-container h1 {
                font-size: 24px;
            }

            .error-container .buttons {
                flex-direction: column;
            }

            .error-container .btn {
                width: 100%;
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo"><?php render_site_logo($site_config); ?></a>
            <nav class="nav">
                <a href="/">🏠 Volver al inicio</a>
            </nav>
        </div>
    </header>

    <div class="error-container">
        <div class="error-icon"><?php echo $payment_message['icon']; ?></div>

        <h1><?php echo htmlspecialchars($payment_message['title']); ?></h1>
        <p class="subtitle">
            <?php echo htmlspecialchars($payment_message['message']); ?>
            <br><br>
            No te preocupes, tu orden no ha sido confirmada y no se ha realizado ningún cargo.
        </p>

        <?php if (!empty($payment_message['suggestions'])): ?>
        <div class="info-box">
            <h3>💡 ¿Qué puedes hacer?</h3>
            <ul>
                <?php foreach ($payment_message['suggestions'] as $suggestion): ?>
                <li><?php echo htmlspecialchars($suggestion); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="info-box" style="background: #e3f2fd; border-left-color: #2196F3;">
            <h3 style="color: #1976D2;">📞 ¿Necesitas ayuda?</h3>
            <p style="color: #555;">
                Si el problema persiste, contáctanos:<br>
                Email: <?php echo htmlspecialchars($site_config['contact_email'] ?? 'contacto@tienda.com'); ?><br>
                Teléfono: <?php echo htmlspecialchars($site_config['contact_phone'] ?? '+54 9 11 1234-5678'); ?>
            </p>
        </div>

        <div class="buttons">
            <a href="/checkout.php" class="btn btn-primary">
                🔄 Intentar nuevamente
            </a>
            <a href="/carrito.php" class="btn btn-secondary">
                🛒 Ver carrito
            </a>
        </div>

        <p style="margin-top: 30px; color: #999; font-size: 14px;">
            También puedes elegir pago presencial en el checkout
        </p>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
