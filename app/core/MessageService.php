<?php

declare(strict_types=1);

final class MessageService
{
    /**
     * Send a private message between users
     */
    public static function sendMessage(int $senderId, int $receiverId, string $body): int
    {
        $stmt = db()->prepare("INSERT INTO messages (sender_id, receiver_id, message_body, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$senderId, $receiverId, $body]);
        $msgId = (int) db()->lastInsertId();
        AuditLogger::log($senderId, 'messages', 'INSERT', $msgId, "Message sent to User ID: $receiverId");
        return $msgId;
    }

    /**
     * Get conversation history between two users
     */
    public static function getConversation(int $userA, int $userB, int $limit = 50): array
    {
        $stmt = db()->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = :a1 AND receiver_id = :b1) OR (sender_id = :b2 AND receiver_id = :a2)
            ORDER BY created_at ASC 
            LIMIT :limit
        ");
        $stmt->bindValue(':a1', $userA, PDO::PARAM_INT);
        $stmt->bindValue(':a2', $userA, PDO::PARAM_INT);
        $stmt->bindValue(':b1', $userB, PDO::PARAM_INT);
        $stmt->bindValue(':b2', $userB, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get only new messages since a certain ID
     */
    public static function getNewMessages(int $userA, int $userB, int $lastId): array
    {
        $stmt = db()->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = :a1 AND receiver_id = :b1) OR (sender_id = :b2 AND receiver_id = :a2))
            AND id > :last
            ORDER BY created_at ASC
        ");
        $stmt->execute([':a1' => $userA, ':a2' => $userA, ':b1' => $userB, ':b2' => $userB, ':last' => $lastId]);
        return $stmt->fetchAll();
    }

    /**
     * Get list of users with whom the current user has interacted
     */
    public static function getContacts(int $userId): array
    {
        $stmt = db()->prepare("
            SELECT DISTINCT u.user_id, u.first_name, u.last_name, u.role,
            (SELECT message_body FROM messages 
             WHERE (sender_id = u.user_id AND receiver_id = :uid1) OR (sender_id = :uid2 AND receiver_id = u.user_id)
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages 
             WHERE (sender_id = u.user_id AND receiver_id = :uid3) OR (sender_id = :uid4 AND receiver_id = u.user_id)
             ORDER BY created_at DESC LIMIT 1) as last_message_at
            FROM user u
            JOIN messages m ON (m.sender_id = u.user_id OR m.receiver_id = u.user_id)
            WHERE (m.sender_id = :uid5 OR m.receiver_id = :uid6) AND u.user_id != :uid7
            ORDER BY last_message_at DESC
        ");
        $stmt->execute([
            ':uid1' => $userId,
            ':uid2' => $userId,
            ':uid3' => $userId,
            ':uid4' => $userId,
            ':uid5' => $userId,
            ':uid6' => $userId,
            ':uid7' => $userId
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Mark all messages from a sender as read
     */
    public static function markAsRead(int $receiverId, int $senderId): void
    {
        $stmt = db()->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE receiver_id = :rid AND sender_id = :sid AND is_read = 0");
        $stmt->execute([':rid' => $receiverId, ':sid' => $senderId]);
    }
}
