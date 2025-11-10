<?php
/**
 * Configuration File
 * Configuración global de la aplicación
 */

// =============================================================================
// PATH CONFIGURATION
// =============================================================================

/**
 * BASE_PATH: Subdirectorio donde está instalada la aplicación
 *
 * Ejemplos:
 * - Si tu sitio es https://tudominio.com/ → BASE_PATH = ''
 * - Si tu sitio es https://tudominio.com/shop/ → BASE_PATH = '/shop'
 * - Si tu sitio es https://tudominio.com/tienda/ → BASE_PATH = '/tienda'
 *
 * IMPORTANTE: NO incluir el / final
 */
define('BASE_PATH', '/shop');

/**
 * Generate URL with BASE_PATH
 * @param string $path Path relative to base (with or without leading /)
 * @return string Full URL path
 */
if (!function_exists('url')) {
    function url($path = '') {
        // Ensure path starts with /
        if (!empty($path) && $path[0] !== '/') {
            $path = '/' . $path;
        }

        return BASE_PATH . $path;
    }
}

/**
 * Redirect to URL with BASE_PATH (alias for existing redirect function)
 * Note: The redirect() function already exists in includes/functions.php
 * This is just documented here for reference
 */

/**
 * Get current URL path without BASE_PATH
 * @return string Current path
 */
if (!function_exists('current_path')) {
    function current_path() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        $path = parse_url($request_uri, PHP_URL_PATH);

        // Remove BASE_PATH from beginning
        if (BASE_PATH !== '' && strpos($path, BASE_PATH) === 0) {
            $path = substr($path, strlen(BASE_PATH));
        }

        return $path ?: '/';
    }
}

/**
 * Check if current path matches
 * @param string $path Path to check
 * @return bool True if current path matches
 */
if (!function_exists('is_current_path')) {
    function is_current_path($path) {
        return current_path() === $path;
    }
}
