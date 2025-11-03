<?php
/**
 * Favorites / Wishlist Page
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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Favoritos - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            color: #2c3e50;
        }

        .share-wishlist {
            padding: 12px 24px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .share-wishlist:hover {
            background: #f5f7ff;
        }

        .favorites-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h2 {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .empty-state p {
            margin-bottom: 30px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }

        .product-card {
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
            border-color: #e74c3c;
        }

        .remove-favorite {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .remove-favorite:hover {
            background: #e74c3c;
            color: white;
            transform: scale(1.1);
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

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 20px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
            cursor: pointer;
            min-height: 48px;
        }

        .product-name:hover {
            color: #667eea;
        }

        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }

        .product-stock {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }

        .stock-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
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

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f5f7ff;
        }

        .btn-large {
            padding: 15px 30px;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
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
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .product-image {
                height: 180px;
            }

            .product-actions {
                flex-direction: column;
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
                <a href="/favoritos.php">Favoritos (<span id="favorites-count">0</span>)</a>
                <a href="/carrito.php">Carrito (<span id="cart-count">0</span>)</a>
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
                <a href="/" class="btn btn-primary btn-large">Explorar Productos</a>
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
                    <a href="/" class="btn btn-primary btn-large">Explorar Productos</a>
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

            const existingItem = cart.find(item => item.id === productId);

            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: productId,
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
            window.location.href = '/producto.php?slug=' + encodeURIComponent(slug);
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
    <script src="/includes/cart-validator.js"></script>
    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>
</body>
</html>
