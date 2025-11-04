<?php
/**
 * Search Page with Filters
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
            gap: 30px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-decoration: none;
            white-space: nowrap;
        }

        .search-box {
            flex: 1;
            max-width: 500px;
        }

        .search-box form {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav a {
            color: #666;
            text-decoration: none;
            transition: color 0.3s;
            white-space: nowrap;
        }

        .nav a:hover {
            color: #333;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .search-header {
            margin-bottom: 30px;
        }

        .search-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .search-header p {
            color: #666;
            font-size: 16px;
        }

        .search-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        /* Filters Sidebar */
        .filters {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .filters h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .filter-group {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .filter-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .filter-group h3 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #2c3e50;
        }

        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .filter-group label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 0;
        }

        .filter-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .apply-filters {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .apply-filters:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .clear-filters {
            width: 100%;
            padding: 10px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .clear-filters:hover {
            background: #f5f7ff;
        }

        /* Results */
        .results {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .results-count {
            font-size: 16px;
            color: #666;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 25px;
        }

        .product-card {
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .product-image {
            width: 100%;
            height: 220px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 14px;
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
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
            min-height: 40px;
        }

        .product-price {
            font-size: 22px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
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

        .rating {
            color: #f39c12;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-results h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .no-results p {
            margin-bottom: 25px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-layout {
                grid-template-columns: 1fr;
            }

            .filters {
                position: static;
            }

            .header-content {
                flex-direction: column;
                gap: 15px;
            }

            .search-box {
                width: 100%;
                max-width: none;
            }

            .nav {
                width: 100%;
                justify-content: space-around;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }

            .product-image {
                height: 180px;
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

            <div class="search-box">
                <form method="GET" action="/buscar.php">
                    <input type="text"
                           name="q"
                           placeholder="Buscar productos..."
                           value="<?php echo htmlspecialchars($query); ?>"
                           autofocus>
                    <button type="submit">🔍 Buscar</button>
                </form>
            </div>

            <nav class="nav">
                <a href="/">Inicio</a>
                <a href="/favoritos.php">Favoritos</a>
                <a href="/carrito.php">Carrito (<span id="cart-count">0</span>)</a>
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

                <form method="GET" action="/buscar.php">
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
                    <a href="/buscar.php?q=<?php echo urlencode($query); ?>" class="clear-filters">Limpiar Filtros</a>
                </form>
            </aside>

            <!-- Results -->
            <div class="results">
                <?php if (empty($results)): ?>
                    <div class="no-results">
                        <h2>No se encontraron resultados</h2>
                        <?php if (!empty($query)): ?>
                            <p>No encontramos productos que coincidan con "<?php echo htmlspecialchars($query); ?>"</p>
                            <p>Intenta con otros términos de búsqueda o <a href="/buscar.php">explora todos los productos</a></p>
                        <?php else: ?>
                            <p>No hay productos disponibles en este momento.</p>
                            <a href="/" class="btn">Volver al Inicio</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($results as $product): ?>
                            <div class="product-card" onclick="window.location.href='/producto.php?slug=<?php echo urlencode($product['slug']); ?>'">
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
    <script src="/includes/cart-validator.js"></script>
    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>
</body>
</html>
