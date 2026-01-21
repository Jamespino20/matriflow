<?php
require_once __DIR__ . '/../bootstrap.php';

// Prevent browser caching of authenticated pages after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Proxies.

// Only logout if confirmed or if it's a direct logout request (backward compatibility)
if (!empty($_GET['confirm']) || Auth::check()) {
    if ($user = Auth::user()) {
        AuditLogger::log((int)$user['user_id'], 'users', 'LOGOUT', (int)$user['user_id'], "User logged out");
    }
    Auth::logout();
    SessionManager::regenerate(); // regenerate for new guest session
}

redirect(base_url('/?logged_out=1'));
