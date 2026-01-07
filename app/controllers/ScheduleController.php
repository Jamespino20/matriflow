<?php

declare(strict_types=1);

final class ScheduleController
{
    /**
     * Get schedule for a doctor
     */
    public static function getDoctorSchedule(int $doctorId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM doctor_schedule WHERE doctor_user_id = :did ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC");
        $stmt->execute([':did' => $doctorId]);
        return $stmt->fetchAll();
    }

    /**
     * Update/Set schedule for a doctor
     */
    public static function updateSchedule(int $doctorId, array $schedule): bool
    {
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            // Delete old schedule or mark as inactive? 
            // For simplicity, let's delete and re-insert for the given days
            $stmt = $db->prepare("DELETE FROM doctor_schedule WHERE doctor_user_id = :did");
            $stmt->execute([':did' => $doctorId]);

            $insert = $db->prepare("INSERT INTO doctor_schedule (doctor_user_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
            foreach ($schedule as $s) {
                $insert->execute([
                    $doctorId,
                    $s['day_of_week'],
                    $s['start_time'],
                    $s['end_time'],
                    $s['is_available'] ?? 1
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
     * Get all schedules (for secretary view)
     */
    public static function getAllSchedules(): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT s.*, u.first_name, u.last_name 
            FROM doctor_schedule s
            JOIN user u ON s.doctor_user_id = u.user_id
            WHERE u.role = 'doctor' AND u.is_active = 1
            ORDER BY u.last_name ASC, FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
