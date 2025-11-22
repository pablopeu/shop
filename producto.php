<?php
/**
 * Product Detail Page
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

// Get product by slug
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    redirect(url('/'));
}

$product = get_product_by_slug($slug);

if (!$product) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Producto no encontrado</title></head><body><h1>Producto no encontrado</h1><a href="' . url('/') . '">Volver al inicio</a>
    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body></html>';
    exit;
}

// Get site configuration
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Get reviews for this product
$reviews_data = read_json(__DIR__ . '/data/reviews.json');
$product_reviews = array_filter($reviews_data['reviews'] ?? [], function($r) use ($product) {
    return $r['product_id'] === $product['id'] && $r['status'] === 'approved';
});

// Sort reviews by date (newest first)
usort($product_reviews, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get all products for cart panel
$all_products = get_all_products(true); // Only active products

// Get only last 5 reviews for display
$display_reviews = array_slice($product_reviews, 0, 5);

// Calculate average rating
$total_rating = 0;
foreach ($product_reviews as $review) {
    $total_rating += $review['rating'];
}
$avg_rating = count($product_reviews) > 0 ? round($total_rating / count($product_reviews), 1) : 0;

// Get promotion if applicable
$promotions_data = read_json(__DIR__ . '/data/promotions.json');
$active_promotion = null;
$discounted_price = null;

foreach ($promotions_data['promotions'] ?? [] as $promo) {
    if (!$promo['active']) continue;

    // Check if promotion is valid by date
    $now = time();
    if (!empty($promo['start_date']) && strtotime($promo['start_date']) > $now) continue;
    if (!empty($promo['end_date']) && strtotime($promo['end_date']) < $now) continue;

    // Check if applies to this product
    if ($promo['scope'] === 'all' || in_array($product['id'], $promo['products'] ?? [])) {
        $active_promotion = $promo;

        $price = $product['price_' . strtolower($selected_currency)];
        if ($promo['type'] === 'percentage') {
            $discounted_price = $price * (1 - $promo['value'] / 100);
        } else {
            $discounted_price = $price - $promo['value'];
        }
        break;
    }
}

// Meta tags for SEO
$page_title = $product['seo']['title'] ?? $product['name'] . ' - ' . $site_config['site_name'];
$page_description = $product['seo']['description'] ?? substr($product['description'], 0, 160);

// Handle both image formats: array of strings or array of objects
$first_image = '';
if (!empty($product['images'])) {
    if (is_array($product['images'][0])) {
        $first_image = $product['images'][0]['url'] ?? '';
    } else {
        $first_image = $product['images'][0];
    }
}
$og_image = $first_image ? get_base_url() . $first_image : '';

// Record visit
$visits_file = __DIR__ . '/data/visits.json';
$visits_data = read_json($visits_file);
if (!isset($visits_data['products'][$product['id']])) {
    $visits_data['products'][$product['id']] = [
        'total_visits' => 0,
        'last_visit' => null
    ];
}
$visits_data['products'][$product['id']]['total_visits']++;
$visits_data['products'][$product['id']]['last_visit'] = get_timestamp();
write_json($visits_file, $visits_data);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($product['seo']['keywords'] ?? ''); ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($product['name']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:url" content="<?php echo get_base_url() . '/producto.php?slug=' . urlencode($slug); ?>">
    <meta property="og:type" content="product">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($product['name']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/includes/mobile-menu.css'); ?>">

    <!-- Product Page Styles -->
    <style>
        /* Product Title with Favorite Heart */
        .product-title-container {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .product-title-container h1 {
            flex: 1;
            margin-bottom: 0 !important;
        }

        .favorite-heart {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 28px;
            color: var(--color-text-light, #999);
            transition: all 0.3s;
            padding: 5px;
            margin-top: 5px;
        }

        .favorite-heart:hover {
            color: #e74c3c;
            transform: scale(1.2);
        }

        .favorite-heart.active {
            color: #e74c3c;
        }

        .favorite-heart.active i {
            font-weight: 900;
        }

        .favorite-heart.active::before {
            content: '';
        }

        /* Compact Price */
        .price-container {
            margin-bottom: 12px !important;
        }

        /* Stock Info */
        .stock-info {
            padding: 10px 15px !important;
            margin-bottom: 8px !important;
        }

        /* Pickup Only Badge */
        .pickup-only-badge {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 12px;
            display: inline-block;
        }

        /* Rating Container */
        .rating-container {
            margin-bottom: 20px !important;
        }

        /* Description Box */
        .description-box {
            background: var(--color-bg-light, #f8f9fa);
            border: 1px solid var(--color-border, #e0e0e0);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .description-box h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--color-primary, #2c3e50);
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--color-secondary, #d4af37);
        }

        .description-box p {
            font-size: 15px;
            line-height: 1.7;
            color: var(--color-text, #555);
            margin: 0;
        }

        /* Product Actions Container */
        .product-actions-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        /* Main Action Row - Quantity + Add to Cart */
        .main-action-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 0;
            border: 2px solid var(--color-primary, #2c3e50);
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-btn {
            background: var(--color-primary, #2c3e50);
            color: white;
            border: none;
            width: 40px;
            height: 48px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quantity-btn:hover:not(:disabled) {
            background: var(--color-secondary, #d4af37);
            color: var(--color-primary, #2c3e50);
        }

        .quantity-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.5;
        }

        #quantity-input {
            width: 60px;
            height: 48px;
            border: none;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            -moz-appearance: textfield;
        }

        #quantity-input::-webkit-outer-spin-button,
        #quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        #quantity-input:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }

        .btn-add-cart {
            flex: 1;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            height: 48px;
        }

        /* Share Section - Compact */
        .share-section {
            display: flex;
            align-items: center;
        }

        .share-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .share-btn {
            width: 42px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            text-decoration: none;
            background: var(--color-text-lighter, #e0e0e0);
            color: var(--color-text, #666);
        }

        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .share-btn.copy:hover {
            background: #6c757d;
            color: white;
        }

        .share-btn.facebook:hover {
            background: #1877f2;
            color: white;
        }

        .share-btn.x-twitter:hover {
            background: #000000;
            color: white;
        }

        .share-btn.instagram:hover {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            color: white;
        }

        .share-btn.whatsapp:hover {
            background: #25D366;
            color: white;
        }

        .share-btn.telegram:hover {
            background: #0088cc;
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .product-actions-container {
                gap: 15px;
            }

            .main-action-row {
                flex-direction: column;
                width: 100%;
            }

            .quantity-selector {
                width: 100%;
                justify-content: center;
            }

            .btn-add-cart {
                width: 100%;
            }

            .share-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .description-box {
                padding: 15px;
            }

            .description-box h3 {
                font-size: 16px;
            }
        }
    </style>
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

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?php echo url('/'); ?>">Inicio</a> / <?php echo htmlspecialchars($product['name']); ?>
    </div>

    <!-- Product -->
    <div class="container">
        <div class="product-container">
            <div class="product-grid">
                <!-- Gallery -->
                <div class="gallery-container">
                    <div class="main-image-container" id="mainImageContainer">
                        <?php if (!empty($product['images'])): ?>
                            <?php
                            // Get first image URL and alt text
                            $first_img_url = is_array($product['images'][0]) ? ($product['images'][0]['url'] ?? '') : $product['images'][0];
                            $first_img_alt = is_array($product['images'][0]) ? ($product['images'][0]['alt'] ?? $product['name']) : $product['name'];
                            ?>
                            <img src="<?php echo htmlspecialchars(url($first_img_url)); ?>"
                                 alt="<?php echo htmlspecialchars($first_img_alt); ?>"
                                 class="main-image"
                                 id="mainImage">

                            <?php if (count($product['images']) > 1): ?>
                                <button class="gallery-nav prev" onclick="changeImage(-1)">‹</button>
                                <button class="gallery-nav next" onclick="changeImage(1)">›</button>
                            <?php endif; ?>

                            <div class="image-counter">
                                <span id="currentImageIndex">1</span>/<?php echo count($product['images']); ?>
                            </div>
                        <?php else: ?>
                            <div class="no-image">Sin imagen disponible</div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($product['images']) && count($product['images']) > 1): ?>
                    <div class="thumbnails">
                        <?php foreach ($product['images'] as $index => $image): ?>
                            <?php
                            $img_url = is_array($image) ? ($image['url'] ?? '') : $image;
                            $img_alt = is_array($image) ? ($image['alt'] ?? $product['name']) : $product['name'];
                            ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                 onclick="selectImage(<?php echo $index; ?>)">
                                <img src="<?php echo htmlspecialchars(url($img_url)); ?>"
                                     alt="<?php echo htmlspecialchars($img_alt); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Description Box -->
                    <div class="description-box">
                        <h3>Descripción</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <div class="product-title-container">
                        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                        <button class="favorite-heart" onclick="toggleFavorite('<?php echo $product['id']; ?>')" id="favorite-btn-<?php echo $product['id']; ?>" title="Agregar a favoritos">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>

                    <!-- Price -->
                    <div class="price-container">
                        <?php if ($active_promotion && $discounted_price): ?>
                            <div class="price-original">
                                <?php echo format_product_price($product, $selected_currency); ?>
                            </div>
                            <div class="price-current">
                                <?php echo format_price($discounted_price, $selected_currency); ?>
                            </div>
                            <span class="promotion-badge">
                                PROMOCIÓN: <?php echo $active_promotion['value']; ?><?php echo $active_promotion['type'] === 'percentage' ? '%' : '$'; ?> OFF
                            </span>
                        <?php else: ?>
                            <div class="price-current">
                                <?php echo format_product_price($product, $selected_currency); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stock -->
                    <div class="stock-info <?php
                        if ($product['stock'] === 0) {
                            echo 'stock-out';
                        } elseif ($product['stock'] <= $product['stock_alert']) {
                            echo 'stock-low';
                        } else {
                            echo 'stock-available';
                        }
                    ?>">
                        <?php if ($product['stock'] === 0): ?>
                            🚫 Producto agotado
                        <?php elseif ($product['stock'] <= $product['stock_alert']): ?>
                            ⚠️ ¡Últimas <?php echo $product['stock']; ?> unidades disponibles!
                        <?php else: ?>
                            ✅ Stock disponible (<?php echo $product['stock']; ?> unidades)
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($product['pickup_only'])): ?>
                        <div class="pickup-only-badge">
                            🏪 Solo retiro en persona
                        </div>
                    <?php endif; ?>

                    <!-- Rating -->
                    <div class="rating-container">
                        <div class="stars">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $avg_rating ? '★' : '☆';
                            }
                            ?>
                        </div>
                        <span class="rating-text">
                            <?php echo $avg_rating; ?> / 5 (<?php echo count($product_reviews); ?> reviews)
                        </span>
                    </div>

                    <!-- Quantity & Actions -->
                    <div class="product-actions-container">
                        <div class="main-action-row">
                            <div class="quantity-selector">
                                <button class="quantity-btn" onclick="decreaseQuantity()" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>-</button>
                                <input type="number"
                                       id="quantity-input"
                                       value="1"
                                       min="1"
                                       max="<?php echo $product['stock']; ?>"
                                       <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                                <button class="quantity-btn" onclick="increaseQuantity()" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>+</button>
                            </div>
                            <button class="btn btn-add-cart"
                                    onclick="addToCartWithQuantity('<?php echo $product['id']; ?>')"
                                    <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-shopping-cart"></i> <?php echo $product['stock'] === 0 ? 'Agotado' : 'Agregar al Carrito'; ?>
                            </button>
                        </div>

                        <div class="share-section">
                            <div class="share-buttons">
                                <button class="share-btn copy" onclick="copyLink()" title="Copiar enlace">
                                    <i class="fas fa-link"></i>
                                </button>
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_base_url() . '/producto.php?slug=' . $slug); ?>"
                                   class="share-btn facebook"
                                   target="_blank"
                                   title="Compartir en Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_base_url() . '/producto.php?slug=' . $slug); ?>&text=<?php echo urlencode($product['name']); ?>"
                                   class="share-btn x-twitter"
                                   target="_blank"
                                   title="Compartir en X (Twitter)">
                                    <i class="fab fa-x-twitter"></i>
                                </a>
                                <a href="https://www.instagram.com/"
                                   class="share-btn instagram"
                                   target="_blank"
                                   title="Compartir en Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' - ' . get_base_url() . '/producto.php?slug=' . $slug); ?>"
                                   class="share-btn whatsapp"
                                   target="_blank"
                                   title="Compartir en WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <?php if (!empty($site_config['telegram'])): ?>
                                <a href="https://t.me/share/url?url=<?php echo urlencode(get_base_url() . '/producto.php?slug=' . $slug); ?>&text=<?php echo urlencode($product['name']); ?>"
                                   class="share-btn telegram"
                                   target="_blank"
                                   title="Compartir en Telegram">
                                    <i class="fab fa-telegram-plane"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="reviews-section">
                <h2>⭐ Opiniones de Clientes</h2>

                <?php if (empty($display_reviews)): ?>
                    <div class="no-reviews">
                        <p>Aún no hay opiniones para este producto.</p>
                        <p>¡Sé el primero en dejar tu review!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($display_reviews as $review): ?>
                        <div class="review">
                            <div class="review-header">
                                <span class="review-author"><?php echo htmlspecialchars($review['user_name']); ?></span>
                                <span class="review-date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
                            </div>
                            <div class="review-rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $review['rating'] ? '★' : '☆';
                                }
                                ?>
                            </div>
                            <div class="review-text">
                                <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($product_reviews) > 5): ?>
                        <p style="text-align: center; margin-top: 20px; color: #666;">
                            Mostrando 5 de <?php echo count($product_reviews); ?> opiniones
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox(event)">
        <div class="lightbox-content">
            <button class="lightbox-close" onclick="closeLightbox(event)">×</button>
            <img src="" alt="" class="lightbox-image" id="lightboxImage">
            <?php if (!empty($product['images']) && count($product['images']) > 1): ?>
                <button class="lightbox-nav prev" onclick="changeImage(-1); event.stopPropagation();">‹</button>
                <button class="lightbox-nav next" onclick="changeImage(1); event.stopPropagation();">›</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
        // Base path for subdirectory support
        const basePath = '<?php echo BASE_PATH; ?>';

        // Current product data
        const productData = <?php
            $current_product = $product;
            if (isset($current_product['thumbnail'])) {
                $current_product['thumbnail'] = url($current_product['thumbnail']);
            }
            if (isset($current_product['images']) && is_array($current_product['images'])) {
                foreach ($current_product['images'] as &$img) {
                    if (is_array($img) && isset($img['url'])) {
                        $img['url'] = url($img['url']);
                    } elseif (is_string($img)) {
                        $img = url($img);
                    }
                }
                unset($img);
            }
            echo json_encode($current_product);
        ?>;

        // All products data for cart panel
        const products = <?php
            $products_for_js = json_decode(json_encode($all_products), true);
            foreach ($products_for_js as &$p) {
                if (isset($p['thumbnail'])) {
                    $p['thumbnail'] = url($p['thumbnail']);
                }
                if (isset($p['images']) && is_array($p['images'])) {
                    foreach ($p['images'] as &$img) {
                        if (is_array($img) && isset($img['url'])) {
                            $img['url'] = url($img['url']);
                        } elseif (is_string($img)) {
                            $img = url($img);
                        }
                    }
                    unset($img);
                }
            }
            unset($p);
            echo json_encode($products_for_js);
        ?>;

        // Product images data - normalize to consistent format
        const rawImages = <?php echo json_encode($product['images'] ?? []); ?>;
        const productImages = rawImages.map(img => {
            if (typeof img === 'string') {
                // Si la URL ya es absoluta (empieza con / o http), no agregar basePath
                const url = (img.startsWith('/') || img.startsWith('http')) ? img : basePath + img;
                return { url: url, alt: '<?php echo htmlspecialchars($product['name']); ?>' };
            }
            // Si la URL ya es absoluta, no agregar basePath
            const url = (img.url.startsWith('/') || img.url.startsWith('http')) ? img.url : basePath + img.url;
            return { ...img, url: url };
        });
        let currentImageIndex = 0;

        // Change image in gallery
        function changeImage(direction) {
            if (productImages.length === 0) return;

            currentImageIndex = (currentImageIndex + direction + productImages.length) % productImages.length;
            updateImage();
        }

        // Select specific image
        function selectImage(index) {
            currentImageIndex = index;
            updateImage();
        }

        // Update displayed image
        function updateImage() {
            const mainImage = document.getElementById('mainImage');
            const lightboxImage = document.getElementById('lightboxImage');
            const currentIndexEl = document.getElementById('currentImageIndex');

            if (mainImage && productImages[currentImageIndex]) {
                mainImage.src = productImages[currentImageIndex].url;
                mainImage.alt = productImages[currentImageIndex].alt;
            }

            if (lightboxImage && productImages[currentImageIndex]) {
                lightboxImage.src = productImages[currentImageIndex].url;
                lightboxImage.alt = productImages[currentImageIndex].alt;
            }

            if (currentIndexEl) {
                currentIndexEl.textContent = currentImageIndex + 1;
            }

            // Update thumbnail active state
            document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
                thumb.classList.toggle('active', index === currentImageIndex);
            });
        }

        // Open lightbox
        document.getElementById('mainImageContainer')?.addEventListener('click', function(e) {
            if (e.target.classList.contains('main-image')) {
                document.getElementById('lightbox').classList.add('active');
                updateImage();
            }
        });

        // Close lightbox
        function closeLightbox(event) {
            if (event.target.id === 'lightbox' || event.target.classList.contains('lightbox-close')) {
                document.getElementById('lightbox').classList.remove('active');
            }
        }

        // ESC key to close lightbox
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('lightbox').classList.remove('active');
            }
            if (e.key === 'ArrowLeft') {
                changeImage(-1);
            }
            if (e.key === 'ArrowRight') {
                changeImage(1);
            }
        });

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

        // Check cart expiration on page load
        checkCartExpiration();

        // Add to cart
        // Increase quantity
        function increaseQuantity() {
            const input = document.getElementById('quantity-input');
            const max = parseInt(input.getAttribute('max'));
            const current = parseInt(input.value);
            if (current < max) {
                input.value = current + 1;
            }
        }

        // Decrease quantity
        function decreaseQuantity() {
            const input = document.getElementById('quantity-input');
            const min = parseInt(input.getAttribute('min'));
            const current = parseInt(input.value);
            if (current > min) {
                input.value = current - 1;
            }
        }

        // Add to cart with selected quantity
        function addToCartWithQuantity(productId) {
            const input = document.getElementById('quantity-input');
            const quantity = parseInt(input.value);

            let cart = JSON.parse(localStorage.getItem('cart') || '[]');

            // Check if product already in cart (support both formats)
            const existingItem = cart.find(item => (item.product_id || item.id) === productId);

            if (existingItem) {
                existingItem.quantity += quantity;
            } else {
                cart.push({
                    product_id: productId,
                    quantity: quantity,
                    added_at: new Date().toISOString()
                });
            }

            saveCart(cart);
            updateCartCount();
            renderCartPanel();
            openCartPanel();

            // Reset quantity to 1 after adding to cart
            input.value = 1;
        }

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

            saveCart(cart);
            updateCartCount();
            renderCartPanel();
            openCartPanel();
        }

        // Toggle favorite
        function toggleFavorite(productId) {
            let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            const heartBtn = document.getElementById('favorite-btn-' + productId);
            const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;

            const index = favorites.indexOf(productId);
            if (index > -1) {
                favorites.splice(index, 1);
                showToast('💔 Eliminado de favoritos');
                if (heartBtn) heartBtn.classList.remove('active');
                if (heartIcon) {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                }
            } else {
                favorites.push(productId);
                showToast('❤️ Agregado a favoritos');
                if (heartBtn) heartBtn.classList.add('active');
                if (heartIcon) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                }
            }

            localStorage.setItem('favorites', JSON.stringify(favorites));
        }

        // Check if product is in favorites on page load
        document.addEventListener('DOMContentLoaded', function() {
            const favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
            const productId = '<?php echo $product['id']; ?>';

            if (favorites.indexOf(productId) > -1) {
                const heartBtn = document.getElementById('favorite-btn-' + productId);
                const heartIcon = heartBtn ? heartBtn.querySelector('i') : null;
                if (heartBtn) heartBtn.classList.add('active');
                if (heartIcon) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                }
            }
        });

        // Copy link
        function copyLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(function() {
                showToast('📋 Link copiado al portapapeles');
            });
        }

        // Show toast notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        }

        // Initialize
        updateCartCount();

        // Touch swipe support for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        let touchStartTime = 0;
        let touchMoveDistance = 0;

        const mainImageContainer = document.getElementById('mainImageContainer');

        function handleSwipe() {
            const swipeThreshold = 50;
            if (touchEndX < touchStartX - swipeThreshold) {
                changeImage(1); // Swipe left
            }
            if (touchEndX > touchStartX + swipeThreshold) {
                changeImage(-1); // Swipe right
            }
        }

        if (mainImageContainer) {
            mainImageContainer.addEventListener('touchstart', e => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartTime = Date.now();
                touchMoveDistance = 0;
            }, { passive: true });

            mainImageContainer.addEventListener('touchmove', e => {
                const touchCurrentX = e.changedTouches[0].screenX;
                touchMoveDistance = Math.abs(touchCurrentX - touchStartX);
            }, { passive: true });

            mainImageContainer.addEventListener('touchend', e => {
                touchEndX = e.changedTouches[0].screenX;
                const touchDuration = Date.now() - touchStartTime;

                // Only trigger swipe if it was quick and significant distance
                if (touchDuration < 300 && touchMoveDistance > 50) {
                    handleSwipe();
                }
            }, { passive: true });
        }

        // Cart Panel Functions
        function renderCartPanel() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const body = document.getElementById('cart-panel-body');
            const footer = document.getElementById('cart-panel-footer');
            const totalEl = document.getElementById('cart-total');

            if (cart.length === 0) {
                body.innerHTML = '<div class="cart-empty">Tu carrito está vacío</div>';
                footer.style.display = 'none';
                return;
            }

            const exchangeRate = <?php echo $currency_config['exchange_rate']; ?>;
            let totalARS = 0;
            let totalUSD = 0;
            let allProductsUSD = true;
            let html = '';
            let validCart = [];

            cart.forEach(item => {
                const product = products.find(p => p.id === item.product_id);

                if (!product) {
                    console.warn('Product not found:', item.product_id);
                    return;
                }

                // Add to valid cart
                validCart.push(item);

                const priceARS = parseFloat(product.price_ars) || 0;
                const priceUSD = parseFloat(product.price_usd) || 0;

                let itemPriceARS = 0;
                let itemPriceUSD = 0;
                let displayPrice = '';

                if (priceUSD > 0 && priceARS === 0) {
                    itemPriceUSD = priceUSD;
                    itemPriceARS = priceUSD * exchangeRate;
                    displayPrice = 'U$D ' + priceUSD.toFixed(2);
                } else if (priceARS > 0 && priceUSD === 0) {
                    allProductsUSD = false;
                    itemPriceARS = priceARS;
                    displayPrice = '$' + priceARS.toFixed(2);
                } else if (priceARS > 0 && priceUSD > 0) {
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
                saveCart(validCart);
                updateCartCount();
            }

            body.innerHTML = html || '<div class="cart-empty">Tu carrito está vacío</div>';

            if (allProductsUSD && totalUSD > 0) {
                totalEl.textContent = 'U$D ' + totalUSD.toFixed(2);
            } else {
                totalEl.textContent = '$' + totalARS.toFixed(2);
            }

            footer.style.display = validCart.length > 0 ? 'block' : 'none';
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
            updateCartCount();
            renderCartPanel();
        }

        function removeFromCart(productId) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            cart = cart.filter(i => i.product_id !== productId);
            saveCart(cart);
            updateCartCount();
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

        async function goToCheckout() {
            window.location.href = '<?php echo url('/carrito.php'); ?>';
        }
    </script>

    <!-- Cart Validator -->
    <script src="<?php echo url('/includes/cart-validator.js'); ?>"></script>

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
