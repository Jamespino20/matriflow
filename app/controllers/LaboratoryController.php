<?php

declare(strict_types=1);

final class LaboratoryController
{
    public static function listAll(array $filters = []): array
    {
        $sql = "SELECT lt.*, u.first_name, u.last_name, u.role
                FROM laboratory_test lt
                JOIN patient p ON lt.patient_id = p.patient_id
                JOIN user u ON p.user_id = u.user_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR lt.test_name LIKE :q3)";
            $params[':q1'] = "%" . $filters['q'] . "%";
            $params[':q2'] = "%" . $filters['q'] . "%";
            $params[':q3'] = "%" . $filters['q'] . "%";
        }

        if (!empty($filters['status'])) {
            $sql .= " AND lt.status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY lt.ordered_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateResult(int $testId, string $result, string $status, int $released): bool
    {
        $stmt = db()->prepare("UPDATE laboratory_test 
                               SET test_result = :res, status = :status, 
                                   released_to_patient = :rel, released_at = IF(:rel = 1, NOW(), released_at)
                               WHERE test_id = :id");
        return $stmt->execute([
            ':res' => $result,
            ':status' => $status,
            ':rel' => $released,
            ':id' => $testId
        ]);
    }

    public static function listByPatient(int $patientId): array
    {
        $stmt = db()->prepare("SELECT * FROM laboratory_test WHERE patient_id = :pid ORDER BY ordered_at DESC");
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll();
    }
}
