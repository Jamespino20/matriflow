<?php

declare(strict_types=1);

final class NotificationService
{
    public static function create(int $userId, string $title, string $message, string $type = 'info', ?string $url = null): bool
    {
        try {
            $stmt = db()->prepare("INSERT INTO notifications (user_id, title, message, type, action_url) VALUES (?, ?, ?, ?, ?)");
            return $stmt->execute([$userId, $title, $message, $type, $url]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function alertAccountChange(int $userId, string $changeType): void
    {
        self::create(
            $userId,
            "Security Alert",
            "Your account $changeType was recently updated. If you did not make this change, please contact support immediately.",
            "warning",
            "/public/profile.php"
        );
    }
}
