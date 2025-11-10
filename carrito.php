<?php
/**
 * Shopping Cart Page
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

// Get site configuration
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
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

// Check for messages
$cart_message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'expired') {
    $cart_message = 'Tu carrito ha expirado por inactividad (4 horas). Por favor, agrega los productos nuevamente.';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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
                <a href="<?php echo url('/carrito.php'); ?>">Carrito (<span id="cart-count">0</span>)</a>
            </nav>
        </div>
    </header>

    <?php if ($cart_message): ?>
    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px auto; max-width: 1200px; border-radius: 4px;">
        <strong>⚠️ Aviso:</strong> <?php echo htmlspecialchars($cart_message); ?>
    </div>
    <?php endif; ?>

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
                        <a href="<?php echo url('/'); ?>" class="btn">Ir a la Tienda</a>
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
                <a href="<?php echo url('/'); ?>" class="continue-shopping">Continuar Comprando</a>
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
                    localStorage.removeItem('applied_coupon');
                    console.log('Cart expired and cleared after 4 hours of inactivity');
                    return true;
                }
            }
            return false;
        }

        // Check cart expiration on page load
        if (checkCartExpiration()) {
            window.location.href = '/carrito.php?msg=expired';
        }

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
            let validItems = [];

            cartData.items.forEach(item => {
                const productId = item.product_id || item.id;
                const product = products.find(p => p.id === productId);

                if (!product) {
                    console.warn('Product not found:', productId);
                    return; // Skip invalid products
                }

                validItems.push(item);

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

            // Clean invalid items from localStorage
            if (validItems.length !== cartData.items.length) {
                console.warn('Cleaning invalid items from cart');
                cartData.items = validItems;
                saveCart();
            }

            // Check if we have any valid items to display
            if (html === '' || validItems.length === 0) {
                document.getElementById('cartItemsContainer').innerHTML = '<p>Tu carrito está vacío</p>';
                document.getElementById('cartSummary').style.display = 'none';
                updateCartCount();
                return;
            }

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
            localStorage.setItem('cart_timestamp', Date.now().toString());
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
                    credentials: 'same-origin',
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
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
