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
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .admin-topbar h1 {
        font-size: 22px;
        color: #2c3e50;
        margin: 0;
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

    @media (max-width: 768px) {
        .admin-topbar {
            flex-direction: column;
            gap: 15px;
            align-items: stretch;
        }

        .admin-topbar-actions {
            flex-direction: column;
        }

        .admin-topbar-user {
            justify-content: center;
        }
    }
</style>

<div class="admin-topbar">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>
    <div class="admin-topbar-actions">
        <div class="admin-topbar-user">
            <span>👤</span>
            <span class="username"><?php echo htmlspecialchars($username); ?></span>
        </div>
        <a href="/" class="btn btn-secondary" target="_blank">🌐 Ver Sitio</a>
        <a href="/admin/logout.php" class="btn btn-logout">🚪 Cerrar Sesión</a>
    </div>
</div>
