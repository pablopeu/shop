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
 * AUTO-DETECCIÓN: Se detecta automáticamente basado en la ubicación del archivo
 * Si prefieres configurarlo manualmente, descomenta y ajusta la línea:
 * define('BASE_PATH', '/shop');
 *
 * IMPORTANTE: NO incluir el / final
 */
if (!defined('BASE_PATH')) {
    // Auto-detect BASE_PATH from config.php location (application root)
    // This ensures BASE_PATH is always the application root, not subdirectories
    $config_dir = __DIR__;
    $document_root = $_SERVER['DOCUMENT_ROOT'];

    // Calculate relative path from document root to application root
    if (strpos($config_dir, $document_root) === 0) {
        $relative_path = substr($config_dir, strlen($document_root));
        // Clean up the path
        $relative_path = str_replace('\\', '/', $relative_path);
        $relative_path = rtrim($relative_path, '/');
        define('BASE_PATH', $relative_path);
    } else {
        // Fallback: empty base path (root installation)
        define('BASE_PATH', '');
    }
}

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
