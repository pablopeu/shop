<?php
/**
 * Payment Approved Email Template
 * Sent to customer when payment is approved
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
    <title>¡Pago Aprobado!</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 40px 30px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">✓</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                ¡Pago Aprobado!
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 16px; opacity: 0.95;">
                                Tu compra ha sido confirmada
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
                                ¡Excelentes noticias! Tu pago ha sido procesado exitosamente y tu pedido está siendo preparado para el envío.
                            </p>

                            <!-- Success Box -->
                            <div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #155724; font-size: 15px; font-weight: 600;">
                                    ✓ Pago confirmado
                                </p>
                                <p style="margin: 10px 0 0 0; color: #155724; font-size: 14px;">
                                    Tu compra ha sido aprobada y registrada correctamente.
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
                                            <?php if (isset($order['mercadopago_data']['payment_id'])): ?>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">ID de Pago:</td>
                                                <td style="color: #333333; font-size: 14px; text-align: right; font-family: monospace;">
                                                    <?= htmlspecialchars($order['mercadopago_data']['payment_id']) ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Monto Pagado:</td>
                                                <td style="color: #28a745; font-size: 18px; font-weight: 700; text-align: right;">
                                                    <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                            <?php if (isset($order['mercadopago_data']['payment_method_id'])): ?>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Método de Pago:</td>
                                                <td style="color: #333333; font-size: 14px; text-align: right;">
                                                    <?= strtoupper(htmlspecialchars($order['mercadopago_data']['payment_method_id'])) ?>
                                                    <?php if (isset($order['mercadopago_data']['card_last_four_digits'])): ?>
                                                    **** <?= htmlspecialchars($order['mercadopago_data']['card_last_four_digits']) ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (isset($order['mercadopago_data']['installments']) && $order['mercadopago_data']['installments'] > 1): ?>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Cuotas:</td>
                                                <td style="color: #333333; font-size: 14px; text-align: right;">
                                                    <?= $order['mercadopago_data']['installments'] ?>x
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Products Summary -->
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 18px; font-weight: 600; border-bottom: 2px solid #28a745; padding-bottom: 10px;">
                                Productos Comprados
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
                                </tr>
                                <?php endforeach; ?>
                            </table>

                            <!-- Next Steps -->
                            <div style="background-color: #cfe2ff; border-left: 4px solid #0d6efd; padding: 15px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #084298; font-size: 14px; line-height: 1.6;">
                                    <strong>📦 Próximo paso:</strong> Estamos preparando tu pedido para el envío.
                                    Te notificaremos cuando tu paquete esté en camino.
                                </p>
                            </div>

                            <!-- Tracking Link -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="<?= $site_url ?>/seguimiento.php?order=<?= urlencode($order['id']) ?>&token=<?= urlencode($order['tracking_token']) ?>"
                                           style="display: inline-block; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 2px 8px rgba(17, 153, 142, 0.4);">
                                            🔍 Seguir mi Pedido
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #888888; font-size: 13px; line-height: 1.6; text-align: center;">
                                Gracias por tu confianza. ¡Esperamos que disfrutes tu compra!
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
