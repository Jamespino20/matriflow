<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check()) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validate($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';
    $u = Auth::user();

    if ($action === 'checkin' && $u['role'] === 'secretary') {
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        if (QueueController::checkIn($appointmentId)) {
            AuditLogger::log((int)$u['user_id'], 'patient_queue', 'CREATE', $appointmentId, "Checked in patient for appointment #$appointmentId");
            header('Location: ' . base_url('/public/secretary/queues.php?success=checked_in'));
        } else {
            header('Location: ' . base_url('/public/secretary/queues.php?error=checkin_failed'));
        }
    }

    if ($action === 'start' && $u['role'] === 'doctor') {
        $queueId = (int)($_POST['queue_id'] ?? 0);
        if (QueueController::updateStatus($queueId, 'in_consultation')) {
            AuditLogger::log((int)$u['user_id'], 'patient_queue', 'UPDATE', $queueId, "Started consultation for queue item #$queueId");
            header('Location: ' . base_url('/public/doctor/queues.php?success=started'));
        } else {
            header('Location: ' . base_url('/public/doctor/queues.php?error=update_failed'));
        }
    }

    if ($action === 'finish' && $u['role'] === 'doctor') {
        $queue_id = (int)($_POST['queue_id'] ?? 0);
        if (QueueController::updateStatus($queue_id, 'finished')) {
            AuditLogger::log((int)$u['user_id'], 'patient_queue', 'UPDATE', $queue_id, "Finished consultation for queue item #$queue_id");
            header('Location: ' . base_url('/public/doctor/queues.php?success=finished'));
        } else {
            header('Location: ' . base_url('/public/doctor/queues.php?error=update_failed'));
        }
    }
}
