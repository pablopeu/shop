<?php
/**
 * Tracking Scripts for <body> tag
 * Google Tag Manager noscript fallback
 *
 * Include this file immediately after the opening <body> tag
 * Example: <?php include __DIR__ . '/includes/tracking-body.php'; ?>
 */

// Load analytics configuration
$analytics_config = read_json(__DIR__ . '/../config/analytics.json');

// Google Tag Manager - Body section (noscript fallback)
if (($analytics_config['google_tag_manager']['enabled'] ?? false) && !empty($analytics_config['google_tag_manager']['container_id'])) {
    $gtm_id = htmlspecialchars($analytics_config['google_tag_manager']['container_id']);
    ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo $gtm_id; ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <?php
}
?>
