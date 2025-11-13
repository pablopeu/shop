<?php
/**
 * API Endpoint - Send Telegram Test Message
 * Sends a test message to validate customer's Telegram
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/telegram.php';

// Start session
session_start();

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Get JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

// Validate required fields
$chat_id = $data['chat_id'] ?? '';
$customer_name = $data['customer_name'] ?? '';

if (empty($chat_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'chat_id es requerido']);
    exit;
}

if (empty($customer_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'customer_name es requerido']);
    exit;
}

// Get site configuration
$site_config = read_json(__DIR__ . '/../config/site.json');
$site_name = $site_config['site_name'] ?? 'Nuestra Tienda';

// Prepare test message
$message = "✅ <b>Mensaje de Prueba</b>\n\n";
$message .= "Hola <b>{$customer_name}</b>,\n\n";
$message .= "Este es un mensaje de prueba de <b>{$site_name}</b>.\n\n";
$message .= "Si recibiste este mensaje, tu Telegram está correctamente configurado para recibir notificaciones sobre tu pedido.\n\n";
$message .= "📅 " . date('d/m/Y H:i:s');

// Send message
try {
    $result = send_telegram_to_user($chat_id, $message);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Mensaje de prueba enviado exitosamente'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo enviar el mensaje. Verifica que el Chat ID sea correcto y que hayas iniciado una conversación con el bot.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al enviar mensaje: ' . $e->getMessage()
    ]);
}
