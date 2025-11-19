<?php
/**
 * Classic Theme - Product Detail Template
 */
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

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>
    
    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/includes/mobile-menu.css'); ?>">
</head>
<body class="product-detail-page">
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

    <main class="main-content">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container">
            <div class="container">
                <ul class="breadcrumb">
                    <li><a href="<?php echo url('/'); ?>">Inicio</a></li>
                    <li><span class="separator">/</span></li>
                    <li><span class="current"><?php echo htmlspecialchars($product['name']); ?></span></li>
                </ul>
            </div>
        </div>

        <div class="container">
            <div class="product-wrapper">
                <!-- Product Gallery -->
                <div class="product-gallery">
                    <div class="main-image-wrapper" id="mainImageContainer">
                        <?php if (!empty($product['images'])): ?>
                            <?php
                            $first_img_url = is_array($product['images'][0]) ? ($product['images'][0]['url'] ?? '') : $product['images'][0];
                            $first_img_alt = is_array($product['images'][0]) ? ($product['images'][0]['alt'] ?? $product['name']) : $product['name'];
                            ?>
                            <img src="<?php echo htmlspecialchars(url($first_img_url)); ?>" 
                                 alt="<?php echo htmlspecialchars($first_img_alt); ?>" 
                                 class="main-image" 
                                 id="mainImage">
                            
                            <?php if ($active_promotion && $discounted_price): ?>
                                <div class="badge-promo">
                                    -<?php echo $active_promotion['value']; ?><?php echo $active_promotion['type'] === 'percentage' ? '%' : '$'; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <i class="fa fa-image"></i>
                                <span>Sin imagen</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($product['images']) && count($product['images']) > 1): ?>
                        <div class="thumbnails-scroll">
                            <div class="thumbnails-list">
                                <?php foreach ($product['images'] as $index => $image): ?>
                                    <?php
                                    $img_url = is_array($image) ? ($image['url'] ?? '') : $image;
                                    $img_alt = is_array($image) ? ($image['alt'] ?? $product['name']) : $product['name'];
                                    ?>
                                    <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                         onclick="selectImage(<?php echo $index; ?>)">
                                        <img src="<?php echo htmlspecialchars(url($img_url)); ?>" 
                                             alt="<?php echo htmlspecialchars($img_alt); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Details -->
                <div class="product-details">
                    <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="product-meta">
                        <div class="rating-stars">
                            <div class="stars-outer">
                                <div class="stars-inner" style="width: <?php echo ($avg_rating / 5) * 100; ?>%"></div>
                            </div>
                            <span class="rating-count">(<?php echo count($product_reviews); ?> opiniones)</span>
                        </div>
                        
                        <?php if (!empty($product['sku'])): ?>
                            <span class="sku">SKU: <?php echo htmlspecialchars($product['sku']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-price-box">
                        <?php if ($active_promotion && $discounted_price): ?>
                            <div class="price-old">
                                <?php echo format_product_price($product, $selected_currency); ?>
                            </div>
                            <div class="price-new">
                                <?php echo format_price($discounted_price, $selected_currency); ?>
                            </div>
                        <?php else: ?>
                            <div class="price-new">
                                <?php echo format_product_price($product, $selected_currency); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>

                    <div class="product-actions-card">
                        <!-- Stock Status -->
                        <div class="stock-status <?php echo $product['stock'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                            <?php if ($product['stock'] > 0): ?>
                                <i class="fa fa-check-circle"></i> 
                                <?php if ($product['stock'] <= $product['stock_alert']): ?>
                                    <span class="low-stock">¡Solo quedan <?php echo $product['stock']; ?> unidades!</span>
                                <?php else: ?>
                                    <span>Stock disponible</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="fa fa-times-circle"></i> <span>Agotado</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($product['pickup_only'])): ?>
                            <div class="pickup-alert">
                                <i class="fa fa-store"></i> Solo retiro en tienda
                            </div>
                        <?php endif; ?>

                        <div class="add-to-cart-form">
                            <div class="quantity-control">
                                <button type="button" class="qty-btn minus" onclick="decreaseQuantity()" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>-</button>
                                <input type="number" id="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>" readonly>
                                <button type="button" class="qty-btn plus" onclick="increaseQuantity()" <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>+</button>
                            </div>

                            <button class="btn-add-cart" 
                                    onclick="addToCartWithQuantity('<?php echo $product['id']; ?>')"
                                    <?php echo $product['stock'] === 0 ? 'disabled' : ''; ?>>
                                <?php echo $product['stock'] === 0 ? 'Sin Stock' : 'Agregar al Carrito'; ?>
                            </button>
                            
                            <button class="btn-favorite" onclick="toggleFavorite('<?php echo $product['id']; ?>')" title="Agregar a favoritos">
                                <i class="fa fa-heart-o"></i>
                            </button>
                        </div>
                    </div>

                    <div class="share-section">
                        <span>Compartir:</span>
                        <div class="social-links">
                            <button onclick="copyLink()" title="Copiar enlace"><i class="fa fa-link"></i></button>
                            <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' ' . get_base_url() . '/producto.php?slug=' . $slug); ?>" target="_blank" class="whatsapp"><i class="fa fa-whatsapp"></i></a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_base_url() . '/producto.php?slug=' . $slug); ?>" target="_blank" class="facebook"><i class="fa fa-facebook"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Section -->
            <div class="reviews-wrapper">
                <h2 class="section-title">Opiniones de clientes</h2>
                
                <?php if (empty($display_reviews)): ?>
                    <div class="empty-reviews">
                        <div class="empty-icon"><i class="fa fa-star-o"></i></div>
                        <p>Aún no hay opiniones para este producto.</p>
                        <button class="btn-write-review">Escribir opinión</button>
                    </div>
                <?php else: ?>
                    <div class="reviews-grid">
                        <?php foreach ($display_reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="review-user">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($review['user_name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <span class="name"><?php echo htmlspecialchars($review['user_name']); ?></span>
                                            <span class="date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-content">
                                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
        <div class="lightbox-content">
            <button class="lightbox-close">&times;</button>
            <img src="" alt="" id="lightboxImage">
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Scripts -->
    <script>
        // Pass PHP data to JS
        const basePath = '<?php echo BASE_PATH; ?>';
        const productData = <?php echo json_encode($product); ?>;
        
        // Normalize images for JS
        const productImages = <?php echo json_encode($product['images'] ?? []); ?>.map(img => {
            const url = typeof img === 'string' ? img : img.url;
            const alt = typeof img === 'string' ? '<?php echo htmlspecialchars($product['name']); ?>' : (img.alt || '<?php echo htmlspecialchars($product['name']); ?>');
            return {
                url: (url.startsWith('/') || url.startsWith('http')) ? url : basePath + url,
                alt: alt
            };
        });

        // Image Gallery Logic
        let currentImageIndex = 0;

        function selectImage(index) {
            currentImageIndex = index;
            updateGallery();
        }

        function updateGallery() {
            const mainImg = document.getElementById('mainImage');
            if (mainImg && productImages[currentImageIndex]) {
                mainImg.src = productImages[currentImageIndex].url;
                mainImg.alt = productImages[currentImageIndex].alt;
            }

            document.querySelectorAll('.thumbnail-item').forEach((thumb, idx) => {
                thumb.classList.toggle('active', idx === currentImageIndex);
            });
        }

        // Quantity Logic
        function increaseQuantity() {
            const input = document.getElementById('quantity-input');
            const max = parseInt(input.getAttribute('max'));
            if (parseInt(input.value) < max) input.value = parseInt(input.value) + 1;
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantity-input');
            if (parseInt(input.value) > 1) input.value = parseInt(input.value) - 1;
        }

        // Cart Logic (Simplified for template)
        function addToCartWithQuantity(id) {
            const qty = parseInt(document.getElementById('quantity-input').value);
            // Call global cart function (assumed to exist in main JS)
            if (typeof window.addToCart === 'function') {
                // We need to adapt this to the existing cart logic
                // For now, we'll use the existing localStorage logic
                let cart = JSON.parse(localStorage.getItem('cart') || '[]');
                const existingItem = cart.find(item => (item.product_id || item.id) === id);
                
                if (existingItem) {
                    existingItem.quantity += qty;
                } else {
                    cart.push({ product_id: id, quantity: qty, added_at: new Date().toISOString() });
                }
                
                localStorage.setItem('cart', JSON.stringify(cart));
                localStorage.setItem('cart_timestamp', Date.now().toString());
                
                // Update UI
                const countEl = document.getElementById('cart-count');
                if (countEl) {
                    const count = cart.reduce((sum, item) => sum + item.quantity, 0);
                    countEl.textContent = count;
                }
                
                showToast('🛒 Producto agregado al carrito');
            }
        }

        // Toast Logic
        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        // Copy Link
        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(() => showToast('📋 Enlace copiado'));
        }

        // Lightbox
        document.getElementById('mainImageContainer')?.addEventListener('click', () => {
            const lightbox = document.getElementById('lightbox');
            const img = document.getElementById('lightboxImage');
            if (productImages[currentImageIndex]) {
                img.src = productImages[currentImageIndex].url;
                lightbox.classList.add('active');
            }
        });

        document.querySelector('.lightbox-close')?.addEventListener('click', () => {
            document.getElementById('lightbox').classList.remove('active');
        });

        document.getElementById('lightbox')?.addEventListener('click', (e) => {
            if (e.target.id === 'lightbox') e.target.classList.remove('active');
        });
        
        // Initialize cart count
        document.addEventListener('DOMContentLoaded', () => {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').textContent = count;
        });
    </script>
</body>
</html>
