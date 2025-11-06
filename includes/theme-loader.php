<?php
/**
 * Theme Loader
 * Carga dinámica de CSS de themes según configuración
 *
 * Orden de carga:
 * 1. Base: reset, layout, components, utilities
 * 2. Theme: variables, theme.css
 * 3. Components: carousel, mobile-menu, etc.
 */

/**
 * Render theme CSS links
 * Genera los tags <link> para cargar el theme activo
 *
 * @param string $active_theme Slug del theme activo (minimal, elegant, fresh, bold)
 * @return void Imprime los tags <link>
 */
function render_theme_css($active_theme = 'minimal') {
    // Validar theme
    $valid_themes = ['minimal', 'elegant', 'fresh', 'bold'];
    if (!in_array($active_theme, $valid_themes)) {
        $active_theme = 'minimal';
    }

    // Font Awesome 4.7 for icons (compatible with footer design)
    echo '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">' . PHP_EOL . '    ';

    // Base path para themes
    $base_path = '/themes';

    // Array de archivos CSS a cargar en orden
    $css_files = [
        // 1. Base CSS (compartido por todos los themes)
        "{$base_path}/_base/reset.css",
        "{$base_path}/_base/layout.css",
        "{$base_path}/_base/components.css",
        "{$base_path}/_base/utilities.css",
        "{$base_path}/_base/pages.css",

        // 2. Theme-specific CSS
        "{$base_path}/{$active_theme}/variables.css",
        "{$base_path}/{$active_theme}/theme.css",
    ];

    // Generar tags <link> para cada archivo
    foreach ($css_files as $css_file) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($css_file) . '">' . PHP_EOL . '    ';
    }
}

/**
 * Get theme configuration
 * Lee el archivo theme.json del theme activo
 *
 * @param string $active_theme Slug del theme activo
 * @return array|null Configuración del theme o null si no existe
 */
function get_theme_config($active_theme = 'minimal') {
    $theme_json_path = __DIR__ . "/../themes/{$active_theme}/theme.json";

    if (!file_exists($theme_json_path)) {
        return null;
    }

    $json_content = file_get_contents($theme_json_path);
    return json_decode($json_content, true);
}

/**
 * Get all available themes
 * Lista todos los themes disponibles en /themes/
 *
 * @return array Array de themes con su configuración
 */
function get_available_themes() {
    $themes_dir = __DIR__ . '/../themes';
    $themes = [];

    // Leer directorios en /themes/
    if (!is_dir($themes_dir)) {
        return $themes;
    }

    $directories = array_diff(scandir($themes_dir), ['.', '..', '_base']);

    foreach ($directories as $dir) {
        $theme_path = $themes_dir . '/' . $dir;

        // Verificar que sea un directorio y tenga theme.json
        if (is_dir($theme_path) && file_exists($theme_path . '/theme.json')) {
            $config = json_decode(file_get_contents($theme_path . '/theme.json'), true);

            if ($config) {
                $themes[$dir] = $config;
            }
        }
    }

    return $themes;
}

/**
 * Cache theme configuration
 * Implementación simple de cache para configuración de theme
 * (Para futuras optimizaciones)
 */
function cache_theme_config($theme_slug, $ttl = 3600) {
    // TODO: Implementar cache con APCu o file-based cache
    // Por ahora retorna la config sin cache
    return get_theme_config($theme_slug);
}

// NOTE: validate_theme() moved to includes/functions.php to avoid duplication
