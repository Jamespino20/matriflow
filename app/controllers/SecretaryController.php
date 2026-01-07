<?php

declare(strict_types=1);

final class SecretaryController
{
    public static function getDashboardStats(int $userId): array
    {
        $today = date('Y-m-d');

        // Today's All Appointments (Scheduled)
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment
                               WHERE DATE(appointment_date) = :today 
                               AND appointment_status = 'scheduled'");
        $stmt->execute([':today' => $today]);
        $todayAppointments = (int) $stmt->fetchColumn();

        // Pending Appointment Requests (Pending)
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment WHERE appointment_status = 'pending'");
        $stmt->execute();
        $pendingRequests = (int) $stmt->fetchColumn();

        // Pending Payments (Unpaid Billing)
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM billing WHERE payment_status = 'unpaid'");
        $stmt->execute();
        $pendingPayments = (int) $stmt->fetchColumn();

        // Unread Messages Count
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $userId]);
        $unreadCount = (int)$stmt->fetchColumn();

        // Recent Appointments for the list (Today)
        $stmt = Database::getInstance()->prepare("SELECT a.*, p.identification_number, u.first_name, u.last_name
                               FROM appointment a
                               JOIN patient p ON a.patient_id = p.patient_id
                               JOIN user u ON p.user_id = u.user_id
                               WHERE DATE(a.appointment_date) = :today
                               ORDER BY a.appointment_date ASC LIMIT 5");
        $stmt->execute([':today' => $today]);
        $recentAppointments = $stmt->fetchAll() ?: [];

        return [
            'todayAppointments' => $todayAppointments,
            'pendingRequests' => $pendingRequests,
            'pendingPayments' => $pendingPayments,
            'unreadCount' => $unreadCount,
            'recentAppointments' => $recentAppointments,
            'systemStatus' => 'Operational',
            'quote' => self::getRotatingQuote()
        ];
    }

    private static function getRotatingQuote(): string
    {
        $quotes = [
            "Organization is the key to efficiency. Every minute spent in planning saves an hour in execution.",
            "The secret of joy in work is contained in one word - excellence. To know how to do something well is to enjoy it.",
            "Quality is not an act, it is a habit. - Aristotle",
            "Be so good they can't ignore you. - Steve Martin",
            "Focus on being productive instead of busy. - Tim Ferriss"
        ];
        $index = (int)date('H') % count($quotes);
        return $quotes[$index];
    }
}
