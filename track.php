<?php
/**
 * Track Order - Search Page
 * Permite buscar pedidos por email y número de orden
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme-loader.php';
require_once __DIR__ . '/includes/orders.php';

// Set security headers
set_security_headers();

// Start session
session_start();

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
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
            header("Location: " . url("/pedido.php?order={$found_order['id']}&token={$found_order['tracking_token']}"));
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

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/includes/mobile-menu.css'); ?>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="<?php echo url('/'); ?>" class="logo"><?php render_site_logo($site_config); ?></a>
            <nav class="nav">
                <a href="<?php echo url('/'); ?>">Inicio</a>
                <a href="<?php echo url('/buscar.php'); ?>">Buscar</a>
                <a href="<?php echo url('/favoritos.php'); ?>">Favoritos</a>
                <a href="<?php echo url('/track.php'); ?>">📦 Rastrear</a>
                <a href="<?php echo url('/carrito.php'); ?>">
                    🛒 Carrito (<span id="cart-count">0</span>)
                </a>
            </nav>
        </div>
    </header>

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
                <a href="<?php echo url('/'); ?>" class="link-btn">
                    🏠 Volver al inicio
                </a>
            </div>
        </div>
    </div>

    <script>
        // Update cart count from localStorage
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Update on page load
        updateCartCount();
    </script>

    <!-- Mobile Menu -->
    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
