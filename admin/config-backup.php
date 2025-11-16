<?php
/**
 * Admin - System Backup
 * Create, download and manage complete site backups
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Start session
session_start();

// Check admin authentication
require_admin();

// Get configurations
$site_config = read_json(__DIR__ . '/../config/site.json');

// Page title for header
$page_title = '💾 Backup del Sistema';

// Project root directory (dynamic path)
$project_root = dirname(__DIR__);
$backups_dir = $project_root . '/data/backups';

// Create backups directory if it doesn't exist
if (!file_exists($backups_dir)) {
    mkdir($backups_dir, 0700, true);
}

// Handle messages
$message = '';
$error = '';

/**
 * Get list of existing backups
 */
function getBackupsList($backups_dir) {
    $backups = [];

    if (!is_dir($backups_dir)) {
        return $backups;
    }

    $files = scandir($backups_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        if (preg_match('/^backup_(\d{8}_\d{6})\.tar\.gz$/', $file, $matches)) {
            $filepath = $backups_dir . '/' . $file;
            $backups[] = [
                'filename' => $file,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'date' => filemtime($filepath),
                'formatted_date' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }

    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    return $backups;
}

/**
 * Format bytes to human readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get available disk space in MB
 */
function getAvailableSpace($path) {
    $free = disk_free_space($path);
    return $free ? round($free / 1024 / 1024, 2) : 0;
}

/**
 * Create complete site backup
 */
function createBackup($project_root, $backups_dir) {
    $timestamp = date('Ymd_His');
    $backup_filename = "backup_{$timestamp}.tar.gz";
    $backup_filepath = $backups_dir . '/' . $backup_filename;

    // Check available space (require at least 500MB)
    $available_mb = getAvailableSpace($backups_dir);
    if ($available_mb < 500) {
        return [
            'success' => false,
            'message' => "Espacio insuficiente en disco. Disponible: {$available_mb}MB. Mínimo requerido: 500MB"
        ];
    }

    // Build tar command
    // -c: create archive
    // -z: compress with gzip
    // -p: preserve permissions
    // -f: output file
    // --exclude: exclude backups directory to avoid recursion
    $exclude_path = 'data/backups';

    $command = sprintf(
        'tar -czpf %s --exclude=%s -C %s .',
        escapeshellarg($backup_filepath),
        escapeshellarg($exclude_path),
        escapeshellarg($project_root)
    );

    // Execute tar command
    exec($command . ' 2>&1', $output, $return_var);

    // Check if backup was created successfully
    if ($return_var !== 0) {
        return [
            'success' => false,
            'message' => 'Error al crear el backup: ' . implode("\n", $output)
        ];
    }

    if (!file_exists($backup_filepath) || filesize($backup_filepath) === 0) {
        return [
            'success' => false,
            'message' => 'El archivo de backup no se creó correctamente'
        ];
    }

    // Set restrictive permissions on backup file
    chmod($backup_filepath, 0600);

    // Clean old backups (keep only last 10)
    $backups = getBackupsList($backups_dir);
    if (count($backups) > 10) {
        $to_delete = array_slice($backups, 10);
        foreach ($to_delete as $backup) {
            @unlink($backup['filepath']);
        }
    }

    return [
        'success' => true,
        'filename' => $backup_filename,
        'filepath' => $backup_filepath,
        'size' => filesize($backup_filepath)
    ];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '❌ Token de seguridad inválido';
    } else {
        // Create backup
        if (isset($_POST['create_backup'])) {
            $result = createBackup($project_root, $backups_dir);

            if ($result['success']) {
                $size_formatted = formatBytes($result['size']);
                $message = "✅ Backup creado exitosamente: {$result['filename']} ({$size_formatted})";

                // Auto-download the backup
                if (file_exists($result['filepath'])) {
                    header('Content-Type: application/x-gzip');
                    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
                    header('Content-Length: ' . filesize($result['filepath']));
                    readfile($result['filepath']);
                    exit;
                }
            } else {
                $error = '❌ ' . $result['message'];
            }
        }

        // Delete backup
        if (isset($_POST['delete_backup'])) {
            $filename = sanitize_input($_POST['backup_filename'] ?? '');
            $filepath = $backups_dir . '/' . $filename;

            // Security: verify filename is valid backup format
            if (preg_match('/^backup_\d{8}_\d{6}\.tar\.gz$/', $filename) && file_exists($filepath)) {
                if (unlink($filepath)) {
                    $message = "✅ Backup eliminado: {$filename}";
                } else {
                    $error = "❌ Error al eliminar el backup";
                }
            } else {
                $error = "❌ Backup no válido o no existe";
            }
        }
    }
}

// Handle download
if (isset($_GET['download'])) {
    $filename = sanitize_input($_GET['download']);
    $filepath = $backups_dir . '/' . $filename;

    // Security: verify filename is valid backup format
    if (preg_match('/^backup_\d{8}_\d{6}\.tar\.gz$/', $filename) && file_exists($filepath)) {
        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $error = "❌ Backup no válido o no existe";
    }
}

// Get list of backups
$backups_list = getBackupsList($backups_dir);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo htmlspecialchars($site_config['site_name']); ?></title>
    <link rel="stylesheet" href="<?php echo url('/css/admin-styles.css'); ?>">
    <?php include __DIR__ . '/includes/admin-common-styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="dashboard-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1><?php echo $page_title; ?></h1>
                <p>Crear y gestionar copias de seguridad completas del sitio</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>📊 Información del Sistema</h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Directorio del Proyecto:</strong>
                            <code><?php echo htmlspecialchars($project_root); ?></code>
                        </div>
                        <div class="info-item">
                            <strong>Directorio de Backups:</strong>
                            <code><?php echo htmlspecialchars($backups_dir); ?></code>
                        </div>
                        <div class="info-item">
                            <strong>Espacio Disponible:</strong>
                            <span class="<?php echo getAvailableSpace($backups_dir) < 500 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo getAvailableSpace($backups_dir); ?> MB
                            </span>
                        </div>
                        <div class="info-item">
                            <strong>Backups Almacenados:</strong>
                            <?php echo count($backups_list); ?> / 10 (máximo)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Backup -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>💾 Crear Backup Completo</h2>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        El backup incluirá <strong>TODO el sitio completo</strong>, preservando permisos y estructura de archivos.
                    </p>

                    <div class="backup-info mb-3">
                        <h4>✅ Incluye:</h4>
                        <ul>
                            <li>📁 Código fuente completo (PHP, JS, CSS)</li>
                            <li>⚙️ Configuraciones (/config)</li>
                            <li>💾 Datos (/data - excepto backups previos)</li>
                            <li>🖼️ Imágenes (/images)</li>
                            <li>📦 Dependencias (/vendor)</li>
                            <li>🔧 Archivos de configuración (.htaccess, composer.json, etc.)</li>
                        </ul>

                        <h4 class="mt-3">❌ Excluye:</h4>
                        <ul>
                            <li>📦 /data/backups (evita recursión infinita)</li>
                        </ul>
                    </div>

                    <form method="POST" onsubmit="return confirmBackup()">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" name="create_backup" class="btn btn-primary btn-lg">
                            💾 Crear Backup Ahora
                        </button>
                    </form>

                    <p class="text-muted mt-3">
                        <small>
                            ⏱️ El proceso puede tomar varios minutos dependiendo del tamaño del sitio.<br>
                            📥 El backup se descargará automáticamente al completarse.
                        </small>
                    </p>
                </div>
            </div>

            <!-- Existing Backups -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2>📦 Backups Disponibles</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($backups_list)): ?>
                        <p class="text-muted">No hay backups disponibles. Crea uno usando el botón de arriba.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>📅 Fecha y Hora</th>
                                        <th>📦 Archivo</th>
                                        <th>💾 Tamaño</th>
                                        <th>⚙️ Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backups_list as $backup): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($backup['formatted_date']); ?></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($backup['filename']); ?></code>
                                            </td>
                                            <td><?php echo formatBytes($backup['size']); ?></td>
                                            <td>
                                                <a href="?download=<?php echo urlencode($backup['filename']); ?>"
                                                   class="btn btn-sm btn-primary"
                                                   title="Descargar backup">
                                                    📥 Descargar
                                                </a>

                                                <form method="POST" style="display: inline;"
                                                      onsubmit="return confirm('¿Estás seguro de eliminar este backup?\n\n<?php echo $backup['filename']; ?>');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                    <button type="submit" name="delete_backup"
                                                            class="btn btn-sm btn-danger"
                                                            title="Eliminar backup">
                                                        🗑️ Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Restoration Warning -->
            <div class="card card-warning">
                <div class="card-header">
                    <h2>⚠️ Cómo Restaurar un Backup</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <strong>⚠️ IMPORTANTE:</strong> La restauración de backups debe hacerse <strong>MANUALMENTE vía FTP/SSH</strong> por razones de seguridad.
                    </div>

                    <h4>📋 Pasos para Restaurar:</h4>
                    <ol class="restoration-steps">
                        <li>
                            <strong>📥 Descarga el backup deseado</strong>
                            <p>Usa el botón "Descargar" en la tabla de arriba para obtener el archivo .tar.gz</p>
                        </li>

                        <li>
                            <strong>💻 Extrae el archivo en tu computadora</strong>
                            <p>Usa un programa como WinRAR, 7-Zip o el comando: <code>tar -xzpf backup_YYYYMMDD_HHMMSS.tar.gz</code></p>
                        </li>

                        <li>
                            <strong>🔐 Conéctate al servidor vía FTP/SFTP</strong>
                            <p>Usa FileZilla, WinSCP o tu cliente FTP preferido</p>
                        </li>

                        <li class="critical-step">
                            <strong>⛔ CRÍTICO: Crea un backup manual del estado ACTUAL</strong>
                            <p class="text-danger">Antes de hacer cualquier cambio, descarga una copia de los archivos actuales del servidor</p>
                        </li>

                        <li>
                            <strong>📂 Reemplaza las carpetas necesarias con cuidado</strong>
                            <p>Sube solo los archivos/carpetas que necesites restaurar. <strong>NO borres todo el sitio de golpe.</strong></p>
                        </li>

                        <li>
                            <strong>✅ Verifica permisos de archivos</strong>
                            <p>Especialmente las carpetas <code>/data</code> y <code>/config</code> deben tener permisos correctos (generalmente 755 para carpetas, 644 para archivos)</p>
                        </li>

                        <li>
                            <strong>🧪 Prueba que el sitio funcione correctamente</strong>
                            <p>Verifica que todas las funcionalidades estén operativas antes de considerarlo completo</p>
                        </li>
                    </ol>

                    <div class="alert alert-danger mt-4">
                        <h4>⛔ PRECAUCIONES EXTREMAS EN PRODUCCIÓN:</h4>
                        <ul>
                            <li><strong>NO elimines archivos sin respaldarlos primero</strong></li>
                            <li><strong>Restaura durante horarios de bajo tráfico</strong> (madrugada)</li>
                            <li><strong>Ten acceso directo al servidor</strong> por si algo falla</li>
                            <li><strong>Considera hacer pruebas en un entorno de staging</strong> primero</li>
                            <li><strong>Avisa a los usuarios</strong> si vas a poner el sitio en mantenimiento</li>
                        </ul>
                    </div>

                    <div class="mt-3">
                        <h4>📝 Comando de Restauración (vía SSH):</h4>
                        <p>Si tienes acceso SSH, puedes restaurar con:</p>
                        <pre><code># 1. Subir el backup al servidor
# 2. Crear backup del estado actual
tar -czpf backup_before_restore_$(date +%Y%m%d_%H%M%S).tar.gz --exclude='data/backups' -C <?php echo htmlspecialchars($project_root); ?> .

# 3. Extraer el backup (¡CUIDADO! Esto sobrescribirá archivos)
tar -xzpf backup_YYYYMMDD_HHMMSS.tar.gz -C <?php echo htmlspecialchars($project_root); ?>

# 4. Verificar permisos
chmod -R 755 <?php echo htmlspecialchars($project_root); ?>/data
chmod -R 755 <?php echo htmlspecialchars($project_root); ?>/config</code></pre>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        function confirmBackup() {
            return confirm('¿Deseas crear un backup completo del sitio?\n\nEsto puede tomar varios minutos.\nEl backup se descargará automáticamente al completarse.');
        }
    </script>

    <style>
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .info-item strong {
            display: block;
            margin-bottom: 5px;
            color: #495057;
        }

        .info-item code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.9em;
        }

        .backup-info h4 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .backup-info ul {
            margin-left: 20px;
            color: #6c757d;
        }

        .backup-info ul li {
            margin-bottom: 5px;
        }

        .card-warning {
            border-left: 4px solid #ff9800;
        }

        .restoration-steps {
            counter-reset: step-counter;
            list-style: none;
            padding-left: 0;
        }

        .restoration-steps li {
            counter-increment: step-counter;
            margin-bottom: 20px;
            padding-left: 40px;
            position: relative;
        }

        .restoration-steps li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .restoration-steps li.critical-step::before {
            background: #dc3545;
        }

        .restoration-steps li strong {
            display: block;
            margin-bottom: 5px;
            color: #212529;
        }

        .restoration-steps li p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 0.95em;
        }

        .text-danger {
            color: #dc3545 !important;
        }

        .text-success {
            color: #28a745 !important;
        }

        .text-muted {
            color: #6c757d !important;
        }

        pre {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
        }

        pre code {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #212529;
        }
    </style>
</body>
</html>
