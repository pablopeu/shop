<?php
/**
 * Admin Chargeback Alert Email Template
 * Sent to admin when a chargeback is detected
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
    <title>¡ALERTA! Contracargo Detectado</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 3px solid #dc3545;">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 30px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">🚨</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; text-transform: uppercase;">
                                ¡ALERTA DE CONTRACARGO!
                            </h1>
                            <p style="margin: 8px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.95; font-weight: 600;">
                                Se ha detectado un chargeback en una orden
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <!-- Critical Alert Box -->
                            <div style="background-color: #f8d7da; border: 2px solid #dc3545; border-radius: 6px; padding: 20px; margin-bottom: 25px;">
                                <p style="margin: 0 0 10px 0; color: #721c24; font-size: 16px; font-weight: 700;">
                                    ⚠️ ACCIÓN INMEDIATA REQUERIDA
                                </p>
                                <p style="margin: 0; color: #721c24; font-size: 14px; line-height: 1.6;">
                                    Se ha recibido un contracargo (chargeback) de Mercadopago. El cliente está disputando el pago de esta orden.
                                </p>
                            </div>

                            <!-- Chargeback Info -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff3cd; border-radius: 6px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #ffc107;">
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="6" cellspacing="0">
                                            <tr>
                                                <td style="color: #856404; font-size: 13px; font-weight: 700;">Chargeback ID:</td>
                                                <td style="color: #856404; font-size: 13px; text-align: right; font-family: monospace;">
                                                    <?= htmlspecialchars($chargeback['chargeback_id'] ?? 'N/A') ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #856404; font-size: 13px; font-weight: 700;">Acción:</td>
                                                <td style="text-align: right;">
                                                    <span style="display: inline-block; padding: 4px 10px; background-color: #dc3545; color: #fff; font-size: 11px; font-weight: 700; border-radius: 12px; text-transform: uppercase;">
                                                        <?= htmlspecialchars($chargeback['action'] ?? 'unknown') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #856404; font-size: 13px; font-weight: 700;">Fecha:</td>
                                                <td style="color: #856404; font-size: 13px; text-align: right;">
                                                    <?= date('d/m/Y H:i', strtotime($chargeback['date'] ?? 'now')) ?>
                                                </td>
                                            </tr>
                                            <?php if (isset($chargeback['payment_id'])): ?>
                                            <tr>
                                                <td style="color: #856404; font-size: 13px; font-weight: 700;">Payment ID:</td>
                                                <td style="color: #856404; font-size: 13px; text-align: right; font-family: monospace;">
                                                    <?= htmlspecialchars($chargeback['payment_id']) ?>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Order Info Box -->
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #dc3545; padding-bottom: 8px;">
                                📋 Información de la Orden
                            </h2>

                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 25px;">
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="6" cellspacing="0">
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Número de Orden:</td>
                                                <td style="color: #dc3545; font-size: 15px; text-align: right; font-weight: 700;">
                                                    #<?= htmlspecialchars($order['order_number']) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Fecha de Orden:</td>
                                                <td style="color: #333333; font-size: 13px; text-align: right;">
                                                    <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Monto:</td>
                                                <td style="color: #dc3545; font-size: 17px; font-weight: 700; text-align: right;">
                                                    <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Estado Actual:</td>
                                                <td style="text-align: right;">
                                                    <span style="display: inline-block; padding: 4px 10px; background-color: #6c757d; color: #fff; font-size: 11px; font-weight: 600; border-radius: 12px; text-transform: uppercase;">
                                                        <?= htmlspecialchars($order['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Customer Info -->
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #dc3545; padding-bottom: 8px;">
                                👤 Información del Cliente
                            </h2>

                            <table width="100%" cellpadding="6" cellspacing="0" style="margin-bottom: 25px; background-color: #f8f9fa; border-radius: 6px; padding: 15px;">
                                <tr>
                                    <td style="color: #666666; font-size: 13px; font-weight: 600; width: 120px;">Nombre:</td>
                                    <td style="color: #333333; font-size: 13px;">
                                        <strong><?= htmlspecialchars($order['customer_name'] ?? 'N/A') ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #666666; font-size: 13px; font-weight: 600;">Email:</td>
                                    <td style="color: #333333; font-size: 13px;">
                                        <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>" style="color: #dc3545; text-decoration: none; font-weight: 600;">
                                            <?= htmlspecialchars($order['customer_email']) ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php if (isset($order['customer_phone']) && !empty($order['customer_phone'])): ?>
                                <tr>
                                    <td style="color: #666666; font-size: 13px; font-weight: 600;">Teléfono:</td>
                                    <td style="color: #333333; font-size: 13px;">
                                        <?= htmlspecialchars($order['customer_phone']) ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <!-- Products -->
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #dc3545; padding-bottom: 8px;">
                                📦 Productos Involucrados
                            </h2>

                            <table width="100%" cellpadding="8" cellspacing="0" style="margin-bottom: 25px; font-size: 12px;">
                                <?php foreach ($order['items'] as $item): ?>
                                <tr style="border-bottom: 1px solid #e0e0e0;">
                                    <td style="color: #333333; padding: 8px;">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                        <?php if (isset($item['variant']) && !empty($item['variant'])): ?>
                                        <br><span style="color: #888888; font-size: 11px;"><?= htmlspecialchars($item['variant']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #666666; text-align: center; padding: 8px;">
                                        x <?= $item['quantity'] ?>
                                    </td>
                                    <td style="color: #333333; text-align: right; padding: 8px; font-weight: 600;">
                                        <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($item['price'] * $item['quantity'], 2) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>

                            <!-- Action Steps -->
                            <div style="background-color: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin-bottom: 25px; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; color: #0c5460; font-size: 14px; font-weight: 700;">
                                    📝 Pasos a Seguir:
                                </p>
                                <ol style="margin: 0; padding-left: 20px; color: #0c5460; font-size: 13px; line-height: 1.8;">
                                    <li>Revisa la orden en el panel de administración</li>
                                    <li>Verifica el estado del envío y la entrega</li>
                                    <li>Reúne toda la documentación (comprobante de envío, tracking, etc.)</li>
                                    <li>Accede a tu panel de Mercadopago para responder el contracargo</li>
                                    <li>Si el stock fue restaurado automáticamente, verifica el inventario</li>
                                </ol>
                            </div>

                            <!-- Admin Action Buttons -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding-right: 10px;">
                                                    <a href="<?= $site_url ?>/admin/ventas.php"
                                                       style="display: inline-block; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-size: 13px; font-weight: 600; box-shadow: 0 2px 8px rgba(220, 53, 69, 0.4);">
                                                        🔍 Ver Orden Completa
                                                    </a>
                                                </td>
                                                <td>
                                                    <a href="https://www.mercadopago.com.ar/chargebacks"
                                                       target="_blank"
                                                       style="display: inline-block; background: #007bff; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-size: 13px; font-weight: 600; box-shadow: 0 2px 8px rgba(0, 123, 255, 0.4);">
                                                        💳 Ir a Mercadopago
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 0 0; color: #888888; font-size: 12px; line-height: 1.6; text-align: center; border-top: 1px solid #e0e0e0; padding-top: 20px;">
                                <strong style="color: #dc3545;">IMPORTANTE:</strong> Los chargebacks deben ser atendidos con prontitud.<br>
                                La falta de respuesta puede resultar en pérdida del monto disputado.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #2c3e50; padding: 20px; text-align: center;">
                            <p style="margin: 0; color: #95a5a6; font-size: 11px;">
                                Sistema de Alertas - <?= htmlspecialchars($site_name) ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
