<?php
/**
 * Home Page - Public Site
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';

// Set security headers
set_security_headers();

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

// Start session
session_start();

// Get all active products
$products = get_all_products(true);

// Filter out products that are out of stock and should be hidden
$products = array_filter($products, function($product) {
    // Show product if it has stock OR if hide_when_out_of_stock is not set/false
    $hide_when_no_stock = $product['hide_when_out_of_stock'] ?? false;
    if ($hide_when_no_stock && $product['stock'] <= 0) {
        return false; // Hide this product
    }
    return true; // Show this product
});

// Get site configuration
$site_config = read_json(__DIR__ . '/config/site.json');
$hero_config = read_json(__DIR__ . '/config/hero.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$products_heading_config = read_json(__DIR__ . '/config/products-heading.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_config['site_name']); ?> - E-commerce</title>
    <meta name="description" content="<?php echo htmlspecialchars($site_config['site_description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($site_config['site_keywords']); ?>">

    <!-- Theme CSS -->
    <link rel="stylesheet" href="/themes/<?php echo $active_theme; ?>/theme.css">

    <!-- Carousel CSS -->
    <link rel="stylesheet" href="/includes/carousel.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1001;
            width: 100%;
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

        .nav {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav a {
            color: #666;
            text-decoration: none;
            transition: color 0.3s;
        }

        .nav a:hover {
            color: #333;
        }

        .cart-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-badge.hidden {
            display: none;
        }

        /* Hero Section */
        .hero {
            color: white;
            text-align: center;
            padding: 80px 20px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .hero.has-image {
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .hero.has-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        }

        /* Carousel Section */
        .carousel-section {
            width: 100%;
            max-height: 500px;
            overflow: hidden;
        }

        .carousel-container {
            position: relative;
            width: 100%;
            height: 500px;
            overflow: hidden;
        }

        .carousel-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 100%;
            transition: left 0.6s ease-in-out;
            display: block;
        }

        .carousel-slide.active {
            left: 0;
        }

        .carousel-slide.prev {
            left: -100%;
        }

        .carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-slide-link {
            display: block;
            width: 100%;
            height: 100%;
            position: relative;
            cursor: pointer;
        }

        .carousel-slide-link:hover .carousel-slide img {
            transform: scale(1.02);
            transition: transform 0.3s ease;
        }

        .carousel-caption {
            position: absolute;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            color: white;
            z-index: 5;
            pointer-events: none;
        }

        .carousel-caption h2 {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.8);
        }

        .carousel-caption p {
            font-size: 16px;
            margin-top: 5px;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.8);
        }

        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 15px 20px;
            cursor: pointer;
            font-size: 24px;
            z-index: 10;
            transition: background 0.3s;
        }

        .carousel-control:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .carousel-control.prev {
            left: 20px;
        }

        .carousel-control.next {
            right: 20px;
        }

        .carousel-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .carousel-dots .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: background 0.3s;
        }

        .carousel-dots .dot.active {
            background: white;
        }

        .carousel-dots .dot:hover {
            background: rgba(255, 255, 255, 0.8);
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .hero .btn {
            position: relative;
            z-index: 2;
        }

        /* Products Grid */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .section-title {
            font-size: 32px;
            margin-bottom: 30px;
            text-align: center;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 30px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 250px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
            cursor: pointer;
        }

        .product-info {
            padding: 20px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .product-stock {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }

        .stock-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-low {
            background: #fff3cd;
            color: #856404;
        }

        .stock-out {
            background: #f8d7da;
            color: #721c24;
        }

        .product-buttons {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            flex: 1;
            text-align: center;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-add-cart {
            background: #4CAF50;
        }

        .btn-add-cart:hover {
            background: #45a049;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }

        /* Cart Panel */
        .cart-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.2);
            transition: right 0.3s ease;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .cart-panel.open {
            right: 0;
        }

        .cart-panel-header {
            padding: 20px;
            background: #667eea;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-panel-header h2 {
            font-size: 20px;
            margin: 0;
        }

        .cart-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .cart-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .cart-empty {
            text-align: center;
            color: #999;
            padding: 40px 20px;
        }

        .cart-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .cart-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .cart-item-price {
            color: #667eea;
            font-weight: bold;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }

        .qty-btn {
            background: #f5f5f5;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .qty-btn:hover {
            background: #e0e0e0;
        }

        .cart-item-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .cart-panel-footer {
            padding: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 999;
        }

        .cart-overlay.open {
            display: block;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px 20px;
            margin-top: 60px;
        }

        /* WhatsApp Button */
        .whatsapp-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: transform 0.3s;
            z-index: 1000;
        }

        .whatsapp-button:hover {
            transform: scale(1.1);
        }

        .whatsapp-button svg {
            width: 35px;
            height: 35px;
            fill: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 32px;
            }

            .products-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 0 10px;
            }

            .product-card {
                max-width: 100%;
            }

            .product-image {
                height: 200px;
            }

            .product-info {
                padding: 15px;
            }

            .product-name {
                font-size: 16px;
                margin-bottom: 8px;
            }

            .product-price {
                font-size: 18px;
                margin-bottom: 8px;
                word-break: break-word;
                line-height: 1.3;
            }

            .product-price span {
                display: block;
                margin-top: 4px;
                font-size: 0.8em !important;
            }

            .product-stock {
                font-size: 13px;
                margin-bottom: 12px;
            }

            .product-buttons {
                flex-direction: column;
                gap: 8px;
            }

            .btn {
                padding: 10px 16px;
                font-size: 14px;
                width: 100%;
            }

            .header-content {
                padding: 0 15px;
            }

            .container {
                padding: 30px 10px;
            }
        }
    </style>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="/" class="logo"><?php echo htmlspecialchars($site_config['site_name']); ?></a>
            <nav class="nav">
                <a href="/">Inicio</a>
                <a href="/buscar.php">Buscar</a>
                <a href="/favoritos.php">Favoritos</a>
                <a href="/track.php">📦 Rastrear</a>
                <a href="#" class="cart-link" onclick="openCartPanel(); return false;">
                    🛒 Carrito
                    <span class="cart-badge hidden" id="cart-count">0</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Carousel Section (priority over hero) -->
    <?php
    $carousel_config = read_json(__DIR__ . '/config/carousel.json');
    $show_carousel = ($carousel_config['enabled'] ?? false) && !empty($carousel_config['slides']);
    $show_hero = ($hero_config['enabled'] ?? false) && !$show_carousel; // Hero only if carousel is disabled
    ?>

    <?php if ($show_carousel): ?>
    <section class="carousel-section">
        <div class="carousel-container">
            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
            <div class="carousel-slide <?php echo $index === 0 ? 'active' : ($index === count($carousel_config['slides']) - 1 ? 'prev' : ''); ?>">
                <?php if (!empty($slide['link'])): ?>
                <a href="<?php echo htmlspecialchars($slide['link']); ?>" class="carousel-slide-link">
                    <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                    <div class="carousel-caption">
                        <h2><?php echo htmlspecialchars($slide['title']); ?></h2>
                        <?php if (!empty($slide['subtitle'])): ?>
                            <p><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php else: ?>
                <div class="carousel-slide-link" style="cursor: default;">
                    <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                    <div class="carousel-caption">
                        <h2><?php echo htmlspecialchars($slide['title']); ?></h2>
                        <?php if (!empty($slide['subtitle'])): ?>
                            <p><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (count($carousel_config['slides']) > 1): ?>
            <button class="carousel-control prev" onclick="moveCarousel(-1)">&#10094;</button>
            <button class="carousel-control next" onclick="moveCarousel(1)">&#10095;</button>
            <div class="carousel-dots">
                <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $index; ?>)"></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php elseif ($show_hero): ?>
    <!-- Hero Section (only if carousel is disabled) -->
    <section class="hero <?php echo !empty($hero_config['image']) ? 'has-image' : ''; ?>"
             style="<?php if (!empty($hero_config['image'])): ?>
                        background-image: url('<?php echo htmlspecialchars($hero_config['image']); ?>');
                    <?php else: ?>
                        background: <?php echo htmlspecialchars($hero_config['background_color'] ?? '#667eea'); ?>;
                    <?php endif; ?>">
        <h1><?php echo htmlspecialchars($hero_config['title']); ?></h1>
        <?php if (!empty($hero_config['subtitle'])): ?>
            <p><?php echo htmlspecialchars($hero_config['subtitle']); ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Carousel Section -->
    <?php include __DIR__ . '/includes/carousel.php'; ?>

    <!-- Products Section -->
    <div class="container">
        <?php if ($products_heading_config['enabled'] ?? true): ?>
            <?php if (!empty($products_heading_config['heading'])): ?>
                <h2 class="section-title"><?php echo htmlspecialchars($products_heading_config['heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($products_heading_config['subheading'])): ?>
                <p style="text-align: center; font-size: 16px; color: #666; margin: -20px auto 30px; max-width: 600px;">
                    <?php echo htmlspecialchars($products_heading_config['subheading']); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <h3>No hay productos disponibles</h3>
                <p>Pronto agregaremos productos a nuestra tienda.</p>
                <br>
                <a href="/admin/login.php" class="btn">Ir al Admin Panel</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image" onclick="window.location.href='/producto.php?slug=<?php echo urlencode($product['slug']); ?>'">
                            <?php if (!empty($product['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($product['thumbnail']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                Sin imagen
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>

                            <div class="product-price">
                                <?php echo format_product_price($product, $selected_currency); ?>
                            </div>

                            <div class="product-stock">
                                <?php if ($product['stock'] === 0): ?>
                                    <span class="stock-badge stock-out">Sin stock</span>
                                <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                                    <span class="stock-badge stock-low">¡Últimas unidades!</span>
                                <?php else: ?>
                                    Stock disponible: <?php echo $product['stock']; ?>
                                <?php endif; ?>
                            </div>

                            <div class="product-buttons">
                                <button class="btn btn-secondary" onclick="window.location.href='/producto.php?slug=<?php echo urlencode($product['slug']); ?>'" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                                    Ver detalle
                                </button>
                                <button class="btn btn-add-cart" onclick="addToCart('<?php echo htmlspecialchars($product['id']); ?>', event)" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                                    🛒 Agregar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p><?php echo htmlspecialchars($site_config['footer_text']); ?></p>
    </footer>

    <!-- WhatsApp Button -->
    <?php if ($site_config['whatsapp']['enabled']): ?>
    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $site_config['whatsapp']['number']); ?>?text=<?php echo urlencode($site_config['whatsapp']['message']); ?>"
       class="whatsapp-button"
       target="_blank"
       title="Contáctanos por WhatsApp">
        <svg viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>
    <?php endif; ?>

    <!-- Cart Overlay -->
    <div class="cart-overlay" id="cart-overlay" onclick="closeCartPanel()"></div>

    <!-- Cart Panel -->
    <div class="cart-panel" id="cart-panel">
        <div class="cart-panel-header">
            <h2>🛒 Tu Carrito</h2>
            <button class="cart-close" onclick="closeCartPanel()">&times;</button>
        </div>
        <div class="cart-panel-body" id="cart-panel-body">
            <div class="cart-empty">Tu carrito está vacío</div>
        </div>
        <div class="cart-panel-footer" id="cart-panel-footer" style="display: none;">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cart-total">$0.00</span>
            </div>
            <button onclick="goToCheckout()" class="btn" style="width: 100%; text-align: center; border: none; cursor: pointer;">Ir a Pagar</button>
        </div>
    </div>

    <script>
        // Products data for cart panel
        const products = <?php echo json_encode($products); ?>;
        const exchangeRate = <?php echo $currency_config['exchange_rate']; ?>;

        function addToCart(productId, event) {
            event.stopPropagation();

            // Get current cart from localStorage
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');

            // Check if product already exists
            const existingItem = cart.find(item => item.product_id === productId);

            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({
                    product_id: productId,
                    quantity: 1
                });
            }

            // Save to localStorage
            localStorage.setItem('cart', JSON.stringify(cart));

            // Update UI
            updateCartBadge();
            renderCartPanel();
            openCartPanel();
        }

        function updateCartBadge() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            const badge = document.getElementById('cart-count');

            if (badge) {
                badge.textContent = totalItems;
                if (totalItems > 0) {
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }

        function renderCartPanel() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const body = document.getElementById('cart-panel-body');
            const footer = document.getElementById('cart-panel-footer');
            const totalEl = document.getElementById('cart-total');

            console.log('Cart from localStorage:', cart);
            console.log('Products array:', products);

            if (cart.length === 0) {
                body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
                footer.style.display = 'none';
                return;
            }

            let totalARS = 0;
            let totalUSD = 0;
            let allProductsUSD = true;
            let html = '';
            let validCart = [];

            cart.forEach(item => {
                const product = products.find(p => p.id === item.product_id);
                console.log('Looking for product:', item.product_id, 'Found:', product);
                if (!product) {
                    console.warn('Product not found:', item.product_id);
                    return;
                }

                // Add to valid cart
                validCart.push(item);

                const priceARS = parseFloat(product.price_ars) || 0;
                const priceUSD = parseFloat(product.price_usd) || 0;

                // Determinar si el producto está en USD o ARS
                let itemPriceARS = 0;
                let itemPriceUSD = 0;
                let displayPrice = '';
                let isUSDProduct = false;

                if (priceUSD > 0 && priceARS === 0) {
                    // Producto solo en USD
                    isUSDProduct = true;
                    itemPriceUSD = priceUSD;
                    itemPriceARS = priceUSD * exchangeRate;
                    displayPrice = 'U$D ' + priceUSD.toFixed(2);
                } else if (priceARS > 0 && priceUSD === 0) {
                    // Producto solo en ARS
                    allProductsUSD = false;
                    itemPriceARS = priceARS;
                    displayPrice = '$' + priceARS.toFixed(2);
                } else if (priceARS > 0 && priceUSD > 0) {
                    // Producto con ambos precios - usar ARS
                    allProductsUSD = false;
                    itemPriceARS = priceARS;
                    displayPrice = '$' + priceARS.toFixed(2);
                }

                totalARS += itemPriceARS * item.quantity;
                totalUSD += itemPriceUSD * item.quantity;

                html += `
                    <div class="cart-item">
                        <img src="${product.thumbnail || ''}" class="cart-item-image" alt="${product.name}">
                        <div class="cart-item-details">
                            <div class="cart-item-name">${product.name}</div>
                            <div class="cart-item-price">${displayPrice}</div>
                            <div class="cart-item-quantity">
                                <button class="qty-btn" onclick="updateQuantity('${product.id}', -1)">-</button>
                                <span>${item.quantity}</span>
                                <button class="qty-btn" onclick="updateQuantity('${product.id}', 1)">+</button>
                                <button class="cart-item-remove" onclick="removeFromCart('${product.id}')">Eliminar</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            // Clean invalid items from localStorage
            if (validCart.length !== cart.length) {
                console.warn('Cleaning invalid items from cart');
                localStorage.setItem('cart', JSON.stringify(validCart));
                updateCartBadge();
            }

            // Check if we have any valid items to display
            if (html === '' || validCart.length === 0) {
                body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
                footer.style.display = 'none';
                return;
            }

            body.innerHTML = html;

            // Mostrar total en USD si todos los productos están en USD, sino en ARS
            if (allProductsUSD && totalUSD > 0) {
                totalEl.textContent = 'U$D ' + totalUSD.toFixed(2);
            } else {
                totalEl.textContent = '$' + totalARS.toFixed(2);
            }

            footer.style.display = 'block';
        }

        function updateQuantity(productId, change) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const item = cart.find(i => i.product_id === productId);

            if (item) {
                item.quantity += change;
                if (item.quantity <= 0) {
                    cart = cart.filter(i => i.product_id !== productId);
                }
            }

            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartBadge();
            renderCartPanel();
        }

        function removeFromCart(productId) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            cart = cart.filter(i => i.product_id !== productId);
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartBadge();
            renderCartPanel();
        }

        function openCartPanel() {
            renderCartPanel();
            document.getElementById('cart-panel').classList.add('open');
            document.getElementById('cart-overlay').classList.add('open');
        }

        function closeCartPanel() {
            document.getElementById('cart-panel').classList.remove('open');
            document.getElementById('cart-overlay').classList.remove('open');
        }

        // Go to checkout - sync cart first
        async function goToCheckout() {
            try {
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');

                if (cart.length === 0) {
                    alert('El carrito está vacío');
                    return;
                }

                // Sync to session
                const response = await fetch('/api/sync_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ cart: cart })
                });

                if (!response.ok) {
                    throw new Error('Failed to sync cart');
                }

                const result = await response.json();

                if (result.success) {
                    // Redirect to checkout
                    window.location.href = '/checkout.php';
                } else {
                    alert('Error al procesar el carrito');
                }
            } catch (error) {
                console.error('Error syncing cart:', error);
                alert('Error al procesar el carrito');
            }
        }

        // Update badge and render cart on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateCartBadge();
            renderCartPanel();
        });
    </script>

    <!-- Carousel Script -->
    <script>
        let currentSlideIndex = 0;

        function moveCarousel(direction) {
            const slides = document.querySelectorAll('.carousel-slide');
            const dots = document.querySelectorAll('.carousel-dots .dot');

            if (slides.length === 0) return;

            // Calculate new index
            const prevIndex = currentSlideIndex;
            currentSlideIndex += direction;
            if (currentSlideIndex >= slides.length) currentSlideIndex = 0;
            if (currentSlideIndex < 0) currentSlideIndex = slides.length - 1;

            // Remove all positioning classes
            slides.forEach(slide => {
                slide.classList.remove('active', 'prev');
            });

            // Set new positions
            if (direction > 0) {
                // Moving forward
                slides[prevIndex].classList.add('prev');
                slides[currentSlideIndex].classList.add('active');
            } else {
                // Moving backward - need to position the new slide on the left first
                slides[currentSlideIndex].style.transition = 'none';
                slides[currentSlideIndex].style.left = '-100%';

                setTimeout(() => {
                    slides[currentSlideIndex].style.transition = '';
                    slides[prevIndex].style.left = '100%';
                    slides[currentSlideIndex].classList.add('active');
                }, 10);
            }

            // Update dots
            if (dots.length > 0) {
                dots.forEach(dot => dot.classList.remove('active'));
                if (dots[currentSlideIndex]) dots[currentSlideIndex].classList.add('active');
            }
        }

        function currentSlide(index) {
            const slides = document.querySelectorAll('.carousel-slide');
            const dots = document.querySelectorAll('.carousel-dots .dot');

            if (slides.length === 0 || index === currentSlideIndex) return;

            const direction = index > currentSlideIndex ? 1 : -1;
            const prevIndex = currentSlideIndex;
            currentSlideIndex = index;

            // Remove all positioning classes
            slides.forEach(slide => {
                slide.classList.remove('active', 'prev');
            });

            // Set new positions
            if (direction > 0) {
                slides[prevIndex].classList.add('prev');
                slides[currentSlideIndex].classList.add('active');
            } else {
                slides[currentSlideIndex].style.transition = 'none';
                slides[currentSlideIndex].style.left = '-100%';

                setTimeout(() => {
                    slides[currentSlideIndex].style.transition = '';
                    slides[prevIndex].style.left = '100%';
                    slides[currentSlideIndex].classList.add('active');
                }, 10);
            }

            // Update dots
            if (dots.length > 0) {
                dots.forEach(dot => dot.classList.remove('active'));
                if (dots[currentSlideIndex]) dots[currentSlideIndex].classList.add('active');
            }
        }

        // Auto-advance carousel
        if (document.querySelector('.carousel-container')) {
            const autoAdvanceTime = <?php echo intval($carousel_config['auto_advance_time'] ?? 5000); ?>;
            setInterval(() => moveCarousel(1), autoAdvanceTime);
        }
    </script>

    <!-- Cart Validator -->
    <script src="/includes/cart-validator.js"></script>

    <!-- Carousel JS -->
    <script src="/includes/carousel.js"></script>

    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>
</body>
</html>
