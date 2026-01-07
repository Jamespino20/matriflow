<?php
declare(strict_types=1);

final class LaboratoryTest
{
    public static function countNewForPatient(int $patientId): int
    {
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM laboratory_test
                               WHERE patient_id = :pid 
                               AND released_to_patient = 1 
                               AND viewed_by_patient = 0");
        $stmt->execute([':pid' => $patientId]);
        return (int) $stmt->fetchColumn();
    }

    public static function listRecent(int $patientId, int $limit = 5): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM laboratory_test
                               WHERE patient_id = :pid 
                               AND released_to_patient = 1 
                               ORDER BY released_at DESC 
                               LIMIT " . (int) $limit);
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll();
    }
}
