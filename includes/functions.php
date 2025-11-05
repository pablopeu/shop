<?php
/**
 * Core Functions
 * Sistema de funciones principales para la aplicación
 */

// Security headers
function set_security_headers() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// HTTPS Enforcement
function enforce_https() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

/**
 * Read JSON file with proper error handling
 * @param string $file Path to JSON file
 * @param bool $associative Return associative array
 * @return mixed Decoded JSON data or false on error
 */
function read_json($file, $associative = true) {
    if (!file_exists($file)) {
        error_log("JSON file not found: $file");
        return $associative ? [] : new stdClass();
    }

    $fp = fopen($file, 'r');
    if (!$fp) {
        error_log("Cannot open file for reading: $file");
        return $associative ? [] : new stdClass();
    }

    // Acquire shared lock for reading
    if (flock($fp, LOCK_SH)) {
        $content = fread($fp, filesize($file) ?: 1);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($content, $associative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error in $file: " . json_last_error_msg());
            return $associative ? [] : new stdClass();
        }

        return $data;
    }

    fclose($fp);
    error_log("Cannot acquire lock for reading: $file");
    return $associative ? [] : new stdClass();
}

/**
 * Write JSON file with file locking
 * @param string $file Path to JSON file
 * @param mixed $data Data to write
 * @param bool $pretty Pretty print JSON
 * @return bool Success status
 */
function write_json($file, $data, $pretty = true) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty) {
        $flags |= JSON_PRETTY_PRINT;
    }

    $json = json_encode($data, $flags);
    if ($json === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        return false;
    }

    // Create directory if it doesn't exist
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fp = fopen($file, 'w');
    if (!$fp) {
        error_log("Cannot open file for writing: $file");
        return false;
    }

    // Acquire exclusive lock for writing
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    fclose($fp);
    error_log("Cannot acquire lock for writing: $file");
    return false;
}

/**
 * Sanitize input string
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitize_input($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Validate email address
 * @param string $email Email address
 * @return bool Valid email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate unique ID
 * @param string $prefix Prefix for ID
 * @return string Unique ID
 */
function generate_id($prefix = '') {
    return $prefix . uniqid() . '-' . bin2hex(random_bytes(4));
}

/**
 * Generate secure token
 * @param int $length Token length
 * @return string Secure token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool Valid token
 */
function validate_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get current timestamp in ISO 8601 format
 * @return string ISO 8601 timestamp
 */
function get_timestamp() {
    return date('c'); // ISO 8601 format
}

/**
 * Format price for display
 * @param float $price Price
 * @param string $currency Currency code
 * @return string Formatted price
 */
function format_price($price, $currency = 'ARS') {
    $symbols = [
        'ARS' => '$',
        'USD' => 'U$D'
    ];

    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format($price, 2, ',', '.');
}

/**
 * Format product price intelligently based on available prices
 * If only USD price exists, shows USD with ARS in parentheses
 * Otherwise follows the selected currency
 *
 * @param array $product Product with price_ars and price_usd
 * @param string $selected_currency User's selected currency (ARS or USD)
 * @return string Formatted price string
 */
function format_product_price($product, $selected_currency = 'ARS') {
    $price_ars = floatval($product['price_ars'] ?? 0);
    $price_usd = floatval($product['price_usd'] ?? 0);

    // If product only has USD price (no ARS price)
    if ($price_usd > 0 && $price_ars == 0) {
        // Calculate ARS equivalent
        $currency_config = read_json(__DIR__ . '/../config/currency.json');
        $exchange_rate = $currency_config['exchange_rate'] ?? 1500;
        $calculated_ars = $price_usd * $exchange_rate;

        return 'U$D ' . number_format($price_usd, 2, ',', '.') .
               ' <span style="font-size: 0.85em; color: #666;">(' .
               format_price($calculated_ars, 'ARS') . ')</span>';
    }

    // If product only has ARS price (no USD price)
    if ($price_ars > 0 && $price_usd == 0) {
        return format_price($price_ars, 'ARS');
    }

    // Product has both prices - use selected currency
    $price = $selected_currency === 'USD' ? $price_usd : $price_ars;
    return format_price($price, $selected_currency);
}

/**
 * Convert price between currencies
 * @param float $price Price to convert
 * @param string $from Source currency
 * @param string $to Target currency
 * @return float Converted price
 */
function convert_currency($price, $from, $to) {
    if ($from === $to) {
        return $price;
    }

    $currency_config = read_json(__DIR__ . '/../config/currency.json');
    $rate = $currency_config['exchange_rate'] ?? 1000;

    if ($from === 'ARS' && $to === 'USD') {
        return $price / $rate;
    } elseif ($from === 'USD' && $to === 'ARS') {
        return $price * $rate;
    }

    return $price;
}

/**
 * Generate slug from string
 * @param string $string Input string
 * @return string URL-friendly slug
 */
function generate_slug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[áàäâ]/', 'a', $string);
    $string = preg_replace('/[éèëê]/', 'e', $string);
    $string = preg_replace('/[íìïî]/', 'i', $string);
    $string = preg_replace('/[óòöô]/', 'o', $string);
    $string = preg_replace('/[úùüû]/', 'u', $string);
    $string = preg_replace('/[ñ]/', 'n', $string);
    $string = preg_replace('/[^a-z0-9]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

/**
 * Log admin action
 * @param string $action Action description
 * @param string $user User who performed action
 * @param array $details Additional details
 */
function log_admin_action($action, $user, $details = []) {
    $log_file = __DIR__ . '/../data/admin_logs.json';
    $logs = read_json($log_file);

    if (!isset($logs['logs'])) {
        $logs = ['logs' => []];
    }

    $logs['logs'][] = [
        'timestamp' => get_timestamp(),
        'action' => $action,
        'user' => $user,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    // Keep only last 1000 logs
    if (count($logs['logs']) > 1000) {
        $logs['logs'] = array_slice($logs['logs'], -1000);
    }

    write_json($log_file, $logs);
}

/**
 * Check if maintenance mode is enabled
 * @return bool Maintenance mode status
 */
function is_maintenance_mode() {
    $maintenance = read_json(__DIR__ . '/../config/maintenance.json');

    if (!$maintenance['enabled']) {
        return false;
    }

    // Check bypass code
    if (isset($_GET['bypass']) && $_GET['bypass'] === $maintenance['bypass_code']) {
        return false;
    }

    // Check allowed IPs
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($client_ip, $maintenance['allowed_ips'] ?? [])) {
        return false;
    }

    return true;
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Get base URL
 * @return string Base URL
 */
function get_base_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'];
}

/**
 * Check if user is logged in
 * @return bool Login status
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user is admin
 * @return bool Admin status
 */
function is_admin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin authentication
 */
function require_admin() {
    if (!is_admin()) {
        redirect('/admin/login.php');
    }

    // Check session timeout (default: 2 hours)
    require_once __DIR__ . '/auth.php';
    if (!check_session_timeout(7200)) {
        redirect('/admin/login.php?timeout=1');
    }
}

/**
 * Get logged user data
 * @return array|null User data
 */
function get_logged_user() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Validate if a theme exists and has required files
 * @param string $theme_slug Theme slug to validate
 * @return array ['valid' => bool, 'message' => string]
 */
function validate_theme($theme_slug) {
    // Sanitize theme slug
    $theme_slug = preg_replace('/[^a-z0-9_-]/i', '', $theme_slug);

    if (empty($theme_slug)) {
        return [
            'valid' => false,
            'message' => 'Theme slug is empty'
        ];
    }

    $theme_path = __DIR__ . '/../themes/' . $theme_slug;

    // Check if theme directory exists
    if (!is_dir($theme_path)) {
        return [
            'valid' => false,
            'message' => "Theme directory does not exist: {$theme_slug}"
        ];
    }

    // Check required files
    $required_files = [
        'variables.css',
        'theme.css',
        'theme.json'
    ];

    foreach ($required_files as $file) {
        if (!file_exists($theme_path . '/' . $file)) {
            return [
                'valid' => false,
                'message' => "Missing required file: {$file}"
            ];
        }
    }

    return [
        'valid' => true,
        'message' => 'Theme is valid'
    ];
}
