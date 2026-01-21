<?php

declare(strict_types=1);

final class DoctorController
{
    public static function getDashboardStats(int $userId): array
    {
        $today = date('Y-m-d');

        // Today's Appointments
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment
                               WHERE (doctor_user_id = :uid OR doctor_user_id IS NULL) 
                               AND DATE(appointment_date) = :today 
                               AND appointment_status = 'scheduled'");
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        $todayPatients = (int) $stmt->fetchColumn();

        // Lab Results Waiting Review
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM laboratory_test
                               WHERE status = 'completed'");
        $stmt->execute();
        $pendingLabs = (int) $stmt->fetchColumn();

        // Recent Appointments for the list (Today)
        $stmt = Database::getInstance()->prepare("SELECT a.*, u.identification_number, u.first_name, u.last_name, u.contact_number
                               FROM appointment a
                               JOIN users u ON a.user_id = u.user_id
                               WHERE (a.doctor_user_id = :uid OR a.doctor_user_id IS NULL) 
                               AND DATE(a.appointment_date) = :today
                               AND a.appointment_status = 'scheduled'
                               ORDER BY a.appointment_date ASC");
        $stmt->execute([':uid' => $userId, ':today' => $today]);
        $recentAppointments = $stmt->fetchAll() ?: [];

        // Unread Messages Count
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $userId]);
        $unreadCount = (int)$stmt->fetchColumn();

        // Recent Activity - using appointments instead since consultation doesn't have doctor_user_id
        $stmt = Database::getInstance()->prepare("SELECT 'appointment' as type, a.appointment_date as created_at, u.first_name, u.last_name
                                FROM appointment a 
                                JOIN users u ON a.user_id = u.user_id 
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
            "Healing is a matter of time, but it is sometimes also a matter of opportunity. - Hippocrates",
            "The good physician treats the disease; the great physician treats the patient who has the disease. - William Osler",
            "To cure sometimes, to relieve often, to comfort always. - Edward Livingston Trudeau",
            "The art of medicine consists of amusing the patient while nature cures the disease. - Voltaire",
            "It is more important to know what sort of person has a disease than to know what sort of disease a person has. - Hippocrates",
            "The best doctor gives the least medicines. - Benjamin Franklin",
            "Medicine is not only a science; it is also an art. It does not consist of compounding pills and plasters; it deals with the very processes of life. - Paracelsus",
            "Every baby born into the world is a finer one than the last. - Charles Dickens"
        ];
        $index = (int)date('H') % count($quotes);
        return $quotes[$index];
    }
}
