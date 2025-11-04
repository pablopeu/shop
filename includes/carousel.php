<?php
/**
 * Carousel Component
 * Renders the image carousel on the homepage
 */

$carousel_config = read_json(__DIR__ . '/../config/carousel.json');

if (!($carousel_config['enabled'] ?? false) || empty($carousel_config['slides'])) {
    return;
}

// Get alignment configuration
$alignment = $carousel_config['alignment'] ?? 'center';
?>

<div class="carousel-wrapper" data-alignment="<?php echo htmlspecialchars($alignment); ?>">
    <div class="carousel-container">
        <div class="carousel-slides">
            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                <div class="carousel-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                    <?php if (!empty($slide['link'])): ?>
                        <a href="<?php echo htmlspecialchars($slide['link']); ?>" class="carousel-link">
                            <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide ' . ($index + 1)); ?>">
                        </a>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide ' . ($index + 1)); ?>">
                    <?php endif; ?>

                    <?php if (!empty($slide['subtitle'])): ?>
                        <div class="carousel-overlay">
                            <p><?php echo htmlspecialchars($slide['subtitle']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (count($carousel_config['slides']) > 1): ?>
            <!-- Navigation arrows (hidden by default, shown on hover) -->
            <button class="carousel-arrow carousel-prev" aria-label="Previous slide">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button class="carousel-arrow carousel-next" aria-label="Next slide">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        <?php endif; ?>
    </div>

    <!-- Title above dots -->
    <?php
    $currentSlide = $carousel_config['slides'][0] ?? null;
    if ($currentSlide && !empty($currentSlide['title'])):
    ?>
        <h2 class="carousel-title" id="carousel-title"><?php echo htmlspecialchars($currentSlide['title']); ?></h2>
    <?php endif; ?>

    <!-- Dots navigation -->
    <?php if (count($carousel_config['slides']) > 1): ?>
        <div class="carousel-dots">
            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                <button class="carousel-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-index="<?php echo $index; ?>"
                        aria-label="Go to slide <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Initialize carousel data
    window.carouselData = <?php echo json_encode($carousel_config['slides']); ?>;
</script>
