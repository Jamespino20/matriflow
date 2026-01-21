<?php

/**
 * NotificationService
 * 
 * Handles in-app notifications for patients and staff.
 * Extends the reminder_logs table to support general notifications.
 */

declare(strict_types=1);

final class NotificationService
{
    /**
     * Create a notification for a user
     * 
     * @param int $userId The user to notify
     * @param string $type Type: 'appointment_reminder', 'lab_result', 'payment_due', 'general', 'admin_broadcast'
     * @param string $message The notification message
     * @param int|null $relatedId Optional related record ID (appointment_id, billing_id, etc.)
     * @return int The notification ID
     */
    public static function create(int $userId, string $type, string $message, ?int $relatedId = null): int
    {
        $stmt = db()->prepare("
            INSERT INTO notifications (user_id, type, message, related_id, created_at, is_read)
            VALUES (:user_id, :type, :message, :related_id, NOW(), 0)
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':message' => $message,
            ':related_id' => $relatedId
        ]);

        return (int)db()->lastInsertId();
    }

    /**
     * Create a broadcast notification for all users of a specific role
     * 
     * @param string $role Role to notify: 'patient', 'doctor', 'secretary', 'all'
     * @param string $message The notification message
     * @param int $senderId The admin user sending the broadcast
     * @return int Number of notifications created
     */
    public static function broadcast(string $role, string $message, int $senderId): int
    {
        $sql = "SELECT user_id FROM users WHERE is_active = 1 AND deleted_at IS NULL";
        if ($role !== 'all') {
            $sql .= " AND role = :role";
        }

        $stmt = db()->prepare($sql);
        if ($role !== 'all') {
            $stmt->execute([':role' => $role]);
        } else {
            $stmt->execute();
        }

        $users = $stmt->fetchAll();
        $count = 0;

        foreach ($users as $user) {
            self::create(
                (int)$user['user_id'],
                'admin_broadcast',
                $message,
                $senderId
            );
            $count++;
        }

        // Log the broadcast action
        AuditLogger::log($senderId, 'notifications', 'BROADCAST', null, "Sent broadcast to $count users (role: $role)");

        return $count;
    }

    /**
     * Get unread notifications for a user
     * 
     * @param int $userId The user ID
     * @param int $limit Maximum number to fetch
     * @return array Array of notifications
     */
    public static function getUnread(int $userId, int $limit = 20): array
    {
        $stmt = db()->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :user_id AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get all notifications for a user
     * 
     * @param int $userId The user ID
     * @param int $limit Maximum number to fetch
     * @return array Array of notifications
     */
    public static function getAll(int $userId, int $limit = 50): array
    {
        $stmt = db()->prepare("
            SELECT * FROM notifications 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get unread count for a user
     * 
     * @param int $userId The user ID
     * @return int Unread count
     */
    public static function getUnreadCount(int $userId): int
    {
        $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark a notification as read
     * 
     * @param int $notificationId The notification ID
     * @param int $userId The user ID (for security)
     * @return bool Success
     */
    public static function markAsRead(int $notificationId, int $userId): bool
    {
        $stmt = db()->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all notifications as read for a user
     * 
     * @param int $userId The user ID
     * @return int Number of notifications marked
     */
    public static function markAllAsRead(int $userId): int
    {
        $stmt = db()->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Delete all notifications for a user
     * 
     * @param int $userId The user ID
     * @return bool Success
     */
    public static function deleteAll(int $userId): bool
    {
        $stmt = db()->prepare("DELETE FROM notifications WHERE user_id = :user_id");
        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Delete a specific notification
     * 
     * @param int $notificationId The notification ID
     * @param int $userId The user ID (for security)
     * @return bool Success
     */
    public static function delete(int $notificationId, int $userId): bool
    {
        $stmt = db()->prepare("DELETE FROM notifications WHERE notification_id = :id AND user_id = :user_id");
        return $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
    }

    /**
     * Create appointment reminder notification
     * 
     * @param int $appointmentId The appointment ID
     * @param int $userId The patient user ID
     * @param string $appointmentDate The appointment date/time
     * @return int Notification ID
     */
    public static function appointmentReminder(int $appointmentId, int $userId, string $appointmentDate): int
    {
        $date = date('M j, Y', strtotime($appointmentDate));
        $time = date('h:i A', strtotime($appointmentDate));
        $message = "Reminder: You have an appointment on $date at $time. Please arrive 15 minutes early.";

        return self::create($userId, 'appointment_reminder', $message, $appointmentId);
    }

    /**
     * Create lab result notification
     * 
     * @param int $labTestId The lab test ID
     * @param int $userId The patient user ID
     * @param string $testName The test name
     * @return int Notification ID
     */
    public static function labResultReady(int $labTestId, int $userId, string $testName): int
    {
        $message = "Your lab test result for '$testName' is now available. Please check your health records.";
        return self::create($userId, 'lab_result', $message, $labTestId);
    }

    /**
     * Create payment due notification
     * 
     * @param int $billingId The billing ID
     * @param int $userId The patient user ID
     * @param float $amount The amount due
     * @return int Notification ID
     */
    public static function paymentDue(int $billingId, int $userId, float $amount): int
    {
        $formatted = number_format($amount, 2);
        $message = "Payment Reminder: You have an outstanding balance of ₱$formatted. Please settle your account.";
        return self::create($userId, 'payment_due', $message, $billingId);
    }
}
