<?php
/**
 * Checkout Page - Purchase Process
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/orders.php';

// Set security headers
set_security_headers();

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

// Start session
session_start();

// Check if cart exists
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: /carrito.php?error=empty');
    exit;
}

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$payment_config = read_json(__DIR__ . '/config/payment.json');
$theme_config = read_json(__DIR__ . '/config/theme.json');

$active_theme = $theme_config['active_theme'] ?? 'minimal';
$selected_currency = $_SESSION['currency'] ?? $currency_config['primary'];

// Initialize variables
$errors = [];
$success = false;
$coupon_discount = 0;
$coupon_code = $_SESSION['coupon_code'] ?? null;

// Calculate cart totals
$cart_items = [];
$subtotal = 0;
$exchange_rate = $currency_config['exchange_rate'] ?? 1500;

// First pass: determine if all products are USD-only
$all_products_usd = true;
foreach ($_SESSION['cart'] as $product_id => $quantity) {
    $product = get_product_by_id($product_id);
    if (!$product || !$product['active']) {
        continue;
    }

    $price_ars = floatval($product['price_ars'] ?? 0);
    $price_usd = floatval($product['price_usd'] ?? 0);

    // If product has ARS price or both prices, not all are USD
    if ($price_ars > 0) {
        $all_products_usd = false;
        break;
    }
}

// Set currency based on cart contents
$checkout_currency = ($all_products_usd) ? 'USD' : 'ARS';

// Second pass: calculate totals
foreach ($_SESSION['cart'] as $product_id => $quantity) {
    $product = get_product_by_id($product_id);

    if (!$product || !$product['active']) {
        continue;
    }

    // Check stock availability
    if ($product['stock'] < $quantity) {
        $errors[] = "Stock insuficiente para: {$product['name']}";
        continue;
    }

    $price_ars = floatval($product['price_ars'] ?? 0);
    $price_usd = floatval($product['price_usd'] ?? 0);

    // Determine price based on checkout currency
    if ($checkout_currency === 'USD') {
        // All products are USD, use USD price
        $price = $price_usd;
    } else {
        // Mixed cart or all ARS, convert to ARS
        if ($price_ars > 0) {
            $price = $price_ars;
        } else {
            // USD product in mixed cart, convert to ARS
            $price = $price_usd * $exchange_rate;
        }
    }

    $item_total = $price * $quantity;

    $cart_items[] = [
        'product_id' => $product_id,
        'name' => $product['name'],
        'price' => $price,
        'quantity' => $quantity,
        'subtotal' => $item_total,
        'thumbnail' => $product['thumbnail']
    ];

    $subtotal += $item_total;
}

// Update selected currency for display
$selected_currency = $checkout_currency;

// Apply coupon if exists
if ($coupon_code) {
    $coupon_result = validate_coupon($coupon_code, $subtotal);
    if ($coupon_result['valid']) {
        $coupon_discount = $coupon_result['discount'];
    } else {
        unset($_SESSION['coupon_code']);
        $coupon_code = null;
    }
}

$total = $subtotal - $coupon_discount;

// Calculate totals in both currencies for display toggle
$subtotal_ars = 0;
$subtotal_usd = 0;
$total_ars = 0;
$total_usd = 0;

if ($checkout_currency === 'USD') {
    // Cart is in USD, convert to ARS for display
    $subtotal_usd = $subtotal;
    $subtotal_ars = $subtotal * $exchange_rate;
    $total_usd = $total;
    $total_ars = $total * $exchange_rate;
} else {
    // Cart is in ARS, convert to USD for display
    $subtotal_ars = $subtotal;
    $subtotal_usd = $subtotal / $exchange_rate;
    $total_ars = $total;
    $total_usd = $total / $exchange_rate;
}

$coupon_discount_ars = $coupon_discount;
$coupon_discount_usd = $coupon_discount / ($checkout_currency === 'USD' ? 1 : $exchange_rate);
if ($checkout_currency === 'ARS') {
    $coupon_discount_usd = $coupon_discount / $exchange_rate;
} else {
    $coupon_discount_ars = $coupon_discount * $exchange_rate;
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido';
    }

    // Get form data
    $customer_name = sanitize_input($_POST['customer_name'] ?? '');
    $customer_email = sanitize_input($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_input($_POST['customer_phone'] ?? '');
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');

    // Validate required fields
    if (empty($customer_name)) {
        $errors[] = 'El nombre es requerido';
    }

    if (empty($customer_email) || !validate_email($customer_email)) {
        $errors[] = 'Email inválido';
    }

    if (empty($customer_phone)) {
        $errors[] = 'El teléfono es requerido';
    }

    if (!in_array($payment_method, ['presencial', 'mercadopago'])) {
        $errors[] = 'Método de pago inválido';
    }

    // Shipping address (only for delivery)
    $shipping_address = null;
    $needs_shipping = isset($_POST['needs_shipping']) && $_POST['needs_shipping'] === '1';

    if ($needs_shipping) {
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $postal_code = sanitize_input($_POST['postal_code'] ?? '');

        if (empty($address)) {
            $errors[] = 'La dirección es requerida para envío';
        }

        if (empty($city)) {
            $errors[] = 'La ciudad es requerida para envío';
        }

        if (empty($postal_code)) {
            $errors[] = 'El código postal es requerido para envío';
        }

        if (empty($errors)) {
            $shipping_address = [
                'name' => $customer_name,
                'address' => $address,
                'city' => $city,
                'postal_code' => $postal_code,
                'phone' => $customer_phone
            ];
        }
    }

    // If no errors, create order
    if (empty($errors)) {

        // Prepare order items
        $order_items = [];
        foreach ($cart_items as $item) {
            $order_items[] = [
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'final_price' => $item['subtotal']
            ];
        }

        // Prepare order data
        $order_data = [
            'items' => $order_items,
            'currency' => $selected_currency,
            'subtotal' => $subtotal,
            'discount_coupon' => $coupon_discount,
            'coupon_code' => $coupon_code,
            'total' => $total,
            'payment_method' => $payment_method,
            'shipping_address' => $shipping_address,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'notes' => sanitize_input($_POST['notes'] ?? '')
        ];

        // Create order
        $result = create_order($order_data);

        if (isset($result['success']) && $result['success']) {
            $order = $result['order'];

            // Increment coupon usage if applied
            if ($coupon_code) {
                increment_coupon_usage($coupon_code);
            }

            // Clear cart and coupon
            unset($_SESSION['cart']);
            unset($_SESSION['coupon_code']);

            // Redirect based on payment method
            if ($payment_method === 'presencial') {
                // Redirect to thank you page
                header("Location: /gracias.php?order={$order['id']}&token={$order['tracking_token']}");
                exit;
            } else {
                // TODO: Integrate with Mercadopago and redirect to payment
                // For now, redirect to pending page
                header("Location: /pendiente.php?order={$order['id']}&token={$order['tracking_token']}");
                exit;
            }
        } else {
            $errors[] = $result['error'] ?? 'Error al procesar la orden';
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h1 {
            font-size: 32px;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        /* Errors */
        .errors {
            background: #fee;
            border: 2px solid #fcc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .errors h3 {
            color: #c33;
            margin-bottom: 10px;
        }

        .errors ul {
            margin-left: 20px;
        }

        .errors li {
            color: #c33;
            margin: 5px 0;
        }

        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 30px;
        }

        /* Form Section */
        .checkout-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 40px;
        }

        .form-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .order-summary h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        /* Currency Toggle Buttons */
        .currency-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .currency-btn {
            flex: 1;
            padding: 8px 16px;
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .currency-btn:hover {
            background: #e9ecef;
            border-color: #667eea;
        }

        .currency-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
        }

        .summary-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .summary-item-info {
            flex: 1;
        }

        .summary-item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .summary-item-price {
            color: #666;
            font-size: 14px;
        }

        .summary-totals {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
        }

        .summary-row.discount {
            color: #4CAF50;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            margin-right: 10px;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .payment-method {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .payment-method:hover {
            border-color: #4CAF50;
        }

        .payment-method input[type="radio"] {
            margin-bottom: 10px;
        }

        .payment-method.selected {
            border-color: #4CAF50;
            background: #f0fdf4;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
                order: -1;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .currency-btn {
                padding: 6px 12px;
                font-size: 13px;
            }
        }
    </style>
    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="/includes/mobile-menu.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="/" class="logo"><?php echo htmlspecialchars($site_config['site_name']); ?></a>
            <div>
                <a href="/carrito.php" class="btn-secondary">← Volver al carrito</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <h1>Finalizar Compra</h1>

        <?php if (!empty($errors)): ?>
        <div class="errors">
            <h3>⚠️ Por favor corrige los siguientes errores:</h3>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="checkout-layout">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- Customer Information -->
                    <div class="form-section">
                        <h2>📋 Información de Contacto</h2>

                        <div class="form-group">
                            <label for="customer_name">Nombre completo *</label>
                            <input type="text" id="customer_name" name="customer_name"
                                   value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_email">Email *</label>
                            <input type="email" id="customer_email" name="customer_email"
                                   value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_phone">Teléfono *</label>
                            <input type="tel" id="customer_phone" name="customer_phone"
                                   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- Shipping Information -->
                    <div class="form-section">
                        <h2>🚚 Información de Envío</h2>

                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="needs_shipping" name="needs_shipping" value="1"
                                   onchange="toggleShipping(this.checked)"
                                   <?php echo (isset($_POST['needs_shipping']) && $_POST['needs_shipping'] === '1') ? 'checked' : ''; ?>>
                            <label for="needs_shipping">Necesito envío a domicilio</label>
                        </div>

                        <div id="shipping-fields" style="display: none;">
                            <div class="form-group">
                                <label for="address">Dirección</label>
                                <input type="text" id="address" name="address"
                                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="city">Ciudad</label>
                                <input type="text" id="city" name="city"
                                       value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="postal_code">Código Postal</label>
                                <input type="text" id="postal_code" name="postal_code"
                                       value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-section">
                        <h2>💳 Método de Pago</h2>

                        <div class="payment-methods">
                            <label class="payment-method" id="payment-presencial">
                                <input type="radio" name="payment_method" value="presencial" required
                                       onchange="selectPayment('presencial')">
                                <div>
                                    <strong>💵 Pago Presencial</strong>
                                    <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                        Retiro en local o pago contra entrega
                                    </p>
                                </div>
                            </label>

                            <label class="payment-method" id="payment-mercadopago">
                                <input type="radio" name="payment_method" value="mercadopago" required
                                       onchange="selectPayment('mercadopago')">
                                <div>
                                    <strong>💳 Mercadopago</strong>
                                    <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                        Tarjeta de crédito/débito
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-section">
                        <h2>📝 Notas Adicionales</h2>

                        <div class="form-group">
                            <label for="notes">Comentarios (opcional)</label>
                            <textarea id="notes" name="notes" placeholder="Ej: horario preferido de entrega, referencias de ubicación..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="place_order" class="btn btn-primary">
                        🛒 Confirmar Pedido
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h2>📦 Resumen del Pedido</h2>

                <!-- Currency Toggle Buttons -->
                <div class="currency-toggle">
                    <button class="currency-btn <?php echo $checkout_currency === 'ARS' ? 'active' : ''; ?>" data-currency="ARS" onclick="switchCurrency('ARS')">
                        💵 Pesos (ARS)
                    </button>
                    <button class="currency-btn <?php echo $checkout_currency === 'USD' ? 'active' : ''; ?>" data-currency="USD" onclick="switchCurrency('USD')">
                        💵 Dólares (USD)
                    </button>
                </div>

                <?php foreach ($cart_items as $item): ?>
                <div class="summary-item">
                    <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>"
                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <div class="summary-item-info">
                        <div class="summary-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="summary-item-price">
                            <?php echo $item['quantity']; ?>x <?php echo format_price($item['price'], $selected_currency); ?>
                        </div>
                    </div>
                    <div>
                        <strong><?php echo format_price($item['subtotal'], $selected_currency); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="summary-totals">
                    <?php if ($coupon_discount > 0): ?>
                    <div class="summary-row discount">
                        <span>Descuento (<?php echo htmlspecialchars($coupon_code); ?>):</span>
                        <span id="discount-display"
                              data-ars="<?php echo number_format($coupon_discount_ars, 2, '.', ''); ?>"
                              data-usd="<?php echo number_format($coupon_discount_usd, 2, '.', ''); ?>">
                            -<?php echo format_price($coupon_discount, $selected_currency); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total-display"
                              data-ars="<?php echo number_format($total_ars, 2, '.', ''); ?>"
                              data-usd="<?php echo number_format($total_usd, 2, '.', ''); ?>">
                            <?php echo format_price($total, $selected_currency); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle shipping fields
        function toggleShipping(show) {
            const fields = document.getElementById('shipping-fields');
            fields.style.display = show ? 'block' : 'none';

            // Update required attribute on shipping fields
            const inputs = fields.querySelectorAll('input');
            inputs.forEach(input => {
                input.required = show;
            });
        }

        // Initialize shipping fields visibility
        document.addEventListener('DOMContentLoaded', () => {
            const checkbox = document.getElementById('needs_shipping');
            if (checkbox.checked) {
                toggleShipping(true);
            }
        });

        // Select payment method
        function selectPayment(method) {
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            document.getElementById('payment-' + method).classList.add('selected');
        }

        // Switch currency display
        function switchCurrency(currency) {
            // Update button states
            document.querySelectorAll('.currency-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`.currency-btn[data-currency="${currency}"]`).classList.add('active');

            // Format price function
            function formatPrice(amount, curr) {
                if (curr === 'ARS') {
                    return '$ ' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                } else {
                    return 'U$D ' + parseFloat(amount).toFixed(2).replace('.', ',');
                }
            }

            // Update discount
            const discountEl = document.getElementById('discount-display');
            if (discountEl) {
                const discount = currency === 'ARS' ? discountEl.dataset.ars : discountEl.dataset.usd;
                discountEl.textContent = '-' + formatPrice(discount, currency);
            }

            // Update total
            const totalEl = document.getElementById('total-display');
            if (totalEl) {
                const total = currency === 'ARS' ? totalEl.dataset.ars : totalEl.dataset.usd;
                totalEl.textContent = formatPrice(total, currency);
            }
        }
    </script>
    <!-- Mobile Menu -->
    <script src="/includes/mobile-menu.js"></script>
</body>
</html>
