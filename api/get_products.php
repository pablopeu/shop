<?php
/**
 * API: Get Products by IDs
 * Returns product details for given product IDs
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/products.php';

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['product_ids']) || !is_array($data['product_ids'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. product_ids array required.']);
    exit;
}

$product_ids = $data['product_ids'];
$products = [];

foreach ($product_ids as $product_id) {
    $product = get_product_by_id($product_id);
    if ($product) {
        $products[] = $product;
    }
}

echo json_encode($products);
