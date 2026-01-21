<?php

declare(strict_types=1);

final class LaboratoryTest
{
    public static function countNewForPatient(int $patientId): int
    {
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM laboratory_test
                               WHERE user_id = :uid 
                               AND status = 'completed'
                               AND viewed_at IS NULL");
        $stmt->execute([':uid' => $patientId]);
        return (int) $stmt->fetchColumn();
    }

    public static function listRecent(int $patientId, int $limit = 5): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM laboratory_test
                               WHERE user_id = :uid 
                               AND status IN ('completed', 'reviewed', 'released')
                               ORDER BY ordered_at DESC 
                               LIMIT " . (int) $limit);
        $stmt->execute([':uid' => $patientId]);
        return $stmt->fetchAll();
    }
}
