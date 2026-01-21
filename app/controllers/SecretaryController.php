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
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM billing WHERE billing_status = 'unpaid'");
        $stmt->execute();
        $pendingPayments = (int) $stmt->fetchColumn();

        // Unread Messages Count
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $userId]);
        $unreadCount = (int)$stmt->fetchColumn();

        // Recent Appointments for the list (Today)
        $stmt = Database::getInstance()->prepare("SELECT a.*, u.identification_number, u.first_name, u.last_name, u.contact_number
                               FROM appointment a
                               JOIN users u ON a.user_id = u.user_id
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
            "Focus on being productive instead of busy. - Tim Ferriss",
            "Success is the sum of small efforts repeated day in and day out. - Robert Collier",
            "Excellence is doing ordinary things extraordinarily well. - John W. Gardner",
            "The difference between ordinary and extraordinary is that little extra. - Jimmy Johnson",
            "Professionalism is knowing how to do it, when to do it, and doing it. - Frank Tyger",
            "Do your work with your whole heart, and you will succeed. - Elbert Hubbard",
            "Organization isn't about perfection; it's about efficiency. - Alexandra Stoddard"
        ];
        $index = (int)date('H') % count($quotes);
        return $quotes[$index];
    }
}
