<?php

declare(strict_types=1);

final class LaboratoryController
{
    public static function listAll(array $filters = []): array
    {
        $sql = "SELECT lt.*, u.first_name, u.last_name, u.role
                FROM laboratory_test lt
                JOIN users u ON lt.user_id = u.user_id
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
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findById(int $testId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT lt.*, u.first_name, u.last_name 
                                                FROM laboratory_test lt
                                                JOIN users u ON lt.user_id = u.user_id 
                                                WHERE lt.test_id = :id");
        $stmt->execute([':id' => $testId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateResult(int $testId, string $result, string $status, ?string $filePath = null): bool
    {
        $sql = "UPDATE laboratory_test
                SET test_result = :res, status = :status";
        $params = [
            ':res' => $result,
            ':status' => $status,
            ':id' => $testId
        ];

        if ($filePath) {
            $sql .= ", result_file_path = :file";
            $params[':file'] = $filePath;
        }

        $sql .= " WHERE test_id = :id";

        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute($params);
    }

    public static function create(int $userId, string $testType, string $status = 'ordered'): int
    {
        $stmt = Database::getInstance()->prepare("INSERT INTO laboratory_test (user_id, test_name, status, ordered_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $testType, $status]);
        return (int)Database::getInstance()->lastInsertId();
    }

    public static function listByPatient(int $userId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM laboratory_test WHERE user_id = :uid ORDER BY ordered_at DESC");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }
}
