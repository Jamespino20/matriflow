<?php

/**
 * Avatar Image Serve Controller
 * Serves avatar images from storage directory securely
 */
require_once __DIR__ . '/../../bootstrap.php';

$userId = (int)($_GET['uid'] ?? 0);
if (!$userId) {
    http_response_code(404);
    exit;
}

// Check if avatar exists in storage
$avatarPath = BASE_PATH . '/storage/uploads/avatars/' . $userId . '.png';
$defaultAvatar = BASE_PATH . '/public/assets/images/default-avatar.png';

if (file_exists($avatarPath)) {
    $file = $avatarPath;
} elseif (file_exists($defaultAvatar)) {
    $file = $defaultAvatar;
} else {
    // Generate a simple placeholder
    http_response_code(404);
    exit;
}

// Set proper headers
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($file));

readfile($file);
exit;
