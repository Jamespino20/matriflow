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
      (patient_id, doctor_user_id, appointment_purpose, appointment_status, appointment_date)
      VALUES
      (:pid, :duid, :purpose, 'scheduled', :dt)";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':pid' => $patientId,
            ':duid' => $doctorUserId,
            ':purpose' => $purpose,
            ':dt' => $dateTime,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function listByPatient(int $patientId, int $limit = 50): array
    {
        $stmt = db()->prepare("SELECT * FROM appointment
                           WHERE patient_id = :pid AND deleted_at IS NULL
                           ORDER BY appointment_date DESC
                           LIMIT " . (int) $limit);
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll() ?: [];
    }
}
