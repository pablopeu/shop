<?php
/**
 * Admin Logout
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Destroy session
destroy_admin_session();

// Redirect to login
redirect(url('/admin/login.php?logged_out=1'));
