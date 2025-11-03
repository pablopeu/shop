<?php
/**
 * Maintenance Mode Page
 */

$maintenance = read_json(__DIR__ . '/config/maintenance.json');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitio en Mantenimiento</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 20px;
        }

        p {
            font-size: 18px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .time {
            font-weight: bold;
            color: #667eea;
        }

        .contact {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }

        .contact p {
            font-size: 14px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Sitio en Mantenimiento</h1>
        <p><?php echo htmlspecialchars($maintenance['message']); ?></p>

        <?php if (!empty($maintenance['estimated_time'])): ?>
            <p>Tiempo estimado: <span class="time"><?php echo htmlspecialchars($maintenance['estimated_time']); ?></span></p>
        <?php endif; ?>

        <div class="contact">
            <p>Para consultas urgentes, contáctanos por email</p>
        </div>
    </div>
</body>
</html>
