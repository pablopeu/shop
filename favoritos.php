<?php
/**
 * Favorites / Wishlist Page
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/theme-loader.php';
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
$footer_config = read_json(__DIR__ . '/config/footer.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Favoritos - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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
                <a href="<?php echo url('/favoritos.php'); ?>">Favoritos (<span id="favorites-count">0</span>)</a>
                <a href="<?php echo url('/carrito.php'); ?>">Carrito (<span id="cart-count">0</span>)</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1>❤️ Mis Favoritos</h1>
            <button class="share-wishlist" onclick="shareWishlist()">
                🔗 Compartir Mi Lista
            </button>
        </div>

        <div class="favorites-container" id="favoritesContainer">
            <div class="empty-state">
                <h2>💔</h2>
                <h3>No tienes favoritos aún</h3>
                <p>Agrega productos a tu lista de favoritos para verlos aquí</p>
                <a href="<?php echo url('/'); ?>" class="btn btn-primary btn-large">Explorar Productos</a>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
        const CURRENCY = '<?php echo $selected_currency; ?>';
        const EXCHANGE_RATE = <?php echo $currency_config['exchange_rate']; ?>;

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

        // Load favorites
        function loadFavorites() {
            const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');

            document.getElementById('favorites-count').textContent = favorites.length;

            if (favorites.length === 0) {
                showEmptyState();
                return;
            }

            fetchFavoriteProducts(favorites);
        }

        // Show empty state
        function showEmptyState() {
            document.getElementById('favoritesContainer').innerHTML = `
                <div class="empty-state">
                    <h2>💔</h2>
                    <h3>No tienes favoritos aún</h3>
                    <p>Agrega productos a tu lista de favoritos para verlos aquí</p>
                    <a href="<?php echo url('/'); ?>" class="btn btn-primary btn-large">Explorar Productos</a>
                </div>
            `;
        }

        // Fetch favorite products
        async function fetchFavoriteProducts(favoriteIds) {
            try {
                const response = await fetch('/api/get_products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ product_ids: favoriteIds })
                });

                const products = await response.json();
                renderFavorites(products);
            } catch (error) {
                console.error('Error fetching favorites:', error);
                showEmptyState();
            }
        }

        // Render favorites
        function renderFavorites(products) {
            if (products.length === 0) {
                showEmptyState();
                return;
            }

            let html = '<div class="products-grid">';

            products.forEach(product => {
                const inStock = product.stock > 0;

                html += `
                    <div class="product-card">
                        <button class="remove-favorite" onclick="removeFavorite('${product.id}')" title="Eliminar de favoritos">
                            ❤️
                        </button>

                        <div class="product-image" onclick="goToProduct('${product.slug}')">
                            ${product.images && product.images[0] ?
                                `<img src="${product.images[0].url}" alt="${product.name}">` :
                                '<div style="color: #999;">Sin imagen</div>'
                            }
                        </div>

                        <div class="product-info">
                            <div class="product-name" onclick="goToProduct('${product.slug}')">
                                ${product.name}
                            </div>

                            <div class="product-price">
                                ${formatProductPrice(product, CURRENCY)}
                            </div>

                            <div class="product-stock">
                                ${product.stock === 0 ?
                                    '<span class="stock-badge stock-out">Sin stock</span>' :
                                    product.stock <= product.stock_alert ?
                                        '<span class="stock-badge stock-low">¡Últimas unidades!</span>' :
                                        'Stock disponible: ' + product.stock
                                }
                            </div>

                            ${product.pickup_only ? '<div class="pickup-only-badge">🏪 Solo retiro en persona</div>' : ''}

                            <div class="product-actions">
                                <button class="btn btn-primary"
                                        onclick="addToCart('${product.id}')"
                                        ${!inStock ? 'disabled' : ''}>
                                    ${!inStock ? '🚫 Agotado' : '🛒 Agregar'}
                                </button>
                                <button class="btn btn-secondary" onclick="goToProduct('${product.slug}')">
                                    👁️ Ver
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            document.getElementById('favoritesContainer').innerHTML = html;
        }

        // Remove from favorites
        function removeFavorite(productId) {
            let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            favorites = favorites.filter(id => id !== productId);
            localStorage.setItem('favorites', JSON.stringify(favorites));

            loadFavorites();
            showToast('💔 Eliminado de favoritos');
        }

        // Add to cart
        function addToCart(productId) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');

            // Check if product already in cart (support both formats)
            const existingItem = cart.find(item => (item.product_id || item.id) === productId);

            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    product_id: productId,
                    quantity: 1,
                    added_at: new Date().toISOString()
                });
            }

            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
            showToast('✅ Producto agregado al carrito');
        }

        // Go to product
        function goToProduct(slug) {
            window.location.href = '<?php echo url('/producto.php?slug='); ?>' + encodeURIComponent(slug);
        }

        // Share wishlist
        function shareWishlist() {
            const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');

            if (favorites.length === 0) {
                showToast('⚠️ Tu lista de favoritos está vacía');
                return;
            }

            // Create shareable URL with favorite IDs
            const favoritesParam = favorites.join(',');
            const shareUrl = window.location.origin + '/favoritos.php?shared=' + encodeURIComponent(favoritesParam);

            // Copy to clipboard
            navigator.clipboard.writeText(shareUrl).then(() => {
                showToast('🔗 Link de tu lista copiado al portapapeles');
            }).catch(() => {
                // Fallback: show the URL
                prompt('Comparte este link:', shareUrl);
            });
        }

        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Show toast
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Check if shared wishlist
        const urlParams = new URLSearchParams(window.location.search);
        const sharedFavorites = urlParams.get('shared');

        if (sharedFavorites) {
            // Load shared favorites
            const sharedIds = sharedFavorites.split(',');
            localStorage.setItem('favorites', JSON.stringify(sharedIds));
            showToast('✅ Lista de favoritos compartida cargada');

            // Remove query parameter from URL
            window.history.replaceState({}, document.title, '/favoritos.php');
        }

        // Initialize
        loadFavorites();
        updateCartCount();
    </script>

    <!-- Cart Validator -->
    <script src="<?php echo url('/includes/cart-validator.js'); ?>"></script>
    <!-- Mobile Menu -->
    <?php include __DIR__ . '/includes/mobile-menu.php'; ?>
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
