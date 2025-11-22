<?php
/**
 * Order Confirmation Email Template
 * Sent to customer when order is created
 */

$site_config = read_json(__DIR__ . '/../../config/site.json');
$site_name = $site_config['site_name'] ?? 'Mi Tienda';
$site_url = $site_config['site_url'] ?? 'https://tienda.com';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                ✓ ¡Pedido Confirmado!
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 16px; opacity: 0.9;">
                                Gracias por tu compra
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
                                Hemos recibido tu pedido correctamente. A continuación encontrarás los detalles:
                            </p>

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
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Fecha:</td>
                                                <td style="color: #333333; font-size: 14px; text-align: right;">
                                                    <?= date('d/m/Y H:i', strtotime($order['created_at'] ?? $order['date'] ?? 'now')) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Total:</td>
                                                <td style="color: #667eea; font-size: 18px; font-weight: 700; text-align: right;">
                                                    <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Products -->
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 18px; font-weight: 600; border-bottom: 2px solid #667eea; padding-bottom: 10px;">
                                Productos
                            </h2>

                            <table width="100%" cellpadding="10" cellspacing="0" style="margin-bottom: 30px;">
                                <?php foreach ($order['items'] as $item): ?>
                                <tr style="border-bottom: 1px solid #e0e0e0;">
                                    <td style="color: #333333; font-size: 14px;">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                        <?php if (isset($item['variant']) && !empty($item['variant'])): ?>
                                        <br><span style="color: #888888; font-size: 12px;"><?= htmlspecialchars($item['variant']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #666666; font-size: 14px; text-align: center; white-space: nowrap;">
                                        x <?= $item['quantity'] ?>
                                    </td>
                                    <td style="color: #333333; font-size: 14px; text-align: right; white-space: nowrap;">
                                        <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($item['price'] * $item['quantity'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>

                            <!-- Shipping Info -->
                            <?php if (isset($order['customer_address']) && !empty($order['customer_address'])): ?>
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; font-weight: 600;">
                                📍 Dirección de Envío
                            </h2>
                            <p style="margin: 0 0 30px 0; color: #555555; font-size: 14px; line-height: 1.6; background-color: #f8f9fa; padding: 15px; border-radius: 6px;">
                                <?= nl2br(htmlspecialchars($order['customer_address'])) ?>
                                <?php if (isset($order['customer_phone']) && !empty($order['customer_phone'])): ?>
                                <br><strong>Tel:</strong> <?= htmlspecialchars($order['customer_phone']) ?>
                                <?php endif; ?>
                            </p>
                            <?php endif; ?>

                            <!-- Customer Notes -->
                            <?php if (isset($order['notes']) && !empty(trim($order['notes']))): ?>
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; font-weight: 600;">
                                💬 Tu Mensaje
                            </h2>
                            <p style="margin: 0 0 30px 0; color: #555555; font-size: 14px; line-height: 1.6; background-color: #f8f9fa; padding: 15px; border-radius: 6px;">
                                <?= nl2br(htmlspecialchars($order['notes'])) ?>
                            </p>
                            <?php endif; ?>

                            <!-- Next Steps -->
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                    <strong>⏳ Próximo paso:</strong> Tu pedido está pendiente de pago.
                                    <?php if (isset($order['payment_method']) && $order['payment_method'] === 'mercadopago'): ?>
                                    Completa el pago para que podamos procesar tu orden.
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- MercadoPago Alternative Payment Option (for non-MP orders) -->
                            <?php if (isset($order['payment_method']) && $order['payment_method'] !== 'mercadopago' && isset($order['payment_link']) && !empty($order['payment_link'])): ?>
                            <div style="background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 20px; margin-bottom: 30px; border-radius: 6px;">
                                <p style="margin: 0 0 12px 0; color: #0d47a1; font-size: 15px; line-height: 1.6;">
                                    <strong>💳 ¿Preferís pagar con tarjeta o MercadoPago?</strong>
                                </p>
                                <p style="margin: 0 0 15px 0; color: #1565c0; font-size: 14px; line-height: 1.6;">
                                    Si te resulta más cómodo, podés completar el pago de forma segura usando MercadoPago.
                                    Aceptamos todas las tarjetas y podés pagar en cuotas.
                                </p>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center">
                                            <a href="<?= htmlspecialchars($order['payment_link']) ?>"
                                               style="display: inline-block; background: #009ee3; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-size: 14px; font-weight: 600; box-shadow: 0 2px 6px rgba(0, 158, 227, 0.3);">
                                                💳 Pagar con MercadoPago
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin: 12px 0 0 0; color: #0d47a1; font-size: 12px; text-align: center;">
                                    Es opcional - podés seguir con el método de pago que elegiste
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- Tracking Link -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="<?= $site_url ?>/seguimiento.php?order=<?= urlencode($order['id']) ?>&token=<?= urlencode($order['tracking_token']) ?>"
                                           style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);">
                                            🔍 Seguir mi Pedido
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #888888; font-size: 13px; line-height: 1.6; text-align: center;">
                                Si tienes alguna pregunta, no dudes en contactarnos.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #2c3e50; padding: 30px; text-align: center;">
                            <p style="margin: 0 0 10px 0; color: #ffffff; font-size: 16px; font-weight: 600;">
                                <?= htmlspecialchars($site_name) ?>
                            </p>
                            <p style="margin: 0; color: #95a5a6; font-size: 12px;">
                                © <?= date('Y') ?> Todos los derechos reservados.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
