<?php
/**
 * Test Page for Carousel V2
 * Página de prueba para visualizar el nuevo carrusel antes de integrarlo
 */

require_once __DIR__ . '/includes/functions.php';
$site_config = read_json(__DIR__ . '/config/site.json');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test - Carousel V2</title>

    <!-- Carousel V2 CSS -->
    <link rel="stylesheet" href="/includes/carousel-v2.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .test-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .test-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .test-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #333;
        }

        .test-header p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .info-box h3 {
            margin-bottom: 10px;
            color: #1976d2;
            font-size: 18px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
        }

        .info-box li {
            margin-bottom: 8px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-link:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .carousel-demo-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🎠 Test - Carrusel V2</h1>
            <p>
                Esta es una página de prueba para visualizar la nueva versión del carrusel.
                El carrusel V2 trae mejoras en la animación y presentación de contenido.
            </p>

            <div class="info-box">
                <h3>✨ Características del Carrusel V2:</h3>
                <ul>
                    <li><strong>Auto-rotación hacia la izquierda:</strong> Las imágenes se desplazan automáticamente según el tiempo configurado</li>
                    <li><strong>Título en esquina inferior derecha:</strong> El nombre de cada imagen aparece en una etiqueta moderna</li>
                    <li><strong>Puntos indicadores mejorados:</strong> El punto activo es más ancho (estilo píldora) para mejor visibilidad</li>
                    <li><strong>Links clickeables:</strong> Click en la imagen te lleva al producto configurado</li>
                    <li><strong>Pausa automática:</strong> Se detiene al pasar el mouse sobre el carrusel</li>
                    <li><strong>Navegación con teclado:</strong> Usa las flechas izquierda/derecha para navegar</li>
                    <li><strong>Responsive:</strong> Se adapta perfectamente a todos los dispositivos</li>
                </ul>
            </div>

            <a href="/index.php" class="back-link">← Volver a la página principal</a>
            <a href="/admin/config-carrusel.php" class="back-link" style="background: #28a745;">⚙️ Configurar Carrusel</a>
        </div>

        <div class="carousel-demo-section">
            <h2 class="section-title">Vista Previa del Carrusel V2</h2>

            <!-- Include Carousel V2 -->
            <?php include __DIR__ . '/includes/carousel-v2.php'; ?>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #666;">
            <p>
                Si el carrusel funciona correctamente, puedes proceder a integrarlo en la página principal.
                <br>
                Consulta el archivo <code>CAROUSEL-V2-README.md</code> para instrucciones de integración.
            </p>
        </div>
    </div>

    <!-- Carousel V2 JavaScript -->
    <script src="/includes/carousel-v2.js"></script>
</body>
</html>
