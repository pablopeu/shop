<?php
/**
 * DEBUG: Base Path Detection
 * Archivo temporal para verificar cómo se detecta BASE_PATH
 */

require_once __DIR__ . '/config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug BASE_PATH</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #00ff00;
        }
        .info {
            background: #2d2d2d;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #00ff00;
        }
        .value {
            color: #ffff00;
            font-weight: bold;
        }
        h1 { color: #00ffff; }
        h2 { color: #ff00ff; }
    </style>
</head>
<body>
    <h1>🔍 DEBUG: BASE_PATH Detection</h1>

    <h2>📍 Detected Configuration:</h2>

    <div class="info">
        <strong>BASE_PATH:</strong> <span class="value"><?php echo BASE_PATH === '' ? '[empty string - root installation]' : BASE_PATH; ?></span>
    </div>

    <div class="info">
        <strong>DOCUMENT_ROOT:</strong> <span class="value"><?php echo $_SERVER['DOCUMENT_ROOT']; ?></span>
    </div>

    <div class="info">
        <strong>SCRIPT_FILENAME:</strong> <span class="value"><?php echo $_SERVER['SCRIPT_FILENAME']; ?></span>
    </div>

    <div class="info">
        <strong>Script Directory:</strong> <span class="value"><?php echo dirname($_SERVER['SCRIPT_FILENAME']); ?></span>
    </div>

    <h2>🔗 URL Generation Tests:</h2>

    <div class="info">
        <strong>url('/'):</strong> <span class="value"><?php echo url('/'); ?></span>
    </div>

    <div class="info">
        <strong>url('/admin'):</strong> <span class="value"><?php echo url('/admin'); ?></span>
    </div>

    <div class="info">
        <strong>url('/admin/login.php'):</strong> <span class="value"><?php echo url('/admin/login.php'); ?></span>
    </div>

    <div class="info">
        <strong>url('/includes/carousel.css'):</strong> <span class="value"><?php echo url('/includes/carousel.css'); ?></span>
    </div>

    <h2>✅ Expected Results:</h2>

    <div class="info">
        Si estás en <strong>https://peu.net/shop/</strong>:
        <ul>
            <li>BASE_PATH debería ser: <span class="value">/shop</span></li>
            <li>url('/admin') debería ser: <span class="value">/shop/admin</span></li>
        </ul>
    </div>

    <div class="info">
        Si estás en la <strong>raíz</strong> (localhost o servidor de prueba):
        <ul>
            <li>BASE_PATH debería ser: <span class="value">[empty string]</span></li>
            <li>url('/admin') debería ser: <span class="value">/admin</span></li>
        </ul>
    </div>

    <hr style="border-color: #00ff00; margin: 30px 0;">

    <p style="color: #ffffff;">
        <strong>Instrucciones:</strong><br>
        1. Abre este archivo en tu navegador: <span class="value">https://peu.net/shop/debug-basepath.php</span><br>
        2. Verifica que BASE_PATH esté correcto<br>
        3. Verifica que las URLs generadas sean correctas<br>
        4. Si todo está OK, puedes eliminar este archivo
    </p>
</body>
</html>
