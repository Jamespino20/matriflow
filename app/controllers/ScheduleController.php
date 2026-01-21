<?php

declare(strict_types=1);

final class ScheduleController
{
    /**
     * Get schedule for a staff member (doctor/secretary)
     */
    public static function getSchedule(int $userId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM staff_schedule WHERE user_id = :uid ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Alias for backward compatibility if needed, but better to update calls
     */
    public static function getDoctorSchedule(int $doctorId): array
    {
        return self::getSchedule($doctorId);
    }

    /**
     * Update/Set schedule for a staff member
     */
    public static function updateSchedule(int $userId, array $schedule): bool
    {
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("DELETE FROM staff_schedule WHERE user_id = :uid");
            $stmt->execute([':uid' => $userId]);

            $insert = $db->prepare("INSERT INTO staff_schedule (user_id, day_of_week, start_time, end_time, is_available, comments) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($schedule as $s) {
                $insert->execute([
                    $userId,
                    $s['day_of_week'],
                    $s['start_time'],
                    $s['end_time'],
                    $s['is_available'] ?? 1,
                    $s['comments'] ?? null
                ]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log("Failed to update schedule: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all staff schedules
     */
    public static function getAllSchedules(?string $role = null, ?string $day = null): array
    {
        $sql = "
            SELECT s.*, u.user_id as u_user_id, u.first_name, u.last_name, u.role
            FROM users u
            LEFT JOIN staff_schedule s ON u.user_id = s.user_id
            WHERE u.is_active = 1 AND u.role IN ('doctor', 'secretary')
        ";
        $params = [];

        if ($role) {
            $sql .= " AND u.role = :role";
            $params[':role'] = $role;
        }

        if ($day) {
            $sql .= " AND s.day_of_week = :day";
            $params[':day'] = $day;
        }

        $sql .= " ORDER BY u.last_name ASC, u.first_name ASC, FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC";

        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Normalize results to ensure user_id is set even if schedule is null
        foreach ($results as &$r) {
            if (empty($r['user_id'])) {
                $r['user_id'] = $r['u_user_id'];
            }
        }
        return $results;
    }
}
