<?php
/**
 * Admin - Edit Coupon
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/auth.php';

session_start();
require_admin();

$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Editar Cupón';
$coupon_id = $_GET['id'] ?? '';

if (empty($coupon_id)) {
    header('Location: ' . url('/admin/cupones-listado.php'));
    exit;
}

$coupon = get_coupon_by_id($coupon_id);
if (!$coupon) {
    header('Location: ' . url('/admin/cupones-listado.php?error=not_found'));
    exit;
}

$all_products = get_all_products(false);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $coupon_data = [
            'code' => sanitize_input($_POST['code'] ?? ''),
            'type' => sanitize_input($_POST['type'] ?? 'percentage'),
            'value' => floatval($_POST['value'] ?? 0),
            'min_purchase' => floatval($_POST['min_purchase'] ?? 0),
            'max_uses' => intval($_POST['max_uses'] ?? 0),
            'one_per_user' => isset($_POST['one_per_user']) ? true : false,
            'start_date' => sanitize_input($_POST['start_date'] ?? ''),
            'end_date' => sanitize_input($_POST['end_date'] ?? ''),
            'applicable_to' => sanitize_input($_POST['applicable_to'] ?? 'all'),
            'products' => $_POST['products'] ?? [],
            'not_combinable' => isset($_POST['not_combinable']) ? true : false,
            'active' => isset($_POST['active']) ? true : false
        ];

        if (empty($coupon_data['code'])) {
            $error = 'El código es requerido';
        } elseif ($coupon_data['value'] <= 0) {
            $error = 'El valor del descuento debe ser mayor a 0';
        } elseif (update_coupon($coupon_id, $coupon_data)) {
            $message = 'Cupón actualizado exitosamente';
            log_admin_action('coupon_updated', $_SESSION['username'], [
                'coupon_id' => $coupon_id,
                'code' => $coupon_data['code']
            ]);
            $coupon = get_coupon_by_id($coupon_id);
        } else {
            $error = 'Error al actualizar el cupón';
        }
    }
}

$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cupón - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 30px; max-width: 1200px; }
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .btn { padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer; transition: all 0.3s; border: none; }
        .btn-primary { background: #4CAF50; color: white; }
        .btn-primary:hover { background: #45a049; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .card { background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        .form-group label .required { color: #dc3545; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .checkbox-group input { width: auto; }
        .section-divider { margin: 30px 0; padding: 15px 0; border-top: 2px solid #f0f0f0; font-size: 18px; font-weight: 600; color: #2c3e50; }
        .products-selection { max-height: 300px; overflow-y: auto; border: 2px solid #e0e0e0; border-radius: 6px; padding: 15px; }
        .product-checkbox { display: flex; align-items: center; gap: 10px; padding: 8px; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="section-divider">🎫 Información del Cupón</div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="code">Código del Cupón <span class="required">*</span></label>
                        <input type="text" id="code" name="code" required value="<?php echo htmlspecialchars($coupon['code']); ?>" style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label for="type">Tipo de Descuento <span class="required">*</span></label>
                        <select id="type" name="type" required>
                            <option value="percentage" <?php echo $coupon['type'] === 'percentage' ? 'selected' : ''; ?>>Porcentaje (%)</option>
                            <option value="fixed" <?php echo $coupon['type'] === 'fixed' ? 'selected' : ''; ?>>Monto Fijo ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="value">Valor del Descuento <span class="required">*</span></label>
                        <input type="number" id="value" name="value" step="0.01" required value="<?php echo $coupon['value']; ?>">
                    </div>
                </div>

                <div class="section-divider">🔒 Restricciones</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="min_purchase">Compra Mínima (ARS)</label>
                        <input type="number" id="min_purchase" name="min_purchase" step="0.01" value="<?php echo $coupon['min_purchase']; ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label for="max_uses">Usos Máximos</label>
                        <input type="number" id="max_uses" name="max_uses" value="<?php echo $coupon['max_uses']; ?>" min="0">
                        <small>Usos actuales: <?php echo $coupon['uses_count']; ?></small>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="one_per_user" name="one_per_user" <?php echo $coupon['one_per_user'] ? 'checked' : ''; ?>>
                        <label for="one_per_user">Un solo uso por usuario</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="not_combinable" name="not_combinable" <?php echo $coupon['not_combinable'] ? 'checked' : ''; ?>>
                        <label for="not_combinable">No combinable con otros cupones</label>
                    </div>
                </div>

                <div class="section-divider">📅 Vigencia</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_date">Fecha de Inicio <span class="required">*</span></label>
                        <input type="date" id="start_date" name="start_date" required value="<?php echo $coupon['start_date']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">Fecha de Fin <span class="required">*</span></label>
                        <input type="date" id="end_date" name="end_date" required value="<?php echo $coupon['end_date']; ?>">
                    </div>
                </div>

                <div class="section-divider">🛍️ Productos Aplicables</div>
                <div class="form-group full-width">
                    <label for="applicable_to">Aplicar a:</label>
                    <select id="applicable_to" name="applicable_to" onchange="toggleProductsSelection(this.value)">
                        <option value="all" <?php echo $coupon['applicable_to'] === 'all' ? 'selected' : ''; ?>>Todos los productos</option>
                        <option value="specific" <?php echo $coupon['applicable_to'] === 'specific' ? 'selected' : ''; ?>>Productos específicos</option>
                    </select>
                </div>

                <div id="products_container" class="form-group full-width" style="display: <?php echo $coupon['applicable_to'] === 'specific' ? 'block' : 'none'; ?>;">
                    <label>Seleccionar Productos:</label>
                    <div class="products-selection">
                        <?php foreach ($all_products as $product): ?>
                            <div class="product-checkbox">
                                <input type="checkbox" name="products[]" value="<?php echo htmlspecialchars($product['id']); ?>"
                                       id="product_<?php echo htmlspecialchars($product['id']); ?>"
                                       <?php echo in_array($product['id'], $coupon['products']) ? 'checked' : ''; ?>>
                                <label for="product_<?php echo htmlspecialchars($product['id']); ?>">
                                    <?php echo htmlspecialchars($product['name']); ?> (<?php echo format_price($product['price_ars'], 'ARS'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-divider">⚙️ Estado</div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="active" name="active" <?php echo $coupon['active'] ? 'checked' : ''; ?>>
                        <label for="active">Cupón Activo</label>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" name="save_coupon" class="btn btn-primary">💾 Guardar Cambios</button>
                    <a href="/admin/cupones-listado.php" class="btn btn-secondary">❌ Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        function toggleProductsSelection(value) {
            document.getElementById('products_container').style.display = value === 'specific' ? 'block' : 'none';
        }
    </script>

    <!-- Unsaved Changes Warning -->
    <script src="<?php echo url('/admin/includes/unsaved-changes-warning.js'); ?>"></script>
</body>
</html>
