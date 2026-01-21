<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'secretary'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid CSRF token.";
        $role = Auth::user()['role'];
        $labPage = $role === 'secretary' ? '/public/secretary/lab-tests.php' : '/public/doctor/lab-tests.php';
        redirect(base_url($labPage));
    }

    $action = $_POST['action'] ?? '';
    $testId = (int)($_POST['test_id'] ?? 0);
    $result = trim((string)($_POST['test_result'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'ordered'));
    $released = (int)($_POST['released'] ?? 0);

    if ($action === 'create') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $testType = trim((string)($_POST['test_type'] ?? ''));

        if ($patientId > 0 && !empty($testType)) {
            $newId = LaboratoryController::create($patientId, $testType);
            if ($newId) {
                AuditLogger::log(Auth::user()['user_id'], 'laboratory_test', 'CREATE', $newId, "New lab test order ($testType) created.");

                // Automated Billing for Lab Order
                PricingService::chargeLabTest($patientId, $testType);

                error_log("Lab Handler: Created new test #$newId");
                $_SESSION['success'] = "Lab test ordered successfully.";
            } else {
                error_log("Lab Handler: Failed to create test (LaboratoryController::create returned false)");
                $_SESSION['error'] = "Failed to create lab test order.";
            }
        } else {
            error_log("Lab Handler: Invalid input - Patient: $patientId, Type: $testType");
            $_SESSION['error'] = "Invalid patient or test type.";
        }
    } elseif ($testId > 0) {
        error_log("Lab Handler: Processing update for Test #$testId");
        $u = Auth::user(); // Fix: Define $u for file upload
        $filePath = null;

        // Handle file upload if present
        if (isset($_FILES['lab_file']) && $_FILES['lab_file']['error'] === UPLOAD_ERR_OK) {
            try {
                // Fetch test to get patient ID
                $test = LaboratoryController::findById($testId);
                if ($test) {
                    $uploadResult = FileService::saveDocument(
                        (int)$u['user_id'],
                        $_FILES['lab_file'],
                        'lab_result',
                        (int)$test['user_id'],
                        "Laboratory result for: " . $test['test_name']
                    );
                    $filePath = $uploadResult['file_path'];
                }
            } catch (Throwable $e) {
                error_log("Lab file upload error: " . $e->getMessage());
            }
        }

        // Force released status if checked
        if ($released) {
            $status = 'released';
        }

        $success = LaboratoryController::updateResult($testId, $result, $status, $filePath);

        if ($success) {
            AuditLogger::log(Auth::user()['user_id'], 'laboratory_test', 'UPDATE', $testId, "Result updated and status set to $status.");
            $_SESSION['success'] = "Lab result updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update lab result.";
        }
    }
}

$role = Auth::user()['role'];
$labPage = $role === 'secretary' ? '/public/secretary/lab-tests.php' : '/public/doctor/lab-tests.php';
redirect($_SERVER['HTTP_REFERER'] ?: base_url($labPage));
