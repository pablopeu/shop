<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
session_start();
require_admin();

$message = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token inválido';
    } else {
        $config = read_json(__DIR__ . '/../config/maintenance.json');
        $config['enabled'] = isset($_POST['enabled']);
        $config['message'] = sanitize_input($_POST['message'] ?? '');
        $config['bypass_code'] = sanitize_input($_POST['bypass_code'] ?? '');
        if (write_json(__DIR__ . '/../config/maintenance.json', $config)) {
            $message = 'Guardado';
            log_admin_action('maintenance_updated', $_SESSION['username'], $config);
        } else $error = 'Error';
    }
}
$config = read_json(__DIR__ . '/../config/maintenance.json');
$csrf_token = generate_csrf_token();
$user = get_logged_user();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Mantenimiento</title>
<style>* { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: system-ui; background: #f5f7fa; } .main-content { margin-left: 260px; padding: 20px; max-width: 900px; } .content-header h1 { font-size: 24px; margin-bottom: 20px; } .message { padding: 12px; border-radius: 6px; margin-bottom: 15px; } .message.success { background: #d4edda; color: #155724; } .message.error { background: #f8d7da; color: #721c24; } .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); } .form-group { margin-bottom: 18px; } .form-group label { display: block; margin-bottom: 6px; font-weight: 500; } .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; } textarea { min-height: 80px; } .checkbox-group { display: flex; align-items: center; gap: 8px; } .checkbox-group input { width: auto; } .btn-save { padding: 12px 30px; background: #6c757d; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; } .btn-save.changed { background: #dc3545; animation: pulse 1.5s infinite; } .btn-save.saved { background: #28a745; } @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } } @media (max-width: 1024px) { .main-content { margin-left: 0; } }</style>
</head><body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main-content">
<div class="content-header"><h1>🚧 Modo Mantenimiento</h1></div>
<?php if ($message): ?><div class="message success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="card">
<form method="POST" id="configForm">
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
<div class="form-group"><div class="checkbox-group"><input type="checkbox" id="enabled" name="enabled" <?= ($config['enabled'] ?? false) ? 'checked' : '' ?>><label for="enabled">Activar Modo Mantenimiento</label></div></div>
<div class="form-group"><label for="message">Mensaje</label><textarea id="message" name="message"><?= htmlspecialchars($config['message'] ?? '') ?></textarea></div>
<div class="form-group"><label for="bypass_code">Código Bypass</label><input type="text" id="bypass_code" name="bypass_code" value="<?= htmlspecialchars($config['bypass_code'] ?? '') ?>"></div>
<button type="submit" name="save_config" class="btn-save" id="saveBtn">💾 Guardar</button>
</form></div></div>
<script>const form=document.getElementById('configForm'),saveBtn=document.getElementById('saveBtn'),inputs=form.querySelectorAll('input,textarea');let o={},s=<?= $message?'true':'false'?>;inputs.forEach(i=>o[i.name]=i.type==='checkbox'?i.checked:i.value);inputs.forEach(i=>i.addEventListener('change',()=>{let c=Array.from(inputs).some(inp=>(inp.type==='checkbox'?inp.checked:inp.value)!==o[inp.name]);saveBtn.classList.toggle('changed',c);saveBtn.classList.toggle('saved',!c&&s);}));if(s){saveBtn.classList.add('saved');setTimeout(()=>saveBtn.classList.remove('saved'),3000);}</script>
</body></html>
