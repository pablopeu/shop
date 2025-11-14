<?php
/**
 * Checkout Page - Purchase Process
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/theme-loader.php';
require_once __DIR__ . '/includes/email.php';
require_once __DIR__ . '/includes/telegram.php';

// Set security headers
set_security_headers();

// Check maintenance mode
if (is_maintenance_mode()) {
    require_once __DIR__ . '/maintenance.php';
    exit;
}

// Start session
session_start();

// IMPORTANT: Always check cart in session
// If user deleted items in frontend, session might have old data
// The sync should happen via goToCheckout() in carrito.php
// But we need to validate the cart is actually valid

// Check if cart exists in session
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: ' . url('/carrito.php?error=empty'));
    exit;
}

// Re-validate cart items exist and have stock
$valid_items = [];
foreach ($_SESSION['cart'] as $item) {
    $product = get_product_by_id($item['product_id']);
    if ($product && $product['stock'] > 0) {
        $valid_items[] = $item;
    }
}

// If no valid items remain, redirect back
if (empty($valid_items)) {
    unset($_SESSION['cart']);
    header('Location: ' . url('/carrito.php?error=empty'));
    exit;
}

// Update session with only valid items
$_SESSION['cart'] = $valid_items;

// Get configurations
$site_config = read_json(__DIR__ . '/config/site.json');
$footer_config = read_json(__DIR__ . '/config/footer.json');
$currency_config = read_json(__DIR__ . '/config/currency.json');
$payment_config = read_json(__DIR__ . '/config/payment.json');
$payment_credentials = get_payment_credentials();
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
foreach ($_SESSION['cart'] as $cart_item) {
    $product_id = $cart_item['product_id'];
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
foreach ($_SESSION['cart'] as $cart_item) {
    $product_id = $cart_item['product_id'];
    $quantity = $cart_item['quantity'];
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

    // Check if this is an AJAX request
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Clean output buffer for AJAX requests to ensure clean JSON response
    if ($is_ajax) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
    }

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido';
    }

    // Get form data
    $customer_name = sanitize_input($_POST['customer_name'] ?? '');
    $customer_email = sanitize_input($_POST['customer_email'] ?? '');
    $country_code = sanitize_input($_POST['country_code'] ?? '+54');
    $customer_phone = sanitize_input($_POST['customer_phone'] ?? '');
    $contact_preference = sanitize_input($_POST['contact_preference'] ?? 'email');
    $delivery_method = sanitize_input($_POST['delivery_method'] ?? 'pickup');
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');

    // Combine country code and phone
    $full_phone = $country_code . ' ' . $customer_phone;

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

    if (!in_array($contact_preference, ['email', 'telegram'])) {
        $errors[] = 'Preferencia de contacto inválida';
    }

    if (!in_array($delivery_method, ['pickup', 'shipping'])) {
        $errors[] = 'Método de entrega inválido';
    }

    if (!in_array($payment_method, ['arrangement', 'pickup_payment', 'mercadopago'])) {
        $errors[] = 'Método de pago inválido';
    }

    // Shipping address (only for delivery)
    $shipping_address = null;
    $needs_shipping = ($delivery_method === 'shipping');

    if ($needs_shipping) {
        $address = sanitize_input($_POST['address'] ?? '');
        $address_line2 = sanitize_input($_POST['address_line2'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $postal_code = sanitize_input($_POST['postal_code'] ?? '');
        $state = sanitize_input($_POST['state'] ?? '');
        $country = sanitize_input($_POST['country'] ?? '');

        if (empty($address)) {
            $errors[] = 'La dirección es requerida para envío';
        }

        if (empty($city)) {
            $errors[] = 'La ciudad es requerida para envío';
        }

        if (empty($postal_code)) {
            $errors[] = 'El código postal es requerido para envío';
        }

        if (empty($state)) {
            $errors[] = 'La provincia/estado es requerida para envío';
        }

        if (empty($country)) {
            $errors[] = 'El país es requerido para envío';
        }

        if (empty($errors)) {
            $shipping_address = [
                'name' => $customer_name,
                'address' => $address,
                'address_line2' => $address_line2,
                'city' => $city,
                'postal_code' => $postal_code,
                'state' => $state,
                'country' => $country,
                'phone' => $full_phone
            ];
        }
    }

    // If there are errors and this is AJAX, return JSON
    if (!empty($errors) && $is_ajax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => implode(', ', $errors)
        ]);
        exit;
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
            'customer_phone' => $full_phone,
            'contact_preference' => $contact_preference,
            'delivery_method' => $delivery_method,
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

            // Send order confirmation email to customer (always send)
            send_order_confirmation_email($order);

            // For non-mercadopago payments: send all notifications immediately
            // For Mercadopago: notifications will be sent when payment is processed
            if ($payment_method !== 'mercadopago') {
                send_admin_new_order_email($order);
                send_telegram_new_order($order);
            }

            // Clear cart and coupon only for non-Mercadopago payments
            // For Mercadopago: cart will be cleared when payment is confirmed (webhook)
            if ($payment_method !== 'mercadopago') {
                unset($_SESSION['cart']);
                unset($_SESSION['coupon_code']);
            }

            // Redirect based on payment method
            if ($payment_method === 'mercadopago') {
                // If AJAX request, return JSON for modal
                if ($is_ajax) {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'order_id' => $order['id'],
                        'order_number' => $order['order_number'],
                        'tracking_token' => $order['tracking_token'],
                        'total' => $order['total'],
                        'currency' => $order['currency'],
                        'items' => $order['items']
                    ]);
                    exit;
                }

                // Normal redirect to payment page
                header("Location: " . url("/pagar-mercadopago.php?order={$order['id']}&token={$order['tracking_token']}"));
                exit;
            } else {
                // For arrangement and pickup_payment: redirect to thank you page
                header("Location: " . url("/gracias.php?order={$order['id']}&token={$order['tracking_token']}"));
                exit;
            }
        } else {
            $errors[] = $result['error'] ?? 'Error al procesar la orden';

            // If AJAX, return error as JSON
            if ($is_ajax) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $result['error'] ?? 'Error al procesar la orden'
                ]);
                exit;
            }
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

    <!-- Theme System CSS -->
    <?php render_theme_css($active_theme); ?>

    <!-- Mobile Menu Styles -->
    <link rel="stylesheet" href="<?php echo url('/includes/mobile-menu.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/admin/includes/modal.php'; ?>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="<?php echo url('/'); ?>" class="logo"><?php render_site_logo($site_config); ?></a>
            <div>
                <a href="<?php echo url('/carrito.php'); ?>" class="btn-secondary">← Volver al carrito</a>
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
                <!-- Step Indicators -->
                <div class="step-indicators">
                    <div class="step-indicator active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-title">Contacto</div>
                    </div>
                    <div class="step-indicator" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-title">Envío</div>
                    </div>
                    <div class="step-indicator" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-title">Pago</div>
                    </div>
                    <div class="step-indicator" data-step="4">
                        <div class="step-number">4</div>
                        <div class="step-title">Confirmar</div>
                    </div>
                </div>

                <form method="POST" action="" id="checkout-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- STEP 1: Contact Information -->
                    <div class="form-step active" data-step="1">
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
                                <label for="customer_phone">Teléfono / WhatsApp *</label>
                                <div class="phone-input-group">
                                    <select id="country_code" name="country_code" class="country-select" required>
                                        <option value="+54" data-flag="🇦🇷" selected>🇦🇷 +54</option>
                                        <option value="+1" data-flag="🇺🇸">🇺🇸 +1</option>
                                        <option value="+52" data-flag="🇲🇽">🇲🇽 +52</option>
                                        <option value="+34" data-flag="🇪🇸">🇪🇸 +34</option>
                                        <option value="+55" data-flag="🇧🇷">🇧🇷 +55</option>
                                        <option value="+56" data-flag="🇨🇱">🇨🇱 +56</option>
                                        <option value="+57" data-flag="🇨🇴">🇨🇴 +57</option>
                                        <option value="+51" data-flag="🇵🇪">🇵🇪 +51</option>
                                        <option value="+598" data-flag="🇺🇾">🇺🇾 +598</option>
                                        <option value="+595" data-flag="🇵🇾">🇵🇾 +595</option>
                                        <option value="+591" data-flag="🇧🇴">🇧🇴 +591</option>
                                        <option value="+593" data-flag="🇪🇨">🇪🇨 +593</option>
                                        <option value="+58" data-flag="🇻🇪">🇻🇪 +58</option>
                                        <option value="+44" data-flag="🇬🇧">🇬🇧 +44</option>
                                        <option value="+33" data-flag="🇫🇷">🇫🇷 +33</option>
                                        <option value="+49" data-flag="🇩🇪">🇩🇪 +49</option>
                                        <option value="+39" data-flag="🇮🇹">🇮🇹 +39</option>
                                    </select>
                                    <input type="tel" id="customer_phone" name="customer_phone"
                                           value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>"
                                           placeholder="11 1234-5678" required>
                                </div>
                                <small style="color: #666;">Ingresa tu número sin el código de país</small>
                            </div>

                            <div class="form-group">
                                <label>Preferencia de contacto *</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="contact_preference" value="email"
                                               <?php echo (!isset($_POST['contact_preference']) || $_POST['contact_preference'] === 'email') ? 'checked' : ''; ?> required>
                                        <span>✉️ Prefiero Email</span>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="contact_preference" value="telegram"
                                               <?php echo (isset($_POST['contact_preference']) && $_POST['contact_preference'] === 'telegram') ? 'checked' : ''; ?>>
                                        <span>📱 Prefiero Telegram</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="btn btn-primary" onclick="validateAndProceedStep1()">
                                Siguiente →
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Delivery/Pickup -->
                    <div class="form-step" data-step="2">
                        <div class="form-section">
                            <h2>🚚 Envío o Retiro</h2>

                            <div class="form-group">
                                <label>Método de entrega *</label>
                                <div class="radio-group">
                                    <label class="radio-option">
                                        <input type="radio" name="delivery_method" value="pickup"
                                               onchange="toggleShippingFields()"
                                               <?php echo (!isset($_POST['delivery_method']) || $_POST['delivery_method'] === 'pickup') ? 'checked' : ''; ?> required>
                                        <div>
                                            <strong>🏪 Retiro en persona</strong>
                                            <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                                Coordinaremos lugar y horario
                                            </p>
                                        </div>
                                    </label>
                                    <label class="radio-option">
                                        <input type="radio" name="delivery_method" value="shipping"
                                               onchange="toggleShippingFields()"
                                               <?php echo (isset($_POST['delivery_method']) && $_POST['delivery_method'] === 'shipping') ? 'checked' : ''; ?>>
                                        <div>
                                            <strong>📦 Envío a domicilio</strong>
                                            <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                                Completa tu dirección de envío
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div id="shipping-fields" style="display: none;">
                                <div class="form-group">
                                    <label for="address">Dirección *</label>
                                    <input type="text" id="address" name="address"
                                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                                           placeholder="Calle y número">
                                </div>

                                <div class="form-group">
                                    <label for="address_line2">Depto / Piso / Barrio (opcional)</label>
                                    <input type="text" id="address_line2" name="address_line2"
                                           value="<?php echo htmlspecialchars($_POST['address_line2'] ?? ''); ?>"
                                           placeholder="Ej: Depto 4B, Piso 3, Barrio Norte">
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="postal_code">Código Postal *</label>
                                        <input type="text" id="postal_code" name="postal_code"
                                               value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="city">Ciudad *</label>
                                        <input type="text" id="city" name="city"
                                               value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="state">Provincia / Estado *</label>
                                    <input type="text" id="state" name="state"
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="country">País *</label>
                                    <select id="country" name="country" required>
                                        <option value="Argentina" selected>Argentina</option>
                                        <option value="Chile">Chile</option>
                                        <option value="Uruguay">Uruguay</option>
                                        <option value="Paraguay">Paraguay</option>
                                        <option value="Brasil">Brasil</option>
                                        <option value="Bolivia">Bolivia</option>
                                        <option value="Perú">Perú</option>
                                        <option value="Colombia">Colombia</option>
                                        <option value="Ecuador">Ecuador</option>
                                        <option value="Venezuela">Venezuela</option>
                                        <option value="México">México</option>
                                        <option value="España">España</option>
                                        <option value="Estados Unidos">Estados Unidos</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                ← Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Siguiente →
                            </button>
                        </div>
                    </div>

                    <!-- STEP 3: Payment Method -->
                    <div class="form-step" data-step="3">
                        <div class="form-section">
                            <h2>💳 Método de Pago</h2>

                            <div class="form-group">
                                <div class="payment-methods">
                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="arrangement" required>
                                        <div>
                                            <strong>🤝 Arreglo con <?php echo htmlspecialchars($site_config['site_owner']); ?></strong>
                                            <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                                Coordinaremos el pago directamente
                                            </p>
                                        </div>
                                    </label>

                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="pickup_payment" required>
                                        <div>
                                            <strong>💵 Pago al retirar</strong>
                                            <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                                Pago en efectivo o transferencia al momento del retiro
                                            </p>
                                        </div>
                                    </label>

                                    <label class="payment-method">
                                        <input type="radio" name="payment_method" value="mercadopago" required>
                                        <div>
                                            <strong>💳 Mercadopago</strong>
                                            <p style="font-size: 14px; color: #666; margin-top: 5px;">
                                                Tarjeta de crédito/débito - Pago inmediato
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="form-group">
                                <label for="notes">Comentarios (opcional)</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Ej: horario preferido, referencias de ubicación, forma de pago preferida..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                ← Anterior
                            </button>
                            <button type="button" class="btn btn-primary" onclick="nextStep()">
                                Siguiente →
                            </button>
                        </div>
                    </div>

                    <!-- STEP 4: Confirmation -->
                    <div class="form-step" data-step="4">
                        <div class="form-section">
                            <h2>✅ Confirmar Pedido</h2>

                            <div class="confirmation-summary">
                                <h3>Resumen de tu información:</h3>

                                <div class="summary-section">
                                    <h4>📋 Contacto</h4>
                                    <p><strong>Nombre:</strong> <span id="confirm-name"></span></p>
                                    <p><strong>Email:</strong> <span id="confirm-email"></span></p>
                                    <p><strong>Teléfono:</strong> <span id="confirm-phone"></span></p>
                                    <p><strong>Preferencia:</strong> <span id="confirm-contact-pref"></span></p>
                                </div>

                                <div class="summary-section">
                                    <h4>🚚 Entrega</h4>
                                    <p><strong>Método:</strong> <span id="confirm-delivery"></span></p>
                                    <div id="confirm-shipping-address" style="display: none;">
                                        <p><strong>Dirección:</strong> <span id="confirm-address"></span></p>
                                        <p><strong>Ciudad:</strong> <span id="confirm-city"></span></p>
                                        <p><strong>CP:</strong> <span id="confirm-postal"></span></p>
                                    </div>
                                </div>

                                <div class="summary-section">
                                    <h4>💳 Pago</h4>
                                    <p><strong>Método:</strong> <span id="confirm-payment"></span></p>
                                    <div id="confirm-notes-display" style="display: none;">
                                        <p><strong>Comentarios:</strong> <span id="confirm-notes"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step-navigation">
                            <button type="button" class="btn btn-secondary" onclick="prevStep()">
                                ← Anterior
                            </button>
                            <button type="submit" name="place_order" class="btn btn-primary">
                                🛒 Confirmar Pedido
                            </button>
                        </div>
                    </div>
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

    <!-- Telegram Validation Modal -->
    <div id="telegram-validation-modal" class="modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <h3>📱 Validar Telegram</h3>
            <p>Vamos a enviar un mensaje de prueba a tu Telegram:</p>
            <p class="phone-display"><strong id="telegram-phone-display"></strong></p>
            <p style="color: #ff9800; font-weight: bold;">⚠️ Por favor revisa si te llegó el mensaje, ya que es la única forma que tendremos de avisarte sobre tu compra.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="skipTelegramValidation()">Omitir verificación</button>
                <button type="button" class="btn btn-primary" onclick="sendTelegramTest()">Enviar mensaje de prueba</button>
            </div>
            <div id="telegram-status" style="margin-top: 15px; display: none;"></div>
        </div>
    </div>

    <!-- Email Validation Modal -->
    <div id="email-validation-modal" class="modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <h3>✉️ Validar Email</h3>
            <p>Vamos a enviar un correo de prueba a:</p>
            <p class="email-display"><strong id="email-display"></strong></p>
            <p style="color: #ff9800; font-weight: bold;">⚠️ Por favor revisa tu bandeja de entrada y spam, ya que es la única forma que tendremos de avisarte sobre tu compra.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="skipEmailValidation()">Omitir verificación</button>
                <button type="button" class="btn btn-primary" onclick="sendEmailTest()">Enviar email de prueba</button>
            </div>
            <div id="email-status" style="margin-top: 15px; display: none;"></div>
        </div>
    </div>

    <!-- Payment Warning Modal -->
    <div id="payment-warning-modal" class="modal" style="display: none;">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <h3>⚠️ Importante</h3>
            <p>Tu orden fue recibida pero el producto sigue disponible en el shop. <strong>Alguien más puede comprarlo y pagarlo antes que vos.</strong></p>
            <p>La única forma de garantizar la disponibilidad es pagando y avisándole a <strong><?php echo htmlspecialchars($site_config['site_owner']); ?></strong>.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary" onclick="closePaymentWarningModal()">Entendido, finalizar compra</button>
                <button type="button" class="btn btn-secondary" onclick="backToPaymentMethod()">Prefiero volver y cambiar el método de pago</button>
            </div>
        </div>
    </div>

    <!-- Mercadopago Payment Modal -->
    <div id="mercadopago-modal" class="modal" style="display: none;">
        <div class="modal-overlay" onclick="closeMercadopagoModal()"></div>
        <div class="modal-content mercadopago-modal-content">
            <button class="modal-close-btn" onclick="closeMercadopagoModal()">✕</button>
            <div class="header">
                <h2>💳 Pagar con Mercadopago</h2>
                <p style="color: #666; font-size: 14px; margin-top: 5px;">Orden <span id="mp-order-number"></span></p>
            </div>

            <div class="order-summary-modal">
                <h3 style="margin-bottom: 12px; color: #333;">Resumen de la orden</h3>
                <div id="mp-order-items"></div>
                <div class="order-row total" style="border-top: 2px solid #ddd; margin-top: 12px; padding-top: 12px; font-weight: bold;">
                    <span>Total a pagar</span>
                    <span id="mp-total"></span>
                </div>
            </div>

            <div class="payment-form-modal">
                <div class="loading" id="mp-loading">
                    <div class="spinner"></div>
                    <p>Preparando pago...</p>
                </div>

                <div id="walletBrick_container"></div>

                <div class="error" id="mp-error-message" style="display: none;"></div>
            </div>

            <div class="payment-info-modal">
                <p style="margin: 0 0 10px 0;"><strong>💳 Pago seguro con Mercadopago</strong></p>
                <p style="margin: 0; line-height: 1.6; font-size: 14px; color: #666;">
                    Al hacer clic en "Pagar", serás redirigido a Mercadopago para completar tu pago de forma segura.
                </p>
            </div>
        </div>
    </div>

    <script>
        // Mercadopago configuration
        <?php
        $mp_mode = $payment_config['mercadopago']['mode'] ?? 'sandbox';
        $mp_public_key = $mp_mode === 'sandbox' ?
            ($payment_credentials['mercadopago']['public_key_sandbox'] ?? '') :
            ($payment_credentials['mercadopago']['public_key_prod'] ?? '');
        ?>
        const MERCADOPAGO_PUBLIC_KEY = '<?php echo $mp_public_key; ?>';

        // Step management
        let currentStep = 1;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            updateStepDisplay();
            toggleShippingFields();

            // Add click handlers to step indicators for navigation
            document.querySelectorAll('.step-indicator').forEach(indicator => {
                indicator.style.cursor = 'pointer';
                indicator.addEventListener('click', function() {
                    const targetStep = parseInt(this.dataset.step);

                    // Only allow navigation to current step or previous steps
                    // Don't allow jumping ahead without validation
                    if (targetStep <= currentStep) {
                        currentStep = targetStep;
                        updateStepDisplay();
                        window.scrollTo(0, 0);
                    } else {
                        // Try to validate current steps before jumping ahead
                        let canProceed = true;
                        for (let i = currentStep; i < targetStep; i++) {
                            if (!validateStep(i)) {
                                canProceed = false;
                                break;
                            }
                        }

                        if (canProceed) {
                            // Update confirmation if jumping to step 4
                            if (targetStep === 4) {
                                updateConfirmationSummary();
                            }
                            currentStep = targetStep;
                            updateStepDisplay();
                            window.scrollTo(0, 0);
                        }
                    }
                });
            });
        });

        // Validate current step
        function validateStep(stepNumber) {
            const step = document.querySelector(`.form-step[data-step="${stepNumber}"]`);
            if (!step) return false;

            // Get all required inputs in this step
            const requiredInputs = step.querySelectorAll('input[required], textarea[required], select[required]');

            for (let input of requiredInputs) {
                // Skip validation if the input is hidden (like shipping fields when not needed)
                if (input.offsetParent === null) continue;

                if (input.type === 'radio') {
                    // For radio buttons, check if at least one in the group is checked
                    const radioGroup = step.querySelectorAll(`input[name="${input.name}"]`);
                    const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                    if (!isChecked) {
                        showModal({
                            title: 'Campos incompletos',
                            message: 'Por favor completa todos los campos requeridos',
                            icon: '⚠️',
                            iconClass: 'warning',
                            confirmText: 'Entendido',
                            onConfirm: () => {}
                        });
                        return false;
                    }
                } else if (input.type === 'checkbox') {
                    if (!input.checked && input.required) {
                        showModal({
                            title: 'Campos incompletos',
                            message: 'Por favor completa todos los campos requeridos',
                            icon: '⚠️',
                            iconClass: 'warning',
                            confirmText: 'Entendido',
                            onConfirm: () => {}
                        });
                        return false;
                    }
                } else {
                    if (!input.value.trim()) {
                        showModal({
                            title: 'Campos incompletos',
                            message: 'Por favor completa todos los campos requeridos',
                            icon: '⚠️',
                            iconClass: 'warning',
                            confirmText: 'Entendido',
                            onConfirm: () => { input.focus(); }
                        });
                        return false;
                    }

                    // Email validation
                    if (input.type === 'email' && !isValidEmail(input.value)) {
                        showModal({
                            title: 'Email inválido',
                            message: 'Por favor ingresa un email válido',
                            icon: '⚠️',
                            iconClass: 'warning',
                            confirmText: 'Entendido',
                            onConfirm: () => { input.focus(); }
                        });
                        return false;
                    }
                }
            }

            return true;
        }

        // Email validation helper
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Next step
        function nextStep() {
            if (!validateStep(currentStep)) {
                return;
            }

            // Update confirmation summary when moving to step 4
            if (currentStep === 3) {
                updateConfirmationSummary();
            }

            if (currentStep < 4) {
                currentStep++;
                updateStepDisplay();
                window.scrollTo(0, 0);
            }
        }

        // Previous step
        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateStepDisplay();
                window.scrollTo(0, 0);
            }
        }

        // Update step display
        function updateStepDisplay() {
            // Update step indicators
            document.querySelectorAll('.step-indicator').forEach(indicator => {
                const step = parseInt(indicator.dataset.step);
                indicator.classList.remove('active', 'completed');

                if (step === currentStep) {
                    indicator.classList.add('active');
                } else if (step < currentStep) {
                    indicator.classList.add('completed');
                }
            });

            // Update form steps
            document.querySelectorAll('.form-step').forEach(step => {
                const stepNum = parseInt(step.dataset.step);
                step.classList.remove('active');
                step.style.display = 'none';

                if (stepNum === currentStep) {
                    step.classList.add('active');
                    step.style.display = 'block';
                }
            });
        }

        // Toggle shipping fields based on delivery method
        function toggleShippingFields() {
            const shippingRadio = document.querySelector('input[name="delivery_method"][value="shipping"]');
            const shippingFields = document.getElementById('shipping-fields');
            const inputs = shippingFields.querySelectorAll('input');

            if (shippingRadio && shippingRadio.checked) {
                shippingFields.style.display = 'block';
                inputs.forEach(input => input.required = true);
            } else {
                shippingFields.style.display = 'none';
                inputs.forEach(input => input.required = false);
            }
        }

        // Update confirmation summary
        function updateConfirmationSummary() {
            // Contact info
            document.getElementById('confirm-name').textContent = document.getElementById('customer_name').value;
            document.getElementById('confirm-email').textContent = document.getElementById('customer_email').value;
            document.getElementById('confirm-phone').textContent = document.getElementById('customer_phone').value;

            const contactPref = document.querySelector('input[name="contact_preference"]:checked');
            document.getElementById('confirm-contact-pref').textContent =
                contactPref.value === 'email' ? '✉️ Email' : '📱 Telegram';

            // Delivery info
            const deliveryMethod = document.querySelector('input[name="delivery_method"]:checked');
            const deliveryText = deliveryMethod.value === 'pickup' ? '🏪 Retiro en persona' : '📦 Envío a domicilio';
            document.getElementById('confirm-delivery').textContent = deliveryText;

            // Show/hide shipping address
            const shippingAddressDiv = document.getElementById('confirm-shipping-address');
            if (deliveryMethod.value === 'shipping') {
                shippingAddressDiv.style.display = 'block';
                document.getElementById('confirm-address').textContent = document.getElementById('address').value;
                document.getElementById('confirm-city').textContent = document.getElementById('city').value;
                document.getElementById('confirm-postal').textContent = document.getElementById('postal_code').value;
            } else {
                shippingAddressDiv.style.display = 'none';
            }

            // Payment info
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            let paymentText = '';
            if (paymentMethod.value === 'arrangement') {
                paymentText = '🤝 Arreglo con <?php echo htmlspecialchars($site_config['site_owner']); ?>';
            } else if (paymentMethod.value === 'pickup_payment') {
                paymentText = '💵 Pago al retirar';
            } else if (paymentMethod.value === 'mercadopago') {
                paymentText = '💳 Mercadopago';
            }
            document.getElementById('confirm-payment').textContent = paymentText;

            // Notes
            const notes = document.getElementById('notes').value;
            const notesDisplay = document.getElementById('confirm-notes-display');
            if (notes.trim()) {
                notesDisplay.style.display = 'block';
                document.getElementById('confirm-notes').textContent = notes;
            } else {
                notesDisplay.style.display = 'none';
            }
        }

        // Validate and proceed to Step 2 from Step 1
        let contactValidated = false;

        function validateAndProceedStep1() {
            if (!validateStep(1)) {
                return;
            }

            // Check if user already validated or skipped in this session
            const telegramValidated = sessionStorage.getItem('telegramValidated');
            const emailValidated = sessionStorage.getItem('emailValidated');
            const contactPref = document.querySelector('input[name="contact_preference"]:checked').value;

            // If already validated or skipped for this preference, proceed
            if ((contactPref === 'telegram' && telegramValidated) ||
                (contactPref === 'email' && emailValidated)) {
                contactValidated = true;
                nextStep();
                return;
            }

            // If already validated in this page load, proceed
            if (contactValidated) {
                nextStep();
                return;
            }

            // Show validation modal
            if (contactPref === 'telegram') {
                showTelegramValidationModal();
            } else if (contactPref === 'email') {
                showEmailValidationModal();
            }
        }

        // Telegram Validation Modal functions
        function showTelegramValidationModal() {
            const countryCode = document.getElementById('country_code').value;
            const phone = document.getElementById('customer_phone').value;
            const fullPhone = countryCode + ' ' + phone;

            document.getElementById('telegram-phone-display').textContent = fullPhone;
            document.getElementById('telegram-validation-modal').style.display = 'flex';
        }

        function closeTelegramModal() {
            document.getElementById('telegram-validation-modal').style.display = 'none';
            document.getElementById('telegram-status').style.display = 'none';
        }

        function skipTelegramValidation() {
            sessionStorage.setItem('telegramValidated', 'skipped');
            contactValidated = true;
            closeTelegramModal();
            nextStep();
        }

        function sendTelegramTest() {
            const countryCode = document.getElementById('country_code').value;
            const phone = document.getElementById('customer_phone').value;
            const name = document.getElementById('customer_name').value;

            // Show loading status
            const statusDiv = document.getElementById('telegram-status');
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '<p style="color: #007bff;">Preparando mensaje de prueba...</p>';

            // Send test message via Telegram link (t.me)
            const message = encodeURIComponent(`Hola ${name}, este es un mensaje de prueba de ${<?php echo json_encode($site_config['site_name']); ?>}. Si recibiste este mensaje, tu Telegram está correctamente configurado para recibir notificaciones de tu pedido. ✅`);
            const telegramURL = `https://t.me/${countryCode.replace('+', '')}${phone}?text=${message}`;

            // Open Telegram in new window
            window.open(telegramURL, '_blank');

            statusDiv.innerHTML = `
                <p style="color: #28a745;">✅ Se abrió Telegram con el mensaje de prueba.</p>
                <p><strong>¿Recibiste el mensaje?</strong></p>
                <button type="button" class="btn btn-primary" onclick="confirmTelegramValidation()">Sí, lo recibí</button>
                <button type="button" class="btn btn-secondary" onclick="closeTelegramModal()">No, intentar de nuevo</button>
            `;
        }

        function confirmTelegramValidation() {
            sessionStorage.setItem('telegramValidated', 'confirmed');
            contactValidated = true;
            closeTelegramModal();
            nextStep();
        }

        // Email Validation Modal functions
        function showEmailValidationModal() {
            const email = document.getElementById('customer_email').value;
            document.getElementById('email-display').textContent = email;
            document.getElementById('email-validation-modal').style.display = 'flex';
        }

        function closeEmailModal() {
            document.getElementById('email-validation-modal').style.display = 'none';
            document.getElementById('email-status').style.display = 'none';
        }

        function skipEmailValidation() {
            sessionStorage.setItem('emailValidated', 'skipped');
            contactValidated = true;
            closeEmailModal();
            nextStep();
        }

        function sendEmailTest() {
            const email = document.getElementById('customer_email').value;
            const name = document.getElementById('customer_name').value;

            // Show loading status
            const statusDiv = document.getElementById('email-status');
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '<p style="color: #007bff;">Enviando email...</p>';

            // Send test email via AJAX
            fetch('<?php echo url('/api/send-test-email.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    name: name
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = `
                        <p style="color: #28a745;">✅ Email enviado correctamente.</p>
                        <p><strong>¿Recibiste el email?</strong> (Revisa también la carpeta de spam)</p>
                        <button type="button" class="btn btn-primary" onclick="confirmEmailValidation()">Sí, lo recibí</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">No, intentar de nuevo</button>
                    `;
                } else {
                    statusDiv.innerHTML = '<p style="color: #dc3545;">❌ Error al enviar el email. Por favor verifica la dirección.</p>';
                }
            })
            .catch(error => {
                statusDiv.innerHTML = '<p style="color: #dc3545;">❌ Error al enviar el email. Intenta de nuevo.</p>';
            });
        }

        function confirmEmailValidation() {
            sessionStorage.setItem('emailValidated', 'confirmed');
            contactValidated = true;
            closeEmailModal();
            nextStep();
        }

        // Form submission handler
        let formSubmitAllowed = false;
        let mercadopagoOrder = null;

        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

            // If Mercadopago, create order via AJAX and show modal
            if (paymentMethod && paymentMethod.value === 'mercadopago' && !formSubmitAllowed) {
                e.preventDefault();
                createOrderAndShowMercadopagoModal();
                return false;
            }

            // Show warning modal for non-mercadopago payments
            if (paymentMethod && paymentMethod.value !== 'mercadopago' && !formSubmitAllowed) {
                e.preventDefault();
                showPaymentWarningModal();
                return false;
            }
        });

        // Create order via AJAX and show Mercadopago modal
        function createOrderAndShowMercadopagoModal() {
            // Get form data
            const formData = new FormData(document.getElementById('checkout-form'));

            // IMPORTANT: Add the place_order parameter manually
            // When using FormData constructor, button values are not included
            formData.append('place_order', '1');

            // Show loading
            const submitBtn = document.querySelector('button[name="place_order"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Creando orden...';

            // Submit form via AJAX with XMLHttpRequest header
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('Content-Type'));

                // Clone response to read it twice
                return response.clone().text().then(text => {
                    console.log('Raw response:', text);

                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor (HTTP ' + response.status + ')');
                    }

                    // Try to parse as JSON
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('La respuesta no es un JSON válido. Respuesta: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.success) {
                    // Store order data
                    mercadopagoOrder = data;

                    // Show Mercadopago modal
                    showMercadopagoModal(data);

                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                } else {
                    showModal({
                        title: 'Error al crear la orden',
                        message: data.error || 'Error desconocido',
                        icon: '❌',
                        iconClass: 'danger',
                        confirmText: 'Entendido',
                        onConfirm: () => {}
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error completo:', error);
                showModal({
                    title: 'Error al procesar la orden',
                    message: 'Por favor intenta nuevamente.',
                    details: '<strong>Detalle técnico:</strong><br>' + error.message,
                    icon: '❌',
                    iconClass: 'danger',
                    confirmText: 'Entendido',
                    onConfirm: () => {}
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        // Show Mercadopago modal with Wallet Brick
        function showMercadopagoModal(orderData) {
            // Populate modal with order data
            document.getElementById('mp-order-number').textContent = '#' + orderData.order_number;

            // Format items
            let itemsHTML = '';
            orderData.items.forEach(item => {
                itemsHTML += `
                    <div class="order-row" style="display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; color: #666;">
                        <span>${item.name} x${item.quantity}</span>
                        <span>$${parseFloat(item.final_price).toFixed(2)}</span>
                    </div>
                `;
            });
            document.getElementById('mp-order-items').innerHTML = itemsHTML;

            // Format total
            const currency = orderData.currency === 'USD' ? 'U$D' : '$';
            document.getElementById('mp-total').textContent = currency + ' ' + parseFloat(orderData.total).toFixed(2);

            // Show modal
            document.getElementById('mercadopago-modal').style.display = 'flex';

            // Load Mercadopago Wallet Brick
            loadMercadopagoWalletBrick(orderData.order_id, orderData.tracking_token);
        }

        // Load Mercadopago Wallet Brick
        function loadMercadopagoWalletBrick(orderId, trackingToken) {
            const loading = document.getElementById('mp-loading');
            const errorMessage = document.getElementById('mp-error-message');

            loading.style.display = 'block';
            errorMessage.style.display = 'none';

            // Create preference
            fetch('<?php echo url('/crear-preferencia-mp.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId,
                    tracking_token: trackingToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.preference_id) {
                    throw new Error(data.error || 'Error al crear preferencia de pago');
                }

                // Initialize Mercadopago SDK
                const mp = new MercadoPago(MERCADOPAGO_PUBLIC_KEY, {
                    locale: 'es-AR'
                });

                const bricksBuilder = mp.bricks();

                // Clear container first
                document.getElementById('walletBrick_container').innerHTML = '';

                // Render Wallet Brick
                bricksBuilder.create('wallet', 'walletBrick_container', {
                    initialization: {
                        preferenceId: data.preference_id,
                    },
                    customization: {
                        texts: {
                            valueProp: 'security_safety',
                        },
                    },
                    callbacks: {
                        onReady: () => {
                            loading.style.display = 'none';
                        },
                        onError: (error) => {
                            console.error('Error en Wallet Brick:', error);
                            errorMessage.textContent = 'Error al cargar el pago. Intenta nuevamente.';
                            errorMessage.style.display = 'block';
                            loading.style.display = 'none';
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error creating preference:', error);
                errorMessage.textContent = 'Error al preparar el pago. Intenta nuevamente.';
                errorMessage.style.display = 'block';
                loading.style.display = 'none';
            });
        }

        // Payment Warning Modal functions
        function showPaymentWarningModal() {
            document.getElementById('payment-warning-modal').style.display = 'flex';
        }

        function closePaymentWarningModal() {
            document.getElementById('payment-warning-modal').style.display = 'none';
            // Allow form submission after modal is closed
            formSubmitAllowed = true;
            document.getElementById('checkout-form').submit();
        }

        function backToPaymentMethod() {
            document.getElementById('payment-warning-modal').style.display = 'none';
            // Go back to step 3 (payment method)
            currentStep = 3;
            updateStepDisplay();
            window.scrollTo(0, 0);
        }

        // Mercadopago Modal functions
        function closeMercadopagoModal() {
            document.getElementById('mercadopago-modal').style.display = 'none';
            // Optionally reload or redirect
            window.location.href = '<?php echo url('/'); ?>';
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

    <style>
        /* Step indicators */
        .step-indicators {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 10px;
        }

        .step-indicator {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 10px;
            opacity: 0.5;
        }

        .step-indicator.active,
        .step-indicator.completed {
            opacity: 1;
        }

        .step-indicator::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }

        .step-indicator:last-child::after {
            display: none;
        }

        .step-indicator.completed::after {
            background: #28a745;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            font-weight: bold;
        }

        .step-indicator.active .step-number {
            background: #007bff;
            color: white;
        }

        .step-indicator.completed .step-number {
            background: #28a745;
            color: white;
        }

        .step-title {
            font-size: 12px;
            color: #666;
        }

        /* Form steps */
        .form-step {
            display: none;
        }

        .form-step.active {
            display: block;
        }

        /* Step navigation */
        .step-navigation {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 20px;
        }

        .step-navigation button {
            flex: 1;
        }

        /* Radio group styling */
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .radio-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .radio-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .radio-option input[type="radio"] {
            margin-top: 3px;
        }

        .radio-option input[type="radio"]:checked + span,
        .radio-option:has(input[type="radio"]:checked) {
            font-weight: bold;
        }

        .radio-option:has(input[type="radio"]:checked) {
            border-color: #007bff;
            background: #e7f3ff;
        }

        /* Confirmation summary */
        .confirmation-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }

        .summary-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .summary-section h4 {
            margin-bottom: 10px;
            color: #007bff;
        }

        .summary-section p {
            margin: 5px 0;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
        }

        .modal-content {
            position: relative;
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            margin: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            z-index: 10001;
        }

        .modal-content h3 {
            margin-top: 0;
            color: #ff9800;
        }

        .modal-content p {
            line-height: 1.6;
        }

        .modal-actions {
            margin-top: 20px;
            text-align: center;
        }

        /* Phone input group */
        .phone-input-group {
            display: flex;
            gap: 10px;
        }

        .country-select {
            flex: 0 0 auto;
            width: 130px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .phone-input-group input[type="tel"] {
            flex: 1;
        }

        /* Form row for side-by-side fields */
        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Modal styling improvements */
        .phone-display, .email-display {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            margin: 10px 0;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .step-indicators {
                gap: 5px;
            }

            .step-title {
                font-size: 10px;
            }

            .step-number {
                width: 30px;
                height: 30px;
                font-size: 14px;
            }

            .step-navigation {
                flex-direction: column;
            }

            .radio-option {
                padding: 10px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .phone-input-group {
                flex-direction: column;
                gap: 10px;
            }

            .country-select {
                width: 100%;
            }
        }
        /* Mercadopago Modal specific styles */
        .mercadopago-modal-content {
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .modal-close-btn:hover {
            background: #f0f0f0;
            color: #333;
        }

        .order-summary-modal {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: #666;
        }

        .payment-form-modal {
            margin: 20px 0;
        }

        .payment-info-modal {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- Mercadopago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>

    <!-- Mobile Menu -->
    <script src="<?php echo url('/includes/mobile-menu.js'); ?>"></script>

    <!-- Footer -->
    <footer class="footer">
        <?php render_footer($site_config, $footer_config); ?>
    </footer>
</body>
</html>
