<?php

declare(strict_types=1);

final class AbuseDetectionService
{
    private const MAX_DAILY_NO_SHOWS = 2;
    private const MAX_TOTAL_UNPAID_PENALTIES = 3;

    /**
     * Check a user's history for abuse patterns and apply penalties if thresholds are met.
     * Should be called after a 'no_show' status update or penalty charge.
     */
    public static function checkAndPenalize(int $userId): void
    {
        if ($userId <= 0) return;

        $db = Database::getInstance();
        $isAbusive = false;
        $reasons = [];

        // Rule 1: Check Daily No-Shows (Sabotager scenario: "Multiple no-shows detected")
        $stmtDaily = $db->prepare("SELECT COUNT(*) FROM appointment 
                                 WHERE user_id = :uid 
                                 AND appointment_status = 'no_show' 
                                 AND DATE(appointment_date) = CURDATE()");
        $stmtDaily->execute([':uid' => $userId]);
        $dailyCount = (int) $stmtDaily->fetchColumn();

        if ($dailyCount >= self::MAX_DAILY_NO_SHOWS) {
            $isAbusive = true;
            $reasons[] = "High daily no-show rate ({$dailyCount} in one day)";
        }

        // Rule 2: Check Total Unpaid Penalties (Financial abuse)
        // We look for 'overdue' bills with 'No-Show Penalty' description
        $stmtUnpaid = $db->prepare("SELECT COUNT(*) FROM billing 
                                  WHERE user_id = :uid 
                                  AND billing_status = 'overdue' 
                                  AND service_description LIKE '%No-show penalty%'");
        $stmtUnpaid->execute([':uid' => $userId]);
        $unpaidCount = (int) $stmtUnpaid->fetchColumn();

        if ($unpaidCount >= self::MAX_TOTAL_UNPAID_PENALTIES) {
            $isAbusive = true;
            $reasons[] = "Excessive unpaid penalties ({$unpaidCount} total)";
        }

        // Action: Suspend User if abusive
        if ($isAbusive) {
            self::suspendUser($userId, implode(', ', $reasons));
        }
    }

    /**
     * Suspend a user account and log the action.
     */
    public static function suspendUser(int $userId, string $reason): bool
    {
        $db = Database::getInstance();

        // 1. Deactivate user
        $stmt = $db->prepare("UPDATE users SET is_active = 0, account_status = 'suspended' WHERE user_id = :uid");
        $success = $stmt->execute([':uid' => $userId]);

        if ($success) {
            // 2. Log to Audit System
            // We use user_id 1 (System Admin) as the actor for automated actions if no session user
            $actorId = Auth::user()['user_id'] ?? 1;

            AuditLogger::log(
                $actorId,
                'user_management',
                'SUSPEND',
                $userId,
                "Automated Suspension: $reason"
            );

            // 3. (Optional) Create an Admin Notification or System Note
            // Ideally we'd add to an admin_alerts table if one existed.
            error_log("AbuseDetection: Suspended User ID $userId. Reason: $reason");
        }

        return $success;
    }
}
