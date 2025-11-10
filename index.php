<?php
/**
 * Home Page - Public Site
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/theme-loader.php';

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
$footer_config = read_json(__DIR__ . '/config/footer.json');

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

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Carousel CSS -->
    <link rel="stylesheet" href="<?php echo url('/includes/carousel.css'); ?>">

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

    <?php if ($show_hero): ?>
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
                <a href="<?php echo url('/admin/login.php'); ?>" class="btn">Ir al Admin Panel</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image" onclick="window.location.href='<?php echo url('/producto.php?slug=' . urlencode($product['slug'])); ?>'">
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
                                <button class="btn btn-secondary" onclick="window.location.href='<?php echo url('/producto.php?slug=' . urlencode($product['slug'])); ?>'" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
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
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- WhatsApp Button -->
    <?php if ($site_config['whatsapp']['enabled']): ?>
    <?php
    // Priorizar custom_link si está configurado, sino usar number
    if (!empty($site_config['whatsapp']['custom_link'])) {
        $whatsapp_url = $site_config['whatsapp']['custom_link'];
    } else {
        $whatsapp_number = preg_replace('/[^0-9]/', '', $site_config['whatsapp']['number']);
        $whatsapp_message = urlencode($site_config['whatsapp']['message']);
        $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_message;
    }
    ?>
    <a href="<?php echo htmlspecialchars($whatsapp_url); ?>"
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
            <button onclick="goToCheckout()" class="btn" style="width: 100%; text-align: center; border: none; cursor: pointer;">Ver Carrito Completo</button>
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

            // Save to localStorage with timestamp
            saveCart(cart);

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

        // Save cart with timestamp
        function saveCart(cart) {
            localStorage.setItem('cart', JSON.stringify(cart));
            localStorage.setItem('cart_timestamp', Date.now().toString());
        }

        // Check if cart has expired (after 4 hours of inactivity)
        function checkCartExpiration() {
            const EXPIRATION_TIME = 4 * 60 * 60 * 1000; // 4 hours in milliseconds
            const timestamp = localStorage.getItem('cart_timestamp');

            if (timestamp) {
                const elapsed = Date.now() - parseInt(timestamp);
                if (elapsed > EXPIRATION_TIME) {
                    // Cart expired, clear it
                    localStorage.removeItem('cart');
                    localStorage.removeItem('cart_timestamp');
                    console.log('Cart expired and cleared after 4 hours of inactivity');
                    return true;
                }
            }
            return false;
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

            saveCart(cart);
            updateCartBadge();
            renderCartPanel();
        }

        function removeFromCart(productId) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            cart = cart.filter(i => i.product_id !== productId);
            saveCart(cart);
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
                const response = await fetch('<?php echo url('/api/sync_cart.php'); ?>', {
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
                    // Redirect to cart page (full view)
                    window.location.href = '<?php echo url('/carrito.php'); ?>';
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
            // Check if cart has expired
            checkCartExpiration();

            updateCartBadge();
            renderCartPanel();
        });
    </script>

    <!-- Cart Validator -->
    <script src="<?php echo url('/includes/cart-validator.js'); ?>"></script>

    <!-- Carousel JS -->
    <script src="<?php echo url('/includes/carousel.js'); ?>"></script>

    <!-- Mobile Menu -->
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>
</body>
</html>
