<?php

declare(strict_types=1);

final class PatientController
{
    public static function currentPatient(): ?array
    {
        $u = Auth::user();
        if (!$u)
            return null;
        return Patient::findByUserId((int) $u['user_id']);
    }

    public static function getDashboardStats(int $userId): array
    {
        $patient = Patient::findByUserId($userId);
        if (!$patient) {
            return [];
        }

        $pid = (int) $patient['patient_id'];

        // Appointments
        $appointments = Appointment::listByPatient($pid, 5); // Get recent 5
        $upcomingCount = 0;
        $now = new DateTime();
        foreach ($appointments as $appt) {
            if ($appt['appointment_status'] === 'scheduled' && new DateTime($appt['appointment_date']) > $now) {
                $upcomingCount++;
            }
        }

        // Lab results
        $newLabsCount = 0;
        try {
            $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM laboratory_test WHERE patient_id = :pid AND status = 'completed' AND viewed_at IS NULL");
            $stmt->execute([':pid' => $pid]);
            $newLabsCount = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log("Failed to fetch lab stats: " . $e->getMessage());
        }

        // Unread Messages
        $unreadMsgCount = 0;
        try {
            $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
            $stmt->execute([':uid' => $userId]);
            $unreadMsgCount = (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log("Failed to fetch unread messages: " . $e->getMessage());
        }

        // Weight History (for charts)
        $weightHistory = [];
        try {
            $stmt = Database::getInstance()->prepare("SELECT weight_kg, recorded_at FROM vital_signs WHERE patient_id = :pid ORDER BY recorded_at DESC LIMIT 10");
            $stmt->execute([':pid' => $pid]);
            $weightHistory = array_reverse($stmt->fetchAll());
        } catch (Throwable $e) {
            error_log("Failed to fetch weight history: " . $e->getMessage());
        }

        // Prenatal Progress
        $baseline = null;
        try {
            $stmt = Database::getInstance()->prepare("SELECT * FROM prenatal_baseline WHERE patient_id = :pid ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([':pid' => $pid]);
            $baseline = $stmt->fetch();
        } catch (Throwable $e) {
            error_log("Failed to fetch prenatal baseline: " . $e->getMessage());
        }


        // Hourly Rotating Quote
        $quotes = [
            "The natural healing force within each of us is the greatest force in getting well. - Hippocrates",
            "Wherever the art of Medicine is loved, there is also a love of Humanity. - Hippocrates",
            "A mother's joy begins when new life is stirring inside; when a tiny heartbeat is heard for the very first time.",
            "Giving birth and being born brings us into the essence of creation, where the human spirit is courageous and bold.",
            "Medicine is a science of uncertainty and an art of probability. - William Osler",
            "Every child begins the world again. - Henry David Thoreau"
        ];
        $dailyQuote = $quotes[(int)date('H') % count($quotes)];

        return [
            'patient' => $patient,
            'appointments' => $appointments,
            'upcomingCount' => $upcomingCount,
            'newLabsCount' => $newLabsCount,
            'unreadMsgCount' => $unreadMsgCount,
            'weightHistory' => $weightHistory,
            'baseline' => $baseline,
            'quote' => $dailyQuote,
        ];
    }

    public static function getAll(array $filters = []): array
    {
        $sql = "SELECT p.*, u.first_name, u.last_name, u.email, u.contact_number, u.created_at,
                (SELECT appointment_date FROM appointment WHERE patient_id = p.patient_id ORDER BY appointment_date DESC LIMIT 1) as last_visit
                FROM patient p 
                JOIN user u ON p.user_id = u.user_id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR p.identification_number LIKE :q3)";
            $params[':q1'] = "%" . $filters['q'] . "%";
            $params[':q2'] = "%" . $filters['q'] . "%";
            $params[':q3'] = "%" . $filters['q'] . "%";
        }

        $sql .= " ORDER BY u.last_name ASC, u.first_name ASC";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
