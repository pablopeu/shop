<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
require_admin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $config = read_json(__DIR__ . '/../config/currency.json');
        $config['primary'] = sanitize_input($_POST['primary'] ?? 'ARS');
        $config['secondary'] = sanitize_input($_POST['secondary'] ?? 'USD');
        $config['exchange_rate'] = floatval($_POST['exchange_rate'] ?? 1000);
        
        if (write_json(__DIR__ . '/../config/currency.json', $config)) {
            $message = 'Configuración guardada';
            log_admin_action('currency_config_updated', $_SESSION['username'], $config);
        } else {
            $error = 'Error al guardar';
        }
    }
}

$config = read_json(__DIR__ . '/../config/currency.json');
$csrf_token = generate_csrf_token();
$user = get_logged_user();
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
        .content-header h1 { font-size: 24px; color: #2c3e50; margin-bottom: 20px; }
        .message { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .message.success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .message.error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #555; font-size: 14px; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; }
        .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; }
        .btn-save.saved { background: #28a745; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        @media (max-width: 1024px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-header"><h1>💱 Moneda y Cambio</h1></div>
        <?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="card">
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
                <div class="form-group">
                    <label for="exchange_rate">Tipo de Cambio (1 USD = X ARS)</label>
                    <input type="number" id="exchange_rate" name="exchange_rate" step="0.01" required value="<?= $config['exchange_rate'] ?? 1000 ?>">
                </div>
                <button type="submit" name="save_config" class="btn-save" id="saveBtn">💾 Guardar</button>
            </form>
        </div>
    </div>
    <script>
        const form = document.getElementById('configForm'), saveBtn = document.getElementById('saveBtn'), inputs = form.querySelectorAll('input, select');
        let originalValues = {}, saveSuccess = <?= $message ? 'true' : 'false' ?>;
        inputs.forEach(i => originalValues[i.name] = i.value);
        inputs.forEach(i => i.addEventListener('input', () => {
            let changed = Array.from(inputs).some(inp => inp.value !== originalValues[inp.name]);
            saveBtn.classList.toggle('changed', changed);
            saveBtn.classList.toggle('saved', !changed && saveSuccess);
        }));
        if (saveSuccess) { saveBtn.classList.add('saved'); setTimeout(() => saveBtn.classList.remove('saved'), 3000); }
    </script>
</body>
</html>
