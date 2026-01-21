<?php

/**
 * Heartbeat Handler - Session Keep-Alive and Validation
 * Pings every 60 seconds from client to maintain session and detect logout.
 */
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

// Check if user is still authenticated
if (!Auth::check()) {
    echo json_encode(['ok' => false, 'reason' => 'not_authenticated']);
    exit;
}

// Update last seen time
$_SESSION['_last_seen'] = time();

// Return success
echo json_encode(['ok' => true, 'timestamp' => time()]);
