<?php
declare(strict_types=1);

/**
 * Messaging: Handle SMS, email, and in-app notifications
 */
class Messaging
{
    /**
     * Send alert/notification to user(s)
     */
    public static function sendAlert(int $userId, string $title, string $message, string $type = 'info', ?string $actionUrl = null): bool
    {
        try {
            $stmt = Database::getInstance()->prepare('
                INSERT INTO notifications (user_id, title, message, type, action_url, is_read, created_at)
                VALUES (:user_id, :title, :message, :type, :action_url, 0, NOW())
            ');
            return $stmt->execute([
                ':user_id' => $userId,
                ':title' => $title,
                ':message' => $message,
                ':type' => $type,
                ':action_url' => $actionUrl,
            ]);
        } catch (Throwable $e) {
            error_log('Messaging::sendAlert failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS follow-up
     */
    public static function sendSMS(int $userId, string $phoneNumber, string $message): bool
    {
        try {
            // TODO: Integrate with SMS gateway (Twilio, AWS SNS, etc.)
            // For now, log the SMS
            error_log("SMS to {$phoneNumber}: {$message}");

            $stmt = Database::getInstance()->prepare('
                INSERT INTO sms_logs (user_id, phone_number, message, status, sent_at)
                VALUES (:user_id, :phone_number, :message, "pending", NOW())
            ');
            return $stmt->execute([
                ':user_id' => $userId,
                ':phone_number' => $phoneNumber,
                ':message' => $message,
            ]);
        } catch (Throwable $e) {
            error_log('Messaging::sendSMS failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread notifications for user
     */
    public static function getUnreadNotifications(int $userId, int $limit = 5): array
    {
        try {
            $stmt = Database::getInstance()->prepare('
                SELECT * FROM notifications
                WHERE user_id = :user_id AND is_read = 0
                ORDER BY created_at DESC
                LIMIT :limit
            ');
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Messaging::getUnreadNotifications failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark notification as read
     */
    public static function markAsRead(int $notificationId): bool
    {
        try {
            $stmt = Database::getInstance()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id');
            return $stmt->execute([':id' => $notificationId]);
        } catch (Throwable $e) {
            error_log('Messaging::markAsRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Schedule SMS followup reminder
     */
    public static function scheduleReminder(int $userId, string $message, DateTime $sendAt): bool
    {
        try {
            $user = User::findById($userId);
            if (!$user)
                return false;

            $phoneNumber = $user['contact_number'] ?? '';
            if (empty($phoneNumber)) {
                error_log("No phone number for user {$userId}");
                return false;
            }

            $stmt = Database::getInstance()->prepare('
                INSERT INTO sms_reminders (user_id, phone_number, message, scheduled_for, status)
                VALUES (:user_id, :phone_number, :message, :scheduled_for, "pending")
            ');
            return $stmt->execute([
                ':user_id' => $userId,
                ':phone_number' => $phoneNumber,
                ':message' => $message,
                ':scheduled_for' => $sendAt->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('Messaging::scheduleReminder failed: ' . $e->getMessage());
            return false;
        }
    }
}
