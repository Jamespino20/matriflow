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
            SELECT q.*, u.identification_number, u.first_name, u.last_name, a.appointment_purpose,
                   doc_u.first_name as doctor_first, doc_u.last_name as doctor_last
            FROM patient_queue q
            JOIN users u ON q.user_id = u.user_id
            JOIN appointment a ON q.appointment_id = a.appointment_id
            LEFT JOIN users doc_u ON q.doctor_user_id = doc_u.user_id
            WHERE q.status IN ('waiting', 'in_consultation')
        ";

        if ($doctorId > 0) {
            $sql .= " AND q.doctor_user_id = :did";
        }

        $sql .= " ORDER BY q.position ASC";

        $stmt = Database::getInstance()->prepare($sql);
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
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $apt = $db->prepare("SELECT user_id, doctor_user_id FROM appointment WHERE appointment_id = ?");
            $apt->execute([$appointmentId]);
            $row = $apt->fetch();
            if (!$row) throw new Exception("Appointment not found");

            // Get current max position for the doctor today
            $posStmt = $db->prepare("SELECT COALESCE(MAX(position), 0) FROM patient_queue WHERE DATE(checked_in_at) = CURDATE()");
            $posStmt->execute();
            $nextPos = (int)$posStmt->fetchColumn() + 1;

            $stmt = $db->prepare("INSERT INTO patient_queue (appointment_id, user_id, doctor_user_id, position, status) VALUES (?, ?, ?, ?, 'waiting')");
            $stmt->execute([$appointmentId, $row['user_id'], $row['doctor_user_id'], $nextPos]);

            // [FIX] Update appointment status to checked_in
            $upd = $db->prepare("UPDATE appointment SET appointment_status = 'checked_in' WHERE appointment_id = ?");
            $upd->execute([$appointmentId]);

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
        $db = Database::getInstance();
        try {
            $db->beginTransaction();

            $timeCol = '';
            if ($status === 'in_consultation') $timeCol = ", started_at = NOW()";
            if ($status === 'finished') $timeCol = ", finished_at = NOW()";

            $stmt = $db->prepare("UPDATE patient_queue SET status = :status $timeCol WHERE queue_id = :id");
            $stmt->execute([':id' => $queueId, ':status' => $status]);

            // Sync with appointment table
            if (in_array($status, ['in_consultation', 'finished', 'cancelled', 'no_show'])) {
                $aptStatus = 'scheduled'; // default
                if ($status === 'in_consultation') $aptStatus = 'in_consultation';
                if ($status === 'finished') $aptStatus = 'completed';
                if ($status === 'cancelled') $aptStatus = 'cancelled';
                if ($status === 'no_show') $aptStatus = 'no_show';

                // Get appointment_id
                $getApt = $db->prepare("SELECT appointment_id FROM patient_queue WHERE queue_id = ?");
                $getApt->execute([$queueId]);
                $aptId = (int)$getApt->fetchColumn();

                if ($aptId) {
                    $aptUpdate = $db->prepare("UPDATE appointment SET appointment_status = ? WHERE appointment_id = ?");
                    $aptUpdate->execute([$aptStatus, $aptId]);
                }
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            error_log("Queue update failed: " . $e->getMessage());
            return false;
        }
    }
}
