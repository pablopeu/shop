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
 * Create complete site backup using PHP PharData
 */
function createBackup($project_root, $backups_dir) {
    error_log(">>> createBackup() INICIADA");
    error_log(">>> project_root: " . $project_root);
    error_log(">>> backups_dir: " . $backups_dir);

    $timestamp = date('Ymd_His');
    $backup_filename = "backup_{$timestamp}.tar.gz";
    $backup_filepath = $backups_dir . '/' . $backup_filename;

    error_log(">>> backup_filename: " . $backup_filename);
    error_log(">>> backup_filepath: " . $backup_filepath);

    // Check available space (require at least 500MB)
    $available_mb = getAvailableSpace($backups_dir);
    error_log(">>> Espacio disponible: " . $available_mb . " MB");

    if ($available_mb < 500) {
        error_log(">>> ERROR: Espacio insuficiente");
        return [
            'success' => false,
            'message' => "Espacio insuficiente en disco. Disponible: {$available_mb}MB. Mínimo requerido: 500MB"
        ];
    }

    // Create backup using PHP PharData (no shell commands needed)
    error_log(">>> Creando backup con PharData");

    try {
        // Create temporary .tar file first
        $temp_tar = $backup_filepath . '.temp.tar';

        // Create Phar archive
        $phar = new PharData($temp_tar);

        // Build directory recursively, excluding backups directory
        error_log(">>> Construyendo archivo tar...");
        $phar->buildFromDirectory($project_root, '/^(?!.*data\/backups).*$/');

        // Compress to .tar.gz
        error_log(">>> Comprimiendo a gzip...");
        $phar->compress(Phar::GZ);

        // Remove temporary .tar file
        @unlink($temp_tar);

        // Rename .tar.gz file to final name
        if (file_exists($temp_tar . '.gz')) {
            rename($temp_tar . '.gz', $backup_filepath);
        }

        error_log(">>> Backup creado con PharData");

    } catch (Exception $e) {
        error_log(">>> ERROR en PharData: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al crear el backup: ' . $e->getMessage()
        ];
    }

    // Verify backup was created
    if (!file_exists($backup_filepath)) {
        error_log(">>> ERROR: El archivo no existe después de crear");
        return [
            'success' => false,
            'message' => 'El archivo de backup no se creó. Verifica permisos del directorio: ' . $backups_dir
        ];
    }

    if (filesize($backup_filepath) === 0) {
        error_log(">>> ERROR: El archivo está vacío");
        return [
            'success' => false,
            'message' => 'El archivo de backup se creó pero está vacío'
        ];
    }

    error_log(">>> Backup exitoso: " . formatBytes(filesize($backup_filepath)));

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

// Handle AJAX backup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode([
            'success' => false,
            'message' => 'Token de seguridad inválido'
        ]);
        exit;
    }

    // Create backup
    if (isset($_POST['create_backup'])) {
        $result = createBackup($project_root, $backups_dir);

        if ($result['success']) {
            $size_formatted = formatBytes($result['size']);
            echo json_encode([
                'success' => true,
                'message' => "Backup creado exitosamente",
                'filename' => $result['filename'],
                'size' => $size_formatted
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        exit;
    }
}

// Handle regular form submissions (delete backup)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '❌ Token de seguridad inválido';
    } else {

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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }

        /* Layout */
        .main-content { margin-left: 260px; padding: 20px; max-width: 1200px; }

        /* Header */
        .content-header { margin-bottom: 20px; }
        .content-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
        .content-header p { color: #6c757d; font-size: 14px; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-error { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }

        /* Cards */
        .card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .card-header h2 { font-size: 18px; color: #2c3e50; }
        .card-body { }
        .card-warning { border-left: 4px solid #ff9800; }

        /* Buttons */
        .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .btn-lg { padding: 14px 28px; font-size: 16px; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: #f8f9fa; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { font-weight: 600; color: #495057; font-size: 14px; }
        .table td { color: #6c757d; font-size: 14px; }
        .table tbody tr:hover { background: #f8f9fa; }
        .table code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
        .info-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .info-item strong { display: block; margin-bottom: 5px; color: #495057; font-size: 13px; }
        .info-item code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; }

        /* Backup Info */
        .backup-info h4 { color: #495057; margin-bottom: 10px; font-size: 1.1em; }
        .backup-info ul { margin-left: 20px; color: #6c757d; }
        .backup-info ul li { margin-bottom: 5px; }

        /* Utilities */
        .text-danger { color: #dc3545 !important; }
        .text-success { color: #28a745 !important; }
        .text-muted { color: #6c757d !important; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content { margin-left: 0; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 15px; }
            .info-grid { grid-template-columns: 1fr; }
            .table { font-size: 13px; }
            .btn { width: 100%; margin-bottom: 5px; }
            .progress-container { padding: 30px 20px; }
        }
    </style>
    <?php include __DIR__ . '/includes/admin-common-styles.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
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

                    <form method="POST" id="backupForm" onsubmit="return confirmBackup(event)">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="create_backup" value="1">
                        <button type="submit" class="btn btn-primary btn-lg">
                            💾 Crear Backup Ahora
                        </button>
                    </form>
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

                                                <form method="POST" style="display: inline;" id="deleteForm_<?php echo htmlspecialchars($backup['filename']); ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                    <input type="hidden" name="delete_backup" value="1">
                                                    <button type="button"
                                                            class="btn btn-sm btn-danger"
                                                            title="Eliminar backup"
                                                            onclick="confirmDelete('<?php echo htmlspecialchars($backup['filename'], ENT_QUOTES); ?>');">
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

    </div>

    <?php include __DIR__ . '/includes/modal.php'; ?>

    <style>
        /* Progress bar dentro del modal */
        #modalProgressContainer {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        #modalProgressContainer.active {
            display: block;
        }
        .modal-progress-bar-container {
            width: 100%;
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin: 15px 0;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .modal-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #007bff, #0056b3);
            border-radius: 6px;
            transition: width 0.3s ease, background 0.5s ease;
            position: relative;
        }
        .modal-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .modal-success-message {
            color: #28a745;
            font-weight: 600;
            margin: 15px 0;
        }
        .modal-error-message {
            color: #dc3545;
            font-weight: 600;
            margin: 15px 0;
        }
    </style>

    <script>
        // Confirmar creación de backup usando modal con progress integrado
        function confirmBackup(event) {
            event.preventDefault();

            const modal = document.getElementById('confirmModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDetails = document.getElementById('modalDetails');
            const confirmBtn = document.getElementById('modalConfirmBtn');
            const cancelBtn = document.getElementById('modalCancelBtn');
            const modalActions = modal.querySelector('.modal-actions');

            // Configurar modal inicial
            modalIcon.textContent = '💾';
            modalIcon.className = 'modal-icon info';
            modalTitle.textContent = 'Crear Backup Completo';
            modalMessage.textContent = '¿Deseas crear un backup completo del sitio?';
            modalDetails.innerHTML = '<strong>📥</strong> Una vez completado, aparecerá en la lista de backups disponibles para descarga.';
            modalDetails.style.display = 'block';

            confirmBtn.textContent = 'Crear Backup';
            confirmBtn.className = 'modal-btn modal-btn-confirm';
            cancelBtn.textContent = 'Cancelar';

            // Limpiar progress container anterior si existe
            const oldProgress = document.getElementById('modalProgressContainer');
            if (oldProgress) {
                oldProgress.remove();
            }

            // Mostrar modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Remover listeners anteriores y agregar nuevo
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            // Listener para cancelar
            newCancelBtn.addEventListener('click', function() {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            });

            // Listener para confirmar - NO cierra el modal
            newConfirmBtn.addEventListener('click', function() {
                // Deshabilitar botones
                newConfirmBtn.disabled = true;
                newCancelBtn.disabled = true;
                newConfirmBtn.style.opacity = '0.5';
                newCancelBtn.style.opacity = '0.5';
                newConfirmBtn.style.cursor = 'not-allowed';
                newCancelBtn.style.cursor = 'not-allowed';

                // Crear y agregar progress container
                const progressContainer = document.createElement('div');
                progressContainer.id = 'modalProgressContainer';
                progressContainer.className = 'active';
                progressContainer.innerHTML = `
                    <p style="color: #6c757d; text-align: center; margin: 10px 0;">
                        <strong>Creando backup...</strong><br>
                        Por favor espera.
                    </p>
                    <div class="modal-progress-bar-container">
                        <div class="modal-progress-bar" id="modalProgressBar" style="width: 0%;"></div>
                    </div>
                    <div id="modalResult"></div>
                `;
                modalActions.parentNode.insertBefore(progressContainer, modalActions.nextSibling);

                // Tiempo mínimo del progress bar (6 segundos)
                const MIN_DURATION = 6000;
                const startTime = Date.now();

                // Animar progress bar
                const progressBar = document.getElementById('modalProgressBar');
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 1;
                    if (progress <= 95) {
                        progressBar.style.width = progress + '%';
                    }
                }, MIN_DURATION / 95); // Distribuir el progreso en el tiempo mínimo

                // Enviar backup via AJAX
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                formData.append('create_backup', '1');
                formData.append('ajax', '1');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Calcular tiempo restante para alcanzar los 6 segundos mínimos
                    const elapsedTime = Date.now() - startTime;
                    const remainingTime = Math.max(0, MIN_DURATION - elapsedTime);

                    // Esperar el tiempo restante antes de mostrar resultado
                    setTimeout(() => {
                        clearInterval(progressInterval);
                        progressBar.style.width = '100%';

                        const resultDiv = document.getElementById('modalResult');
                        const progressText = progressContainer.querySelector('p');
                        const progressBarContainer = progressContainer.querySelector('.modal-progress-bar-container');

                        if (data.success) {
                            // Cambiar color del progress bar a verde
                            progressBar.style.background = 'linear-gradient(90deg, #28a745, #20c997)';

                            progressText.innerHTML = `
                                <strong style="color: #28a745;">✅ Backup completado</strong>
                            `;

                            // Agregar información del archivo debajo del progress bar
                            resultDiv.innerHTML = `
                                <div style="color: #6c757d; margin-top: 15px; text-align: center;">
                                    <strong>Archivo:</strong> ${data.filename}<br>
                                    <strong>Tamaño:</strong> ${data.size}
                                </div>
                                <button class="modal-btn modal-btn-confirm" onclick="location.reload()" style="margin-top: 20px;">
                                    Cerrar y Actualizar
                                </button>
                            `;
                        } else {
                            // Cambiar color del progress bar a rojo
                            progressBar.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';

                            progressText.innerHTML = `
                                <strong style="color: #dc3545;">❌ Error al crear backup</strong>
                            `;

                            // Agregar botón de cerrar
                            resultDiv.innerHTML = `
                                <div style="color: #6c757d; margin-top: 15px; text-align: center;">
                                    ${data.message}
                                </div>
                                <button class="modal-btn modal-btn-cancel" onclick="closeModal(); document.getElementById('modalProgressContainer').remove();" style="margin-top: 20px;">
                                    Cerrar
                                </button>
                            `;
                        }
                    }, remainingTime);
                })
                .catch(error => {
                    // Calcular tiempo restante para alcanzar los 6 segundos mínimos
                    const elapsedTime = Date.now() - startTime;
                    const remainingTime = Math.max(0, MIN_DURATION - elapsedTime);

                    setTimeout(() => {
                        clearInterval(progressInterval);
                        progressBar.style.width = '100%';
                        progressBar.style.background = 'linear-gradient(90deg, #dc3545, #c82333)';

                        const resultDiv = document.getElementById('modalResult');
                        const progressText = progressContainer.querySelector('p');

                        progressText.innerHTML = `
                            <strong style="color: #dc3545;">❌ Error de conexión</strong>
                        `;

                        resultDiv.innerHTML = `
                            <div style="color: #6c757d; margin-top: 15px; text-align: center;">
                                ${error.message}
                            </div>
                            <button class="modal-btn modal-btn-cancel" onclick="closeModal(); document.getElementById('modalProgressContainer').remove();" style="margin-top: 20px;">
                                Cerrar
                            </button>
                        `;
                    }, remainingTime);
                });
            });

            return false;
        }

        // Confirmar eliminación de backup
        function confirmDelete(filename) {
            console.log('confirmDelete llamada para:', filename);

            // Encontrar el formulario por ID
            const formId = 'deleteForm_' + filename;
            const formToSubmit = document.getElementById(formId);

            if (!formToSubmit) {
                console.error('No se encontró el formulario con ID:', formId);
                alert('Error: No se puede encontrar el formulario de eliminación');
                return false;
            }

            console.log('Formulario encontrado:', formToSubmit);

            const modal = document.getElementById('confirmModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDetails = document.getElementById('modalDetails');
            const confirmBtn = document.getElementById('modalConfirmBtn');
            const cancelBtn = document.getElementById('modalCancelBtn');

            // Configurar modal
            modalIcon.textContent = '🗑️';
            modalIcon.className = 'modal-icon danger';
            modalTitle.textContent = 'Eliminar Backup';
            modalMessage.textContent = '¿Estás seguro de eliminar este backup?';
            modalDetails.innerHTML = '<strong>Archivo:</strong> ' + filename + '<br><br><strong>⚠️ Esta acción no se puede deshacer.</strong>';
            modalDetails.style.display = 'block';

            confirmBtn.textContent = 'Eliminar';
            confirmBtn.className = 'modal-btn modal-btn-danger';
            cancelBtn.textContent = 'Cancelar';

            // Limpiar event listeners previos clonando los botones
            const newConfirmBtn = confirmBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

            // Evento cancelar
            newCancelBtn.onclick = function() {
                console.log('Cancelando eliminación');
                modal.classList.remove('active');
                document.body.style.overflow = '';
            };

            // Evento confirmar
            newConfirmBtn.onclick = function() {
                console.log('Confirmando eliminación - enviando formulario');
                modal.classList.remove('active');
                document.body.style.overflow = '';

                // Enviar el formulario
                console.log('Ejecutando submit()...');
                formToSubmit.submit();
            };

            // Mostrar modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            console.log('Modal mostrado');
        }
    </script>
</body>
</html>
