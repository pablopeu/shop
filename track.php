<?php
/**
 * Track Order - Search Page
 * Permite buscar pedidos por email y número de orden
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/orders.php';

// Set security headers
set_security_headers();

// Start session
session_start();

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';

$error = null;
$success = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $order_number = trim($_POST['order_number'] ?? '');

    if (empty($email) || empty($order_number)) {
        $error = 'Por favor completa todos los campos';
    } else {
        // Search for order
        $orders = get_all_orders();
        $found_order = null;

        foreach ($orders as $order) {
            if (strtolower($order['customer_email']) === strtolower($email) &&
                $order['order_number'] === $order_number) {
                $found_order = $order;
                break;
            }
        }

        if ($found_order) {
            // Redirect to tracking page with token
            header("Location: /pedido.php?order={$found_order['id']}&token={$found_order['tracking_token']}");
            exit;
        } else {
            $error = 'No se encontró ningún pedido con esos datos';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rastrear Pedido - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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

        .container {
            max-width: 500px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .header .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="email"],
        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        input[type="email"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            color: #c62828;
        }

        .alert-success {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            color: #2e7d32;
        }

        .help-text {
            font-size: 13px;
            color: #999;
            margin-top: 5px;
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            color: #999;
            font-size: 14px;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #e0e0e0;
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .link-section {
            text-align: center;
        }

        .link-btn {
            display: inline-block;
            padding: 12px 24px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .link-btn:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .card {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .header .icon {
                font-size: 50px;
            }
        }
    </style>
    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="icon">📦</div>
                <h1>Rastrear mi Pedido</h1>
                <p>Ingresa tus datos para ver el estado de tu compra</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="tu@email.com"
                        required
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    >
                    <div class="help-text">El email que usaste al realizar la compra</div>
                </div>

                <div class="form-group">
                    <label for="order_number">Número de Pedido</label>
                    <input
                        type="text"
                        id="order_number"
                        name="order_number"
                        placeholder="ORD-2025-00001"
                        required
                        value="<?php echo htmlspecialchars($_POST['order_number'] ?? ''); ?>"
                    >
                    <div class="help-text">Lo encontrarás en el email de confirmación</div>
                </div>

                <button type="submit" class="btn">
                    🔍 Buscar Pedido
                </button>
            </form>

            <div class="divider">o</div>

            <div class="link-section">
                <a href="/" class="link-btn">
                    🏠 Volver al inicio
                </a>
            </div>
        </div>
    </div>
    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>
</body>
</html>
