<?php
/**
 * Payment Pending Email Template
 * Sent to customer when payment is pending
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
    <title>Pago Pendiente</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%); padding: 40px 30px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">⏳</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">
                                Pago Pendiente
                            </h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 16px; opacity: 0.95;">
                                Estamos procesando tu pago
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
                                Tu pago está siendo procesado. Te notificaremos en cuanto se confirme.
                            </p>

                            <!-- Pending Box -->
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #856404; font-size: 15px; font-weight: 600;">
                                    ⏳ Tu pago está en proceso
                                </p>
                                <p style="margin: 10px 0 0 0; color: #856404; font-size: 14px; line-height: 1.6;">
                                    Esto puede deberse a que el banco está validando la transacción o que seleccionaste un método de pago que requiere más tiempo para procesarse.
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
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Monto:</td>
                                                <td style="color: #f39c12; font-size: 18px; font-weight: 700; text-align: right;">
                                                    <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 14px; font-weight: 600;">Estado:</td>
                                                <td style="color: #f39c12; font-size: 14px; font-weight: 600; text-align: right;">
                                                    PENDIENTE
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Timeline -->
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 18px; font-weight: 600;">
                                ¿Qué sigue?
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                                <tr>
                                    <td style="width: 40px; vertical-align: top; padding-top: 5px;">
                                        <div style="width: 30px; height: 30px; background-color: #ffc107; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">1</div>
                                    </td>
                                    <td style="padding: 5px 0 20px 10px;">
                                        <p style="margin: 0; color: #333333; font-size: 14px; font-weight: 600;">Estamos procesando tu pago</p>
                                        <p style="margin: 5px 0 0 0; color: #666666; font-size: 13px;">El banco está validando la transacción.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 40px; vertical-align: top; padding-top: 5px;">
                                        <div style="width: 30px; height: 30px; background-color: #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">2</div>
                                    </td>
                                    <td style="padding: 5px 0 20px 10px;">
                                        <p style="margin: 0; color: #333333; font-size: 14px; font-weight: 600;">Te notificaremos el resultado</p>
                                        <p style="margin: 5px 0 0 0; color: #666666; font-size: 13px;">Recibirás un email cuando se confirme el pago.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="width: 40px; vertical-align: top; padding-top: 5px;">
                                        <div style="width: 30px; height: 30px; background-color: #e0e0e0; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">3</div>
                                    </td>
                                    <td style="padding: 5px 0;">
                                        <p style="margin: 0; color: #333333; font-size: 14px; font-weight: 600;">Prepararemos tu envío</p>
                                        <p style="margin: 5px 0 0 0; color: #666666; font-size: 13px;">Una vez aprobado, procesaremos tu pedido.</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Info Box -->
                            <div style="background-color: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin-bottom: 30px; border-radius: 4px;">
                                <p style="margin: 0; color: #0c5460; font-size: 14px; line-height: 1.6;">
                                    <strong>ℹ️ Importante:</strong> Dependiendo del método de pago, esto puede tomar desde unos minutos hasta 48 horas. No es necesario que hagas nada más.
                                </p>
                            </div>

                            <!-- Tracking Link -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="<?= $site_url ?>/seguimiento.php?order=<?= urlencode($order['id']) ?>&token=<?= urlencode($order['tracking_token']) ?>"
                                           style="display: inline-block; background: linear-gradient(135deg, #f39c12 0%, #f1c40f 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-size: 15px; font-weight: 600; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.4);">
                                            🔍 Consultar Estado
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 30px 0 0 0; color: #888888; font-size: 13px; line-height: 1.6; text-align: center;">
                                Te mantendremos informado sobre el estado de tu pedido.
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
