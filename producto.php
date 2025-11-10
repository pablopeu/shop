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
    redirect('/');
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
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>

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

                    <!-- Description -->
                    <div class="description">
                        <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>

                    <!-- Actions -->
                    <div class="actions">
                        <button class="btn btn-primary"
                                onclick="addToCart('<?php echo $product['id']; ?>')"
                                <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                            <?php echo $product['stock'] === 0 ? '🚫 Agotado' : '🛒 Agregar al Carrito'; ?>
                        </button>
                        <button class="btn btn-secondary" onclick="toggleFavorite('<?php echo $product['id']; ?>')">
                            ❤️ Favoritos
                        </button>
                    </div>

                    <!-- Share Buttons -->
                    <div class="share-buttons">
                        <button class="share-btn copy" onclick="copyLink()">
                            📋 Copiar Link
                        </button>
                        <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' - ' . get_base_url() . '/producto.php?slug=' . $slug); ?>"
                           class="share-btn whatsapp"
                           target="_blank">
                            📱 WhatsApp
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_base_url() . '/producto.php?slug=' . $slug); ?>"
                           class="share-btn facebook"
                           target="_blank">
                            👥 Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_base_url() . '/producto.php?slug=' . $slug); ?>&text=<?php echo urlencode($product['name']); ?>"
                           class="share-btn twitter"
                           target="_blank">
                            🐦 Twitter
                        </a>
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

        // Product images data - normalize to consistent format
        const rawImages = <?php echo json_encode($product['images'] ?? []); ?>;
        const productImages = rawImages.map(img => {
            if (typeof img === 'string') {
                return { url: basePath + img, alt: '<?php echo htmlspecialchars($product['name']); ?>' };
            }
            return { ...img, url: basePath + img.url };
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
        function addToCart(productId) {
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');

            // Check if product already in cart
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

            saveCart(cart);
            updateCartCount();
            showToast('✅ Producto agregado al carrito');
        }

        // Toggle favorite
        function toggleFavorite(productId) {
            let favorites = JSON.parse(localStorage.getItem('favorites') || '[]');

            const index = favorites.indexOf(productId);
            if (index > -1) {
                favorites.splice(index, 1);
                showToast('💔 Eliminado de favoritos');
            } else {
                favorites.push(productId);
                showToast('❤️ Agregado a favoritos');
            }

            localStorage.setItem('favorites', JSON.stringify(favorites));
        }

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
    </script>

    <!-- Cart Validator -->
    <script src="<?php echo url('/includes/cart-validator.js'); ?>"></script>

    <!-- Mobile Menu -->
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
