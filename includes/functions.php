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

/**
 * Render site logo or site name
 * Muestra el logo si está configurado, sino muestra el nombre del sitio
 * @param array $site_config Site configuration
 * @return void Echoes HTML
 */
function render_site_logo($site_config) {
    if (!empty($site_config['logo']['enabled']) && !empty($site_config['logo']['path'])) {
        // Render logo image
        $logo_path = htmlspecialchars($site_config['logo']['path']);
        $logo_alt = htmlspecialchars($site_config['logo']['alt'] ?? $site_config['site_name']);
        $logo_width = (int)($site_config['logo']['width'] ?? 170);
        $logo_height = (int)($site_config['logo']['height'] ?? 85);

        echo '<img src="' . $logo_path . '"
                   alt="' . $logo_alt . '"
                   width="' . $logo_width . '"
                   height="' . $logo_height . '"
                   style="max-width: 100%; height: auto;">';
    } else {
        // Render site name as text
        echo '<h1>' . htmlspecialchars($site_config['site_name']) . '</h1>';
    }
}

/**
 * Render custom footer HTML
 * Muestra el footer personalizado si está configurado, sino muestra el footer por defecto
 * @param array $site_config Site configuration
 * @param array $footer_config Footer configuration
 * @return void Echoes HTML
 */
function render_footer($site_config, $footer_config) {
    // Check if advanced footer is configured
    if (!empty($footer_config['enabled']) && $footer_config['type'] === 'advanced') {
        // Render advanced 3-column footer
        echo '<div class="footer-distributed">';

        // Left Column
        echo '<div class="footer-left">';

        // Logo
        if (!empty($footer_config['left_column']['logo']['enabled']) && !empty($footer_config['left_column']['logo']['path'])) {
            $logo = $footer_config['left_column']['logo'];
            echo '<img src="' . htmlspecialchars($logo['path']) . '" ';
            echo 'alt="' . htmlspecialchars($logo['alt'] ?? 'Logo') . '" ';
            echo 'height="' . (int)($logo['height'] ?? 83) . '" ';
            echo 'width="' . (int)($logo['width'] ?? 169) . '">';
        }

        // Links
        if (!empty($footer_config['left_column']['links'])) {
            echo '<p class="footer-links">';
            $first = true;
            foreach ($footer_config['left_column']['links'] as $link) {
                if (!$first) echo ' | ';
                echo '<a href="' . htmlspecialchars($link['url']) . '">' . htmlspecialchars($link['text']) . '</a>';
                $first = false;
            }
            echo '</p>';
        }

        // Email (sin icono, solo texto y link)
        if (!empty($footer_config['left_column']['email']['enabled']) && !empty($footer_config['left_column']['email']['address'])) {
            $email = $footer_config['left_column']['email'];
            $subject = urlencode($email['subject'] ?? 'Consulta desde el sitio web');
            $body = !empty($email['body']) ? '&Body=' . urlencode($email['body']) : '';
            echo '<div>';
            echo '<a href="mailto:' . htmlspecialchars($email['address']) . '?Subject=' . $subject . $body . '" style="color: #007eff;">';
            echo htmlspecialchars($email['address']);
            echo '</a>';
            echo '</div>';
        }

        // WhatsApp (sin icono, solo texto configurable y link)
        if (!empty($footer_config['left_column']['whatsapp']['enabled'])) {
            $whatsapp = $footer_config['left_column']['whatsapp'];

            // Usar custom_link si está configurado, sino generar link con número
            if (!empty($whatsapp['custom_link'])) {
                $wa_link = $whatsapp['custom_link'];
            } else if (!empty($whatsapp['number'])) {
                $number = preg_replace('/[^0-9]/', '', $whatsapp['number']);
                $message = urlencode($whatsapp['message'] ?? 'Hola, consulta desde el sitio web');
                $wa_link = 'https://wa.me/' . $number . '?text=' . $message;
            } else {
                $wa_link = '#';
            }

            $display_text = $whatsapp['display_text'] ?? $whatsapp['number'] ?? 'WhatsApp';
            echo '<div>';
            echo '<a href="' . htmlspecialchars($wa_link) . '" target="_blank" style="color: #25D366;">';
            echo htmlspecialchars($display_text);
            echo '</a>';
            echo '</div>';
        }

        echo '</div>'; // End footer-left

        // Center Column
        echo '<div class="footer-center">';

        // Address
        if (!empty($footer_config['center_column']['address']['enabled'])) {
            $address = $footer_config['center_column']['address'];
            if (!empty($address['street']) || !empty($address['city'])) {
                echo '<div>';
                if (!empty($address['map_url'])) {
                    echo '<a href="' . htmlspecialchars($address['map_url']) . '" target="_blank"><i class="fa fa-map-marker"></i></a>';
                } else {
                    echo '<i class="fa fa-map-marker"></i>';
                }
                echo '<p>';
                if (!empty($address['street'])) {
                    echo '<span>' . htmlspecialchars($address['street']) . '</span>';
                }
                if (!empty($address['city']) || !empty($address['country'])) {
                    $location = trim(($address['city'] ?? '') . ', ' . ($address['country'] ?? ''), ', ');
                    if ($location) {
                        echo '<span>' . htmlspecialchars($location) . '</span>';
                    }
                }
                echo '</p>';
                echo '</div>';
            }
        }

        // Phones
        if (!empty($footer_config['center_column']['phones'])) {
            echo '<div>';
            echo '<i class="fa fa-phone"></i>';
            echo '<p>';
            foreach ($footer_config['center_column']['phones'] as $phone) {
                if (!empty($phone['number'])) {
                    echo '<span>' . htmlspecialchars($phone['number']);
                    if (!empty($phone['label'])) {
                        echo ' (' . htmlspecialchars($phone['label']) . ')';
                    }
                    echo '</span>';
                }
            }
            echo '</p>';
            echo '</div>';
        }

        // WhatsApp en sección central (con icono)
        if (!empty($footer_config['center_column']['whatsapp']['enabled'])) {
            $whatsapp = $footer_config['center_column']['whatsapp'];

            // Usar custom_link si está configurado, sino generar link con número
            if (!empty($whatsapp['custom_link'])) {
                $wa_link = $whatsapp['custom_link'];
            } else if (!empty($whatsapp['number'])) {
                $number = preg_replace('/[^0-9]/', '', $whatsapp['number']);
                $message = urlencode($whatsapp['message'] ?? 'Hola, consulta desde el sitio web');
                $wa_link = 'https://wa.me/' . $number . '?text=' . $message;
            } else {
                $wa_link = '#';
            }

            $display_text = $whatsapp['display_text'] ?? $whatsapp['number'] ?? 'WhatsApp';
            echo '<div>';
            echo '<i class="fa fa-whatsapp"></i>';
            echo '<p>';
            echo '<span><a href="' . htmlspecialchars($wa_link) . '" target="_blank" style="color: #25D366; text-decoration: none;">';
            echo htmlspecialchars($display_text);
            echo '</a></span>';
            echo '</p>';
            echo '</div>';
        }

        // Schedule
        if (!empty($footer_config['center_column']['schedule']['enabled'])) {
            $schedule = $footer_config['center_column']['schedule'];
            if (!empty($schedule['days']) || !empty($schedule['hours'])) {
                echo '<div>';
                echo '<i class="fa fa-clock-o"></i>';
                echo '<p>';
                if (!empty($schedule['days'])) {
                    echo '<span>' . htmlspecialchars($schedule['days']) . '</span>';
                }
                if (!empty($schedule['hours'])) {
                    echo '<span>' . htmlspecialchars($schedule['hours']) . '</span>';
                }
                echo '</p>';
                echo '</div>';
            }
        }

        echo '</div>'; // End footer-center

        // Right Column
        echo '<div class="footer-right">';

        // About
        if (!empty($footer_config['right_column']['about']['enabled'])) {
            $about = $footer_config['right_column']['about'];
            echo '<p class="footer-company-about">';
            if (!empty($about['title'])) {
                echo '<span>' . htmlspecialchars($about['title']) . '</span>';
            }
            if (!empty($about['text'])) {
                echo nl2br(htmlspecialchars($about['text']));
            }
            echo '</p>';
        }

        // Social Media - Orden: Facebook / X / Instagram / WhatsApp / Telegram
        if (!empty($footer_config['right_column']['social']['enabled'])) {
            $social = $footer_config['right_column']['social'];
            $has_social = !empty($social['facebook']) || !empty($social['twitter']) ||
                         !empty($social['instagram']) || !empty($social['whatsapp']) || !empty($social['telegram']);

            if ($has_social) {
                echo '<div class="footer-icons">';

                // Facebook
                if (!empty($social['facebook'])) {
                    echo '<a href="' . htmlspecialchars($social['facebook']) . '" target="_blank"><i class="fa fa-facebook"></i></a>';
                }
                // X (Twitter)
                if (!empty($social['twitter'])) {
                    echo '<a href="' . htmlspecialchars($social['twitter']) . '" target="_blank"><i class="fa fa-twitter"></i></a>';
                }
                // Instagram
                if (!empty($social['instagram'])) {
                    echo '<a href="' . htmlspecialchars($social['instagram']) . '" target="_blank"><i class="fa fa-instagram"></i></a>';
                }
                // WhatsApp
                if (!empty($social['whatsapp'])) {
                    $wa_number = preg_replace('/[^0-9]/', '', $social['whatsapp']);
                    echo '<a href="https://wa.me/' . $wa_number . '" target="_blank"><i class="fa fa-whatsapp"></i></a>';
                }
                // Telegram
                if (!empty($social['telegram'])) {
                    echo '<a href="' . htmlspecialchars($social['telegram']) . '" target="_blank"><i class="fa fa-telegram"></i></a>';
                }

                echo '</div>';
            }
        }

        echo '</div>'; // End footer-right

        echo '</div>'; // End footer-distributed

    } else {
        // Default simple footer
        echo '<div class="container">';
        echo '    <div class="footer-content">';
        echo '        <div class="footer-section">';
        echo '            <h3>' . htmlspecialchars($site_config['site_name']) . '</h3>';
        echo '            <p>' . htmlspecialchars($site_config['site_description']) . '</p>';
        echo '        </div>';
        echo '        <div class="footer-section">';
        echo '            <h4>Contacto</h4>';
        if (!empty($site_config['contact_email'])) {
            echo '            <p>Email: ' . htmlspecialchars($site_config['contact_email']) . '</p>';
        }
        if (!empty($site_config['contact_phone'])) {
            echo '            <p>Teléfono: ' . htmlspecialchars($site_config['contact_phone']) . '</p>';
        }
        echo '        </div>';
        echo '        <div class="footer-section">';
        echo '            <h4>Enlaces</h4>';
        echo '            <a href="/">Inicio</a><br>';
        echo '            <a href="/buscar.php">Buscar</a><br>';
        echo '            <a href="/favoritos.php">Favoritos</a>';
        echo '        </div>';
        echo '    </div>';
        echo '    <div class="footer-bottom">';
        echo '        <p>' . htmlspecialchars($site_config['footer_text'] ?? '© ' . date('Y') . ' ' . $site_config['site_name'] . '. Todos los derechos reservados.') . '</p>';
        echo '    </div>';
        echo '</div>';
    }
}
