<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_slots') {
    $doctorId = (int)($_GET['doctor_id'] ?? 0);
    $date = $_GET['date'] ?? '';

    if (!$doctorId || !$date) {
        echo json_encode([]);
        exit;
    }

    try {
        $slots = AppointmentController::getAvailableSlots($doctorId, $date);
        echo json_encode($slots);
    } catch (Throwable $e) {
        error_log('get_slots error: ' . $e->getMessage());
        echo json_encode([]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
