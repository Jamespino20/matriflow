<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || Auth::user()['role'] !== 'doctor') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::validate($_POST['csrf_token'] ?? '');

    $action = $_POST['action'] ?? '';
    $u = Auth::user();

    if ($action === 'update') {
        $rawSchedule = $_POST['schedule'] ?? [];
        $processedSchedule = [];

        foreach ($rawSchedule as $day => $data) {
            $processedSchedule[] = [
                'day_of_week' => $day,
                'start_time' => $data['start'] . ':00',
                'end_time' => $data['end'] . ':00',
                'is_available' => isset($data['available']) ? 1 : 0
            ];
        }

        if (ScheduleController::updateSchedule((int)$u['user_id'], $processedSchedule)) {
            AuditLogger::log((int)$u['user_id'], 'doctor_schedule', 'UPDATE', (int)$u['user_id'], "Updated weekly schedule");
            header('Location: ' . base_url('/public/doctor/schedules.php?success=1'));
        } else {
            header('Location: ' . base_url('/public/doctor/schedules.php?error=update_failed'));
        }
    }
}
