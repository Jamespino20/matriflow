<?php

declare(strict_types=1);

final class DoctorController
{
    public static function getDashboardStats(int $userId): array
    {
        $today = date('Y-m-d');

        // Today's Appointments
        $stmt = db()->prepare("SELECT COUNT(*) FROM appointment 
                               WHERE (doctor_user_id = :uid OR doctor_user_id IS NULL) 
                               AND DATE(appointment_date) = :today 
                               AND appointment_status = 'scheduled'");
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        $todayPatients = (int) $stmt->fetchColumn();

        // Lab Results Waiting Review
        $stmt = db()->prepare("SELECT COUNT(*) FROM laboratory_test 
                               WHERE status = 'completed' AND doctor_reviewed = 0");
        $stmt->execute();
        $pendingLabs = (int) $stmt->fetchColumn();

        // Recent Appointments for the list (Today)
        $stmt = db()->prepare("SELECT a.*, p.identification_number, u.first_name, u.last_name 
                               FROM appointment a
                               JOIN patient p ON a.patient_id = p.patient_id
                               JOIN user u ON p.user_id = u.user_id
                               WHERE (a.doctor_user_id = :uid OR a.doctor_user_id IS NULL) 
                               AND DATE(a.appointment_date) = :today
                               AND a.appointment_status = 'scheduled'
                               ORDER BY a.appointment_date ASC");
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        $recentAppointments = $stmt->fetchAll() ?: [];

        // Unread Messages Count
        $stmt = db()->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $userId]);
        $unreadCount = (int)$stmt->fetchColumn();

        // Recent Activity - using appointments instead since consultation doesn't have doctor_user_id
        $stmt = db()->prepare("SELECT 'appointment' as type, a.appointment_date as created_at, u.first_name, u.last_name 
                                FROM appointment a 
                                JOIN patient p ON a.patient_id = p.patient_id 
                                JOIN user u ON p.user_id = u.user_id 
                                WHERE a.doctor_user_id = :uid AND a.appointment_status = 'completed'
                                ORDER BY a.appointment_date DESC LIMIT 3");
        $stmt->execute([':uid' => $userId]);
        $recentActivity = $stmt->fetchAll() ?: [];

        return [
            'todayPatients' => $todayPatients,
            'pendingLabs' => $pendingLabs,
            'unreadCount' => $unreadCount,
            'recentAppointments' => $recentAppointments,
            'recentActivity' => $recentActivity,
            'quote' => self::getRotatingQuote()
        ];
    }

    private static function getRotatingQuote(): string
    {
        $quotes = [
            "The primary goal of the health professional is to prevent disease and prolong life. - Benjamin Franklin",
            "Medicine is a science of uncertainty and an art of probability. - William Osler",
            "Wherever the art of Medicine is loved, there is also a love of Humanity. - Hippocrates",
            "The natural healing force within each of us is the greatest force in getting well. - Hippocrates",
            "Healing is a matter of time, but it is sometimes also a matter of opportunity. - Hippocrates"
        ];
        $index = (int)date('H') % count($quotes);
        return $quotes[$index];
    }
}
