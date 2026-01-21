<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'secretary', 'admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';
    $u = Auth::user();

    // If secretary/admin, they can update other people's schedules
    $targetUserId = (int)($_POST['target_user_id'] ?? $u['user_id']);

    if ($action === 'update') {
        $rawSchedule = $_POST['schedule'] ?? [];
        $processedSchedule = [];

        foreach ($rawSchedule as $day => $data) {
            $processedSchedule[] = [
                'day_of_week' => $day,
                'start_time' => $data['start'] . ':00',
                'end_time' => $data['end'] . ':00',
                'is_available' => isset($data['available']) ? 1 : 0,
                'comments' => trim((string)($data['comments'] ?? ''))
            ];
        }

        if (ScheduleController::updateSchedule($targetUserId, $processedSchedule)) {
            AuditLogger::log((int)$u['user_id'], 'schedule', 'UPDATE', $targetUserId, "Updated schedule for user ID $targetUserId");

            // Redirect based on role if no referer or generic referer
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '';
            if (empty($redirectUrl) || strpos($redirectUrl, 'schedule-handler.php') !== false) {
                if ($u['role'] === 'secretary') {
                    $redirectUrl = base_url('/public/secretary/schedules.php');
                } else {
                    $redirectUrl = base_url('/public/doctor/schedules.php');
                }
            }
            redirect($redirectUrl . (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'success=1');
        } else {
            error_log("Schedule update failed for user " . $targetUserId);
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? base_url('/public/doctor/schedules.php');
            redirect($redirectUrl . (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'error=update_failed');
        }
    }
}
