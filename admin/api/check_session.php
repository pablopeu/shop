<?php
/**
 * API: Check Session Status
 * Verifies if admin session is still active
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!is_admin()) {
    http_response_code(401);
    echo json_encode([
        'valid' => false,
        'reason' => 'not_authenticated'
    ]);
    exit;
}

// Check session timeout (2 hours)
if (!check_session_timeout(7200)) {
    http_response_code(401);
    echo json_encode([
        'valid' => false,
        'reason' => 'session_expired'
    ]);
    exit;
}

// Session is valid
echo json_encode([
    'valid' => true,
    'user' => [
        'username' => $_SESSION['username'] ?? 'Unknown',
        'role' => $_SESSION['role'] ?? 'unknown'
    ]
]);
