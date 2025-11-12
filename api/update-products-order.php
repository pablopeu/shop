<?php
/**
 * API Endpoint - Update Products Display Order
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
if (!is_admin_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Get the JSON input
$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true);

if (!isset($data['product_order']) || !is_array($data['product_order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Update the products order
$result = update_products_display_order($data['product_order']);

// Log the action
if ($result['success']) {
    log_admin_action('update_products_order', $_SESSION['username'], [
        'products_count' => count($data['product_order'])
    ]);
}

// Return the result
header('Content-Type: application/json');
echo json_encode($result);
