<?php
/**
 * Mercadopago Payment Page - Checkout Bricks
 * Formulario de pago embebido en el sitio
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/orders.php';

session_start();

// Check if order_id is provided
if (!isset($_GET['order']) || !isset($_GET['token'])) {
    header('Location: /');
    exit;
}

$order_id = sanitize_input($_GET['order']);
$token = sanitize_input($_GET['token']);

// Load order
$orders_file = __DIR__ . '/data/orders.json';
$orders_data = read_json($orders_file);

$order = null;
foreach ($orders_data['orders'] as $o) {
    if ($o['id'] === $order_id && $o['tracking_token'] === $token) {
        $order = $o;
        break;
    }
}

if (!$order) {
    die('Orden no encontrada');
}

// Get payment config
$payment_config = read_json(__DIR__ . '/config/payment.json');
$site_config = read_json(__DIR__ . '/config/site.json');

$sandbox_mode = $payment_config['mercadopago']['sandbox_mode'] ?? true;
$public_key = $sandbox_mode ?
    $payment_config['mercadopago']['public_key_sandbox'] :
    $payment_config['mercadopago']['public_key_prod'];

if (empty($public_key)) {
    die('Mercadopago no configurado. Contacte al administrador.');
}

// Calculate total in ARS (MP only accepts ARS)
$currency_config = read_json(__DIR__ . '/config/currency.json');
$exchange_rate = $currency_config['exchange_rate'] ?? 1500;

$total_ars = $order['total'];
if ($order['currency'] === 'USD') {
    $total_ars = $order['total'] * $exchange_rate;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar con Mercadopago - <?php echo htmlspecialchars($site_config['site_name']); ?></title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .payment-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }

        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            color: #666;
        }

        .order-row.total {
            border-top: 2px solid #ddd;
            margin-top: 12px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .payment-form {
            margin-top: 24px;
        }

        #cardPaymentBrick_container {
            margin-top: 20px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
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

        .error {
            background: #fee;
            color: #c33;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            display: none;
        }

        .success {
            background: #efe;
            color: #3c3;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            display: none;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-card">
            <div class="header">
                <h1>💳 Pagar con Mercadopago</h1>
                <p style="color: #666; font-size: 14px;">Orden #<?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>

            <div class="order-summary">
                <h3 style="margin-bottom: 12px; color: #333;">Resumen de la orden</h3>
                <?php foreach ($order['items'] as $item): ?>
                <div class="order-row">
                    <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo $item['quantity']; ?></span>
                    <span>$<?php echo number_format($item['final_price'], 2); ?></span>
                </div>
                <?php endforeach; ?>

                <?php if ($order['discount_coupon'] > 0): ?>
                <div class="order-row" style="color: #28a745;">
                    <span>Descuento</span>
                    <span>-$<?php echo number_format($order['discount_coupon'], 2); ?></span>
                </div>
                <?php endif; ?>

                <div class="order-row total">
                    <span>Total a pagar</span>
                    <span>$<?php echo number_format($total_ars, 2); ?> ARS</span>
                </div>
            </div>

            <div class="payment-form">
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Cargando formulario de pago...</p>
                </div>

                <div id="cardPaymentBrick_container"></div>

                <div class="error" id="error-message"></div>
                <div class="success" id="success-message"></div>
            </div>
        </div>

        <div class="back-link">
            <a href="/">← Volver al inicio</a>
        </div>
    </div>

    <!-- Mercadopago SDK -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>

    <script>
        const mp = new MercadoPago('<?php echo $public_key; ?>', {
            locale: 'es-AR'
        });

        const bricksBuilder = mp.bricks();

        // Render Card Payment Brick
        bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', {
            initialization: {
                amount: <?php echo $total_ars; ?>,
            },
            customization: {
                visual: {
                    style: {
                        theme: 'default'
                    }
                },
                paymentMethods: {
                    maxInstallments: 1
                }
            },
            callbacks: {
                onReady: () => {
                    document.getElementById('loading').style.display = 'none';
                },
                onSubmit: (cardFormData) => {
                    return new Promise((resolve, reject) => {
                        fetch('/procesar-pago-mp.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                ...cardFormData,
                                order_id: '<?php echo $order_id; ?>',
                                token: '<?php echo $token; ?>'
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('success-message').textContent = '¡Pago procesado! Redirigiendo...';
                                document.getElementById('success-message').style.display = 'block';

                                setTimeout(() => {
                                    window.location.href = data.redirect_url;
                                }, 2000);

                                resolve();
                            } else {
                                document.getElementById('error-message').textContent = data.error || 'Error al procesar el pago';
                                document.getElementById('error-message').style.display = 'block';
                                reject();
                            }
                        })
                        .catch(error => {
                            document.getElementById('error-message').textContent = 'Error de conexión. Intente nuevamente.';
                            document.getElementById('error-message').style.display = 'block';
                            reject();
                        });
                    });
                },
                onError: (error) => {
                    console.error('Error en Brick:', error);
                    document.getElementById('error-message').textContent = 'Error al cargar el formulario de pago';
                    document.getElementById('error-message').style.display = 'block';
                }
            }
        });
    </script>
</body>
</html>
