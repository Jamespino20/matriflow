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
    $_SESSION['error'] = "Invalid CSRF token.";
    redirect($_SERVER['HTTP_REFERER'] ?: '/public/secretary/dashboard.php');
}

$action = $_POST['action'] ?? '';
$userId = (int)($_POST['patient_id'] ?? 0); // patient_id in form is now user_id

if (!$userId) {
    redirect('/public/secretary/patients.php');
}

try {
    if ($action === 'save_vitals') {
        $data = [
            'systolic'  => $_POST['systolic'] ?? null,
            'diastolic' => $_POST['diastolic'] ?? null,
            'heart_rate'     => $_POST['heart_rate'] ?? null,
            'temperature_celsius'    => $_POST['temperature_celsius'] ?? null,
            'weight_kg'      => $_POST['weight_kg'] ?? null
        ];

        VitalSigns::create($userId, $data);
        AuditLogger::log((int)$u['user_id'], 'vital_signs', 'INSERT', $userId, 'vitals_recorded');

        // Notify? Maybe not needed for just vitals, but good practice.
        redirect("/public/secretary/patients.php?success=vitals");
    }
} catch (Throwable $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    redirect($_SERVER['HTTP_REFERER'] ?: '/public/secretary/dashboard.php');
}
