<?php

/**
 * Document File Serve Controller
 * Serves documents from storage directory with access control
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$docId = (int)($_GET['id'] ?? 0);
if (!$docId) {
    http_response_code(404);
    exit('Invalid document ID');
}

$u = Auth::user();

// Get document info from database
$stmt = db()->prepare("SELECT * FROM documents WHERE document_id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Document not found');
}

// Access control: check if user has permission
$hasAccess = false;

if ($u['role'] === 'admin' || $u['role'] === 'doctor') {
    $hasAccess = true;
} elseif ($u['role'] === 'secretary') {
    $hasAccess = true;
} elseif ($u['role'] === 'patient') {
    // Patient can only access their own documents
    if ($u['user_id'] == $doc['user_id']) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    exit('Access denied');
}

// Serve the file
$filePath = BASE_PATH . '/' . $doc['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on server');
}

// Determine content type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
unset($finfo);

// Set headers for download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . basename($doc['file_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
