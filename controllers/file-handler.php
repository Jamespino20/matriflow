<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'upload_document') {
        if (empty($_FILES['document'])) {
            throw new Exception('No file uploaded.');
        }

        $file = $_FILES['document'];
        $category = $_POST['category'] ?? 'other';
        $patientId = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
        $description = trim((string)($_POST['description'] ?? ''));

        // Optimization: For patients, we might want to ensure they can only upload for themselves
        if ($u['role'] === 'patient') {
            $patient = db()->prepare("SELECT patient_id FROM patient WHERE user_id = ?");
            $patient->execute([$u['user_id']]);
            $pRow = $patient->fetch();
            if (!$pRow) throw new Exception('Patient profile not found.');
            $patientId = (int)$pRow['patient_id'];
        }

        // Use FileService to save
        // We'll add saveDocument to FileService
        $result = FileService::saveDocument((int)$u['user_id'], $file, $category, $patientId, $description);

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'File uploaded successfully.', 'document' => $result]);
        exit;
    }

    if ($action === 'delete_document') {
        $id = (int)($_POST['document_id'] ?? 0);
        if (!$id) throw new Exception('Invalid document ID.');

        // Check ownership/permission
        $stmt = db()->prepare("SELECT * FROM documents WHERE document_id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) throw new Exception('Document not found.');

        // Only uploader or admin can delete (or secretary for patient docs)
        if ($doc['uploader_user_id'] != $u['user_id'] && $u['role'] !== 'admin') {
            throw new Exception('Permission denied.');
        }

        db()->prepare("UPDATE documents SET deleted_at = NOW() WHERE document_id = ?")->execute([$id]);

        AuditLogger::log((int)$u['user_id'], 'documents', 'DELETE', $id, 'document_deleted');

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'Document deleted.']);
        exit;
    }

    throw new Exception('Unknown action.');
} catch (Throwable $e) {
    if ($isAjax) {
        http_response_code(400);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    http_response_code(400);
    echo "Error: " . $e->getMessage();
}
