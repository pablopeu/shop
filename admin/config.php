<?php
/**
 * Admin - Site Configuration
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Handle form submissions
$message = '';
$error = '';

// Update site config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_site_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/site.json';
        $config = read_json($config_file);

        $config['site_name'] = sanitize_input($_POST['site_name'] ?? '');
        $config['site_description'] = sanitize_input($_POST['site_description'] ?? '');
        $config['site_keywords'] = sanitize_input($_POST['site_keywords'] ?? '');
        $config['contact_email'] = sanitize_input($_POST['contact_email'] ?? '');
        $config['contact_phone'] = sanitize_input($_POST['contact_phone'] ?? '');
        $config['whatsapp_number'] = sanitize_input($_POST['whatsapp_number'] ?? '');

        if (write_json($config_file, $config)) {
            $message = 'Configuración del sitio actualizada';
            log_admin_action('site_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Update currency config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_currency_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/currency.json';
        $config = read_json($config_file);

        $config['primary'] = sanitize_input($_POST['primary'] ?? 'ARS');
        $config['secondary'] = sanitize_input($_POST['secondary'] ?? 'USD');
        $config['exchange_rate'] = floatval($_POST['exchange_rate'] ?? 1000);

        if (write_json($config_file, $config)) {
            $message = 'Configuración de moneda actualizada';
            log_admin_action('currency_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Update maintenance mode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_maintenance'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/maintenance.json';
        $config = read_json($config_file);

        $config['enabled'] = isset($_POST['enabled']) ? true : false;
        $config['message'] = sanitize_input($_POST['message'] ?? '');
        $config['bypass_code'] = sanitize_input($_POST['bypass_code'] ?? '');

        if (write_json($config_file, $config)) {
            $message = 'Modo mantenimiento actualizado';
            log_admin_action('maintenance_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Update hero config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hero'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/hero.json';
        $config = read_json($config_file);

        $config['image'] = sanitize_input($_POST['hero_image'] ?? '');
        $config['subtitle'] = sanitize_input($_POST['hero_subtitle'] ?? '');

        if (write_json($config_file, $config)) {
            $message = 'Hero actualizado';
            log_admin_action('hero_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Update dashboard config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dashboard'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config_file = __DIR__ . '/../config/dashboard.json';
        $config = read_json($config_file);

        // Update widgets visibility
        $config['widgets'] = [
            'productos_activos' => isset($_POST['widget_productos_activos']),
            'stock_bajo' => isset($_POST['widget_stock_bajo']),
            'sin_stock' => isset($_POST['widget_sin_stock']),
            'ordenes_totales' => isset($_POST['widget_ordenes_totales']),
            'promociones' => isset($_POST['widget_promociones']),
            'cupones' => isset($_POST['widget_cupones']),
            'reviews_pendientes' => isset($_POST['widget_reviews_pendientes'])
        ];

        // Update quick actions visibility
        $config['quick_actions'] = [
            'productos' => isset($_POST['action_productos']),
            'ventas' => isset($_POST['action_ventas']),
            'cupones' => isset($_POST['action_cupones']),
            'reviews' => isset($_POST['action_reviews']),
            'config' => isset($_POST['action_config'])
        ];

        if (write_json($config_file, $config)) {
            $message = 'Configuración del dashboard actualizada';
            log_admin_action('dashboard_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar la configuración';
        }
    }
}

// Load current configs
$site_config = read_json(__DIR__ . '/../config/site.json');
$currency_config = read_json(__DIR__ . '/../config/currency.json');
$maintenance_config = read_json(__DIR__ . '/../config/maintenance.json');
$hero_config = read_json(__DIR__ . '/../config/hero.json');
$dashboard_config = read_json(__DIR__ . '/../config/dashboard.json');

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
    <title>Configuración - Admin</title>

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

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .content-header h1 {
            font-size: 28px;
            color: #2c3e50;
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
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .card-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
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

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
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
            <div class="content-header">
                <h1>Configuración del Sitio</h1>
                <a href="/" class="btn btn-secondary" target="_blank">Ver sitio público</a>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Site Config -->
            <div class="card">
                <div class="card-header">🏪 Información del Sitio</div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="site_name">Nombre del Sitio *</label>
                            <input type="text" id="site_name" name="site_name" required
                                   value="<?php echo htmlspecialchars($site_config['site_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label for="site_description">Descripción del Sitio</label>
                            <textarea id="site_description" name="site_description"><?php echo htmlspecialchars($site_config['site_description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label for="site_keywords">Keywords SEO (separadas por comas)</label>
                            <input type="text" id="site_keywords" name="site_keywords"
                                   value="<?php echo htmlspecialchars($site_config['site_keywords'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="contact_email">Email de Contacto</label>
                            <input type="email" id="contact_email" name="contact_email"
                                   value="<?php echo htmlspecialchars($site_config['contact_email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="contact_phone">Teléfono de Contacto</label>
                            <input type="text" id="contact_phone" name="contact_phone"
                                   value="<?php echo htmlspecialchars($site_config['contact_phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group full-width">
                            <label for="whatsapp_number">WhatsApp (con código de país, ej: +5491112345678)</label>
                            <input type="text" id="whatsapp_number" name="whatsapp_number"
                                   value="<?php echo htmlspecialchars($site_config['whatsapp_number'] ?? ''); ?>">
                        </div>
                    </div>

                    <button type="submit" name="save_site_config" class="btn btn-primary">
                        💾 Guardar Configuración
                    </button>
                </form>
            </div>

            <!-- Currency Config -->
            <div class="card">
                <div class="card-header">💱 Configuración de Moneda</div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="primary">Moneda Principal</label>
                            <select id="primary" name="primary">
                                <option value="ARS" <?php echo ($currency_config['primary'] ?? '') === 'ARS' ? 'selected' : ''; ?>>ARS - Peso Argentino</option>
                                <option value="USD" <?php echo ($currency_config['primary'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - Dólar</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="secondary">Moneda Secundaria</label>
                            <select id="secondary" name="secondary">
                                <option value="USD" <?php echo ($currency_config['secondary'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - Dólar</option>
                                <option value="ARS" <?php echo ($currency_config['secondary'] ?? '') === 'ARS' ? 'selected' : ''; ?>>ARS - Peso Argentino</option>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="exchange_rate">Tipo de Cambio (1 USD = X ARS)</label>
                            <input type="number" id="exchange_rate" name="exchange_rate" step="0.01" required
                                   value="<?php echo $currency_config['exchange_rate'] ?? 1000; ?>">
                        </div>
                    </div>

                    <div class="info-box">
                        <p><strong>ℹ️ Nota:</strong> El tipo de cambio se usa para convertir precios entre monedas. Actualízalo regularmente.</p>
                    </div>

                    <button type="submit" name="save_currency_config" class="btn btn-primary">
                        💾 Guardar Configuración
                    </button>
                </form>
            </div>

            <!-- Hero Config -->
            <div class="card">
                <div class="card-header">🖼️ Hero de la Página Principal</div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label for="hero_image">URL de Imagen Hero</label>
                        <input type="text" id="hero_image" name="hero_image"
                               value="<?php echo htmlspecialchars($hero_config['image'] ?? ''); ?>"
                               placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label for="hero_subtitle">Subtítulo del Sitio</label>
                        <textarea id="hero_subtitle" name="hero_subtitle"><?php echo htmlspecialchars($hero_config['subtitle'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="save_hero" class="btn btn-primary">
                        💾 Guardar Hero
                    </button>
                </form>
            </div>

            <!-- Dashboard Config -->
            <div class="card">
                <div class="card-header">📊 Configuración del Dashboard</div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <h3 style="margin-bottom: 15px; font-size: 16px; color: #2c3e50;">Widgets Visibles</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Selecciona qué tarjetas informativas deseas ver en el dashboard</p>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_productos_activos" name="widget_productos_activos"
                                   <?php echo ($dashboard_config['widgets']['productos_activos'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_productos_activos">📦 Productos Activos</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_stock_bajo" name="widget_stock_bajo"
                                   <?php echo ($dashboard_config['widgets']['stock_bajo'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_stock_bajo">⚠️ Stock Bajo</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_sin_stock" name="widget_sin_stock"
                                   <?php echo ($dashboard_config['widgets']['sin_stock'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_sin_stock">🚨 Sin Stock</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_ordenes_totales" name="widget_ordenes_totales"
                                   <?php echo ($dashboard_config['widgets']['ordenes_totales'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_ordenes_totales">💰 Órdenes Totales</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_promociones" name="widget_promociones"
                                   <?php echo ($dashboard_config['widgets']['promociones'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_promociones">🎯 Promociones</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_cupones" name="widget_cupones"
                                   <?php echo ($dashboard_config['widgets']['cupones'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_cupones">🎫 Cupones</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="widget_reviews_pendientes" name="widget_reviews_pendientes"
                                   <?php echo ($dashboard_config['widgets']['reviews_pendientes'] ?? true) ? 'checked' : ''; ?>>
                            <label for="widget_reviews_pendientes">⭐ Reviews Pendientes</label>
                        </div>
                    </div>

                    <hr style="margin: 25px 0; border: 0; border-top: 1px solid #e0e0e0;">

                    <h3 style="margin-bottom: 15px; font-size: 16px; color: #2c3e50;">Acciones Rápidas</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Selecciona qué botones de acciones rápidas deseas mostrar</p>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="action_productos" name="action_productos"
                                   <?php echo ($dashboard_config['quick_actions']['productos'] ?? true) ? 'checked' : ''; ?>>
                            <label for="action_productos">📦 Productos</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="action_ventas" name="action_ventas"
                                   <?php echo ($dashboard_config['quick_actions']['ventas'] ?? true) ? 'checked' : ''; ?>>
                            <label for="action_ventas">💰 Ventas</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="action_cupones" name="action_cupones"
                                   <?php echo ($dashboard_config['quick_actions']['cupones'] ?? true) ? 'checked' : ''; ?>>
                            <label for="action_cupones">🎫 Cupones</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="action_reviews" name="action_reviews"
                                   <?php echo ($dashboard_config['quick_actions']['reviews'] ?? true) ? 'checked' : ''; ?>>
                            <label for="action_reviews">⭐ Reviews</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="action_config" name="action_config"
                                   <?php echo ($dashboard_config['quick_actions']['config'] ?? true) ? 'checked' : ''; ?>>
                            <label for="action_config">⚙️ Configuración</label>
                        </div>
                    </div>

                    <div class="info-box">
                        <p><strong>ℹ️ Nota:</strong> Personaliza tu dashboard mostrando solo lo que necesitas ver. Los cambios se aplicarán inmediatamente.</p>
                    </div>

                    <button type="submit" name="save_dashboard" class="btn btn-primary">
                        💾 Guardar Configuración
                    </button>
                </form>
            </div>

            <!-- Maintenance Mode -->
            <div class="card">
                <div class="card-header">🚧 Modo Mantenimiento</div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="enabled" name="enabled"
                                   <?php echo ($maintenance_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                            <label for="enabled">Activar Modo Mantenimiento</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="message">Mensaje para Visitantes</label>
                        <textarea id="message" name="message"><?php echo htmlspecialchars($maintenance_config['message'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="bypass_code">Código de Bypass (para acceder durante mantenimiento)</label>
                        <input type="text" id="bypass_code" name="bypass_code"
                               value="<?php echo htmlspecialchars($maintenance_config['bypass_code'] ?? ''); ?>"
                               placeholder="Ej: secretcode123">
                    </div>

                    <div class="info-box">
                        <p><strong>ℹ️ Nota:</strong> Cuando el modo mantenimiento está activo, los visitantes verán el mensaje configurado. Los admins pueden acceder normalmente. También puedes usar: <code>/?bypass=<?php echo htmlspecialchars($maintenance_config['bypass_code'] ?? 'codigo'); ?></code></p>
                    </div>

                    <button type="submit" name="save_maintenance" class="btn btn-primary">
                        💾 Guardar Configuración
                    </button>
                </form>
            </div>
        </div>
</body>
</html>
