<?php

declare(strict_types=1);

final class Pregnancy
{
    /**
     * Find the active pregnancy for a user
     */
    public static function findActiveByUserId(int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM pregnancies WHERE user_id = :uid AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Create a new pregnancy episode
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO pregnancies 
                (user_id, gravida, para, abortions, living_children, lmp_date, estimated_due_date, status)
                VALUES 
                (:uid, :g, :p, :a, :l, :lmp, :edd, 'active')";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':uid' => $data['user_id'],
            ':g' => $data['gravidity'] ?? 0,
            ':p' => $data['parity'] ?? 0,
            ':a' => $data['abortion_count'] ?? 0,
            ':l' => $data['living_children'] ?? 0,
            ':lmp' => $data['lmp_date'],
            ':edd' => $data['estimated_due_date']
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }

    /**
     * List all pregnancies for a user
     */
    public static function listByUserId(int $userId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM pregnancies WHERE user_id = :uid ORDER BY created_at DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }
    /**
     * Update the next recommended visit date
     */
    public static function updateNextVisit(int $pregnancyId, string $nextVisitDate): bool
    {
        $stmt = Database::getInstance()->prepare("UPDATE pregnancies SET next_visit_due = :d WHERE pregnancy_id = :id");
        return $stmt->execute([':d' => $nextVisitDate, ':id' => $pregnancyId]);
    }
}
