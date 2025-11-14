<?php
/**
 * Theme Preview Page
 * Permite previsualizar themes antes de activarlos
 * URL: /preview.php?theme=elegant
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme-loader.php';

// Obtener theme desde URL o usar el activo
$preview_theme = isset($_GET['theme']) ? sanitize_input($_GET['theme']) : null;

// Validar que el theme exista
if ($preview_theme) {
    $validation = validate_theme($preview_theme);
    if (!$validation['valid']) {
        // Theme inválido, usar el activo
        $preview_theme = null;
    }
}

// Si no hay preview_theme válido, cargar el activo
if (!$preview_theme) {
    $theme_config = read_json(__DIR__ . '/config/theme.json');
    $active_theme = $theme_config['active_theme'] ?? 'minimal';
} else {
    $active_theme = $preview_theme;
}

// Cargar configuraciones
$site_config = read_json(__DIR__ . '/config/site.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$hero_config = read_json(__DIR__ . '/config/hero.json');
$products_heading_config = read_json(__DIR__ . '/config/products-heading.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');

// Cargar productos
$productos = read_json(__DIR__ . '/data/productos.json');

// Obtener productos destacados (o los primeros 8 si no hay destacados)
$productos_destacados = array_filter($productos, function($p) {
    return isset($p['destacado']) && $p['destacado'];
});

// Si no hay productos destacados, tomar los primeros
if (empty($productos_destacados)) {
    $productos_destacados = $productos;
}

// Limitar a 8 productos
$productos_destacados = array_slice($productos_destacados, 0, 8);

// Calcular precio final si hay descuento (simplificado para preview)
foreach ($productos_destacados as &$producto) {
    if (isset($producto['descuento']) && $producto['descuento'] > 0) {
        $producto['precio_final'] = $producto['precio'] * (1 - $producto['descuento'] / 100);
    } else {
        $producto['precio_final'] = $producto['precio'];
    }
}

// Estado de preview
$is_preview = !empty($preview_theme);
$preview_theme_name = $is_preview ? ucfirst($preview_theme) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_preview ? "Preview: $preview_theme_name - " : ''; ?><?php echo htmlspecialchars($site_config['site_name']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($site_config['site_description']); ?>">

    <!-- Theme CSS -->
    <?php render_theme_css($active_theme); ?>
</head>
<body>
    <?php if ($is_preview): ?>
    <!-- Preview Banner -->
    <div style="position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 20px; text-align: center; z-index: 10000; box-shadow: 0 2px 10px rgba(0,0,0,0.2); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <strong>🎨 Modo Preview</strong> - Estás viendo el theme: <strong><?php echo htmlspecialchars($preview_theme_name); ?></strong>
        <span style="margin: 0 15px;">|</span>
        <a href="<?php echo url('/admin/config-themes.php'); ?>" style="color: white; text-decoration: underline;">← Volver al selector de themes</a>
        <span style="margin: 0 15px;">|</span>
        <a href="<?php echo url('/'); ?>" style="color: white; text-decoration: underline;">Ver sitio normal</a>
    </div>
    <div style="height: 46px;"></div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="<?php echo url('/'); ?>">
                        <?php render_site_logo($site_config); ?>
                    </a>
                </div>
                <nav class="nav">
                    <a href="<?php echo url('/'); ?>">Inicio</a>
                    <a href="<?php echo url('/buscar.php'); ?>">Buscar</a>
                    <a href="<?php echo url('/favoritos.php'); ?>">❤️ Favoritos</a>
                    <a href="<?php echo url('/track.php'); ?>">📦 Rastreo</a>
                </nav>
                <div class="header-actions">
                    <button class="btn-icon" onclick="toggleCart()">
                        🛒 <span class="cart-count">0</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h2><?php echo htmlspecialchars($hero_config['title']); ?></h2>
            <p><?php echo htmlspecialchars($hero_config['subtitle']); ?></p>
            <a href="#productos" class="btn-hero"><?php echo htmlspecialchars($hero_config['cta_text']); ?></a>
        </div>
    </section>

    <!-- Currency Selector -->
    <section class="currency-section">
        <div class="container">
            <div class="currency-selector">
                <h3>Selecciona tu moneda</h3>
                <div class="currency-buttons">
                    <button class="currency-btn active" data-currency="USD">
                        🇺🇸 USD
                    </button>
                    <button class="currency-btn" data-currency="ARS">
                        🇦🇷 ARS
                    </button>
                </div>
                <p class="exchange-info">
                    Tipo de cambio: <strong>1 USD = <?php echo number_format($currency_config['exchange_rate'], 2); ?> ARS</strong>
                </p>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="products-section" id="productos">
        <div class="container">
            <div class="section-header">
                <h2><?php echo htmlspecialchars($products_heading_config['title']); ?></h2>
                <p><?php echo htmlspecialchars($products_heading_config['description']); ?></p>
            </div>

            <div class="products-grid">
                <?php foreach ($productos_destacados as $producto): ?>
                <div class="product-card">
                    <?php if (isset($producto['descuento']) && $producto['descuento'] > 0): ?>
                        <span class="badge-descuento">-<?php echo $producto['descuento']; ?>%</span>
                    <?php endif; ?>

                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars($producto['imagenes'][0] ?? '/assets/placeholder.jpg'); ?>"
                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                             loading="lazy">
                    </div>

                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars(substr($producto['descripcion'], 0, 80)); ?>...</p>

                        <div class="product-price">
                            <?php if (isset($producto['descuento']) && $producto['descuento'] > 0): ?>
                                <span class="price-original">$<?php echo number_format($producto['precio'], 2); ?></span>
                                <span class="price-final">$<?php echo number_format($producto['precio_final'], 2); ?></span>
                            <?php else: ?>
                                <span class="price-final">$<?php echo number_format($producto['precio'], 2); ?></span>
                            <?php endif; ?>
                        </div>

                        <button class="btn-add-cart" data-id="<?php echo $producto['id']; ?>">
                            Agregar al carrito
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- Cart Panel (simplified for preview) -->
    <div class="cart-panel" id="cartPanel">
        <div class="cart-header">
            <h3>🛒 Carrito de Compras</h3>
            <button class="btn-close" onclick="toggleCart()">✕</button>
        </div>
        <div class="cart-body">
            <p style="text-align: center; color: #999; padding: 40px 20px;">
                Preview del theme - El carrito no está funcional en modo preview
            </p>
        </div>
    </div>

    <script>
        function toggleCart() {
            const cartPanel = document.getElementById('cartPanel');
            cartPanel.classList.toggle('active');
        }

        // Close cart when clicking outside
        document.addEventListener('click', function(e) {
            const cartPanel = document.getElementById('cartPanel');
            const cartButton = document.querySelector('.btn-icon');

            if (cartPanel.classList.contains('active') &&
                !cartPanel.contains(e.target) &&
                !cartButton.contains(e.target)) {
                cartPanel.classList.remove('active');
            }
        });

        // Currency switcher (demo only)
        document.querySelectorAll('.currency-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.currency-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
