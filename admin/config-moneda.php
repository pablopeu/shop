<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
require_admin();

$message = '';
$error = '';

// Handle fetch from API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_api'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $api_data = get_bluelytics_rate();
        if ($api_data) {
            $config = read_json(__DIR__ . '/../config/currency.json');
            $config['api_blue_rate'] = $api_data['blue'];
            $config['api_oficial_rate'] = $api_data['oficial'];
            $config['last_update'] = date('Y-m-d\TH:i:s\Z');

            if (write_json(__DIR__ . '/../config/currency.json', $config)) {
                $message = 'Tipo de cambio actualizado desde la API: $' . number_format($api_data['blue'], 2);
                log_admin_action('currency_api_fetched', $_SESSION['username'], $api_data);
            } else {
                $error = 'Error al guardar datos de la API';
            }
        } else {
            $error = 'No se pudo conectar con la API de Bluelytics';
        }
    }
}

// Handle save configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config = read_json(__DIR__ . '/../config/currency.json');
        $config['primary'] = sanitize_input($_POST['primary'] ?? 'ARS');
        $config['secondary'] = sanitize_input($_POST['secondary'] ?? 'USD');

        // API configuration
        $config['api_enabled'] = isset($_POST['api_enabled']);
        $config['manual_override'] = isset($_POST['manual_override']);

        // Exchange rate value
        if ($config['manual_override']) {
            // Manual override - use user input
            $config['exchange_rate'] = floatval($_POST['exchange_rate'] ?? 1000);
            $config['exchange_rate_source'] = 'manual';
        } else if ($config['api_enabled']) {
            // API enabled without override - fetch from API
            $api_data = get_bluelytics_rate();
            if ($api_data) {
                $config['exchange_rate'] = $api_data['blue'];
                $config['exchange_rate_source'] = 'api';
                $config['api_blue_rate'] = $api_data['blue'];
                $config['api_oficial_rate'] = $api_data['oficial'];
                $config['last_update'] = date('Y-m-d\TH:i:s\Z');
            } else {
                // API failed, keep current value
                $error = 'Advertencia: API no disponible, se mantiene el valor actual';
            }
        } else {
            // Manual mode
            $config['exchange_rate'] = floatval($_POST['exchange_rate'] ?? 1000);
            $config['exchange_rate_source'] = 'manual';
        }

        if (write_json(__DIR__ . '/../config/currency.json', $config)) {
            $message = $message ?: 'Configuración guardada exitosamente';
            log_admin_action('currency_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar configuración';
        }
    }
}

$config = read_json(__DIR__ . '/../config/currency.json');
$site_config = read_json(__DIR__ . '/../config/site.json');
$page_title = '💱 Configuración de Moneda';
$csrf_token = generate_csrf_token();
$user = get_logged_user();

// Get time since last update
$last_update_text = 'Nunca';
if (isset($config['last_update'])) {
    $last_update_time = strtotime($config['last_update']);
    $diff = time() - $last_update_time;
    if ($diff < 60) {
        $last_update_text = 'Hace menos de 1 minuto';
    } elseif ($diff < 3600) {
        $last_update_text = 'Hace ' . floor($diff / 60) . ' minutos';
    } elseif ($diff < 86400) {
        $last_update_text = 'Hace ' . floor($diff / 3600) . ' horas';
    } else {
        $last_update_text = date('d/m/Y H:i', $last_update_time);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Moneda y Cambio - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 260px; padding: 20px; max-width: 900px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h3 { margin-bottom: 15px; color: #333; font-size: 16px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #007bff; }
        .checkbox-group { display: flex; align-items: center; margin-bottom: 12px; }
        .checkbox-group input[type="checkbox"] { width: auto; margin-right: 8px; }
        .checkbox-group label { margin-bottom: 0; font-weight: 400; }
        .info-box { background: #e7f3ff; border-left: 4px solid #007bff; padding: 12px; margin-bottom: 15px; border-radius: 4px; font-size: 13px; }
        .warning-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 15px; border-radius: 4px; font-size: 13px; }
        .api-info { background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .api-info .rate { font-size: 24px; font-weight: bold; color: #007bff; }
        .api-info .label { font-size: 12px; color: #666; text-transform: uppercase; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        .btn-group { display: flex; gap: 10px; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .rate-display { display: flex; justify-content: space-between; gap: 15px; margin-bottom: 15px; }
        .rate-card { flex: 1; background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
        .rate-card .value { font-size: 20px; font-weight: bold; color: #007bff; }
        .rate-card .label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .help-text { font-size: 12px; color: #666; margin-top: 4px; }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        <?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- API Status Card -->
        <?php if ($config['api_enabled'] ?? false): ?>
        <div class="card">
            <h3>📊 Cotización Actual (Bluelytics)</h3>
            <div class="rate-display">
                <div class="rate-card">
                    <div class="label">💵 DÓLAR BLUE</div>
                    <div class="value">$<?= number_format($config['api_blue_rate'] ?? 0, 2) ?></div>
                </div>
                <div class="rate-card">
                    <div class="label">🏦 DÓLAR OFICIAL</div>
                    <div class="value">$<?= number_format($config['api_oficial_rate'] ?? 0, 2) ?></div>
                </div>
            </div>
            <div style="text-align: center; color: #666; font-size: 13px;">
                Última actualización: <?= $last_update_text ?>
            </div>
            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <button type="submit" name="fetch_api" class="btn btn-primary" style="width: 100%;">🔄 Actualizar desde API</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Configuration Card -->
        <div class="card">
            <h3>⚙️ Configuración de Moneda</h3>
            <form method="POST" id="configForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="form-group">
                    <label for="primary">Moneda Principal</label>
                    <select id="primary" name="primary">
                        <option value="ARS" <?= ($config['primary'] ?? 'ARS') === 'ARS' ? 'selected' : '' ?>>ARS - Peso Argentino</option>
                        <option value="USD" <?= ($config['primary'] ?? 'ARS') === 'USD' ? 'selected' : '' ?>>USD - Dólar</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="secondary">Moneda Secundaria</label>
                    <select id="secondary" name="secondary">
                        <option value="USD" <?= ($config['secondary'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD - Dólar</option>
                        <option value="ARS" <?= ($config['secondary'] ?? 'USD') === 'ARS' ? 'selected' : '' ?>>ARS - Peso Argentino</option>
                    </select>
                </div>

                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e0e0e0;">

                <div class="checkbox-group">
                    <input type="checkbox" id="api_enabled" name="api_enabled" <?= ($config['api_enabled'] ?? false) ? 'checked' : '' ?>>
                    <label for="api_enabled">Usar API de Bluelytics para obtener tipo de cambio automáticamente</label>
                </div>
                <div class="help-text" style="margin-left: 28px; margin-bottom: 15px;">
                    La cotización se actualizará automáticamente cada hora desde la API de Bluelytics
                </div>

                <div class="checkbox-group" id="override-group">
                    <input type="checkbox" id="manual_override" name="manual_override" <?= ($config['manual_override'] ?? false) ? 'checked' : '' ?>>
                    <label for="manual_override">Forzar tipo de cambio manual (ignorar API)</label>
                </div>
                <div class="help-text" style="margin-left: 28px; margin-bottom: 15px;">
                    Activa esto si quieres usar tu propio valor del dólar en vez del de la API
                </div>

                <div class="form-group">
                    <label for="exchange_rate">
                        Tipo de Cambio (1 USD = X ARS)
                        <?php if (($config['api_enabled'] ?? false) && !($config['manual_override'] ?? false)): ?>
                            <span style="color: #28a745; font-size: 12px;">(Se actualiza automáticamente desde la API)</span>
                        <?php endif; ?>
                    </label>
                    <input
                        type="number"
                        id="exchange_rate"
                        name="exchange_rate"
                        step="0.01"
                        required
                        value="<?= $config['exchange_rate'] ?? 1000 ?>"
                        <?= (($config['api_enabled'] ?? false) && !($config['manual_override'] ?? false)) ? 'readonly style="background: #f8f9fa;"' : '' ?>
                    >
                    <div class="help-text">
                        Valor actual: $1 USD = $<?= number_format($config['exchange_rate'] ?? 1000, 2) ?> ARS
                        <?php if (isset($config['exchange_rate_source'])): ?>
                            (Fuente: <?= $config['exchange_rate_source'] === 'api' ? 'API Bluelytics' : 'Manual' ?>)
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" name="save_config" class="btn-save" id="saveBtn">💾 Guardar Configuración</button>
            </form>
        </div>

        <div class="info-box">
            <strong>ℹ️ Cómo funciona:</strong><br>
            • Con API activada: El tipo de cambio se actualiza automáticamente cada hora desde Bluelytics<br>
            • Con override manual: Puedes establecer tu propio valor del dólar<br>
            • Sin API: El tipo de cambio es completamente manual
        </div>
    </div>
    <script>
        const form = document.getElementById('configForm');
        const saveBtn = document.getElementById('saveBtn');
        const inputs = form.querySelectorAll('input, select');
        const apiEnabled = document.getElementById('api_enabled');
        const manualOverride = document.getElementById('manual_override');
        const exchangeRateInput = document.getElementById('exchange_rate');
        const overrideGroup = document.getElementById('override-group');

        let originalValues = {};
        let saveSuccess = <?= $message ? 'true' : 'false' ?>;

        inputs.forEach(i => originalValues[i.name] = i.type === 'checkbox' ? i.checked : i.value);

        // Update UI based on checkbox states
        function updateUI() {
            if (apiEnabled.checked) {
                overrideGroup.style.display = 'flex';
                if (manualOverride.checked) {
                    exchangeRateInput.readOnly = false;
                    exchangeRateInput.style.background = '';
                } else {
                    exchangeRateInput.readOnly = true;
                    exchangeRateInput.style.background = '#f8f9fa';
                }
            } else {
                overrideGroup.style.display = 'none';
                exchangeRateInput.readOnly = false;
                exchangeRateInput.style.background = '';
            }
        }

        apiEnabled.addEventListener('change', updateUI);
        manualOverride.addEventListener('change', updateUI);
        updateUI();

        inputs.forEach(i => i.addEventListener('input', () => {
            let changed = Array.from(inputs).some(inp => {
                if (inp.type === 'checkbox') return inp.checked !== originalValues[inp.name];
                return inp.value !== originalValues[inp.name];
            });
            saveBtn.classList.toggle('changed', changed);
            saveBtn.classList.toggle('saved', !changed && saveSuccess);
        }));

        if (saveSuccess) {
            saveBtn.classList.add('saved');
            setTimeout(() => saveBtn.classList.remove('saved'), 3000);
        }
    </script>
</body>
</html>
