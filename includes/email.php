<?php
/**
 * Email System
 * Sistema de envío de emails para notificaciones
 */

require_once __DIR__ . '/functions.php';

/**
 * Get email configuration with defaults if file doesn't exist
 */
function get_email_config() {
    $config_file = __DIR__ . '/../config/email.json';

    if (!file_exists($config_file)) {
        // Create default config
        $site_config = read_json(__DIR__ . '/../config/site.json');
        $default_config = [
            'enabled' => true,
            'method' => 'mail',
            'from_email' => 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'tienda.com'),
            'from_name' => $site_config['site_name'] ?? 'Mi Tienda',
            'admin_email' => '',
            'smtp' => [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls'
            ],
            'notifications' => [
                'customer' => [
                    'order_created' => true,
                    'payment_approved' => true,
                    'payment_rejected' => true,
                    'payment_pending' => true,
                    'order_shipped' => true,
                    'chargeback_notice' => true
                ],
                'admin' => [
                    'new_order' => true,
                    'payment_approved' => true,
                    'chargeback_alert' => true,
                    'low_stock_alert' => true
                ]
            ]
        ];

        write_json($config_file, $default_config);
        return $default_config;
    }

    return read_json($config_file);
}

/**
 * Send email using configured method
 */
function send_email($to, $subject, $html_body, $plain_body = '') {
    $config = get_email_config();

    if (!($config['enabled'] ?? true)) {
        error_log("Email system disabled - would send to: $to");
        return false;
    }

    $from_email = $config['from_email'] ?? 'noreply@tienda.com';
    $from_name = $config['from_name'] ?? 'Mi Tienda';

    // Prepare plain text version if not provided
    if (empty($plain_body)) {
        $plain_body = strip_tags($html_body);
    }

    // Use configured method
    $method = $config['method'] ?? 'mail';

    if ($method === 'smtp') {
        // Check if OAuth2 is enabled
        $auth_method = $config['smtp']['auth_method'] ?? 'password';

        if ($auth_method === 'oauth2' && isset($config['oauth2']['access_token'])) {
            return send_email_smtp_oauth2($to, $subject, $html_body, $plain_body, $from_email, $from_name, $config['smtp']);
        } else {
            return send_email_smtp($to, $subject, $html_body, $plain_body, $from_email, $from_name, $config['smtp']);
        }
    } else {
        return send_email_native($to, $subject, $html_body, $plain_body, $from_email, $from_name);
    }
}

/**
 * Send email using PHP's native mail() function
 */
function send_email_native($to, $subject, $html_body, $plain_body, $from_email, $from_name) {
    $boundary = md5(time());

    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plain_body . "\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $html_body . "\r\n";
    $message .= "--$boundary--";

    $result = mail($to, $subject, $message, $headers);

    if ($result) {
        error_log("Email sent successfully to: $to - Subject: $subject");
    } else {
        error_log("Email failed to send to: $to - Subject: $subject");
    }

    return $result;
}

/**
 * Send email using SMTP
 */
function send_email_smtp($to, $subject, $html_body, $plain_body, $from_email, $from_name, $smtp_config) {
    $host = $smtp_config['host'] ?? 'smtp.gmail.com';
    $port = $smtp_config['port'] ?? 587;
    $username = $smtp_config['username'] ?? '';
    $password = $smtp_config['password'] ?? '';
    $encryption = $smtp_config['encryption'] ?? 'tls';

    // Validación básica
    if (empty($username) || empty($password)) {
        error_log("SMTP: Username or password not configured");
        return false;
    }

    try {
        // Conectar al servidor SMTP
        $errno = 0;
        $errstr = '';

        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
            $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
        } else {
            $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
        }

        if (!$smtp) {
            error_log("SMTP: Could not connect to $host:$port - Error: $errno - $errstr");
            return false;
        }

        // Leer respuesta del servidor
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP: Connection failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // EHLO
        fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $response = fgets($smtp, 515);

        // STARTTLS si es necesario
        if ($encryption === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);

            if (substr($response, 0, 3) != '220') {
                error_log("SMTP: STARTTLS failed - Response: $response");
                fclose($smtp);
                return false;
            }

            // Habilitar crypto
            stream_set_blocking($smtp, true);
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP: Failed to enable TLS encryption");
                fclose($smtp);
                return false;
            }

            // EHLO nuevamente después de TLS
            fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $response = fgets($smtp, 515);
        }

        // AUTH LOGIN
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '334') {
            error_log("SMTP: AUTH LOGIN failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // Enviar username
        fputs($smtp, base64_encode($username) . "\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '334') {
            error_log("SMTP: Username authentication failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // Enviar password
        fputs($smtp, base64_encode($password) . "\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '235') {
            error_log("SMTP: Password authentication failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // MAIL FROM
        fputs($smtp, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP: MAIL FROM failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP: RCPT TO failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '354') {
            error_log("SMTP: DATA command failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // Construir mensaje
        $boundary = md5(time());
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "\r\n";

        $message = $headers;
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $plain_body . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n";
        $message .= "--$boundary--\r\n";
        $message .= ".\r\n";

        // Enviar mensaje
        fputs($smtp, $message);
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP: Message send failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);

        error_log("SMTP: Email sent successfully to $to");
        return true;

    } catch (Exception $e) {
        error_log("SMTP Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * ============================================
 * OAuth2 Functions for Gmail
 * ============================================
 */

/**
 * Get OAuth2 authorization URL for Gmail
 */
function get_gmail_oauth2_url($client_id, $redirect_uri) {
    $params = [
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'https://mail.google.com/',
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchange authorization code for tokens
 */
function exchange_oauth2_code($code, $client_id, $client_secret, $redirect_uri) {
    $url = 'https://oauth2.googleapis.com/token';

    $data = [
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("OAuth2: Failed to exchange code for tokens");
        return null;
    }

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        error_log("OAuth2: Token exchange error - " . $result['error']);
        return null;
    }

    return $result;
}

/**
 * Refresh OAuth2 access token
 */
function refresh_oauth2_token($refresh_token, $client_id, $client_secret) {
    $url = 'https://oauth2.googleapis.com/token';

    $data = [
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log("OAuth2: Failed to refresh token");
        return null;
    }

    $result = json_decode($response, true);

    if (isset($result['error'])) {
        error_log("OAuth2: Token refresh error - " . $result['error']);
        return null;
    }

    return $result;
}

/**
 * Get valid OAuth2 access token (refreshes if needed)
 */
function get_valid_oauth2_token() {
    $config_file = __DIR__ . '/../config/email.json';
    $config = read_json($config_file);

    if (!isset($config['oauth2'])) {
        return null;
    }

    $oauth2 = $config['oauth2'];

    // Check if token is still valid
    if (isset($oauth2['expires_at']) && time() < $oauth2['expires_at'] - 60) {
        return $oauth2['access_token'];
    }

    // Token expired or about to expire, refresh it
    if (!isset($oauth2['refresh_token'])) {
        error_log("OAuth2: No refresh token available");
        return null;
    }

    // Load client credentials from email.json
    $client_id = $config['oauth2_credentials']['client_id'] ?? '';
    $client_secret = $config['oauth2_credentials']['client_secret'] ?? '';

    if (empty($client_id) || empty($client_secret)) {
        error_log("OAuth2: Client credentials not configured in email.json");
        return null;
    }

    // Refresh the token
    $result = refresh_oauth2_token($oauth2['refresh_token'], $client_id, $client_secret);

    if (!$result || !isset($result['access_token'])) {
        error_log("OAuth2: Failed to refresh access token");
        return null;
    }

    // Update config with new token
    $config['oauth2']['access_token'] = $result['access_token'];
    $config['oauth2']['expires_at'] = time() + ($result['expires_in'] ?? 3600);

    if (isset($result['refresh_token'])) {
        $config['oauth2']['refresh_token'] = $result['refresh_token'];
    }

    write_json($config_file, $config);

    error_log("OAuth2: Access token refreshed successfully");

    return $result['access_token'];
}

/**
 * Generate XOAUTH2 authentication string
 */
function generate_xoauth2_string($email, $access_token) {
    $auth_string = "user=" . $email . "\1auth=Bearer " . $access_token . "\1\1";
    return base64_encode($auth_string);
}

/**
 * Send email using SMTP with OAuth2
 */
function send_email_smtp_oauth2($to, $subject, $html_body, $plain_body, $from_email, $from_name, $smtp_config) {
    $host = $smtp_config['host'] ?? 'smtp.gmail.com';
    $port = $smtp_config['port'] ?? 587;
    $encryption = $smtp_config['encryption'] ?? 'tls';

    // Get valid access token
    $access_token = get_valid_oauth2_token();

    if (!$access_token) {
        error_log("SMTP OAuth2: No valid access token available");
        return false;
    }

    try {
        // Connect to SMTP server
        $errno = 0;
        $errstr = '';

        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
            $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
        } else {
            $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
        }

        if (!$smtp) {
            error_log("SMTP OAuth2: Could not connect to $host:$port - Error: $errno - $errstr");
            return false;
        }

        // Read server response
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP OAuth2: Connection failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // EHLO
        fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $response = fgets($smtp, 515);

        // STARTTLS if needed
        if ($encryption === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);

            if (substr($response, 0, 3) != '220') {
                error_log("SMTP OAuth2: STARTTLS failed - Response: $response");
                fclose($smtp);
                return false;
            }

            // Enable crypto
            stream_set_blocking($smtp, true);
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log("SMTP OAuth2: Failed to enable TLS encryption");
                fclose($smtp);
                return false;
            }

            // EHLO again after TLS
            fputs($smtp, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
            $response = fgets($smtp, 515);
        }

        // AUTH XOAUTH2
        fputs($smtp, "AUTH XOAUTH2\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '334') {
            error_log("SMTP OAuth2: AUTH XOAUTH2 not supported - Response: $response");
            fclose($smtp);
            return false;
        }

        // Send XOAUTH2 string
        $xoauth2_string = generate_xoauth2_string($from_email, $access_token);
        fputs($smtp, $xoauth2_string . "\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '235') {
            error_log("SMTP OAuth2: Authentication failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // MAIL FROM
        fputs($smtp, "MAIL FROM: <$from_email>\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP OAuth2: MAIL FROM failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP OAuth2: RCPT TO failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '354') {
            error_log("SMTP OAuth2: DATA command failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // Build message
        $boundary = md5(time());
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $headers .= "\r\n";

        $message = $headers;
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $plain_body . "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_body . "\r\n";
        $message .= "--$boundary--\r\n";
        $message .= ".\r\n";

        // Send message
        fputs($smtp, $message);
        $response = fgets($smtp, 515);

        if (substr($response, 0, 3) != '250') {
            error_log("SMTP OAuth2: Message send failed - Response: $response");
            fclose($smtp);
            return false;
        }

        // QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);

        error_log("SMTP OAuth2: Email sent successfully to $to");
        return true;

    } catch (Exception $e) {
        error_log("SMTP OAuth2 Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation email to customer
 */
function send_order_confirmation_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_created'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "Confirmación de Pedido #{$order['order_number']}";

    $html = get_email_template('order_confirmation', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send payment approved email to customer
 */
function send_payment_approved_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['payment_approved'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Pago Aprobado! - Pedido #{$order['order_number']}";

    $html = get_email_template('payment_approved', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send payment rejected email to customer
 */
function send_payment_rejected_email($order, $status_detail = '') {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['payment_rejected'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "Problema con el Pago - Pedido #{$order['order_number']}";

    $payment_message = get_payment_message('rejected', $status_detail);

    $html = get_email_template('payment_rejected', [
        'order' => $order,
        'payment_message' => $payment_message
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send payment pending email to customer
 */
function send_payment_pending_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['payment_pending'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "Pago Pendiente - Pedido #{$order['order_number']}";

    $html = get_email_template('payment_pending', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send order shipped email to customer
 */
function send_order_shipped_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['customer']['order_shipped'] ?? true)) {
        return false;
    }

    $to = $order['customer_email'];
    $subject = "¡Tu Pedido Fue Enviado! - #{$order['order_number']}";

    $html = get_email_template('order_shipped', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send new order notification to admin
 */
function send_admin_new_order_email($order) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['admin']['new_order'] ?? true)) {
        return false;
    }

    $to = $config['admin_email'] ?? 'admin@tienda.com';
    $subject = "🛒 Nueva Orden #{$order['order_number']}";

    $html = get_email_template('admin_new_order', [
        'order' => $order
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Send chargeback alert to admin
 */
function send_admin_chargeback_alert($order, $chargeback) {
    $config = read_json(__DIR__ . '/../config/email.json');

    if (!($config['notifications']['admin']['chargeback_alert'] ?? true)) {
        return false;
    }

    $to = $config['admin_email'] ?? 'admin@tienda.com';
    $subject = "🚨 CONTRACARGO - Orden #{$order['order_number']}";

    $html = get_email_template('admin_chargeback_alert', [
        'order' => $order,
        'chargeback' => $chargeback
    ]);

    return send_email($to, $subject, $html);
}

/**
 * Get email template with variables replaced
 */
function get_email_template($template_name, $vars = []) {
    $template_file = __DIR__ . '/../templates/email/' . $template_name . '.php';

    if (!file_exists($template_file)) {
        error_log("Email template not found: $template_file");
        return get_default_email_template($vars);
    }

    // Extract variables
    extract($vars);

    // Start output buffering
    ob_start();

    // Include template
    include $template_file;

    // Get contents and clean buffer
    $html = ob_get_clean();

    return $html;
}

/**
 * Get default email template if specific template not found
 */
function get_default_email_template($vars) {
    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Mi Tienda';

    $order = $vars['order'] ?? null;

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #667eea; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$site_name</h1>
            </div>
            <div class='content'>
                <p>Gracias por tu compra.</p>
                " . ($order ? "<p><strong>Número de pedido:</strong> {$order['order_number']}</p>" : "") . "
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " $site_name. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return $html;
}
