<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'secretary'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validate($_POST['csrf_token'] ?? '');

    $testId = (int)($_POST['test_id'] ?? 0);
    $result = trim((string)($_POST['test_result'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'pending'));
    $released = (int)($_POST['released'] ?? 0);

    if ($testId > 0) {
        $success = LaboratoryController::updateResult($testId, $result, $status, $released);

        if ($success) {
            AuditLogger::log(Auth::user()['user_id'], 'laboratory_test', 'UPDATE', $testId, "Result updated and status set to $status. Released: $released");
            $_SESSION['success'] = "Lab result updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update lab result.";
        }
    }
}

redirect($_SERVER['HTTP_REFERER'] ?: '/');
