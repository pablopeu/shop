<?php
/**
 * Admin - Add New Coupon
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/products.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = 'Nuevo Cupón';

// Get all products for selection
$all_products = get_all_products(false);

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {

    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Get form data
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

        // Validate required fields
        if (empty($coupon_data['code'])) {
            $error = 'El código es requerido';
        } elseif ($coupon_data['value'] <= 0) {
            $error = 'El valor del descuento debe ser mayor a 0';
        } elseif (empty($coupon_data['start_date']) || empty($coupon_data['end_date'])) {
            $error = 'Las fechas de vigencia son requeridas';
        } elseif (strtotime($coupon_data['end_date']) < strtotime($coupon_data['start_date'])) {
            $error = 'La fecha de fin debe ser posterior a la fecha de inicio';
        } else {
            // Create new coupon
            $result = create_coupon($coupon_data);

            if (isset($result['success']) && $result['success']) {
                $message = 'Cupón creado exitosamente';
                log_admin_action('coupon_created', $_SESSION['username'], [
                    'coupon_id' => $result['coupon']['id'],
                    'code' => $coupon_data['code']
                ]);

                // Redirect to list after 2 seconds
                header("refresh:2;url=/admin/cupones-listado.php");
            } else {
                $error = $result['error'] ?? 'Error al crear el cupón';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get logged user
$user = get_logged_user();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cupón - Admin</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            max-width: 1200px;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box p {
            color: #555;
            font-size: 14px;
            margin: 0;
        }

        .section-divider {
            margin: 30px 0;
            padding: 15px 0;
            border-top: 2px solid #f0f0f0;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }

        .products-selection {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
        }

        .product-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .product-checkbox:hover {
            background: #f8f9fa;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <?php if ($message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
                <br><small>Redirigiendo al listado...</small>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="info-box">
            <p><strong>ℹ️ Información:</strong></p>
            <p>• El código del cupón se convertirá automáticamente a mayúsculas</p>
            <p>• Los cupones con usos máximos = 0 se consideran ilimitados</p>
            <p>• Los cupones expirados no podrán ser usados aunque estén activos</p>
        </div>

        <!-- Coupon Form -->
        <div class="card">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Basic Information -->\n                <div class="section-divider">🎫 Información del Cupón</div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="code">
                            Código del Cupón <span class="required">*</span>
                        </label>
                        <input type="text" id="code" name="code" required
                               placeholder="Ej: BIENVENIDO10" style="text-transform: uppercase;">
                        <small style="color: #666;">El código que los clientes usarán. Ejemplo: VERANO2025, PRIMERACOMPRA</small>
                    </div>

                    <div class="form-group">
                        <label for="type">
                            Tipo de Descuento <span class="required">*</span>
                        </label>
                        <select id="type" name="type" required>
                            <option value="percentage">Porcentaje (%)</option>
                            <option value="fixed">Monto Fijo ($)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="value">
                            Valor del Descuento <span class="required">*</span>
                        </label>
                        <input type="number" id="value" name="value" step="0.01" required
                               placeholder="0.00">
                        <small style="color: #666;">Ejemplo: 10 para 10% o $10 según el tipo</small>
                    </div>
                </div>

                <!-- Restrictions -->
                <div class="section-divider">🔒 Restricciones</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="min_purchase">
                            Compra Mínima (ARS)
                        </label>
                        <input type="number" id="min_purchase" name="min_purchase" step="0.01"
                               value="0" min="0">
                        <small style="color: #666;">0 = sin mínimo requerido</small>
                    </div>

                    <div class="form-group">
                        <label for="max_uses">
                            Usos Máximos
                        </label>
                        <input type="number" id="max_uses" name="max_uses"
                               value="0" min="0">
                        <small style="color: #666;">0 = ilimitado</small>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="one_per_user" name="one_per_user">
                        <label for="one_per_user">
                            Un solo uso por usuario (requiere login)
                        </label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="not_combinable" name="not_combinable">
                        <label for="not_combinable">
                            No combinable con otros cupones
                        </label>
                    </div>
                </div>

                <!-- Validity Period -->
                <div class="section-divider">📅 Vigencia</div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_date">
                            Fecha de Inicio <span class="required">*</span>
                        </label>
                        <input type="date" id="start_date" name="start_date" required
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">
                            Fecha de Fin <span class="required">*</span>
                        </label>
                        <input type="date" id="end_date" name="end_date" required
                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>

                <!-- Applicable Products -->
                <div class="section-divider">🛍️ Productos Aplicables</div>

                <div class="form-group full-width">
                    <label for="applicable_to">
                        Aplicar a:
                    </label>
                    <select id="applicable_to" name="applicable_to" onchange="toggleProductsSelection(this.value)">
                        <option value="all">Todos los productos</option>
                        <option value="specific">Productos específicos</option>
                    </select>
                </div>

                <div id="products_container" class="form-group full-width" style="display: none;">
                    <label>Seleccionar Productos:</label>
                    <div class="products-selection">
                        <?php foreach ($all_products as $product): ?>
                            <div class="product-checkbox">
                                <input type="checkbox" name="products[]"
                                       value="<?php echo htmlspecialchars($product['id']); ?>"
                                       id="product_<?php echo htmlspecialchars($product['id']); ?>">
                                <label for="product_<?php echo htmlspecialchars($product['id']); ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                    (<?php echo format_price($product['price_ars'], 'ARS'); ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status -->
                <div class="section-divider">⚙️ Estado</div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="active" name="active" checked>
                        <label for="active">
                            Cupón Activo (disponible para uso)
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" name="save_coupon" class="btn btn-primary">
                        💾 Crear Cupón
                    </button>
                    <a href="/admin/cupones-listado.php" class="btn btn-secondary">
                        ❌ Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleProductsSelection(value) {
            const container = document.getElementById('products_container');
            if (value === 'specific') {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
</body>
</html>
