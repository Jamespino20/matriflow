<?php

declare(strict_types=1);

final class Appointment
{
    public static function create(int $patientId, string $purpose, string $dateTime, ?int $doctorUserId = null): int
    {
        // Enforce today or future date (Cybersecurity/Policy Requirement)
        $bookingTime = strtotime($dateTime);
        $today = strtotime('today');
        if ($bookingTime < $today) {
            throw new Exception('Appointments cannot be scheduled in the past. Please select a current or future date.');
        }

        $sql = "INSERT INTO appointment
      (user_id, doctor_user_id, appointment_purpose, appointment_status, appointment_date)
      VALUES
      (:uid, :duid, :purpose, 'scheduled', :dt)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':uid' => $patientId,
            ':duid' => $doctorUserId,
            ':purpose' => $purpose,
            ':dt' => $dateTime,
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }

    public static function listByPatient(int $patientId, int $limit = 50): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM appointment
                           WHERE user_id = :uid AND deleted_at IS NULL
                           ORDER BY appointment_date DESC
                           LIMIT " . (int) $limit);
        $stmt->execute([':uid' => $patientId]);
        return $stmt->fetchAll() ?: [];
    }
}
