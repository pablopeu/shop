<?php
/**
 * Sistema de Testing Automatizado para Flujo de Checkout
 *
 * Este script prueba todo el flujo de compra incluyendo:
 * - Gestión de carrito
 * - Validación de stock
 * - Checkout con pago presencial
 * - Checkout con Mercadopago REAL (usando tarjetas de prueba)
 * - Webhooks de Mercadopago
 * - Envío de emails y notificaciones
 * - Generación de informe detallado
 *
 * Uso: php test-checkout-flow.php [--skip-mp] [--skip-webhook-wait]
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/mercadopago.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/telegram.php';

// Parse command line arguments
$skip_mp = in_array('--skip-mp', $argv ?? []);
$skip_webhook_wait = in_array('--skip-webhook-wait', $argv ?? []);

// =============================================================================
// CONFIGURACIÓN DE TESTS
// =============================================================================

// Tarjetas de prueba de Mercadopago (oficiales)
// Docs: https://www.mercadopago.com.ar/developers/es/docs/checkout-api/testing
define('TEST_CARDS', [
    'approved' => [
        'number' => '5031755734530604',
        'cvv' => '123',
        'expiration_month' => '11',
        'expiration_year' => '2025',
        'cardholder' => [
            'name' => 'APRO',
            'identification' => [
                'type' => 'DNI',
                'number' => '12345678'
            ]
        ]
    ],
    'rejected' => [
        'number' => '5031755734530604',
        'cvv' => '123',
        'expiration_month' => '11',
        'expiration_year' => '2025',
        'cardholder' => [
            'name' => 'OCHO',
            'identification' => [
                'type' => 'DNI',
                'number' => '12345678'
            ]
        ]
    ],
    'pending' => [
        'number' => '5031755734530604',
        'cvv' => '123',
        'expiration_month' => '11',
        'expiration_year' => '2025',
        'cardholder' => [
            'name' => 'CONT',
            'identification' => [
                'type' => 'DNI',
                'number' => '12345678'
            ]
        ]
    ]
]);

// =============================================================================
// CLASE PRINCIPAL DE TESTING
// =============================================================================

class CheckoutFlowTester {
    private $results = [];
    private $test_products = [];
    private $test_orders = [];
    private $test_coupons = [];
    private $start_time;
    private $mp_client = null;
    private $skip_mp = false;
    private $skip_webhook_wait = false;

    public function __construct($skip_mp = false, $skip_webhook_wait = false) {
        $this->start_time = microtime(true);
        $this->skip_mp = $skip_mp;
        $this->skip_webhook_wait = $skip_webhook_wait;

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║   SISTEMA DE TESTING AUTOMATIZADO - FLUJO DE CHECKOUT       ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        if ($this->skip_mp) {
            echo "⚠️  Modo: Tests de Mercadopago DESACTIVADOS\n\n";
        } else {
            echo "✓ Modo: Tests con pagos REALES de Mercadopago\n\n";
        }
    }

    /**
     * Ejecutar todos los tests
     */
    public function runAllTests() {
        echo "📋 Iniciando suite de tests...\n\n";

        try {
            // Setup
            $this->setupTestEnvironment();

            // Test Suite
            $this->runTest('Test 1: Crear productos de prueba', [$this, 'testCreateTestProducts']);
            $this->runTest('Test 2: Crear cupón de prueba', [$this, 'testCreateTestCoupon']);
            $this->runTest('Test 3: Validar productos (stock disponible)', [$this, 'testValidateProducts']);
            $this->runTest('Test 4: Aplicar cupón válido', [$this, 'testApplyCoupon']);
            $this->runTest('Test 5: Checkout con pago presencial', [$this, 'testCheckoutPresencial']);

            if (!$this->skip_mp) {
                $this->runTest('Test 6: Checkout MP - Pago aprobado', [$this, 'testCheckoutMercadopagoApproved']);
                $this->runTest('Test 7: Checkout MP - Pago rechazado', [$this, 'testCheckoutMercadopagoRejected']);
                $this->runTest('Test 8: Checkout MP - Pago pendiente', [$this, 'testCheckoutMercadopagoPending']);
            }

            $this->runTest('Test 9: Validar reducción de stock', [$this, 'testStockReduction']);
            $this->runTest('Test 10: Validar generación de emails', [$this, 'testEmailGeneration']);

            // Cleanup
            $this->cleanupTestEnvironment();

        } catch (Exception $e) {
            $this->logError("Error fatal en test suite: " . $e->getMessage());
        }

        // Generar informe
        $this->generateReport();
    }

    /**
     * Ejecutar un test individual
     */
    private function runTest($name, $callback) {
        echo "Running: $name...";

        $start = microtime(true);
        $result = [
            'name' => $name,
            'status' => 'running',
            'time' => 0,
            'details' => '',
            'error' => null
        ];

        try {
            $details = call_user_func($callback);
            $result['status'] = 'passed';
            $result['details'] = $details;
            echo " ✓\n";
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
            echo " ✗\n";
            echo "   Error: " . $e->getMessage() . "\n";
        }

        $result['time'] = round((microtime(true) - $start) * 1000, 2);
        $this->results[] = $result;
    }

    /**
     * Setup del entorno de testing
     */
    private function setupTestEnvironment() {
        echo "🔧 Configurando entorno de testing...\n";

        // Inicializar cliente de Mercadopago si es necesario
        if (!$this->skip_mp) {
            $payment_config = read_json(__DIR__ . '/config/payment.json');
            $payment_credentials = get_payment_credentials();

            $mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
            $sandbox_mode = ($mode === 'sandbox');

            $access_token = $sandbox_mode ?
                ($payment_credentials['mercadopago']['access_token_sandbox'] ?? '') :
                ($payment_credentials['mercadopago']['access_token_prod'] ?? '');

            $public_key = $sandbox_mode ?
                ($payment_credentials['mercadopago']['public_key_sandbox'] ?? '') :
                ($payment_credentials['mercadopago']['public_key_prod'] ?? '');

            if (empty($access_token)) {
                throw new Exception("Access token de Mercadopago no configurado para modo: $mode");
            }

            if (empty($public_key)) {
                throw new Exception("Public key de Mercadopago no configurada para modo: $mode");
            }

            $this->mp_client = new MercadoPago($access_token, $sandbox_mode, $public_key);
            echo "   ✓ Cliente Mercadopago inicializado (modo: $mode)\n";
        }

        echo "   ✓ Entorno configurado correctamente\n\n";
    }

    /**
     * TEST 1: Crear productos de prueba
     */
    private function testCreateTestProducts() {
        $products_to_create = [
            [
                'name' => '[TEST] Producto Test 1',
                'description' => 'Producto de prueba para testing automatizado',
                'price_ars' => 10000,
                'price_usd' => 10,
                'stock' => 100,
                'active' => true
            ],
            [
                'name' => '[TEST] Producto Test 2',
                'description' => 'Producto de prueba USD para testing',
                'price_ars' => 0,
                'price_usd' => 25,
                'stock' => 50,
                'active' => true
            ]
        ];

        $created = [];
        foreach ($products_to_create as $product_data) {
            $result = create_product($product_data);

            if (!$result['success']) {
                throw new Exception("Error creando producto: " . $result['message']);
            }

            $product_id = $result['product_id'];
            $this->test_products[] = ['id' => $product_id];
            $created[] = $product_id;
        }

        return "Productos creados: " . count($created) . " (" . implode(', ', $created) . ")";
    }

    /**
     * TEST 2: Crear cupón de prueba
     */
    private function testCreateTestCoupon() {
        $coupon_data = [
            'code' => 'TEST10',
            'type' => 'percentage',
            'value' => 10,
            'min_purchase' => 0,
            'max_uses' => 100,
            'active' => true,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 year'))
        ];

        // Crear cupón manualmente
        $coupons_file = __DIR__ . '/data/coupons.json';
        $coupons_data = read_json($coupons_file);

        $coupon_id = 'coupon-' . uniqid() . '-' . bin2hex(random_bytes(4));
        $coupon_data['id'] = $coupon_id;
        $coupon_data['uses_count'] = 0;
        $coupon_data['created_at'] = date('c');

        $coupons_data['coupons'][] = $coupon_data;
        write_json($coupons_file, $coupons_data);

        $this->test_coupons[] = $coupon_id;

        return "Cupón creado: TEST10 (10% descuento)";
    }

    /**
     * TEST 3: Validar productos
     */
    private function testValidateProducts() {
        $validation_errors = [];

        foreach ($this->test_products as $product) {
            $product_full = get_product_by_id($product['id']);

            if (!$product_full) {
                $validation_errors[] = "Producto {$product['id']} no encontrado";
                continue;
            }

            if (!$product_full['active']) {
                $validation_errors[] = "Producto {$product['id']} no está activo";
            }

            if ($product_full['stock'] <= 0) {
                $validation_errors[] = "Producto {$product['id']} sin stock";
            }
        }

        if (!empty($validation_errors)) {
            throw new Exception(implode(', ', $validation_errors));
        }

        return "Todos los productos validados correctamente";
    }

    /**
     * TEST 4: Aplicar cupón
     */
    private function testApplyCoupon() {
        $coupons_file = __DIR__ . '/data/coupons.json';
        $coupons_data = read_json($coupons_file);

        $coupon = null;
        foreach ($coupons_data['coupons'] as $c) {
            if ($c['code'] === 'TEST10') {
                $coupon = $c;
                break;
            }
        }

        if (!$coupon) {
            throw new Exception("Cupón TEST10 no encontrado");
        }

        if (!$coupon['active']) {
            throw new Exception("Cupón no está activo");
        }

        // Calcular descuento
        $subtotal = 10000; // Ejemplo
        $discount = 0;

        if ($coupon['type'] === 'percentage') {
            $discount = ($subtotal * $coupon['value']) / 100;
        } else {
            $discount = $coupon['value'];
        }

        return "Cupón válido. Descuento calculado: $" . number_format($discount, 2);
    }

    /**
     * TEST 5: Checkout con pago presencial
     */
    private function testCheckoutPresencial() {
        if (empty($this->test_products)) {
            throw new Exception("No hay productos de prueba disponibles");
        }

        $product_test = $this->test_products[0];
        $product = get_product_by_id($product_test['id']);

        if (!$product) {
            throw new Exception("No se pudo cargar el producto de prueba");
        }

        // Preparar datos de orden
        $order_data = [
            'user_id' => 'test-user',
            'items' => [
                [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price_ars'],
                    'quantity' => 2,
                    'final_price' => $product['price_ars'] * 2
                ]
            ],
            'currency' => 'ARS',
            'subtotal' => $product['price_ars'] * 2,
            'discount_promotion' => 0,
            'discount_coupon' => 0,
            'shipping_cost' => 0,
            'total' => $product['price_ars'] * 2,
            'payment_method' => 'presencial',
            'payment_status' => 'pending',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+5491123456789',
            'notes' => 'Orden de prueba - Presencial'
        ];

        // Crear orden
        $result = create_order($order_data);

        if (!$result['success']) {
            throw new Exception("Error creando orden: " . ($result['error'] ?? 'Unknown error'));
        }

        $order = $result['order'];
        $this->test_orders[] = $order['id'];

        // Verificar que se creó correctamente
        $created_order = get_order_by_id($order['id']);
        if (!$created_order) {
            throw new Exception("Orden no se creó correctamente");
        }

        if ($created_order['status'] !== 'pending') {
            throw new Exception("Estado de orden incorrecto: " . $created_order['status']);
        }

        return "Orden presencial creada: {$order['id']} - Total: \${$order['total']}";
    }

    /**
     * TEST 6: Checkout Mercadopago - Pago aprobado
     */
    private function testCheckoutMercadopagoApproved() {
        return $this->testCheckoutMercadopago('approved');
    }

    /**
     * TEST 7: Checkout Mercadopago - Pago rechazado
     */
    private function testCheckoutMercadopagoRejected() {
        return $this->testCheckoutMercadopago('rejected');
    }

    /**
     * TEST 8: Checkout Mercadopago - Pago pendiente
     */
    private function testCheckoutMercadopagoPending() {
        return $this->testCheckoutMercadopago('pending');
    }

    /**
     * Realizar checkout con Mercadopago (real)
     */
    private function testCheckoutMercadopago($scenario = 'approved') {
        if (empty($this->test_products)) {
            throw new Exception("No hay productos de prueba disponibles");
        }

        $product_test = $this->test_products[0];
        $product = get_product_by_id($product_test['id']);

        if (!$product) {
            throw new Exception("No se pudo cargar el producto de prueba");
        }

        $card = TEST_CARDS[$scenario];

        // Crear orden
        $order_data = [
            'user_id' => 'test-user',
            'items' => [
                [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price_ars'],
                    'quantity' => 1,
                    'final_price' => $product['price_ars']
                ]
            ],
            'currency' => 'ARS',
            'subtotal' => $product['price_ars'],
            'discount_promotion' => 0,
            'discount_coupon' => 0,
            'shipping_cost' => 0,
            'total' => $product['price_ars'],
            'payment_method' => 'mercadopago',
            'payment_status' => 'pending',
            'customer_name' => 'Test Customer MP',
            'customer_email' => 'test-mp@example.com',
            'customer_phone' => '+5491123456789',
            'notes' => "Orden de prueba MP - Escenario: $scenario"
        ];

        $result = create_order($order_data);

        if (!$result['success']) {
            throw new Exception("Error creando orden: " . ($result['error'] ?? 'Unknown error'));
        }

        $order = $result['order'];
        $this->test_orders[] = $order['id'];

        // Crear pago con Mercadopago usando tarjeta de prueba
        echo "\n   → Procesando pago real con Mercadopago (escenario: $scenario)...\n";

        try {
            // Primero, tokenizar la tarjeta
            echo "   → Tokenizando tarjeta...\n";
            $card_token_data = [
                'card_number' => $card['number'],
                'security_code' => $card['cvv'],
                'expiration_month' => intval($card['expiration_month']),
                'expiration_year' => intval($card['expiration_year']),
                'cardholder' => [
                    'name' => $card['cardholder']['name'],
                    'identification' => $card['cardholder']['identification']
                ]
            ];

            $card_token = $this->mp_client->createCardToken($card_token_data);
            echo "   → Token creado: {$card_token['id']}\n";

            // Luego, crear el pago con el token
            $payment_data = [
                'transaction_amount' => floatval($order['total']),
                'description' => "Test Order #{$order['order_number']}",
                'payment_method_id' => 'master',
                'token' => $card_token['id'],
                'payer' => [
                    'email' => $order['customer_email'],
                    'identification' => $card['cardholder']['identification']
                ],
                'external_reference' => $order['id'],
                'metadata' => [
                    'order_id' => $order['id']
                ]
            ];

            $payment = $this->mp_client->createPayment($payment_data);

            // Actualizar orden con información del pago
            update_order_payment($order['id'], $payment['status'], $payment['id']);

            // Verificar status esperado
            $expected_statuses = [
                'approved' => 'approved',
                'rejected' => 'rejected',
                'pending' => ['pending', 'in_process']
            ];

            $expected = $expected_statuses[$scenario];
            if (is_array($expected)) {
                if (!in_array($payment['status'], $expected)) {
                    throw new Exception("Status inesperado: {$payment['status']}, esperaba uno de: " . implode(', ', $expected));
                }
            } else {
                if ($payment['status'] !== $expected) {
                    throw new Exception("Status inesperado: {$payment['status']}, esperaba: $expected");
                }
            }

            echo "   → Pago procesado: {$payment['id']} - Status: {$payment['status']}\n";

            // Si el pago fue aprobado, verificar reducción de stock
            if ($payment['status'] === 'approved') {
                $updated_order = get_order_by_id($order['id']);
                if (!$updated_order['stock_reduced']) {
                    echo "   → Esperando 2 segundos para que se procese el webhook...\n";
                    sleep(2);

                    $updated_order = get_order_by_id($order['id']);
                    if (!$updated_order['stock_reduced']) {
                        throw new Exception("Stock no se redujo después del pago aprobado");
                    }
                }
            }

            return "Pago MP ($scenario): {$payment['id']} - Status: {$payment['status']} - Total: \${$order['total']}";

        } catch (Exception $e) {
            throw new Exception("Error en pago Mercadopago: " . $e->getMessage());
        }
    }

    /**
     * TEST 9: Validar reducción de stock
     */
    private function testStockReduction() {
        $validations = [];

        foreach ($this->test_orders as $order_id) {
            $order = get_order_by_id($order_id);

            if (!$order) {
                $validations[] = "Orden $order_id no encontrada";
                continue;
            }

            // Solo validar órdenes que deberían tener stock reducido
            if ($order['payment_method'] === 'presencial' ||
                ($order['payment_method'] === 'mercadopago' && $order['payment_status'] === 'approved')) {

                if (!isset($order['stock_reduced']) || !$order['stock_reduced']) {
                    $validations[] = "Orden {$order_id}: Stock no reducido (método: {$order['payment_method']}, status: {$order['payment_status']})";
                }
            }
        }

        if (!empty($validations)) {
            throw new Exception(implode('; ', $validations));
        }

        return "Stock reducido correctamente en todas las órdenes aplicables";
    }

    /**
     * TEST 10: Validar generación de emails
     */
    private function testEmailGeneration() {
        // Verificar que las órdenes tengan registro de emails enviados
        $emails_sent = 0;

        foreach ($this->test_orders as $order_id) {
            $order = get_order_by_id($order_id);

            if (!$order) {
                continue;
            }

            if (isset($order['emails_sent']) && is_array($order['emails_sent'])) {
                foreach ($order['emails_sent'] as $email_type => $sent) {
                    if ($sent) {
                        $emails_sent++;
                    }
                }
            }
        }

        return "Emails registrados: $emails_sent";
    }

    /**
     * Limpiar entorno de testing
     */
    private function cleanupTestEnvironment() {
        echo "\n🧹 Limpiando entorno de testing...\n";

        // Eliminar órdenes de prueba
        $orders_file = __DIR__ . '/data/orders.json';
        $orders_data = read_json($orders_file);

        $original_count = count($orders_data['orders']);
        $orders_data['orders'] = array_filter($orders_data['orders'], function($order) {
            return !in_array($order['id'], $this->test_orders);
        });
        $orders_data['orders'] = array_values($orders_data['orders']);

        write_json($orders_file, $orders_data);
        echo "   ✓ Eliminadas " . ($original_count - count($orders_data['orders'])) . " órdenes de prueba\n";

        // Eliminar productos de prueba
        foreach ($this->test_products as $product) {
            delete_product($product['id']);
        }
        echo "   ✓ Eliminados " . count($this->test_products) . " productos de prueba\n";

        // Eliminar cupones de prueba
        $coupons_file = __DIR__ . '/data/coupons.json';
        $coupons_data = read_json($coupons_file);

        $original_count = count($coupons_data['coupons']);
        $coupons_data['coupons'] = array_filter($coupons_data['coupons'], function($coupon) {
            return !in_array($coupon['id'], $this->test_coupons);
        });
        $coupons_data['coupons'] = array_values($coupons_data['coupons']);

        write_json($coupons_file, $coupons_data);
        echo "   ✓ Eliminados " . ($original_count - count($coupons_data['coupons'])) . " cupones de prueba\n";

        echo "\n";
    }

    /**
     * Generar informe de resultados
     */
    private function generateReport() {
        $total_time = round((microtime(true) - $this->start_time) * 1000, 2);

        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'passed'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        $total = count($this->results);

        // Generar informe en consola
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                     RESUMEN DE TESTS                         ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Total de tests:  $total\n";
        echo "✓ Exitosos:      $passed\n";
        echo "✗ Fallidos:      $failed\n";
        echo "⏱  Tiempo total:  {$total_time}ms\n";
        echo "\n";

        // Tabla de resultados
        echo "┌─────────────────────────────────────────────────────────┬──────────┬───────────┐\n";
        echo "│ Test                                                    │ Status   │ Tiempo    │\n";
        echo "├─────────────────────────────────────────────────────────┼──────────┼───────────┤\n";

        foreach ($this->results as $result) {
            $name = str_pad(substr($result['name'], 0, 55), 55);
            $status = $result['status'] === 'passed' ? '✓ PASS' : '✗ FAIL';
            $status_padded = str_pad($status, 8);
            $time = str_pad($result['time'] . 'ms', 9);

            echo "│ $name │ $status_padded │ $time │\n";

            if ($result['status'] === 'failed' && $result['error']) {
                echo "│ Error: " . str_pad(substr($result['error'], 0, 90), 90) . "│\n";
            }
        }

        echo "└─────────────────────────────────────────────────────────┴──────────┴───────────┘\n";
        echo "\n";

        // Generar informe HTML
        $this->generateHTMLReport();

        // Resultado final
        if ($failed === 0) {
            echo "🎉 Todos los tests pasaron exitosamente!\n\n";
            exit(0);
        } else {
            echo "⚠️  Algunos tests fallaron. Revisa el informe para más detalles.\n\n";
            exit(1);
        }
    }

    /**
     * Generar informe HTML
     */
    private function generateHTMLReport() {
        $total_time = round((microtime(true) - $this->start_time) * 1000, 2);
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'passed'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        $total = count($this->results);
        $success_rate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe de Tests - Checkout Flow</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 18px;
            opacity: 0.9;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 40px;
            background: #f8f9fa;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.passed .value {
            color: #28a745;
        }

        .stat-card.failed .value {
            color: #dc3545;
        }

        .stat-card.total .value {
            color: #007bff;
        }

        .stat-card.rate .value {
            color: #667eea;
        }

        .results {
            padding: 40px;
        }

        .results h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }

        .test-item {
            background: #f8f9fa;
            border-left: 4px solid #ddd;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .test-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateX(5px);
        }

        .test-item.passed {
            border-left-color: #28a745;
        }

        .test-item.failed {
            border-left-color: #dc3545;
        }

        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .test-name {
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }

        .test-meta {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .test-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .test-status.passed {
            background: #28a745;
            color: white;
        }

        .test-status.failed {
            background: #dc3545;
            color: white;
        }

        .test-time {
            color: #6c757d;
            font-size: 14px;
        }

        .test-details {
            color: #495057;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 10px;
        }

        .test-error {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }

        .footer {
            background: #343a40;
            color: white;
            padding: 20px 40px;
            text-align: center;
            font-size: 14px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Informe de Tests - Checkout Flow</h1>
            <p>Testing Automatizado del Sistema de Compras</p>
        </div>

        <div class="stats">
            <div class="stat-card total">
                <div class="value">{$total}</div>
                <div class="label">Total Tests</div>
            </div>
            <div class="stat-card passed">
                <div class="value">{$passed}</div>
                <div class="label">Exitosos</div>
            </div>
            <div class="stat-card failed">
                <div class="value">{$failed}</div>
                <div class="label">Fallidos</div>
            </div>
            <div class="stat-card rate">
                <div class="value">{$success_rate}%</div>
                <div class="label">Tasa de Éxito</div>
            </div>
        </div>

        <div class="results">
            <h2>📋 Resultados Detallados</h2>
HTML;

        foreach ($this->results as $result) {
            $status_class = $result['status'];
            $status_label = $result['status'] === 'passed' ? 'PASS' : 'FAIL';
            $details = htmlspecialchars($result['details'] ?? '');
            $error = htmlspecialchars($result['error'] ?? '');

            $html .= <<<HTML

            <div class="test-item {$status_class}">
                <div class="test-header">
                    <div class="test-name">{$result['name']}</div>
                    <div class="test-meta">
                        <span class="test-status {$status_class}">{$status_label}</span>
                        <span class="test-time">{$result['time']}ms</span>
                    </div>
                </div>
HTML;

            if ($details) {
                $html .= <<<HTML
                <div class="test-details">{$details}</div>
HTML;
            }

            if ($error) {
                $html .= <<<HTML
                <div class="test-error"><strong>Error:</strong> {$error}</div>
HTML;
            }

            $html .= <<<HTML
            </div>
HTML;
        }

        $date = date('Y-m-d H:i:s');

        $html .= <<<HTML
        </div>

        <div class="footer">
            <p>Generado el {$date} | Tiempo total de ejecución: {$total_time}ms</p>
        </div>
    </div>
</body>
</html>
HTML;

        $report_file = __DIR__ . '/test-results-' . date('Ymd-His') . '.html';
        file_put_contents($report_file, $html);

        echo "📄 Informe HTML generado: $report_file\n";
    }

    private function logError($message) {
        error_log("[Checkout Test] $message");
        echo "ERROR: $message\n";
    }
}

// =============================================================================
// EJECUTAR TESTS
// =============================================================================

try {
    $tester = new CheckoutFlowTester($skip_mp, $skip_webhook_wait);
    $tester->runAllTests();
} catch (Exception $e) {
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n\n";
    exit(1);
}
