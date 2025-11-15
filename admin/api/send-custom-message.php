<?php
/**
 * API Endpoint: Send Custom Message to Customer
 * Handles sending personalized messages via email or Telegram
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/orders.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/telegram.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== 1) {
    error_log("UNAUTHORIZED: User not logged in");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado - sesión no válida']);
    exit;
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("UNAUTHORIZED: User is not admin. Role: " . ($_SESSION['role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado - se requieren permisos de administrador']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verify CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Get parameters
$order_id = $_POST['order_id'] ?? '';
$custom_message = trim($_POST['custom_message'] ?? '');

// Validate inputs
if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'ID de orden requerido']);
    exit;
}

if (empty($custom_message)) {
    echo json_encode(['success' => false, 'message' => 'El mensaje no puede estar vacío']);
    exit;
}

// Get order
$order = get_order_by_id($order_id);
if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Orden no encontrada']);
    exit;
}

// Get contact preference
$contact_preference = $order['contact_preference'] ?? 'email';
$sent = false;
$channel = '';

try {
    if ($contact_preference === 'telegram') {
        // Send via Telegram
        if (empty($order['telegram_chat_id'])) {
            error_log("Custom message error: No telegram_chat_id for order {$order_id}");
            echo json_encode(['success' => false, 'message' => 'No hay chat_id de Telegram registrado para este cliente']);
            exit;
        }

        $message = "📩 <b>Mensaje del vendedor:</b>\n\n";
        $message .= $custom_message;
        $message .= "\n\n";
        $message .= "Pedido: <b>#{$order['order_number']}</b>";

        error_log("Sending Telegram message to chat_id: {$order['telegram_chat_id']}");
        $sent = send_telegram_to_user($order['telegram_chat_id'], $message);
        $channel = 'telegram';
        error_log("Telegram send result: " . ($sent ? 'SUCCESS' : 'FAILED'));

        if (!$sent) {
            echo json_encode(['success' => false, 'message' => 'Error al enviar mensaje por Telegram. Verifica la configuración del bot.']);
            exit;
        }
    } else {
        // Send via Email
        if (empty($order['customer_email'])) {
            error_log("Custom message error: No email for order {$order_id}");
            echo json_encode(['success' => false, 'message' => 'No hay email registrado para este cliente']);
            exit;
        }

        $subject = "Mensaje sobre tu pedido #{$order['order_number']}";

        // Create simple HTML email
        $html = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #667eea; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
        .message-box { background: white; padding: 20px; border-left: 4px solid #667eea; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin: 0;">📩 Tienes un mensaje</h2>
        </div>
        <div class="content">
            <p>Hola <strong>' . htmlspecialchars($order['customer_name']) . '</strong>,</p>
            <p>Hemos recibido el siguiente mensaje sobre tu pedido <strong>#' . htmlspecialchars($order['order_number']) . '</strong>:</p>

            <div class="message-box">
                ' . nl2br(htmlspecialchars($custom_message)) . '
            </div>

            <p>Si tienes alguna pregunta, no dudes en responder a este correo.</p>

            <div class="footer">
                <p>Este es un mensaje automático, por favor no responder.</p>
            </div>
        </div>
    </div>
</body>
</html>';

        error_log("Sending email to: {$order['customer_email']}");
        $sent = send_email($order['customer_email'], $subject, $html);
        $channel = 'email';
        error_log("Email send result: " . ($sent ? 'SUCCESS' : 'FAILED'));

        if (!$sent) {
            echo json_encode(['success' => false, 'message' => 'Error al enviar email. Verifica la configuración de email.']);
            exit;
        }
    }

    // Save message to order history
    $message_record = [
        'date' => date('Y-m-d H:i:s'),
        'channel' => $channel,
        'message' => $custom_message,
        'sent_by' => $_SESSION['admin_username'] ?? 'admin'
    ];

    // Initialize messages array if it doesn't exist
    if (!isset($order['messages']) || !is_array($order['messages'])) {
        $order['messages'] = [];
    }

    // Add new message to the beginning of the array (newest first)
    array_unshift($order['messages'], $message_record);

    // Update order with new message history
    $orders_file = __DIR__ . '/../../data/orders.json';
    $orders_data = read_json($orders_file);

    foreach ($orders_data['orders'] as &$o) {
        if ($o['id'] === $order_id) {
            $o['messages'] = $order['messages'];
            break;
        }
    }

    write_json($orders_file, $orders_data);

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente por ' . ($channel === 'telegram' ? 'Telegram' : 'Email'),
        'channel' => $channel
    ]);

} catch (Exception $e) {
    error_log("Error sending custom message: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar mensaje: ' . $e->getMessage()
    ]);
}
