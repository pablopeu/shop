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
$background_color = $carousel_config['background_color'] ?? '#f5f5f5';
?>

<div class="carousel-v2-wrapper" data-alignment="<?php echo htmlspecialchars($alignment); ?>">
    <div class="carousel-v2-container" style="background-color: <?php echo htmlspecialchars($background_color); ?>;">
        <div class="carousel-v2-track" id="carousel-track">
            <?php
            // Duplicate slides for infinite scroll effect
            $slides_to_render = array_merge($carousel_config['slides'], $carousel_config['slides'], $carousel_config['slides']);
            foreach ($slides_to_render as $index => $slide):
            ?>
                <div class="carousel-v2-slide" data-index="<?php echo $index; ?>">
                    <?php if (!empty($slide['link'])): ?>
                        <a href="<?php echo htmlspecialchars($slide['link']); ?>" class="carousel-v2-link">
                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>"
                                 alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide'); ?>"
                                 class="carousel-v2-image"
                                 draggable="false">

                            <?php if (!empty($slide['title'])): ?>
                                <div class="carousel-v2-title">
                                    <?php echo htmlspecialchars($slide['title']); ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <div class="carousel-v2-item">
                            <img src="<?php echo htmlspecialchars(url($slide['image'])); ?>"
                                 alt="<?php echo htmlspecialchars($slide['title'] ?? 'Slide'); ?>"
                                 class="carousel-v2-image"
                                 draggable="false">

                            <?php if (!empty($slide['title'])): ?>
                                <div class="carousel-v2-title">
                                    <?php echo htmlspecialchars($slide['title']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    // Initialize carousel v2 data
    window.carouselV2Data = {
        slides: <?php echo json_encode($carousel_config['slides']); ?>,
        autoAdvanceTime: <?php echo intval($carousel_config['auto_advance_time'] ?? 5000); ?>
    };
</script>
