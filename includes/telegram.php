<?php
/**
 * Telegram Notification System
 * Send notifications to admin via Telegram Bot
 */

require_once __DIR__ . '/functions.php';

/**
 * Get secure Telegram credentials from external file
 */
function get_secure_telegram_credentials() {
    // Reuse the same function from email.php
    require_once __DIR__ . '/email.php';
    $credentials = get_secure_credentials();
    return $credentials['telegram'] ?? ['bot_token' => '', 'chat_id' => ''];
}

/**
 * Get Telegram configuration with defaults if file doesn't exist
 */
function get_telegram_config() {
    $config_file = __DIR__ . '/../config/telegram.json';

    if (!file_exists($config_file)) {
        // Create default config
        $default_config = [
            'enabled' => false,
            'bot_token' => '',
            'chat_id' => '',
            'notifications' => [
                'new_order' => true,
                'payment_approved' => true,
                'payment_rejected' => false,
                'chargeback_alert' => true,
                'low_stock_alert' => true,
                'high_value_order' => true,
                'high_value_threshold' => 50000
            ]
        ];

        write_json($config_file, $default_config);
        return $default_config;
    }

    return read_json($config_file);
}

/**
 * Send Telegram message
 */
function send_telegram_message($message, $parse_mode = 'HTML') {
    $config = get_telegram_config();

    if (!($config['enabled'] ?? false)) {
        error_log("Telegram notifications disabled");
        return false;
    }

    // Get credentials from secure external file
    $credentials = get_secure_telegram_credentials();
    $bot_token = $credentials['bot_token'] ?? '';
    $chat_id = $credentials['chat_id'] ?? '';

    if (empty($bot_token) || empty($chat_id)) {
        error_log("Telegram not configured - missing bot_token or chat_id");
        return false;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
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

    try {
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("Failed to send Telegram message");
            return false;
        }

        $response = json_decode($result, true);

        if (!($response['ok'] ?? false)) {
            error_log("Telegram API error: " . ($response['description'] ?? 'Unknown error'));
            return false;
        }

        error_log("Telegram message sent successfully");
        return true;

    } catch (Exception $e) {
        error_log("Exception sending Telegram message: " . $e->getMessage());
        return false;
    }
}

/**
 * Send new order notification to admin via Telegram
 */
function send_telegram_new_order($order) {
    $config = get_telegram_config();

    if (!($config['notifications']['new_order'] ?? true)) {
        return false;
    }

    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Mi Tienda';

    $currency = $order['currency'] === 'USD' ? 'USD' : '$';
    $total = number_format($order['total'], 2);

    $message = "🛒 <b>NUEVA ORDEN</b>\n\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Total: <b>{$currency} {$total}</b>\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n";
    $message .= "📧 Email: {$order['customer_email']}\n";
    $message .= "💳 Pago: " . ucfirst($order['payment_method'] ?? 'N/A') . "\n";
    $message .= "📅 Fecha: " . date('d/m/Y H:i', strtotime($order['created_at'])) . "\n\n";

    // Products
    $message .= "📦 <b>Productos:</b>\n";
    foreach ($order['items'] as $item) {
        $message .= "• {$item['name']} x{$item['quantity']}\n";
    }

    return send_telegram_message($message);
}

/**
 * Send payment approved notification to admin via Telegram
 */
function send_telegram_payment_approved($order) {
    $config = get_telegram_config();

    if (!($config['notifications']['payment_approved'] ?? true)) {
        return false;
    }

    // Check if it's a high value order
    $high_value_threshold = $config['notifications']['high_value_threshold'] ?? 50000;
    $is_high_value = $order['total'] >= $high_value_threshold;

    // Only send if high_value_order is enabled and it's a high value order
    // OR if payment_approved is enabled
    if ($is_high_value && !($config['notifications']['high_value_order'] ?? true)) {
        return false;
    }

    $currency = $order['currency'] === 'USD' ? 'USD' : '$';
    $total = number_format($order['total'], 2);

    $message = "✅ <b>PAGO APROBADO</b>" . ($is_high_value ? " 🌟" : "") . "\n\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Total: <b>{$currency} {$total}</b>\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n";

    if (isset($order['mercadopago_data']['payment_id'])) {
        $message .= "🆔 Payment ID: <code>{$order['mercadopago_data']['payment_id']}</code>\n";
    }

    if (isset($order['mercadopago_data']['payment_method_id'])) {
        $method = strtoupper($order['mercadopago_data']['payment_method_id']);
        if (isset($order['mercadopago_data']['card_last_four_digits'])) {
            $method .= " **** {$order['mercadopago_data']['card_last_four_digits']}";
        }
        $message .= "💳 Método: {$method}\n";
    }

    if (isset($order['mercadopago_data']['installments']) && $order['mercadopago_data']['installments'] > 1) {
        $message .= "📊 Cuotas: {$order['mercadopago_data']['installments']}x\n";
    }

    // Show fees and net amount if available
    if (isset($order['mercadopago_data']['total_fees']) && $order['mercadopago_data']['total_fees'] > 0) {
        $fees = number_format($order['mercadopago_data']['total_fees'], 2);
        $net = number_format($order['mercadopago_data']['net_received_amount'], 2);
        $message .= "\n💵 <b>Detalles Financieros:</b>\n";
        $message .= "   • Cobro: {$currency} {$total}\n";
        $message .= "   • Comisión MP: -{$currency} {$fees}\n";
        $message .= "   • <b>Acreditado: {$currency} {$net}</b>\n";
    }

    $message .= "\n✨ ¡Procesar y preparar para envío!";

    return send_telegram_message($message);
}

/**
 * Send payment rejected notification to admin via Telegram
 */
function send_telegram_payment_rejected($order) {
    $config = get_telegram_config();

    if (!($config['notifications']['payment_rejected'] ?? false)) {
        return false;
    }

    $currency = $order['currency'] === 'USD' ? 'USD' : '$';
    $total = number_format($order['total'], 2);

    $message = "❌ <b>PAGO RECHAZADO</b>\n\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Monto: {$currency} {$total}\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n";

    if (isset($order['payment_status_detail'])) {
        $message .= "⚠️ Detalle: {$order['payment_status_detail']}\n";
    }

    if (isset($order['mercadopago_data']['payment_id'])) {
        $message .= "🆔 Payment ID: <code>{$order['mercadopago_data']['payment_id']}</code>\n";
    }

    return send_telegram_message($message);
}

/**
 * Send chargeback alert to admin via Telegram
 */
function send_telegram_chargeback_alert($order, $chargeback) {
    $config = get_telegram_config();

    if (!($config['notifications']['chargeback_alert'] ?? true)) {
        return false;
    }

    $currency = $order['currency'] === 'USD' ? 'USD' : '$';
    $total = number_format($order['total'], 2);

    $action = strtoupper($chargeback['action'] ?? 'UNKNOWN');

    $message = "🚨🚨🚨 <b>ALERTA DE CONTRACARGO</b> 🚨🚨🚨\n\n";
    $message .= "⚠️ Acción: <b>{$action}</b>\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Monto: <b>{$currency} {$total}</b>\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n";
    $message .= "📧 Email: {$order['customer_email']}\n\n";

    if (isset($chargeback['chargeback_id'])) {
        $message .= "🆔 Chargeback ID: <code>{$chargeback['chargeback_id']}</code>\n";
    }

    if (isset($chargeback['payment_id'])) {
        $message .= "💳 Payment ID: <code>{$chargeback['payment_id']}</code>\n";
    }

    $message .= "\n🔥 <b>ACCIÓN INMEDIATA REQUERIDA</b>\n";
    $message .= "Accede a Mercadopago para responder al contracargo.";

    return send_telegram_message($message);
}

/**
 * Send low stock alert to admin via Telegram
 */
function send_telegram_low_stock_alert($product, $current_stock) {
    $config = get_telegram_config();

    if (!($config['notifications']['low_stock_alert'] ?? true)) {
        return false;
    }

    $message = "⚠️ <b>STOCK BAJO</b>\n\n";
    $message .= "📦 Producto: <b>{$product['name']}</b>\n";
    $message .= "📊 Stock actual: <b>{$current_stock}</b> unidades\n";

    if (isset($product['id'])) {
        $message .= "🆔 ID: <code>{$product['id']}</code>\n";
    }

    $message .= "\n⚡ Considera reabastecer este producto.";

    return send_telegram_message($message);
}

/**
 * Send test message to verify Telegram configuration
 */
function send_telegram_test() {
    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Mi Tienda';

    $message = "✅ <b>Telegram Bot Configurado</b>\n\n";
    $message .= "El bot de notificaciones está funcionando correctamente para <b>{$site_name}</b>.\n\n";
    $message .= "📅 " . date('d/m/Y H:i:s') . "\n";
    $message .= "🤖 Sistema de notificaciones activo";

    return send_telegram_message($message);
}

/**
 * Send Telegram message to a specific chat_id (for customer validation)
 * This allows sending to users other than the admin
 */
function send_telegram_to_user($chat_id, $message, $parse_mode = 'HTML') {
    $config = get_telegram_config();

    if (!($config['enabled'] ?? false)) {
        error_log("Telegram notifications disabled");
        return false;
    }

    // Get bot token from secure credentials
    $credentials = get_secure_telegram_credentials();
    $bot_token = $credentials['bot_token'] ?? '';

    if (empty($bot_token)) {
        error_log("Telegram not configured - missing bot_token");
        return false;
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";

    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
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

    try {
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            error_log("Failed to send Telegram message to user $chat_id");
            return false;
        }

        $response = json_decode($result, true);

        if (!($response['ok'] ?? false)) {
            error_log("Telegram API error: " . ($response['description'] ?? 'Unknown error'));
            return false;
        }

        error_log("Telegram message sent successfully to user $chat_id");
        return true;

    } catch (Exception $e) {
        error_log("Exception sending Telegram message: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation to customer via Telegram
 */
function send_telegram_order_confirmation($order) {
    if (empty($order['telegram_chat_id'])) {
        error_log("No telegram_chat_id in order");
        return false;
    }

    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

    $currency = $order['currency'] === 'USD' ? 'U$D' : '$';
    $total = number_format($order['total'], 2);

    $message = "✅ <b>Orden Confirmada</b>\n\n";
    $message .= "Gracias por tu compra en <b>{$site_name}</b>!\n\n";
    $message .= "📝 Número de orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Total: <b>{$currency} {$total}</b>\n\n";

    $message .= "📦 <b>Productos:</b>\n";
    foreach ($order['items'] as $item) {
        $message .= "• {$item['name']} x{$item['quantity']}\n";
    }

    $message .= "\n💳 Método de pago: " . ucfirst($order['payment_method'] ?? 'N/A') . "\n";

    if ($order['delivery_method'] === 'shipping' && isset($order['shipping_address'])) {
        $message .= "📍 Envío a: {$order['shipping_address']['city']}, {$order['shipping_address']['state']}\n";
    } else {
        $message .= "🏪 Retiro en persona\n";
    }

    $message .= "\nTe mantendremos informado sobre el estado de tu pedido. ✨";

    return send_telegram_to_user($order['telegram_chat_id'], $message);
}

/**
 * Send payment approved notification to customer via Telegram
 */
function send_telegram_payment_approved_to_customer($order) {
    if (empty($order['telegram_chat_id'])) {
        error_log("No telegram_chat_id in order");
        return false;
    }

    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

    $currency = $order['currency'] === 'USD' ? 'U$D' : '$';
    $total = number_format($order['total'], 2);

    $message = "✅ <b>¡Pago Confirmado!</b>\n\n";
    $message .= "Tu pago ha sido aprobado exitosamente.\n\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Monto: <b>{$currency} {$total}</b>\n\n";

    if (isset($order['mercadopago_data']['payment_method_id'])) {
        $method = strtoupper($order['mercadopago_data']['payment_method_id']);
        if (isset($order['mercadopago_data']['card_last_four_digits'])) {
            $method .= " **** {$order['mercadopago_data']['card_last_four_digits']}";
        }
        $message .= "💳 Método: {$method}\n";
    }

    if (isset($order['mercadopago_data']['installments']) && $order['mercadopago_data']['installments'] > 1) {
        $message .= "📊 Cuotas: {$order['mercadopago_data']['installments']}x\n";
    }

    $message .= "\nPronto recibirás actualizaciones sobre el envío de tu pedido. 🎉";

    return send_telegram_to_user($order['telegram_chat_id'], $message);
}

/**
 * Send payment pending notification to customer via Telegram
 */
function send_telegram_payment_pending_to_customer($order) {
    if (empty($order['telegram_chat_id'])) {
        error_log("No telegram_chat_id in order");
        return false;
    }

    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

    $currency = $order['currency'] === 'USD' ? 'U$D' : '$';
    $total = number_format($order['total'], 2);

    $message = "⏳ <b>Pago Pendiente</b>\n\n";
    $message .= "Tu pago está siendo procesado.\n\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Monto: {$currency} {$total}\n\n";
    $message .= "Te notificaremos cuando se confirme el pago. ⏰";

    return send_telegram_to_user($order['telegram_chat_id'], $message);
}

/**
 * Send payment rejected notification to customer via Telegram
 */
function send_telegram_payment_rejected_to_customer($order, $status_detail = '') {
    if (empty($order['telegram_chat_id'])) {
        error_log("No telegram_chat_id in order");
        return false;
    }

    $site_config = read_json(__DIR__ . '/../config/site.json');
    $site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

    $currency = $order['currency'] === 'USD' ? 'U$D' : '$';
    $total = number_format($order['total'], 2);

    $message = "❌ <b>Pago Rechazado</b>\n\n";
    $message .= "Lamentablemente tu pago no pudo ser procesado.\n\n";
    $message .= "📝 Orden: <code>#{$order['order_number']}</code>\n";
    $message .= "💰 Monto: {$currency} {$total}\n";

    if (!empty($status_detail)) {
        $message .= "⚠️ Motivo: {$status_detail}\n";
    }

    $message .= "\nPuedes intentar nuevamente con otro método de pago o contactarnos para asistencia.";

    return send_telegram_to_user($order['telegram_chat_id'], $message);
}

