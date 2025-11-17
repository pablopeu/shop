<?php
/**
 * Admin New Order Email Template
 * Sent to admin when a new order is created
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
    <title>Nueva Orden</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                            <div style="font-size: 36px; margin-bottom: 10px;">🛒</div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                                Nueva Orden Recibida
                            </h1>
                            <p style="margin: 8px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">
                                <?= htmlspecialchars($site_name) ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 30px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 15px; line-height: 1.6;">
                                Se ha recibido una nueva orden en el sistema:
                            </p>

                            <!-- Order Info Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8f9fa; border-radius: 6px; padding: 20px; margin-bottom: 25px; border-left: 4px solid #667eea;">
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="6" cellspacing="0">
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Número de Orden:</td>
                                                <td style="color: #667eea; font-size: 15px; text-align: right; font-weight: 700;">
                                                    #<?= htmlspecialchars($order['order_number']) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Fecha:</td>
                                                <td style="color: #333333; font-size: 13px; text-align: right;">
                                                    <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Total:</td>
                                                <td style="color: #28a745; font-size: 17px; font-weight: 700; text-align: right;">
                                                    <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($order['total'], 2) ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Estado:</td>
                                                <td style="text-align: right;">
                                                    <span style="display: inline-block; padding: 4px 10px; background-color: #ffc107; color: #000; font-size: 11px; font-weight: 600; border-radius: 12px; text-transform: uppercase;">
                                                        <?= htmlspecialchars($order['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: 600;">Método de Pago:</td>
                                                <td style="color: #333333; font-size: 13px; text-align: right; text-transform: capitalize;">
                                                    <?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Customer Info -->
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px;">
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
                                        <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>" style="color: #667eea; text-decoration: none;">
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
                                <?php if (isset($order['customer_address']) && !empty($order['customer_address'])): ?>
                                <tr>
                                    <td style="color: #666666; font-size: 13px; font-weight: 600; vertical-align: top;">Dirección:</td>
                                    <td style="color: #333333; font-size: 13px;">
                                        <?= nl2br(htmlspecialchars($order['customer_address'])) ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <!-- Customer Notes -->
                            <?php if (isset($order['notes']) && !empty(trim($order['notes']))): ?>
                            <div style="margin-bottom: 25px; background-color: #fff9e6; border-left: 4px solid #ffc107; border-radius: 6px; padding: 15px;">
                                <h3 style="margin: 0 0 10px 0; color: #333333; font-size: 14px; font-weight: 600;">
                                    💬 Mensaje del Cliente:
                                </h3>
                                <p style="margin: 0; color: #555555; font-size: 13px; line-height: 1.6; white-space: pre-wrap;">
                                    <?= nl2br(htmlspecialchars($order['notes'])) ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- Products -->
                            <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 16px; font-weight: 600; border-bottom: 2px solid #e0e0e0; padding-bottom: 8px;">
                                📦 Productos
                            </h2>

                            <table width="100%" cellpadding="8" cellspacing="0" style="margin-bottom: 25px;">
                                <thead>
                                    <tr style="background-color: #f8f9fa;">
                                        <th style="text-align: left; color: #666666; font-size: 12px; font-weight: 600; padding: 10px; border-bottom: 2px solid #e0e0e0;">Producto</th>
                                        <th style="text-align: center; color: #666666; font-size: 12px; font-weight: 600; padding: 10px; border-bottom: 2px solid #e0e0e0;">Cant.</th>
                                        <th style="text-align: right; color: #666666; font-size: 12px; font-weight: 600; padding: 10px; border-bottom: 2px solid #e0e0e0;">Precio</th>
                                        <th style="text-align: right; color: #666666; font-size: 12px; font-weight: 600; padding: 10px; border-bottom: 2px solid #e0e0e0;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order['items'] as $item): ?>
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="color: #333333; font-size: 13px; padding: 10px;">
                                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                                            <?php if (isset($item['variant']) && !empty($item['variant'])): ?>
                                            <br><span style="color: #888888; font-size: 11px;"><?= htmlspecialchars($item['variant']) ?></span>
                                            <?php endif; ?>
                                            <?php if (isset($item['product_id'])): ?>
                                            <br><span style="color: #999999; font-size: 11px; font-family: monospace;">ID: <?= htmlspecialchars($item['product_id']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #666666; font-size: 13px; text-align: center; padding: 10px;">
                                            <?= $item['quantity'] ?>
                                        </td>
                                        <td style="color: #333333; font-size: 13px; text-align: right; padding: 10px;">
                                            <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($item['price'], 2) ?>
                                        </td>
                                        <td style="color: #333333; font-size: 13px; text-align: right; padding: 10px; font-weight: 600;">
                                            <?= $order['currency'] === 'USD' ? 'USD' : '$' ?> <?= number_format($item['price'] * $item['quantity'], 2) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Admin Action Button -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 20px 0;">
                                        <a href="<?= $site_url ?>/admin/ventas.php"
                                           style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-size: 14px; font-weight: 600; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);">
                                            📊 Ver en Panel de Administración
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 25px 0 0 0; color: #888888; font-size: 12px; line-height: 1.6; text-align: center; border-top: 1px solid #e0e0e0; padding-top: 20px;">
                                Este es un email automático generado por el sistema.<br>
                                No es necesario responder a este mensaje.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #2c3e50; padding: 20px; text-align: center;">
                            <p style="margin: 0; color: #95a5a6; font-size: 11px;">
                                Panel de Administración - <?= htmlspecialchars($site_name) ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
