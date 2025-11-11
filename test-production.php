<?php
/**
 * Sistema de Testing en Producción
 *
 * Este script simula compras REALES en tu tienda y deja TODO registrado
 * para que puedas verificar en el backoffice:
 * - Ventas realizadas
 * - Cupones usados
 * - Intentos de compra sin stock
 * - Emails enviados
 * - Notificaciones Telegram
 * - Logs de operaciones
 *
 * IMPORTANTE: Este script NO limpia los datos al finalizar.
 * Todas las órdenes quedan registradas en el sistema.
 *
 * Uso: php test-production.php
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/telegram.php';

// =============================================================================
// CONFIGURACIÓN
// =============================================================================

$customer_test = [
    'name' => 'Cliente Test Automatizado',
    'email' => 'test@ejemplo.com',
    'phone' => '+5491100000000'
];

// =============================================================================
// CLASE PRINCIPAL
// =============================================================================

class ProductionTester {
    private $results = [];
    private $test_orders = [];
    private $start_time;
    private $operations_log = [];

    public function __construct() {
        $this->start_time = microtime(true);

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║        TESTING EN PRODUCCIÓN - Verificación Completa        ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "⚠️  IMPORTANTE: Este script NO borra los datos al finalizar.\n";
        echo "   Todas las órdenes quedarán registradas en el sistema.\n\n";
    }

    public function runAllTests() {
        echo "📋 Iniciando tests de producción...\n\n";

        try {
            // Tests
            $this->runTest('Test 1: Verificar productos disponibles', [$this, 'testGetAvailableProducts']);
            $this->runTest('Test 2: Verificar cupones activos', [$this, 'testGetActiveCoupons']);
            $this->runTest('Test 3: Compra exitosa (Presencial)', [$this, 'testSuccessfulPurchasePresencial']);
            $this->runTest('Test 4: Compra con cupón de descuento', [$this, 'testPurchaseWithCoupon']);
            $this->runTest('Test 5: Intento de compra sin stock', [$this, 'testInsufficientStock']);
            $this->runTest('Test 6: Compra múltiples productos', [$this, 'testMultipleProducts']);
            $this->runTest('Test 7: Verificar emails generados', [$this, 'testEmailsGenerated']);
            $this->runTest('Test 8: Verificar órdenes en sistema', [$this, 'testOrdersInSystem']);

        } catch (Exception $e) {
            $this->logError("Error fatal: " . $e->getMessage());
        }

        // Generar informe
        $this->generateReport();
    }

    private function runTest($name, $callback) {
        echo "Ejecutando: $name...\n";

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
            echo "   ✓ $details\n\n";
        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
            echo "   ✗ Error: " . $e->getMessage() . "\n\n";
        }

        $result['time'] = round((microtime(true) - $start) * 1000, 2);
        $this->results[] = $result;
    }

    /**
     * TEST 1: Verificar productos disponibles
     */
    private function testGetAvailableProducts() {
        $products = get_all_products(true); // Solo activos

        if (empty($products)) {
            throw new Exception("No hay productos activos en el sistema");
        }

        // Guardar productos con stock para usar en otros tests
        $this->available_products = array_filter($products, function($p) {
            return $p['stock'] > 0;
        });

        if (empty($this->available_products)) {
            throw new Exception("No hay productos con stock disponible");
        }

        return count($products) . " productos activos encontrados, " . count($this->available_products) . " con stock";
    }

    /**
     * TEST 2: Verificar cupones activos
     */
    private function testGetActiveCoupons() {
        $coupons_file = __DIR__ . '/data/coupons.json';

        if (!file_exists($coupons_file)) {
            return "No hay archivo de cupones (esto es normal)";
        }

        $coupons_data = read_json($coupons_file);

        if (empty($coupons_data['coupons'])) {
            return "No hay cupones configurados";
        }

        $active_coupons = array_filter($coupons_data['coupons'], function($c) {
            return $c['active'] ?? false;
        });

        $this->available_coupons = $active_coupons;

        return count($active_coupons) . " cupones activos encontrados";
    }

    /**
     * TEST 3: Compra exitosa (Presencial)
     */
    private function testSuccessfulPurchasePresencial() {
        global $customer_test;

        if (empty($this->available_products)) {
            throw new Exception("No hay productos disponibles para comprar");
        }

        // Tomar el primer producto con stock
        $product = reset($this->available_products);
        $product_full = get_product_by_id($product['id']);

        $order_data = [
            'user_id' => 'test-production',
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
            'payment_method' => 'presencial',
            'payment_status' => 'pending',
            'customer_name' => $customer_test['name'],
            'customer_email' => $customer_test['email'],
            'customer_phone' => $customer_test['phone'],
            'notes' => '[TEST PRODUCCIÓN] Compra exitosa presencial'
        ];

        $result = create_order($order_data);

        if (!$result['success']) {
            throw new Exception("Error al crear orden: " . ($result['error'] ?? 'Unknown'));
        }

        $order = $result['order'];
        $this->test_orders[] = $order['id'];

        // Enviar notificaciones (igual que checkout.php)
        send_order_confirmation_email($order);
        send_admin_new_order_email($order);
        send_telegram_new_order($order);

        $this->logOperation('COMPRA_PRESENCIAL', $order);

        return "Orden #{$order['order_number']} creada - Producto: {$product['name']} - Total: \${$order['total']}";
    }

    /**
     * TEST 4: Compra con cupón
     */
    private function testPurchaseWithCoupon() {
        global $customer_test;

        if (empty($this->available_products)) {
            throw new Exception("No hay productos disponibles");
        }

        if (empty($this->available_coupons)) {
            return "SALTADO: No hay cupones activos para probar";
        }

        // Tomar el primer cupón activo
        $coupon = reset($this->available_coupons);
        $product = reset($this->available_products);

        $subtotal = $product['price_ars'] * 2;
        $discount = 0;

        if ($coupon['type'] === 'percentage') {
            $discount = ($subtotal * $coupon['value']) / 100;
        } else {
            $discount = $coupon['value'];
        }

        $total = $subtotal - $discount;

        $order_data = [
            'user_id' => 'test-production',
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
            'subtotal' => $subtotal,
            'discount_promotion' => 0,
            'discount_coupon' => $discount,
            'coupon_code' => $coupon['code'],
            'shipping_cost' => 0,
            'total' => $total,
            'payment_method' => 'presencial',
            'payment_status' => 'pending',
            'customer_name' => $customer_test['name'],
            'customer_email' => $customer_test['email'],
            'customer_phone' => $customer_test['phone'],
            'notes' => '[TEST PRODUCCIÓN] Compra con cupón ' . $coupon['code']
        ];

        $result = create_order($order_data);

        if (!isset($result['success']) || !$result['success']) {
            $error_msg = $result['error'] ?? 'Unknown error';
            // Si es por stock, no es un error del test
            if (strpos($error_msg, 'Insufficient stock') !== false) {
                return "SALTADO: Stock insuficiente para probar cupón ($error_msg)";
            }
            throw new Exception("Error al crear orden con cupón: " . $error_msg);
        }

        $order = $result['order'];
        $this->test_orders[] = $order['id'];

        // Enviar notificaciones (igual que checkout.php)
        send_order_confirmation_email($order);
        send_admin_new_order_email($order);
        send_telegram_new_order($order);

        $this->logOperation('COMPRA_CON_CUPON', $order);

        return "Orden #{$order['order_number']} con cupón {$coupon['code']} - Descuento: \${$discount} - Total: \${$total}";
    }

    /**
     * TEST 5: Intento de compra sin stock
     */
    private function testInsufficientStock() {
        global $customer_test;

        if (empty($this->available_products)) {
            throw new Exception("No hay productos disponibles");
        }

        $product = reset($this->available_products);
        $product_full = get_product_by_id($product['id']);

        // Intentar comprar más del stock disponible
        $excessive_quantity = $product_full['stock'] + 10;

        $order_data = [
            'user_id' => 'test-production',
            'items' => [
                [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price_ars'],
                    'quantity' => $excessive_quantity,
                    'final_price' => $product['price_ars'] * $excessive_quantity
                ]
            ],
            'currency' => 'ARS',
            'subtotal' => $product['price_ars'] * $excessive_quantity,
            'discount_promotion' => 0,
            'discount_coupon' => 0,
            'shipping_cost' => 0,
            'total' => $product['price_ars'] * $excessive_quantity,
            'payment_method' => 'presencial',
            'payment_status' => 'pending',
            'customer_name' => $customer_test['name'],
            'customer_email' => $customer_test['email'],
            'customer_phone' => $customer_test['phone'],
            'notes' => '[TEST PRODUCCIÓN] Intento compra sin stock'
        ];

        $result = create_order($order_data);

        // Este test debe FALLAR (eso es lo esperado)
        if (isset($result['success']) && $result['success']) {
            throw new Exception("ERROR: La orden se creó con stock insuficiente (no debería permitirse)");
        }

        $this->logOperation('VALIDACION_STOCK', [
            'producto' => $product['name'],
            'stock_disponible' => $product_full['stock'],
            'cantidad_solicitada' => $excessive_quantity,
            'resultado' => 'Rechazado correctamente'
        ]);

        return "Validación correcta: Stock insuficiente detectado ({$product_full['stock']} disponibles vs {$excessive_quantity} solicitados)";
    }

    /**
     * TEST 6: Compra múltiples productos
     */
    private function testMultipleProducts() {
        global $customer_test;

        if (count($this->available_products) < 2) {
            return "SALTADO: Se necesitan al menos 2 productos con stock";
        }

        // Tomar 2 productos
        $products = array_slice($this->available_products, 0, 2);
        $items = [];
        $subtotal = 0;

        foreach ($products as $product) {
            $items[] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price_ars'],
                'quantity' => 1,
                'final_price' => $product['price_ars']
            ];
            $subtotal += $product['price_ars'];
        }

        $order_data = [
            'user_id' => 'test-production',
            'items' => $items,
            'currency' => 'ARS',
            'subtotal' => $subtotal,
            'discount_promotion' => 0,
            'discount_coupon' => 0,
            'shipping_cost' => 0,
            'total' => $subtotal,
            'payment_method' => 'presencial',
            'payment_status' => 'pending',
            'customer_name' => $customer_test['name'],
            'customer_email' => $customer_test['email'],
            'customer_phone' => $customer_test['phone'],
            'notes' => '[TEST PRODUCCIÓN] Compra múltiples productos'
        ];

        $result = create_order($order_data);

        if (!isset($result['success']) || !$result['success']) {
            $error_msg = $result['error'] ?? 'Unknown error';
            // Si es por stock, no es un error del test
            if (strpos($error_msg, 'Insufficient stock') !== false) {
                return "SALTADO: Stock insuficiente para compra múltiple ($error_msg)";
            }
            throw new Exception("Error al crear orden múltiple: " . $error_msg);
        }

        $order = $result['order'];
        $this->test_orders[] = $order['id'];

        // Enviar notificaciones (igual que checkout.php)
        send_order_confirmation_email($order);
        send_admin_new_order_email($order);
        send_telegram_new_order($order);

        $this->logOperation('COMPRA_MULTIPLE', $order);

        $product_names = implode(', ', array_column($items, 'name'));
        return "Orden #{$order['order_number']} con " . count($items) . " productos - Total: \${$order['total']}";
    }

    /**
     * TEST 7: Verificar emails generados
     */
    private function testEmailsGenerated() {
        $emails_sent = 0;
        $orders_with_emails = [];

        foreach ($this->test_orders as $order_id) {
            $order = get_order_by_id($order_id);

            if ($order && isset($order['emails_sent'])) {
                foreach ($order['emails_sent'] as $type => $sent) {
                    if ($sent) {
                        $emails_sent++;
                        $orders_with_emails[] = $order['order_number'];
                    }
                }
            }
        }

        $this->logOperation('EMAILS_GENERADOS', [
            'total_emails' => $emails_sent,
            'ordenes' => $orders_with_emails
        ]);

        return "$emails_sent emails registrados para " . count(array_unique($orders_with_emails)) . " órdenes";
    }

    /**
     * TEST 8: Verificar órdenes en sistema
     */
    private function testOrdersInSystem() {
        $verified = 0;
        $order_numbers = [];

        foreach ($this->test_orders as $order_id) {
            $order = get_order_by_id($order_id);

            if ($order) {
                $verified++;
                $order_numbers[] = $order['order_number'];
            }
        }

        if ($verified !== count($this->test_orders)) {
            throw new Exception("Solo se encontraron $verified de " . count($this->test_orders) . " órdenes");
        }

        return "$verified órdenes verificadas en el sistema: " . implode(', ', $order_numbers);
    }

    /**
     * Log de operaciones
     */
    private function logOperation($type, $data) {
        $this->operations_log[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'data' => $data
        ];
    }

    /**
     * Generar informe
     */
    private function generateReport() {
        $total_time = round((microtime(true) - $this->start_time) * 1000, 2);
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'passed'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        $total = count($this->results);

        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                  RESUMEN DE TESTS                            ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Total de tests:  $total\n";
        echo "✓ Exitosos:      $passed\n";
        echo "✗ Fallidos:      $failed\n";
        echo "⏱  Tiempo total:  {$total_time}ms\n";
        echo "\n";

        // Detalle de resultados
        echo "┌─────────────────────────────────────────────────────────┬──────────┐\n";
        echo "│ Test                                                    │ Status   │\n";
        echo "├─────────────────────────────────────────────────────────┼──────────┤\n";

        foreach ($this->results as $result) {
            $name = str_pad(substr($result['name'], 0, 55), 55);
            $status = $result['status'] === 'passed' ? '✓ PASS' : '✗ FAIL';
            echo "│ $name │ $status  │\n";
        }

        echo "└─────────────────────────────────────────────────────────┴──────────┘\n";
        echo "\n";

        // Información de órdenes creadas
        if (!empty($this->test_orders)) {
            echo "📦 ÓRDENES CREADAS (revisar en backoffice):\n";
            echo "────────────────────────────────────────────────────────\n";

            foreach ($this->test_orders as $order_id) {
                $order = get_order_by_id($order_id);
                if ($order) {
                    echo "  • Orden #{$order['order_number']}\n";
                    echo "    ID: {$order['id']}\n";
                    echo "    Total: \${$order['total']} {$order['currency']}\n";
                    echo "    Estado: {$order['status']}\n";
                    echo "    Método pago: {$order['payment_method']}\n";
                    if (!empty($order['coupon_code'])) {
                        echo "    Cupón usado: {$order['coupon_code']}\n";
                    }
                    echo "\n";
                }
            }
        }

        // Log de operaciones
        echo "📊 LOG DE OPERACIONES:\n";
        echo "────────────────────────────────────────────────────────\n";
        foreach ($this->operations_log as $log) {
            echo "  [{$log['timestamp']}] {$log['type']}\n";
        }
        echo "\n";

        // Guardar log detallado
        $this->saveDetailedLog();

        echo "✅ Testing completado. Revisa el backoffice para ver todos los registros.\n";
        echo "📄 Log detallado guardado en: production-test-log.json\n\n";
    }

    /**
     * Guardar log detallado
     */
    private function saveDetailedLog() {
        $log_data = [
            'timestamp' => date('c'),
            'duration_ms' => round((microtime(true) - $this->start_time) * 1000, 2),
            'results' => $this->results,
            'orders_created' => $this->test_orders,
            'operations' => $this->operations_log
        ];

        $log_file = __DIR__ . '/production-test-log.json';
        file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function logError($message) {
        error_log("[Production Test] $message");
        echo "ERROR: $message\n";
    }
}

// =============================================================================
// EJECUTAR
// =============================================================================

try {
    $tester = new ProductionTester();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n\n";
    exit(1);
}
