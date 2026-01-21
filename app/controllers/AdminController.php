<?php

declare(strict_types=1);

final class AdminController
{
    public static function getDashboardStats(): array
    {
        // Total Users
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $totalUsers = (int) $stmt->fetchColumn();

        // Active Sessions (last 15 mins)
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM users WHERE last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute();
        $activeSessions = (int) $stmt->fetchColumn();

        // Total Revenue
        $stmt = Database::getInstance()->prepare("SELECT SUM(amount_paid) FROM billing");
        $stmt->execute();
        $totalRevenue = (float) $stmt->fetchColumn();

        // Pending Appointments List
        $stmt = Database::getInstance()->prepare("SELECT a.*, u.first_name, u.last_name FROM appointment a JOIN users u ON a.user_id = u.user_id WHERE a.appointment_status = 'pending' ORDER BY a.appointment_date ASC LIMIT 5");
        $stmt->execute();
        $pendingAppointmentsList = $stmt->fetchAll() ?: [];
        $pendingAppointmentsCount = count($pendingAppointmentsList); // Just a fallback, would be better to have full count

        // Full Pending Count
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment WHERE appointment_status = 'pending'");
        $stmt->execute();
        $pendingAppointmentsCount = (int) $stmt->fetchColumn();

        // Scheduled Appointments (Today)
        $today = date('Y-m-d');
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment WHERE appointment_status = 'scheduled' AND DATE(appointment_date) = :today");
        $stmt->execute([':today' => $today]);
        $scheduledToday = (int) $stmt->fetchColumn();

        // Cancelled Appointments (This Month)
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment WHERE appointment_status = 'cancelled' AND MONTH(appointment_date) = MONTH(NOW())");
        $stmt->execute();
        $cancelledThisMonth = (int) $stmt->fetchColumn();

        // Completed Appointments (This Month)
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM appointment WHERE appointment_status = 'completed' AND MONTH(appointment_date) = MONTH(NOW())");
        $stmt->execute();
        $completedThisMonth = (int) $stmt->fetchColumn();

        // Active Patients
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM users WHERE role = 'patient' AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute();
        $activePatients = (int) $stmt->fetchColumn();

        // Recent Audit Logs
        $stmt = Database::getInstance()->prepare("SELECT * FROM audit_log ORDER BY logged_at DESC LIMIT 5");
        $stmt->execute();
        $recentLogs = $stmt->fetchAll() ?: [];

        // Lab Growth (for charts)
        $stmt = Database::getInstance()->prepare("SELECT DATE(ordered_at) as date, COUNT(*) as count FROM laboratory_test GROUP BY DATE(ordered_at) ORDER BY date DESC LIMIT 7");
        $stmt->execute();
        $labGrowth = $stmt->fetchAll() ?: [];

        // System Health Check
        $dbHealth = true;
        try {
            Database::getInstance()->query("SELECT 1");
        } catch (Throwable $e) {
            $dbHealth = false;
        }
        $storageHealth = is_writable(__DIR__ . '/../../storage');

        return [
            'totalUsers' => $totalUsers,
            'activeSessions' => $activeSessions,
            'recentLogs' => $recentLogs,
            'labGrowth' => array_reverse($labGrowth),
            'totalRevenue' => $totalRevenue,
            'pendingCount' => $pendingAppointmentsCount,
            'scheduledToday' => $scheduledToday,
            'cancelledThisMonth' => $cancelledThisMonth,
            'completedThisMonth' => $completedThisMonth,
            'pendingList' => $pendingAppointmentsList,
            'activePatients' => $activePatients,
            'systemStatus' => ($dbHealth && $storageHealth) ? 'Operational' : 'Degraded',
            'dbHealth' => $dbHealth,
            'storageHealth' => $storageHealth,
            'quote' => self::getRotatingQuote()
        ];
    }

    private static function getRotatingQuote(): string
    {
        $quotes = [
            "Leadership is not about being in charge. It is about taking care of those in your charge. - Simon Sinek",
            "The best way to find yourself is to lose yourself in the service of others. - Mahatma Gandhi",
            "Efficiency is doing things right; effectiveness is doing the right things. - Peter Drucker",
            "Management is doing things right; leadership is doing the right things. - Peter Drucker",
            "Coming together is a beginning; keeping together is progress; working together is success. - Henry Ford",
            "The only way to do great work is to love what you do. - Steve Jobs",
            "The greatest leader is not necessarily the one who does the greatest things, but the one who gets people to do the greatest things. - Ronald Reagan",
            "Excellence is not a skill, it's an attitude. - Ralph Marston",
            "Quality means doing it right when no one is looking. - Henry Ford",
            "Alone we can do so little; together we can do so much. - Helen Keller",
            "Innovation distinguishes between a leader and a follower. - Steve Jobs",
            "The function of leadership is to produce more leaders, not more followers. - Ralph Nader"
        ];
        $index = (int)date('H') % count($quotes);
        return $quotes[$index];
    }
}
