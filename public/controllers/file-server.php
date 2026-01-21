<?php

/**
 * File Server Controller
 * 
 * Securely serves files from the storage directory.
 * Files are stored outside the public directory for security.
 * 
 * Usage: file-server.php?id=DOCUMENT_ID
 */

require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    die('Invalid document ID.');
}

// Fetch document from database
$stmt = db()->prepare("SELECT * FROM documents WHERE document_id = ? AND deleted_at IS NULL");
$stmt->execute([$documentId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die('Document not found.');
}

// Access control: 
// - Admins can see all
// - Doctors can see their patients' files
// - Secretaries can see all patient files
// - Patients can only see their own files
$u = Auth::user();
$allowed = false;

switch ($u['role']) {
    case 'admin':
    case 'secretary':
        $allowed = true;
        break;
    case 'doctor':
        // Doctor can see files of patients they've had appointments with
        $patientId = $doc['user_id'];
        if ($patientId) {
            $check = db()->prepare("SELECT 1 FROM appointment WHERE doctor_user_id = ? AND user_id = ? LIMIT 1");
            $check->execute([$u['user_id'], $patientId]);
            $allowed = $check->fetch() !== false;
        } else {
            // System files - doctors can view
            $allowed = true;
        }
        break;
    case 'patient':
        // Patients can only see their own files
        $allowed = ($doc['user_id'] === $u['user_id']);
        break;
}

if (!$allowed) {
    http_response_code(403);
    die('Access denied.');
}

// Resolve file path - prioritize standardized location
$filePath = $doc['file_path'];
$possiblePaths = [
    STORAGE_PATH . '/uploads/documents/' . basename($filePath),
    BASE_PATH . '/' . ltrim($filePath, '/'), // Fallback for absolute-ish paths in DB
];

$actualPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path) && is_file($path)) {
        $actualPath = $path;
        break;
    }
}

if (!$actualPath) {
    http_response_code(404);
    error_log("File not found for document ID $documentId. Tried paths: " . implode(', ', $possiblePaths));
    die('File not found on server. Path: ' . htmlspecialchars($filePath));
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $actualPath);
finfo_close($finfo);

// Get file size
$fileSize = filesize($actualPath);

// Set headers for download/view
$filename = $doc['file_name'] ?: basename($actualPath);
$action = $_GET['action'] ?? 'view';

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);

if ($action === 'download') {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    // Inline view for PDFs and images
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

// Cache headers
header('Cache-Control: private, max-age=3600');
header('Pragma: cache');

// Output file
readfile($actualPath);
exit;
