<?php
/**
 * Shopping Cart Page
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

// Get site configuration
$site_config = read_json(__DIR__ . '/config/site.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Handle currency change
if (isset($_POST['change_currency'])) {
    $new_currency = $_POST['currency'] ?? 'ARS';
    if (in_array($new_currency, ['ARS', 'USD'])) {
        $_SESSION['currency'] = $new_currency;
        $selected_currency = $new_currency;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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
            position: sticky;
            top: 0;
            z-index: 100;
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

        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h1 {
            font-size: 32px;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        /* Cart Items */
        .cart-items {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-cart h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .empty-cart p {
            margin-bottom: 30px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 2px solid #f0f0f0;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            background: #f5f5f5;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .item-price {
            font-size: 20px;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .item-stock {
            font-size: 13px;
            color: #666;
        }

        .stock-warning {
            color: #e74c3c;
            font-weight: 600;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            background: #667eea;
            color: white;
        }

        .quantity-btn:disabled {
            border-color: #ccc;
            color: #ccc;
            cursor: not-allowed;
        }

        .quantity-value {
            width: 50px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }

        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .remove-btn:hover {
            background: #c0392b;
        }

        /* Summary */
        .cart-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .summary-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        /* Currency Toggle Buttons */
        .currency-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .currency-btn {
            flex: 1;
            padding: 8px 16px;
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .currency-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .currency-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
        }

        .coupon-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .coupon-input {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .coupon-input input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .coupon-input button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .coupon-input button:hover {
            background: #5568d3;
        }

        .coupon-applied {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .coupon-remove {
            background: none;
            border: none;
            color: #155724;
            cursor: pointer;
            font-size: 18px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 15px;
        }

        .summary-row.promotion {
            color: #27ae60;
        }

        .summary-row.discount {
            color: #e74c3c;
        }

        .summary-row.total {
            font-size: 24px;
            font-weight: bold;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            color: #2c3e50;
        }

        .checkout-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .continue-shopping {
            width: 100%;
            padding: 12px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .continue-shopping:hover {
            background: #f5f7ff;
        }

        .btn {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #27ae60;
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 10001;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .toast.show {
            display: block;
        }

        .toast.error {
            background: #e74c3c;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
                gap: 15px;
            }

            .item-image {
                width: 80px;
                height: 80px;
            }

            .item-actions {
                grid-column: 2;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .item-name {
                font-size: 16px;
            }

            .currency-btn {
                padding: 6px 12px;
                font-size: 13px;
            }

            .item-price {
                font-size: 18px;
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
                <a href="/carrito.php">Carrito (<span id="cart-count">0</span>)</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <h1>🛒 Carrito de Compras</h1>

        <div class="cart-layout">
            <!-- Cart Items -->
            <div class="cart-items">
                <div id="cartItemsContainer">
                    <div class="empty-cart">
                        <h2>Tu carrito está vacío</h2>
                        <p>Agrega productos para comenzar tu compra</p>
                        <a href="/" class="btn">Ir a la Tienda</a>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="cart-summary" id="cartSummary" style="display: none;">
                <h2 class="summary-title">Resumen de Compra</h2>

                <!-- Currency Toggle Buttons -->
                <div class="currency-toggle" id="currency-toggle">
                    <button class="currency-btn" data-currency="ARS" onclick="switchDisplayCurrency('ARS')">
                        💵 Pesos (ARS)
                    </button>
                    <button class="currency-btn" data-currency="USD" onclick="switchDisplayCurrency('USD')">
                        💵 Dólares (USD)
                    </button>
                </div>

                <!-- Coupon -->
                <div class="coupon-section">
                    <div class="coupon-input">
                        <input type="text" id="couponCode" placeholder="Código de cupón">
                        <button onclick="applyCoupon()">Aplicar</button>
                    </div>
                    <div id="couponApplied" style="display: none;"></div>
                </div>

                <!-- Totals -->
                <div id="summaryTotals"></div>

                <!-- Actions -->
                <button class="checkout-btn" id="checkoutBtn" onclick="goToCheckout()">
                    Proceder al Checkout
                </button>
                <a href="/" class="continue-shopping">Continuar Comprando</a>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
        const CURRENCY = '<?php echo $selected_currency; ?>';
        const EXCHANGE_RATE = <?php echo $currency_config['exchange_rate']; ?>;
        let displayCurrency = 'ARS'; // Currency for display purposes only

        let cartData = {
            items: [],
            coupon: null,
            currency: CURRENCY
        };

        // Format product price intelligently
        function formatProductPrice(product, currency) {
            const priceArs = parseFloat(product.price_ars || 0);
            const priceUsd = parseFloat(product.price_usd || 0);

            // If product only has USD price
            if (priceUsd > 0 && priceArs == 0) {
                const calculatedArs = priceUsd * EXCHANGE_RATE;
                return 'U$D ' + priceUsd.toFixed(2).replace('.', ',') +
                       ' <span style="font-size: 0.85em; color: #666;">($ ' +
                       calculatedArs.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ')</span>';
            }

            // If product only has ARS price
            if (priceArs > 0 && priceUsd == 0) {
                return '$ ' + priceArs.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            // Product has both prices - use selected currency
            const price = currency === 'USD' ? priceUsd : priceArs;
            const symbol = currency === 'USD' ? 'U$D' : '$';
            return symbol + ' ' + price.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Determine if all products in cart are USD-only
        function areAllProductsUSD(products, cart) {
            for (const item of cart) {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);
                if (!product) continue;

                const priceArs = parseFloat(product.price_ars || 0);
                if (priceArs > 0) {
                    return false; // Found a product with ARS price
                }
            }
            return true; // All products are USD-only
        }

        // Get product price value for calculations
        function getProductPrice(product, currency) {
            const priceArs = parseFloat(product.price_ars || 0);
            const priceUsd = parseFloat(product.price_usd || 0);

            if (currency === 'USD') {
                // All products USD, use USD price
                return priceUsd;
            } else {
                // Mixed or all ARS
                if (priceArs > 0) {
                    return priceArs;
                } else {
                    // USD product in mixed cart, convert to ARS
                    return priceUsd * EXCHANGE_RATE;
                }
            }
        }

        // Load cart from localStorage
        function loadCart() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const coupon = JSON.parse(localStorage.getItem('applied_coupon') || 'null');

            console.log('Cart loaded from localStorage:', cart);

            cartData.items = cart;
            cartData.coupon = coupon;
            cartData.currency = CURRENCY;

            if (cart.length === 0) {
                document.getElementById('cartItemsContainer').innerHTML = `
                    <div class="empty-cart">
                        <h2>Tu carrito está vacío</h2>
                        <p>Agrega productos para comenzar tu compra</p>
                        <a href="/" class="btn">Ir a la Tienda</a>
                    </div>
                `;
                document.getElementById('cartSummary').style.display = 'none';
            } else {
                fetchCartProducts();
            }

            updateCartCount();
        }

        // Fetch product details for cart items
        async function fetchCartProducts() {
            const productIds = cartData.items.map(item => item.product_id || item.id);

            console.log('Fetching products for IDs:', productIds);

            try {
                const response = await fetch('/api/get_products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ product_ids: productIds })
                });

                console.log('API Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const products = await response.json();
                console.log('Products received from API:', products);

                // Validate cart items and remove unavailable products
                validateAndCleanCart(products);

                renderCart(products);
            } catch (error) {
                console.error('Error fetching products:', error);
                showToast('Error al cargar los productos del carrito', 'error');
                // Fallback: render with limited data
                renderCart([]);
            }
        }

        // Validate cart items and remove unavailable products
        function validateAndCleanCart(products) {
            const removedItems = [];
            const originalLength = cartData.items.length;

            // Filter out items that are not available
            cartData.items = cartData.items.filter(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);

                // Check if product exists, is active, and has stock
                if (!product) {
                    removedItems.push({ name: 'Producto no encontrado', reason: 'eliminado' });
                    return false;
                }

                if (!product.active) {
                    removedItems.push({ name: product.name, reason: 'no disponible' });
                    return false;
                }

                if (product.stock <= 0) {
                    removedItems.push({ name: product.name, reason: 'sin stock' });
                    return false;
                }

                // Adjust quantity if exceeds available stock
                if (item.quantity > product.stock) {
                    item.quantity = product.stock;
                }

                return true;
            });

            // If items were removed, save cart and show notification
            if (removedItems.length > 0) {
                saveCart();

                let message = 'Se eliminaron productos del carrito:\n';
                removedItems.forEach(item => {
                    message += `• ${item.name} (${item.reason})\n`;
                });

                // Show all removed items in a single toast
                setTimeout(() => {
                    showToast(removedItems.map(i => `${i.name}: ${i.reason}`).join(', '), 'error');
                }, 500);
            }
        }

        // Render cart items
        function renderCart(products) {
            if (cartData.items.length === 0) {
                loadCart();
                return;
            }

            console.log('Rendering cart with products:', products);

            // Determine currency based on cart contents
            const allUSD = areAllProductsUSD(products, cartData.items);
            const effectiveCurrency = allUSD ? 'USD' : 'ARS';

            // Initialize display currency to match cart currency
            displayCurrency = effectiveCurrency;

            // Update currency button states
            updateCurrencyButtons(effectiveCurrency);

            let html = '';

            cartData.items.forEach(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId) || {
                    name: 'Producto no encontrado',
                    price_ars: 0,
                    price_usd: 0,
                    stock: 0,
                    images: [],
                    thumbnail: ''
                };

                console.log('Product:', product.name, 'Thumbnail:', product.thumbnail, 'Images:', product.images);

                const price = getProductPrice(product, effectiveCurrency);
                const stockWarning = item.quantity > product.stock;

                // Use thumbnail or first image
                // Handle both string images array and object images array
                let imageUrl = product.thumbnail;
                if (!imageUrl && product.images && product.images.length > 0) {
                    // Check if images is array of objects or strings
                    if (typeof product.images[0] === 'object' && product.images[0].url) {
                        imageUrl = product.images[0].url;
                    } else if (typeof product.images[0] === 'string') {
                        imageUrl = product.images[0];
                    }
                }

                let imageHtml = '';
                if (imageUrl) {
                    imageHtml = '<img src="' + imageUrl + '" alt="' + product.name.replace(/"/g, '&quot;') + '">';
                } else {
                    imageHtml = '<div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">Sin imagen</div>';
                }

                html += `
                    <div class="cart-item">
                        <div class="item-image">
                            ${imageHtml}
                        </div>
                        <div class="item-details">
                            <div class="item-name">${product.name}</div>
                            <div class="item-price">${formatProductPrice(product, effectiveCurrency)} × ${item.quantity}</div>
                            <div class="item-stock ${stockWarning ? 'stock-warning' : ''}">
                                ${stockWarning ?
                                    '⚠️ Stock insuficiente (' + product.stock + ' disponibles)' :
                                    'Stock disponible: ' + product.stock
                                }
                            </div>
                        </div>
                        <div class="item-actions">
                            <div class="quantity-control">
                                <button class="quantity-btn" onclick="updateQuantity('${productId}', -1)">−</button>
                                <span class="quantity-value">${item.quantity}</span>
                                <button class="quantity-btn" onclick="updateQuantity('${productId}', 1)"
                                    ${item.quantity >= product.stock ? 'disabled' : ''}>+</button>
                            </div>
                            <button class="remove-btn" onclick="removeItem('${productId}')">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                `;
            });

            document.getElementById('cartItemsContainer').innerHTML = html;
            document.getElementById('cartSummary').style.display = 'block';

            calculateTotals(products);

            if (cartData.coupon) {
                showAppliedCoupon(cartData.coupon);
            }
        }

        // Update quantity
        function updateQuantity(productId, delta) {
            const item = cartData.items.find(i => (i.product_id || i.id) === productId);
            if (!item) return;

            item.quantity += delta;

            if (item.quantity <= 0) {
                removeItem(productId);
                return;
            }

            saveCart();
            loadCart();
        }

        // Remove item
        function removeItem(productId) {
            cartData.items = cartData.items.filter(i => (i.product_id || i.id) !== productId);
            saveCart();
            loadCart();
            showToast('Producto eliminado del carrito');
        }

        // Save cart to localStorage
        function saveCart() {
            localStorage.setItem('cart', JSON.stringify(cartData.items));
        }

        // Calculate totals
        function calculateTotals(products) {
            let subtotal = 0;
            let promotionDiscount = 0;
            let couponDiscount = 0;

            // Determine currency based on cart contents
            const allUSD = areAllProductsUSD(products, cartData.items);
            const effectiveCurrency = allUSD ? 'USD' : 'ARS';

            // Update display currency and buttons if not manually changed
            if (!displayCurrency || displayCurrency === 'ARS' || displayCurrency === 'USD') {
                displayCurrency = effectiveCurrency;
                updateCurrencyButtons(effectiveCurrency);
            }

            cartData.items.forEach(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);
                if (!product) return;

                const price = getProductPrice(product, effectiveCurrency);
                subtotal += price * item.quantity;
            });

            // Apply coupon if exists
            if (cartData.coupon) {
                if (cartData.coupon.type === 'percentage') {
                    couponDiscount = subtotal * (cartData.coupon.value / 100);
                } else {
                    couponDiscount = cartData.coupon.value;
                }
            }

            const total = subtotal - promotionDiscount - couponDiscount;

            // Calculate in both currencies
            let subtotalARS, subtotalUSD, couponDiscountARS, couponDiscountUSD, totalARS, totalUSD;

            if (effectiveCurrency === 'USD') {
                subtotalUSD = subtotal;
                subtotalARS = subtotal * EXCHANGE_RATE;
                couponDiscountUSD = couponDiscount;
                couponDiscountARS = couponDiscount * EXCHANGE_RATE;
                totalUSD = total;
                totalARS = total * EXCHANGE_RATE;
            } else {
                subtotalARS = subtotal;
                subtotalUSD = subtotal / EXCHANGE_RATE;
                couponDiscountARS = couponDiscount;
                couponDiscountUSD = couponDiscount / EXCHANGE_RATE;
                totalARS = total;
                totalUSD = total / EXCHANGE_RATE;
            }

            // Format price based on display currency
            function formatDisplayPrice(ars, usd) {
                const amount = displayCurrency === 'ARS' ? ars : usd;
                const symbol = displayCurrency === 'ARS' ? '$' : 'U$D';
                return `${symbol} ${amount.toFixed(2)}`;
            }

            let html = '';

            if (promotionDiscount > 0) {
                html += `
                    <div class="summary-row promotion">
                        <span>Descuento promoción:</span>
                        <span>-${formatDisplayPrice(promotionDiscount, promotionDiscount / EXCHANGE_RATE)}</span>
                    </div>
                `;
            }

            if (couponDiscount > 0) {
                html += `
                    <div class="summary-row discount">
                        <span>Cupón "${cartData.coupon.code}":</span>
                        <span id="discount-display" data-ars="${couponDiscountARS.toFixed(2)}" data-usd="${couponDiscountUSD.toFixed(2)}">
                            -${formatDisplayPrice(couponDiscountARS, couponDiscountUSD)}
                        </span>
                    </div>
                `;
            }

            html += `
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="total-display" data-ars="${totalARS.toFixed(2)}" data-usd="${totalUSD.toFixed(2)}">
                        ${formatDisplayPrice(totalARS, totalUSD)}
                    </span>
                </div>
            `;

            document.getElementById('summaryTotals').innerHTML = html;
        }

        // Apply coupon
        async function applyCoupon() {
            const code = document.getElementById('couponCode').value.trim().toUpperCase();

            if (!code) {
                showToast('Ingresa un código de cupón', 'error');
                return;
            }

            try {
                const response = await fetch('/api/validate_coupon.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ code: code })
                });

                const result = await response.json();

                if (result.valid) {
                    cartData.coupon = result.coupon;
                    localStorage.setItem('applied_coupon', JSON.stringify(result.coupon));
                    showAppliedCoupon(result.coupon);
                    loadCart();
                    showToast('✅ Cupón aplicado correctamente');
                } else {
                    showToast('❌ ' + result.message, 'error');
                }
            } catch (error) {
                console.error('Error validating coupon:', error);
                showToast('Error al validar el cupón', 'error');
            }
        }

        // Show applied coupon
        function showAppliedCoupon(coupon) {
            document.getElementById('couponApplied').innerHTML = `
                <div class="coupon-applied">
                    <span>✅ Cupón "${coupon.code}" aplicado</span>
                    <button class="coupon-remove" onclick="removeCoupon()">×</button>
                </div>
            `;
            document.getElementById('couponApplied').style.display = 'block';
            document.querySelector('.coupon-input').style.display = 'none';
        }

        // Remove coupon
        function removeCoupon() {
            cartData.coupon = null;
            localStorage.removeItem('applied_coupon');
            document.getElementById('couponApplied').style.display = 'none';
            document.querySelector('.coupon-input').style.display = 'flex';
            document.getElementById('couponCode').value = '';
            loadCart();
            showToast('Cupón removido');
        }

        // Update currency button states
        function updateCurrencyButtons(currency) {
            document.querySelectorAll('.currency-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector(`.currency-btn[data-currency="${currency}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }

        // Switch display currency without reloading page
        function switchDisplayCurrency(currency) {
            displayCurrency = currency;

            // Update button states
            updateCurrencyButtons(currency);

            // Format price function
            function formatPrice(amount, curr) {
                if (curr === 'ARS') {
                    return '$ ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                } else {
                    return 'U$D ' + parseFloat(amount).toFixed(2).replace('.', ',');
                }
            }

            // Update discount
            const discountEl = document.getElementById('discount-display');
            if (discountEl) {
                const discount = currency === 'ARS' ? discountEl.dataset.ars : discountEl.dataset.usd;
                discountEl.textContent = '-' + formatPrice(discount, currency);
            }

            // Update total
            const totalEl = document.getElementById('total-display');
            if (totalEl) {
                const total = currency === 'ARS' ? totalEl.dataset.ars : totalEl.dataset.usd;
                totalEl.textContent = formatPrice(total, currency);
            }
        }

        // Go to checkout
        async function goToCheckout() {
            // Sync cart to PHP session first
            try {
                const cart = JSON.parse(localStorage.getItem('cart') || '[]');

                if (cart.length === 0) {
                    showToast('El carrito está vacío', 'error');
                    return;
                }

                // Prepare cart data for API
                const syncData = {
                    cart: cart,
                    coupon_code: cartData.coupon ? cartData.coupon.code : null
                };

                // Sync to session
                const response = await fetch('/api/sync_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(syncData)
                });

                if (!response.ok) {
                    throw new Error('Failed to sync cart');
                }

                const result = await response.json();

                if (result.success) {
                    // Redirect to checkout
                    window.location.href = '/checkout.php';
                } else {
                    showToast('Error al procesar el carrito', 'error');
                }
            } catch (error) {
                console.error('Error syncing cart:', error);
                showToast('Error al procesar el carrito', 'error');
            }
        }

        // Update cart count
        function updateCartCount() {
            const count = cartData.items.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Show toast
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show ' + (type === 'error' ? 'error' : '');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Initialize
        loadCart();
    </script>

    <!-- Fallback API endpoint (simple version) -->
    <script>
        // Simple fallback if API doesn't exist yet
        if (!window.fetch) {
            console.error('Fetch API not supported');
        }
    </script>
    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>
</body>
</html>
