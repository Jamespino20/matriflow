<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary') {
    redirect('/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/public/secretary/dashboard.php');
}

if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
    die('CSRF token invalid');
}

$action = $_POST['action'] ?? '';
$patientId = (int)($_POST['patient_id'] ?? 0);

if (!$patientId) {
    redirect('/public/secretary/patients.php');
}

try {
    if ($action === 'save_vitals') {
        $data = [
            'blood_pressure' => $_POST['blood_pressure'] ?? null,
            'heart_rate'     => $_POST['heart_rate'] ?? null,
            'temperature'    => $_POST['temperature'] ?? null,
            'weight_kg'      => $_POST['weight_kg'] ?? null
        ];

        VitalSigns::create($patientId, $data);
        AuditLogger::log((int)$u['user_id'], 'vitals', 'INSERT', $patientId, 'vitals_recored');

        // Notify? Maybe not needed for just vitals, but good practice.
        redirect("/public/secretary/patients.php?success=vitals");
    }
} catch (Throwable $e) {
    die('Secretary error: ' . $e->getMessage());
}
