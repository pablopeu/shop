<?php
/**
 * Order Paid Email Template
 * Sent to customer when order status changes to paid
 */

$site_config = read_json(__DIR__ . '/../../config/site.json');
$site_name = $site_config['site_name'] ?? 'Mi Tienda';
$site_url = $site_config['site_url'] ?? 'https://tienda.com';
$currency = ($order['currency'] ?? 'ARS') === 'USD' ? 'U$D' : '$';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Pago Confirmado!</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">✅</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                ¡Pago Confirmado!
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 16px; opacity: 0.95;">
                                Tu pago ha sido recibido exitosamente
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Hola <strong><?= htmlspecialchars($order['customer_name'] ?? 'Cliente') ?></strong>,
                            </p>

                            <p style="margin: 0 0 30px 0; color: #555555; font-size: 15px; line-height: 1.6;">
                                ¡Excelentes noticias! Hemos confirmado el pago de tu pedido. Estamos preparando todo para que recibas tus productos pronto.
                            </p>

                            <!-- Success Box -->
                            <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #065f46; font-size: 15px; font-weight: 600;">
                                    💰 Pago confirmado
                                </p>
                                <p style="margin: 10px 0 0 0; color: #065f46; font-size: 14px;">
                                    <?php if (isset($order['delivery_method']) && $order['delivery_method'] === 'shipping'): ?>
                                        Tu pedido está en proceso de envío. Te notificaremos cuando sea despachado.
                                    <?php else: ?>
                                        Tu pedido está siendo preparado para que puedas retirarlo. Te avisaremos cuando esté listo.
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- Order Info Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 30px;">
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Número de Pedido:</td>
                                                <td style="color: #333333; font-size: 14px; text-align: right;">
                                                    <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Monto Pagado:</td>
                                                <td style="color: #10b981; font-size: 16px; text-align: right; font-weight: 600;">
                                                    <?= $currency ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Método de Pago:</td>
                                                <td style="color: #333333; font-size: 14px; text-align: right;">
                                                    <?php
                                                    $payment_method = 'N/A';
                                                    if (isset($order['payment_method'])) {
                                                        switch ($order['payment_method']) {
                                                            case 'mercadopago':
                                                                $payment_method = '💳 Mercadopago';
                                                                break;
                                                            case 'arrangement':
                                                                $payment_method = '🤝 Arreglo';
                                                                break;
                                                            case 'pickup_payment':
                                                                $payment_method = '💵 Pago al retirar';
                                                                break;
                                                            default:
                                                                $payment_method = ucfirst($order['payment_method']);
                                                        }
                                                    }
                                                    echo htmlspecialchars($payment_method);
                                                    ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Estado:</td>
                                                <td style="color: #10b981; font-size: 14px; text-align: right; font-weight: 600;">
                                                    Cobrado ✓
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Order Items -->
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 20px; font-weight: 600;">
                                📦 Productos
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 30px;">
                                <?php foreach ($order['items'] as $index => $item): ?>
                                <tr style="<?= $index > 0 ? 'border-top: 1px solid #e5e7eb;' : '' ?>">
                                    <td style="padding: 15px; color: #333333; font-size: 14px;">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                        <br>
                                        <span style="color: #666666; font-size: 13px;">
                                            Cantidad: <?= $item['quantity'] ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; color: #333333; font-size: 14px; text-align: right; white-space: nowrap;">
                                        <?php
                                        $item_currency = (isset($item['price_usd']) && $item['price_usd'] > 0 && (!isset($item['price_ars']) || $item['price_ars'] == 0)) ? 'U$D' : '$';
                                        $item_price = (isset($item['price_usd']) && $item['price_usd'] > 0 && (!isset($item['price_ars']) || $item['price_ars'] == 0))
                                            ? $item['price_usd']
                                            : ($item['price_ars'] ?? $item['price'] ?? 0);
                                        ?>
                                        <?= $item_currency ?> <?= number_format($item_price * $item['quantity'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- Total -->
                                <tr style="border-top: 2px solid #333333; background-color: #f8f9fa;">
                                    <td style="padding: 15px; color: #333333; font-size: 16px; font-weight: 600;">
                                        Total
                                    </td>
                                    <td style="padding: 15px; color: #10b981; font-size: 18px; font-weight: 700; text-align: right;">
                                        <?= $currency ?> <?= number_format($order['total'], 2) ?>
                                    </td>
                                </tr>
                            </table>

                            <!-- Shipping Info -->
                            <?php if (isset($order['delivery_method']) && $order['delivery_method'] === 'shipping' && isset($order['shipping_address'])): ?>
                            <h3 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; font-weight: 600;">
                                📍 Dirección de Envío
                            </h3>
                            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 30px;">
                                <p style="margin: 0; color: #333333; font-size: 14px; line-height: 1.6;">
                                    <?= htmlspecialchars($order['shipping_address']['street'] ?? '') ?><br>
                                    <?= htmlspecialchars($order['shipping_address']['city'] ?? '') ?>,
                                    <?= htmlspecialchars($order['shipping_address']['state'] ?? '') ?>
                                    <?= htmlspecialchars($order['shipping_address']['zip'] ?? '') ?>
                                </p>
                            </div>
                            <?php elseif (isset($order['delivery_method']) && $order['delivery_method'] === 'pickup'): ?>
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #92400e; font-size: 14px; font-weight: 600;">
                                    🏪 Retiro en Persona
                                </p>
                                <p style="margin: 10px 0 0 0; color: #92400e; font-size: 13px;">
                                    Te notificaremos cuando tu pedido esté listo para retirar.
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- Next Steps -->
                            <div style="background-color: #e0e7ff; border-left: 4px solid #667eea; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
                                <h3 style="margin: 0 0 10px 0; color: #3730a3; font-size: 16px; font-weight: 600;">
                                    🎯 Próximos Pasos
                                </h3>
                                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #3730a3; font-size: 14px; line-height: 1.8;">
                                    <?php if (isset($order['delivery_method']) && $order['delivery_method'] === 'shipping'): ?>
                                        <li>Estamos preparando tu pedido para el envío</li>
                                        <li>Te notificaremos cuando sea despachado</li>
                                        <li>Recibirás un número de seguimiento para rastrear tu envío</li>
                                        <li>El tiempo estimado de entrega dependerá de tu ubicación</li>
                                    <?php else: ?>
                                        <li>Estamos preparando tu pedido</li>
                                        <li>Te avisaremos cuando esté listo para retirar</li>
                                        <li>Recuerda traer tu número de pedido al momento del retiro</li>
                                        <li>Podrás coordinar el horario de retiro cuando te contactemos</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <p style="margin: 0 0 20px 0; color: #555555; font-size: 14px; line-height: 1.6;">
                                Si tienes alguna pregunta sobre tu pedido, no dudes en contactarnos. Estamos aquí para ayudarte.
                            </p>

                            <p style="margin: 0; color: #333333; font-size: 14px; line-height: 1.6;">
                                Gracias por tu compra,<br>
                                <strong><?= htmlspecialchars($site_name) ?></strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #666666; font-size: 12px;">
                                Este correo fue enviado porque realizaste una compra en nuestra tienda.
                            </p>
                            <p style="margin: 0; color: #999999; font-size: 11px;">
                                &copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
