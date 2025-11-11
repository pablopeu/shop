<?php
/**
 * Send Test Email API
 * Sends a test email to validate customer's email address
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['email']) || !isset($input['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$name = sanitize_input($input['name']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Get site config
$site_config = read_json(__DIR__ . '/../config/site.json');

// Prepare email
$subject = "Mensaje de prueba - " . $site_config['site_name'];
$message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 5px 5px; }
        .success { background: #28a745; color: white; padding: 15px; border-radius: 5px; text-align: center; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✅ Mensaje de Prueba</h1>
        </div>
        <div class='content'>
            <p>Hola <strong>{$name}</strong>,</p>

            <div class='success'>
                <h2 style='margin: 0;'>¡Tu email está correctamente configurado!</h2>
            </div>

            <p>Este es un mensaje de prueba de <strong>{$site_config['site_name']}</strong>.</p>

            <p>Si recibiste este email, significa que podremos enviarte notificaciones sobre:</p>
            <ul>
                <li>✅ Confirmación de tu pedido</li>
                <li>📦 Actualizaciones de envío</li>
                <li>💳 Confirmación de pago</li>
                <li>📞 Información de contacto importante</li>
            </ul>

            <p><strong>No necesitas responder este mensaje.</strong></p>

            <p style='margin-top: 30px;'>Gracias por tu confianza,<br>
            <strong>{$site_config['site_owner']}</strong><br>
            {$site_config['site_name']}</p>
        </div>

        <div class='footer'>
            <p>Este es un mensaje automático de prueba. Por favor no respondas a este email.</p>
        </div>
    </div>
</body>
</html>
";

// Send email
$success = send_email($email, $subject, $message);

// Return response
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Test email sent successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send test email'
    ]);
}
