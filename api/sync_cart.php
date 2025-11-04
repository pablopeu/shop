<?php
/**
 * Sync Cart API - Sincroniza carrito de localStorage a sesión PHP
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';

// Set security headers
set_security_headers();

// Start session
session_start();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['cart']) || !is_array($data['cart'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid cart data']);
    exit;
}

// Validate cart items
$cart = [];
foreach ($data['cart'] as $item) {
    if (!isset($item['product_id']) || !isset($item['quantity'])) {
        continue;
    }

    $product_id = sanitize_input($item['product_id']);
    $quantity = intval($item['quantity']);

    if ($quantity > 0) {
        $cart[$product_id] = $quantity;
    }
}

// Save to session
$_SESSION['cart'] = $cart;

// Also save coupon if present
if (isset($data['coupon_code']) && !empty($data['coupon_code'])) {
    $_SESSION['coupon_code'] = sanitize_input($data['coupon_code']);
}

echo json_encode([
    'success' => true,
    'items' => count($cart),
    'message' => 'Cart synchronized successfully'
]);
