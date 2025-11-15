<?php
/**
 * Search Page with Filters
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

// Get search query and filters
$query = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$in_stock_only = isset($_GET['in_stock']) && $_GET['in_stock'] === '1';

// Build filters array
$filters = [
    'active_only' => true,
    'sort' => $sort
];

if ($min_price !== null) {
    $filters['min_price'] = $min_price;
}

if ($max_price !== null) {
    $filters['max_price'] = $max_price;
}

if ($in_stock_only) {
    $filters['in_stock'] = true;
}

// Perform search
$results = search_products($query, $filters);

// Filter out products that are out of stock and should be hidden
$results = array_filter($results, function($product) {
    // Show product if it has stock OR if hide_when_out_of_stock is not set/false
    $hide_when_no_stock = $product['hide_when_out_of_stock'] ?? false;
    if ($hide_when_no_stock && $product['stock'] <= 0) {
        return false; // Hide this product
    }
    return true; // Show this product
});

// Get site configuration
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Get all products for price range calculation
$all_products = get_all_products(true);
$prices = array_map(function($p) {
    return $p['price_ars'];
}, $all_products);

$absolute_min = !empty($prices) ? min($prices) : 0;
$absolute_max = !empty($prices) ? max($prices) : 10000;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar<?php echo !empty($query) ? ' - ' . htmlspecialchars($query) : ''; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

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

            <div class="search-box">
                <form method="GET" action="<?php echo url('/buscar.php'); ?>">
                    <input type="text"
                           name="q"
                           placeholder="Buscar productos..."
                           value="<?php echo htmlspecialchars($query); ?>"
                           autofocus>
                    <button type="submit">🔍 Buscar</button>
                </form>
            </div>

            <nav class="nav">
                <a href="<?php echo url('/'); ?>">Inicio</a>
                <a href="<?php echo url('/favoritos.php'); ?>">Favoritos</a>
                <a href="<?php echo url('/carrito.php'); ?>">Carrito (<span id="cart-count">0</span>)</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="search-header">
            <h1>
                <?php if (!empty($query)): ?>
                    Resultados para "<?php echo htmlspecialchars($query); ?>"
                <?php else: ?>
                    Todos los Productos
                <?php endif; ?>
            </h1>
            <p>Se encontraron <?php echo count($results); ?> productos</p>
        </div>

        <div class="search-layout">
            <!-- Filters -->
            <aside class="filters">
                <h2>🔍 Filtros</h2>

                <form method="GET" action="<?php echo url('/buscar.php'); ?>">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">

                    <!-- Sort -->
                    <div class="filter-group">
                        <h3>Ordenar por</h3>
                        <select name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Más nuevos</option>
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Mejores valorados</option>
                        </select>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-group">
                        <h3>Rango de Precio (ARS)</h3>
                        <input type="number"
                               name="min_price"
                               placeholder="Mínimo"
                               min="0"
                               value="<?php echo $min_price !== null ? $min_price : ''; ?>">
                        <input type="number"
                               name="max_price"
                               placeholder="Máximo"
                               min="0"
                               value="<?php echo $max_price !== null ? $max_price : ''; ?>">
                        <small style="color: #999; font-size: 12px;">
                            Rango: $<?php echo number_format($absolute_min, 0, ',', '.'); ?> - $<?php echo number_format($absolute_max, 0, ',', '.'); ?>
                        </small>
                    </div>

                    <!-- Availability -->
                    <div class="filter-group">
                        <h3>Disponibilidad</h3>
                        <label>
                            <input type="checkbox" name="in_stock" value="1" <?php echo $in_stock_only ? 'checked' : ''; ?>>
                            <span>Solo con stock</span>
                        </label>
                    </div>

                    <button type="submit" class="apply-filters">Aplicar Filtros</button>
                    <a href="<?php echo url('/buscar.php?q=' . urlencode($query)); ?>" class="clear-filters">Limpiar Filtros</a>
                </form>
            </aside>

            <!-- Results -->
            <div class="results">
                <?php if (empty($results)): ?>
                    <div class="no-results">
                        <h2>No se encontraron resultados</h2>
                        <?php if (!empty($query)): ?>
                            <p>No encontramos productos que coincidan con "<?php echo htmlspecialchars($query); ?>"</p>
                            <p>Intenta con otros términos de búsqueda o <a href="<?php echo url('/buscar.php'); ?>">explora todos los productos</a></p>
                        <?php else: ?>
                            <p>No hay productos disponibles en este momento.</p>
                            <a href="<?php echo url('/'); ?>" class="btn">Volver al Inicio</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($results as $product): ?>
                            <div class="product-card" onclick="window.location.href='<?php echo url('/producto.php?slug=' . urlencode($product['slug'])); ?>'">
                                <div class="product-image">
                                    <?php if (!empty($product['thumbnail'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['thumbnail']); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        Sin imagen
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>

                                    <?php if ($product['rating_avg'] > 0): ?>
                                        <div class="rating">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $product['rating_avg'] ? '★' : '☆';
                                            }
                                            ?>
                                            (<?php echo $product['rating_count']; ?>)
                                        </div>
                                    <?php endif; ?>

                                    <div class="product-price">
                                        <?php echo format_product_price($product, $selected_currency); ?>
                                    </div>

                                    <div class="product-stock">
                                        <?php if ($product['stock'] === 0): ?>
                                            <span class="stock-badge stock-out">Sin stock</span>
                                        <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                                            <span class="stock-badge stock-low">¡Últimas unidades!</span>
                                        <?php else: ?>
                                            Stock: <?php echo $product['stock']; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

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

    <!-- Auto-update Exchange Rate -->
    <?php include __DIR__ . '/includes/auto-update-exchange.php'; ?>
</body>
</html>
