<?php
/**
 * Admin Sidebar Component with Submenu
 */

if (!isset($site_config)) {
    $site_config = read_json(__DIR__ . '/../../config/site.json');
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Sidebar Styles */
    .sidebar {
        background: #2c3e50;
        color: white;
        padding: 20px 0;
        width: 260px;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        padding-bottom: 60px;
        z-index: 1000;
        transition: transform 0.3s ease;
        -webkit-overflow-scrolling: touch;
    }

    .sidebar::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(0,0,0,0.2);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 4px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.5);
    }

    /* Mobile Sidebar */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }
    }

    .sidebar-header {
        padding: 0 20px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin-bottom: 20px;
    }

    .sidebar-header h2 {
        font-size: 20px;
        margin-bottom: 5px;
    }

    .sidebar-header p {
        font-size: 13px;
        opacity: 0.7;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li {
        margin-bottom: 0;
    }

    .sidebar-menu a,
    .sidebar-menu .menu-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        cursor: pointer;
    }

    .sidebar-menu a:hover,
    .sidebar-menu .menu-item:hover,
    .sidebar-menu a.active {
        background: rgba(255,255,255,0.1);
        color: white;
    }

    .sidebar-menu a.active {
        border-left: 3px solid #3498db;
    }

    /* Submenu Styles */
    .submenu {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        background: rgba(0,0,0,0.2);
    }

    .submenu.open {
        max-height: 500px;
    }

    .submenu li a {
        padding: 10px 20px 10px 45px;
        font-size: 14px;
        border-left: 3px solid transparent;
    }

    .submenu li a:hover {
        background: rgba(255,255,255,0.05);
        border-left-color: #3498db;
    }

    .submenu li a.active {
        background: rgba(255,255,255,0.1);
        border-left-color: #3498db;
        color: white;
    }

    .menu-arrow {
        transition: transform 0.3s;
        font-size: 12px;
    }

    .menu-arrow.rotated {
        transform: rotate(90deg);
    }

    /* Nested Submenu Styles */
    .submenu .submenu {
        background: rgba(0,0,0,0.3);
        max-height: 0;
    }

    .submenu .submenu.open {
        max-height: 400px;
    }

    .submenu .submenu li a {
        padding-left: 65px;
        font-size: 13px;
    }

    .submenu .menu-item {
        padding: 10px 20px 10px 45px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: rgba(255,255,255,0.8);
    }

    .submenu .menu-item:hover {
        background: rgba(255,255,255,0.05);
        color: white;
    }

    /* Sidebar Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    @media (max-width: 1024px) {
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
    }
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <h2><?php echo htmlspecialchars($site_config['site_name']); ?></h2>
        <p>Panel de Administración</p>
    </div>

    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li>
            <a href="<?php echo url('/admin/index.php'); ?>" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                📊 Dashboard
            </a>
        </li>

        <!-- Productos -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('productos', '<?php echo url('/admin/productos-listado.php'); ?>')">
                <span>📦 Productos</span>
                <span class="menu-arrow" id="arrow-productos">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['productos-listado.php', 'productos-nuevo.php', 'productos-editar.php', 'productos-archivados.php']) ? 'open' : ''; ?>"
                id="submenu-productos">
                <li>
                    <a href="<?php echo url('/admin/productos-listado.php'); ?>"
                       class="<?php echo $current_page === 'productos-listado.php' ? 'active' : ''; ?>">
                        📋 Listado de Productos
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/productos-nuevo.php'); ?>"
                       class="<?php echo $current_page === 'productos-nuevo.php' ? 'active' : ''; ?>">
                        ➕ Agregar Producto
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/productos-archivados.php'); ?>"
                       class="<?php echo $current_page === 'productos-archivados.php' ? 'active' : ''; ?>">
                        📦 Productos Archivados
                    </a>
                </li>
            </ul>
        </li>

        <!-- Ventas -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('ventas', '<?php echo url('/admin/ventas.php'); ?>')">
                <span>💰 Ventas</span>
                <span class="menu-arrow" id="arrow-ventas">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['ventas.php', 'archivo-ventas.php', 'reviews-listado.php']) ? 'open' : ''; ?>"
                id="submenu-ventas">
                <li>
                    <a href="<?php echo url('/admin/ventas.php'); ?>"
                       class="<?php echo $current_page === 'ventas.php' ? 'active' : ''; ?>">
                        📋 Gestión de Ventas
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/archivo-ventas.php'); ?>"
                       class="<?php echo $current_page === 'archivo-ventas.php' ? 'active' : ''; ?>">
                        📦 Archivo de Ventas
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/reviews-listado.php'); ?>"
                       class="<?php echo $current_page === 'reviews-listado.php' ? 'active' : ''; ?>">
                        ⭐ Reviews
                    </a>
                </li>
            </ul>
        </li>

        <!-- Envíos -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('envios', '<?php echo url('/admin/envios-pendientes.php'); ?>')">
                <span>📦 Envíos</span>
                <span class="menu-arrow" id="arrow-envios">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['envios-pendientes.php', 'envios-archivo.php']) ? 'open' : ''; ?>"
                id="submenu-envios">
                <li>
                    <a href="<?php echo url('/admin/envios-pendientes.php'); ?>"
                       class="<?php echo $current_page === 'envios-pendientes.php' ? 'active' : ''; ?>">
                        📋 Gestión de envíos
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/envios-archivo.php'); ?>"
                       class="<?php echo $current_page === 'envios-archivo.php' ? 'active' : ''; ?>">
                        📦 Archivo
                    </a>
                </li>
            </ul>
        </li>

        <!-- Promociones y Cupones -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('promociones-cupones')">
                <span>🎯 Promociones y Cupones</span>
                <span class="menu-arrow" id="arrow-promociones-cupones">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['promociones-listado.php', 'promociones-nuevo.php', 'promociones-editar.php', 'promociones-archivados.php', 'cupones-listado.php', 'cupones-nuevo.php', 'cupones-editar.php', 'cupones-archivados.php']) ? 'open' : ''; ?>"
                id="submenu-promociones-cupones">
                <li><a href="<?php echo url('/admin/promociones-listado.php'); ?>" <?php echo $current_page === 'promociones-listado.php' ? 'class="active"' : ''; ?>>📋 Listado de Promociones</a></li>
                <li><a href="<?php echo url('/admin/promociones-nuevo.php'); ?>" <?php echo $current_page === 'promociones-nuevo.php' ? 'class="active"' : ''; ?>>➕ Nueva Promoción</a></li>
                <li>
                    <a href="<?php echo url('/admin/promociones-archivados.php'); ?>"
                       class="<?php echo $current_page === 'promociones-archivados.php' ? 'active' : ''; ?>">
                        📦 Promociones Archivadas
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/cupones-listado.php'); ?>"
                       class="<?php echo $current_page === 'cupones-listado.php' ? 'active' : ''; ?>">
                        🎫 Listado de Cupones
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/cupones-nuevo.php'); ?>"
                       class="<?php echo $current_page === 'cupones-nuevo.php' ? 'active' : ''; ?>">
                        ➕ Nuevo Cupón
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/cupones-archivados.php'); ?>"
                       class="<?php echo $current_page === 'cupones-archivados.php' ? 'active' : ''; ?>">
                        📦 Cupones Archivados
                    </a>
                </li>
            </ul>
        </li>

        <!-- Email y Notificaciones -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('notificaciones')">
                <span>🔔 Email y Notificaciones</span>
                <span class="menu-arrow" id="arrow-notificaciones">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['notificaciones.php', 'secretos-path.php']) ? 'open' : ''; ?>"
                id="submenu-notificaciones">
                <li>
                    <a href="<?php echo url('/admin/notificaciones.php'); ?>"
                       class="<?php echo $current_page === 'notificaciones.php' ? 'active' : ''; ?>">
                        ⚙️ Configuración
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/secretos-path.php'); ?>"
                       class="<?php echo $current_page === 'secretos-path.php' ? 'active' : ''; ?>">
                        🔐 Ubicación de Secretos
                    </a>
                </li>
            </ul>
        </li>

        <!-- Medios de Pago -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('payment')">
                <span>💳 Medios de Pago</span>
                <span class="menu-arrow" id="arrow-payment">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['config-payment.php', 'payment-secretos-path.php', 'reprocesar-pago-mp.php']) ? 'open' : ''; ?>"
                id="submenu-payment">
                <li>
                    <a href="<?php echo url('/admin/config-payment.php'); ?>"
                       class="<?php echo $current_page === 'config-payment.php' ? 'active' : ''; ?>">
                        ⚙️ Configuración
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/payment-secretos-path.php'); ?>"
                       class="<?php echo $current_page === 'payment-secretos-path.php' ? 'active' : ''; ?>">
                        🔐 Ubicación de Secretos
                    </a>
                </li>
                <li>
                    <a href="<?php echo url('/admin/reprocesar-pago-mp.php'); ?>"
                       class="<?php echo $current_page === 'reprocesar-pago-mp.php' ? 'active' : ''; ?>">
                        🔄 Reprocesar Pagos
                    </a>
                </li>
            </ul>
        </li>

        <!-- Tracking & Analytics -->
        <li>
            <a href="<?php echo url('/admin/config-analytics.php'); ?>"
               class="<?php echo $current_page === 'config-analytics.php' ? 'active' : ''; ?>">
                📊 Tracking & Analytics
            </a>
        </li>

        <!-- Configuración -->
        <li>
            <div class="menu-item" onclick="toggleSubmenu('configuracion')">
                <span>⚙️ Configuración</span>
                <span class="menu-arrow" id="arrow-configuracion">▶</span>
            </div>
            <ul class="submenu <?php echo in_array($current_page, ['config-sistema.php', 'config-sitio.php', 'config-moneda.php', 'config-mantenimiento.php', 'config-backup.php', 'config-hero.php', 'config-carrusel.php', 'config-productos-heading.php', 'config-dashboard.php', 'config-themes.php', 'config-footer.php']) ? 'open' : ''; ?>"
                id="submenu-configuracion">

                <!-- Configuración del Sistema (Subsección) -->
                <li>
                    <div class="menu-item" onclick="toggleSubmenu('config-sistema')">
                        <span>⚙️ Configuración del Sistema</span>
                        <span class="menu-arrow" id="arrow-config-sistema">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['config-sistema.php', 'config-sitio.php', 'config-moneda.php', 'config-mantenimiento.php', 'config-backup.php']) ? 'open' : ''; ?>"
                        id="submenu-config-sistema">
                        <li><a href="<?php echo url('/admin/config-sistema.php'); ?>" <?php echo $current_page === 'config-sistema.php' ? 'class="active"' : ''; ?>>🔐 Credenciales del Sistema</a></li>
                        <li><a href="<?php echo url('/admin/config-sitio.php'); ?>" <?php echo $current_page === 'config-sitio.php' ? 'class="active"' : ''; ?>>📄 Información del Sitio</a></li>
                        <li><a href="<?php echo url('/admin/config-moneda.php'); ?>" <?php echo $current_page === 'config-moneda.php' ? 'class="active"' : ''; ?>>💱 Moneda y Cambio</a></li>
                        <li><a href="<?php echo url('/admin/config-mantenimiento.php'); ?>" <?php echo $current_page === 'config-mantenimiento.php' ? 'class="active"' : ''; ?>>🚧 Mantenimiento</a></li>
                        <li><a href="<?php echo url('/admin/config-backup.php'); ?>" <?php echo $current_page === 'config-backup.php' ? 'class="active"' : ''; ?>>💾 Backup</a></li>
                    </ul>
                </li>

                <!-- Ajustes Visuales (Submenu anidado) -->
                <li>
                    <div class="menu-item" onclick="toggleSubmenu('ajustes-visuales')">
                        <span>🎨 Ajustes Visuales</span>
                        <span class="menu-arrow" id="arrow-ajustes-visuales">▶</span>
                    </div>
                    <ul class="submenu <?php echo in_array($current_page, ['config-themes.php', 'config-hero.php', 'config-carrusel.php', 'config-footer.php', 'config-dashboard.php', 'config-productos-heading.php']) ? 'open' : ''; ?>"
                        id="submenu-ajustes-visuales">
                        <li><a href="<?php echo url('/admin/config-themes.php'); ?>" <?php echo $current_page === 'config-themes.php' ? 'class="active"' : ''; ?>>🎨 Themes</a></li>
                        <li><a href="<?php echo url('/admin/config-hero.php'); ?>" <?php echo $current_page === 'config-hero.php' ? 'class="active"' : ''; ?>>🖼️ Hero Principal</a></li>
                        <li><a href="<?php echo url('/admin/config-carrusel.php'); ?>" <?php echo $current_page === 'config-carrusel.php' ? 'class="active"' : ''; ?>>🎠 Carrusel</a></li>
                        <li><a href="<?php echo url('/admin/config-footer.php'); ?>" <?php echo $current_page === 'config-footer.php' ? 'class="active"' : ''; ?>>🦶 Footer</a></li>
                        <li><a href="<?php echo url('/admin/config-dashboard.php'); ?>" <?php echo $current_page === 'config-dashboard.php' ? 'class="active"' : ''; ?>>📊 Dashboard</a></li>
                        <li><a href="<?php echo url('/admin/config-productos-heading.php'); ?>" <?php echo $current_page === 'config-productos-heading.php' ? 'class="active"' : ''; ?>>📝 Encabezado Productos</a></li>
                    </ul>
                </li>
            </ul>
        </li>
    </ul>
</aside>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    // Toggle Sidebar for Mobile
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');

        // Prevent body scroll when sidebar is open
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    // Close sidebar when clicking overlay
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }
    });

    function toggleSubmenu(menuId, redirectUrl) {
        const submenu = document.getElementById('submenu-' + menuId);
        const arrow = document.getElementById('arrow-' + menuId);

        if (submenu.classList.contains('open')) {
            submenu.classList.remove('open');
            arrow.classList.remove('rotated');
        } else {
            submenu.classList.add('open');
            arrow.classList.add('rotated');
        }

        // Redirect if URL is provided
        if (redirectUrl) {
            window.location.href = redirectUrl;
        }
    }

    // Auto-open submenu if on a submenu page
    document.addEventListener('DOMContentLoaded', function() {
        const openSubmenus = document.querySelectorAll('.submenu.open');
        openSubmenus.forEach(submenu => {
            const menuId = submenu.id.replace('submenu-', '');
            const arrow = document.getElementById('arrow-' + menuId);
            if (arrow) {
                arrow.classList.add('rotated');
            }
        });
    });
</script>

<!-- Session Monitor - Auto-redirects if session expires -->
<script>
    // Define BASE_PATH for JavaScript to use
    window.BASE_PATH = '<?php echo BASE_PATH; ?>';
</script>
<script src="<?php echo url('/admin/includes/session-monitor.js'); ?>"></script>
