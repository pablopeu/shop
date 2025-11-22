<?php
/**
 * Carousel V2 Component
 * Nueva versión mejorada del carrusel con rotación automática hacia la izquierda
 * y nombre de imagen en la esquina inferior derecha
 */

$carousel_config = read_json(__DIR__ . '/../config/carousel.json');

if (!($carousel_config['enabled'] ?? false) || empty($carousel_config['slides'])) {
    return;
}

// Get alignment configuration
$alignment = $carousel_config['alignment'] ?? 'center';
?>

<div class="carousel-v2-wrapper" data-alignment="<?php echo htmlspecialchars($alignment); ?>">
    <div class="carousel-v2-container">
        <div class="carousel-v2-track">
            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                <div class="carousel-v2-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">
                    <?php if (!empty($slide['link'])): ?>
                        <a href="<?php echo htmlspecialchars($slide['link']); ?>" class="carousel-v2-link">
                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>"
                                 alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide ' . ($index + 1)); ?>"
                                 class="carousel-v2-image">

                            <?php if (!empty($slide['title'])): ?>
                                <div class="carousel-v2-title">
                                    <?php echo htmlspecialchars($slide['title']); ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>"
                             alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide ' . ($index + 1)); ?>"
                             class="carousel-v2-image">

                        <?php if (!empty($slide['title'])): ?>
                            <div class="carousel-v2-title">
                                <?php echo htmlspecialchars($slide['title']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Dots navigation -->
    <?php if (count($carousel_config['slides']) > 1): ?>
        <div class="carousel-v2-dots">
            <?php foreach ($carousel_config['slides'] as $index => $slide): ?>
                <button class="carousel-v2-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                        data-index="<?php echo $index; ?>"
                        aria-label="Ir a slide <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Initialize carousel v2 data
    window.carouselV2Data = {
        slides: <?php echo json_encode($carousel_config['slides']); ?>,
        autoAdvanceTime: <?php echo intval($carousel_config['auto_advance_time'] ?? 5000); ?>
    };
</script>
