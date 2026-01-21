<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($action === 'upload') {
        if (empty($_FILES['file'])) {
            throw new Exception('No file selected.');
        }

        $file = $_FILES['file'];
        $category = $_POST['category'] ?? 'other';
        $description = trim((string)($_POST['description'] ?? ''));

        // userId can be a single ID (from doctor portal) or an array (shared_with from patient portal)
        $userId = $_POST['user_id'] ?? null;
        if (isset($_POST['shared_with']) && is_array($_POST['shared_with'])) {
            $userId = $_POST['shared_with'];
        }

        // For patients, ensure they are at least one of the targets (handled in FileService now)
        // but we should still enforce that if they provide a user_id, it is relevant.

        $result = FileService::saveDocument((int)$u['user_id'], $file, $category, $userId, $description);

        AuditLogger::log((int)$u['user_id'], 'documents', 'UPLOAD', $result['document_ids'][0] ?? 0, 'file_uploaded');

        if ($isAjax) {
            echo json_encode(['ok' => true, 'message' => 'Document uploaded successfully.', 'data' => $result]);
            exit;
        } else {
            $_SESSION['flash_success'] = 'Document uploaded successfully.';
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['document_id'] ?? 0);
        if (!$id) throw new Exception('Invalid document ID.');

        // Check permission
        $stmt = db()->prepare("SELECT * FROM documents WHERE document_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) throw new Exception('Document not found.');

        // Permission: Uploader can delete, or Admin can delete.
        // For patient/doctor specific logic, we check uploader_user_id.
        if ($doc['uploader_user_id'] != $u['user_id'] && $u['role'] !== 'admin') {
            throw new Exception('Access denied.');
        }

        db()->prepare("UPDATE documents SET deleted_at = NOW() WHERE document_id = ?")->execute([$id]);
        AuditLogger::log((int)$u['user_id'], 'documents', 'DELETE', $id, 'file_deleted');

        if ($isAjax) {
            echo json_encode(['ok' => true, 'message' => 'Document deleted.']);
            exit;
        } else {
            $_SESSION['flash_success'] = 'Document deleted.';
            redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    } else {
        $_SESSION['flash_error'] = $e->getMessage();
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
