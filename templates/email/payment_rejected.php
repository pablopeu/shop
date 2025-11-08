<?php
/**
 * Payment Rejected Email Template
 * Sent to customer when payment is rejected
 */

require_once __DIR__ . '/../../includes/functions.php';

$site_config = read_json(__DIR__ . '/../../config/site.json');
$site_name = $site_config['site_name'] ?? 'Mi Tienda';
$site_url = $site_config['site_url'] ?? 'https://tienda.com';

$status_detail = $order['payment_status_detail'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problema con el Pago</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 40px 30px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">✕</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                Problema con el Pago
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 16px; opacity: 0.95;">
                                No pudimos procesar tu pago
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
                                Lamentablemente no pudimos procesar tu pago. Por favor revisa la información a continuación.
                            </p>

                            <!-- Error Box -->
                            <div style="background-color: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #721c24; font-size: 15px; font-weight: 600;">
                                    <?= htmlspecialchars($payment_message['title'] ?? 'Pago Rechazado') ?>
                                </p>
                                <p style="margin: 10px 0 0 0; color: #721c24; font-size: 14px; line-height: 1.6;">
                                    <?= htmlspecialchars($payment_message['message'] ?? 'El pago no pudo ser procesado.') ?>
                                </p>
                                <?php if (isset($payment_message['action']) && !empty($payment_message['action'])): ?>
                                <p style="margin: 10px 0 0 0; color: #721c24; font-size: 14px; line-height: 1.6;">
                                    <strong>Sugerencia:</strong> <?= htmlspecialchars($payment_message['action']) ?>
                                </p>
                                <?php endif; ?>
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
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Monto:</td>
                                                <td style="color: #333333; font-size: 16px; font-weight: 600; text-align: right;">
                                                    <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                            <?php if (isset($order['mercadopago_data']['payment_id'])): ?>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">ID de Pago:</td>
                                                <td style="color: #333333; font-size: 12px; text-align: right; font-family: monospace;">
                                                    <?= htmlspecialchars($order['mercadopago_data']['payment_id']) ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- What to do next -->
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; font-weight: 600;">
                                ¿Qué puedo hacer?
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                <tr>
                                    <td style="padding: 15px; background-color: #fff3cd; border-radius: 6px; margin-bottom: 10px;">
                                        <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                            <strong>1.</strong> Verifica que los datos de tu tarjeta sean correctos (número, fecha de vencimiento, código CVV).
                                        </p>
                                    </td>
                                </tr>
                                <tr><td style="height: 10px;"></td></tr>
                                <tr>
                                    <td style="padding: 15px; background-color: #fff3cd; border-radius: 6px; margin-bottom: 10px;">
                                        <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                            <strong>2.</strong> Asegúrate de tener fondos suficientes o cupo disponible en tu tarjeta.
                                        </p>
                                    </td>
                                </tr>
                                <tr><td style="height: 10px;"></td></tr>
                                <tr>
                                    <td style="padding: 15px; background-color: #fff3cd; border-radius: 6px;">
                                        <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                            <strong>3.</strong> Intenta con otro método de pago o comunícate con tu banco para más información.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Retry Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="<?= $site_url ?>/pagar-mp.php?order=<?= urlencode($order['id']) ?>&token=<?= urlencode($order['tracking_token']) ?>"
                                           style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);">
                                            🔄 Intentar Nuevamente
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #888888; font-size: 13px; line-height: 1.6; text-align: center;">
                                Si continúas teniendo problemas, no dudes en contactarnos. Estamos aquí para ayudarte.
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
