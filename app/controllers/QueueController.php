<?php

declare(strict_types=1);

final class QueueController
{
    /**
     * Get the current queue for a doctor (or all if doctorId is 0)
     */
    public static function getQueue(int $doctorId = 0): array
    {
        $sql = "
            SELECT q.*, p.identification_number, u.first_name, u.last_name, a.appointment_purpose,
                   doc_u.first_name as doctor_first, doc_u.last_name as doctor_last
            FROM patient_queue q
            JOIN patient p ON q.patient_id = p.patient_id
            JOIN user u ON p.user_id = u.user_id
            JOIN appointment a ON q.appointment_id = a.appointment_id
            LEFT JOIN user doc_u ON q.doctor_user_id = doc_u.user_id
            WHERE q.status IN ('waiting', 'in_consultation')
        ";

        if ($doctorId > 0) {
            $sql .= " AND q.doctor_user_id = :did";
        }

        $sql .= " ORDER BY q.position ASC";

        $stmt = db()->prepare($sql);
        if ($doctorId > 0) {
            $stmt->execute([':did' => $doctorId]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    /**
     * Check-in a patient (move from appointment to queue)
     */
    public static function checkIn(int $appointmentId): bool
    {
        $db = db();
        try {
            $db->beginTransaction();

            $apt = $db->prepare("SELECT patient_id, doctor_user_id FROM appointment WHERE appointment_id = ?");
            $apt->execute([$appointmentId]);
            $row = $apt->fetch();
            if (!$row) throw new Exception("Appointment not found");

            // Get current max position for the doctor today
            $posStmt = $db->prepare("SELECT COALESCE(MAX(position), 0) FROM patient_queue WHERE DATE(checked_in_at) = CURDATE()");
            $posStmt->execute();
            $nextPos = (int)$posStmt->fetchColumn() + 1;

            $stmt = $db->prepare("INSERT INTO patient_queue (appointment_id, patient_id, doctor_user_id, position, status) VALUES (?, ?, ?, ?, 'waiting')");
            $stmt->execute([$appointmentId, $row['patient_id'], $row['doctor_user_id'], $nextPos]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log("Check-in failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Advance queue status
     */
    public static function updateStatus(int $queueId, string $status): bool
    {
        $db = db();
        $params = [':id' => $queueId, ':status' => $status];
        $timeCol = '';

        if ($status === 'in_consultation') $timeCol = ", started_at = NOW()";
        if ($status === 'finished') $timeCol = ", finished_at = NOW()";

        $stmt = $db->prepare("UPDATE patient_queue SET status = :status $timeCol WHERE queue_id = :id");
        return $stmt->execute($params);
    }
}
