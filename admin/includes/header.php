<?php
/**
 * Admin Header Component
 * Top bar with page title and action buttons
 */

if (!isset($page_title)) {
    $page_title = 'Panel de Administración';
}

// Get current user info if available
$username = $_SESSION['username'] ?? 'Admin';
?>

<style>
    .admin-topbar {
        background: white;
        border-bottom: 1px solid #e0e0e0;
        padding: 15px 20px;
        margin: -15px -20px 20px -20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .admin-topbar-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .admin-topbar-top {
        display: none;
    }

    .admin-logo {
        display: none;
    }

    /* Show hamburger button in tablet and mobile */
    @media (max-width: 1024px) {
        .admin-topbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
    }

    .admin-topbar h1 {
        font-size: 22px;
        color: #2c3e50;
        margin: 0;
    }

    /* Hamburger Menu Button */
    .hamburger-btn {
        display: none;
        background: #2c3e50;
        border: none;
        color: white;
        padding: 10px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 20px;
        line-height: 1;
        transition: all 0.3s;
    }

    .hamburger-btn:hover {
        background: #34495e;
    }

    .hamburger-btn:active {
        transform: scale(0.95);
    }

    @media (max-width: 1024px) {
        .hamburger-btn {
            display: block;
        }
    }

    .admin-topbar-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .admin-topbar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
        background: #f8f9fa;
        border-radius: 6px;
        font-size: 14px;
        color: #2c3e50;
    }

    .admin-topbar-user .username {
        font-weight: 600;
    }

    .admin-topbar .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        cursor: pointer;
        border: none;
        transition: all 0.3s;
    }

    .admin-topbar .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .admin-topbar .btn-secondary:hover {
        background: #5a6268;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .admin-topbar .btn-logout {
        background: #e74c3c;
        color: white;
    }

    .admin-topbar .btn-logout:hover {
        background: #c0392b;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    }

    /* Tablet layout: compacto y eficiente */
    @media (max-width: 1024px) and (min-width: 769px) {
        .admin-topbar {
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            padding: 18px 25px;
            gap: 15px;
        }

        /* Fila 1 - Parte 1: Logo */
        .admin-topbar-top {
            display: flex;
            align-items: center;
            gap: 15px;
            order: 1;
            flex: 0 0 auto;
        }

        .admin-logo {
            display: block;
            max-height: 60px;
            height: auto;
            max-width: 150px;
            object-fit: contain;
        }

        /* Ocultar hamburger de .admin-topbar-top, lo moveremos a actions */
        .admin-topbar-top .hamburger-btn {
            display: none;
        }

        /* Fila 1 - Parte 2: Botones + Hamburger */
        .admin-topbar-actions {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
            order: 2;
            flex: 1;
            justify-content: flex-end;
        }

        /* Ocultar indicador de usuario */
        .admin-topbar-user {
            display: none;
        }

        .admin-topbar-buttons {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .admin-topbar .btn {
            padding: 10px 16px;
            font-size: 13px;
            border-radius: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .admin-topbar .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
        }

        .admin-topbar .btn-logout {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        /* Mover hamburger después de los botones */
        .hamburger-btn {
            order: 3;
            flex-shrink: 0;
            padding: 10px 14px;
            font-size: 22px;
            border-radius: 6px;
            margin-left: 10px;
        }

        /* Fila 2: Título de página */
        .admin-topbar-left {
            order: 4;
            width: 100%;
            justify-content: center;
            padding: 12px 0 0 0;
            border-top: 1px solid #e9ecef;
        }

        .admin-topbar h1 {
            font-size: 18px;
            text-align: center;
            font-weight: 600;
            color: #2c3e50;
        }
    }

    /* Mobile layout: reorganize completely */
    @media (max-width: 768px) {
        .admin-topbar {
            flex-direction: column;
            align-items: stretch;
            padding: 12px 15px;
            gap: 12px;
        }

        .admin-logo {
            display: block;
            max-height: 40px;
            height: auto;
            max-width: 120px;
            object-fit: contain;
        }

        .admin-topbar-left {
            order: 2;
            width: 100%;
            justify-content: center;
        }

        .admin-topbar h1 {
            font-size: 16px;
            text-align: center;
        }

        /* Bottom row: Actions */
        .admin-topbar-actions {
            order: 3;
            flex-direction: column;
            width: 100%;
            gap: 8px;
        }

        .admin-topbar-user {
            width: 100%;
            justify-content: center;
            padding: 10px;
        }

        /* Buttons row */
        .admin-topbar-buttons {
            display: flex;
            gap: 8px;
            width: 100%;
        }

        .admin-topbar .btn {
            flex: 1;
            text-align: center;
            padding: 12px;
        }

        .hamburger-btn {
            flex-shrink: 0;
        }
    }
</style>

<div class="admin-topbar">
    <!-- Top row: Logo + Burger (mobile only) -->
    <div class="admin-topbar-top">
        <?php if (!empty($site_config['logo']['path'])): ?>
            <img src="<?php echo htmlspecialchars(url($site_config['logo']['path'])); ?>"
                 alt="<?php echo htmlspecialchars($site_config['logo']['alt'] ?? $site_config['site_name']); ?>"
                 class="admin-logo">
        <?php else: ?>
            <div class="admin-logo"></div>
        <?php endif; ?>
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle menu">
            ☰
        </button>
    </div>

    <!-- Page title -->
    <div class="admin-topbar-left">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
    </div>

    <!-- Actions -->
    <div class="admin-topbar-actions">
        <div class="admin-topbar-user">
            <span>👤</span>
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
        </div>
        <div class="admin-topbar-buttons">
            <a href="<?php echo url('/'); ?>" class="btn btn-secondary" target="_blank">🌐 Ver Sitio</a>
            <a href="<?php echo url('/admin/logout.php'); ?>" class="btn btn-logout">🚪 Cerrar Sesión</a>
        </div>
    </div>
</div>
