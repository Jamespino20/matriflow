<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'secretary'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?: '/'));
    exit;
}

if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = "Invalid CSRF token.";
    redirect($_SERVER['HTTP_REFERER'] ?: '/');
}

$action = $_POST['action'] ?? '';
$patientId = (int)($_POST['patient_id'] ?? 0);

if (!$patientId) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?: '/'));
    exit;
}

// Determine proper redirect URL based on role
$roleRecordsPage = $u['role'] === 'secretary'
    ? '/public/secretary/records.php?patient_id=' . $patientId
    : '/public/doctor/records.php?patient_id=' . $patientId;
$redirectUrl = base_url($roleRecordsPage);
$separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';

try {
    if ($action === 'save_consultation') {
        $data = [
            'consultation_type' => $_POST['consultation_type'] ?? 'general',
            'subjective'  => $_POST['subjective'] ?? '',
            'objective'   => $_POST['objective'] ?? '',
            'assessment'  => $_POST['assessment'] ?? '',
            'plan'        => $_POST['plan'] ?? '',
            'appointment_id' => !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null
        ];
        $consultationId = ConsultationController::create($patientId, (int)$u['user_id'], $data);
        AuditLogger::log((int)$u['user_id'], 'consultation', 'INSERT', $patientId, 'clinical_consultation_saved');

        // [NEW] Automated Billing Creation using PricingService
        PricingService::chargeConsultation($patientId, (int)$consultationId, $data['consultation_type'], $data['appointment_id']);

        NotificationService::create($patientId, 'general', 'New Consultation: Clinical record saved. A new invoice has been generated for your visit.', $consultationId);

        // [NEW] Auto-schedule next visit if date provided
        if (!empty($_POST['next_visit'])) {
            AppointmentController::autoBook($patientId, $_POST['next_visit'], $data['consultation_type']);
        }

        header('Location: ' . $redirectUrl . $separator . "success=consult");
        exit;
    }

    if ($action === 'save_prescription') {
        $meds = $_POST['medication_name'] ?? '';
        if (is_array($meds)) {
            $meds = implode(', ', $meds);
        }
        $data = [
            'medication_name' => $meds,
            'dosage'          => $_POST['dosage'] ?? '',
            'frequency'       => $_POST['frequency'] ?? '',
            'duration'        => $_POST['duration'] ?? '',
            'instructions'    => $_POST['instructions'] ?? '',
            'consultation_id' => !empty($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : null
        ];
        Prescription::create($patientId, (int)$u['user_id'], $data);
        AuditLogger::log((int)$u['user_id'], 'prescription', 'INSERT', $patientId, 'prescription_issued');

        NotificationService::create($patientId, 'general', 'New Prescription Issued: A new prescription has been issued to you.');

        header('Location: ' . $redirectUrl . $separator . "success=presc");
        exit;
    }
} catch (Throwable $e) {
    $_SESSION['error'] = "Clinical error: " . $e->getMessage();
    $roleRecordsPage = $u['role'] === 'secretary'
        ? '/public/secretary/records.php'
        : '/public/doctor/records.php';
    redirect($_SERVER['HTTP_REFERER'] ?: base_url($roleRecordsPage));
}
