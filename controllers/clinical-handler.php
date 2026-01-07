<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor') {
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/doctor/records.php');
}

if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
    die('CSRF token invalid');
}

$action = $_POST['action'] ?? '';
$patientId = (int)($_POST['patient_id'] ?? 0);

if (!$patientId) {
    redirect('/public/doctor/records.php');
}

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
        // Use Controller instead of Model directly for MVC compliance
        ConsultationController::create($patientId, (int)$u['user_id'], $data);
        AuditLogger::log((int)$u['user_id'], 'consultation', 'INSERT', $patientId, 'clinical_consultation_saved');

        // Notify patient
        $pid_row = db()->prepare("SELECT user_id FROM patient WHERE patient_id = ?");
        $pid_row->execute([$patientId]);
        $row = $pid_row->fetch();
        if ($row) {
            NotificationService::create((int)$row['user_id'], 'New Consultation Record', 'Your doctor has updated your clinical record.', 'info', '/public/patient/appointments.php');
        }

        redirect("/public/doctor/records.php?patient_id=$patientId&success=consult");
    }

    if ($action === 'save_prescription') {
        $data = [
            'medication_name' => $_POST['medication_name'] ?? '',
            'dosage'          => $_POST['dosage'] ?? '',
            'frequency'       => $_POST['frequency'] ?? '',
            'duration'        => $_POST['duration'] ?? '',
            'instructions'    => $_POST['instructions'] ?? '',
            'consultation_id' => !empty($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : null
        ];
        Prescription::create($patientId, (int)$u['user_id'], $data);
        AuditLogger::log((int)$u['user_id'], 'prescription', 'INSERT', $patientId, 'prescription_issued');

        // Notify patient
        $pid_row = db()->prepare("SELECT user_id FROM patient WHERE patient_id = ?");
        $pid_row->execute([$patientId]);
        $row = $pid_row->fetch();
        if ($row) {
            NotificationService::create((int)$row['user_id'], 'New Prescription Issued', 'A new prescription has been issued to you.', 'success', '/public/patient/appointments.php');
        }

        redirect("/public/doctor/records.php?patient_id=$patientId&success=presc");
    }
} catch (Throwable $e) {
    die('Clinical error: ' . $e->getMessage());
}
