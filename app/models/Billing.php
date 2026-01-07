<?php

declare(strict_types=1);

final class Billing
{
    public static function findById(int $billingId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM billing WHERE billing_id = :bid LIMIT 1");
        $stmt->execute([':bid' => $billingId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByPatient(int $patientId, int $limit = 50): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM billing
                           WHERE patient_id = :pid
                           ORDER BY created_at DESC
                           LIMIT " . (int) $limit);
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll() ?: [];
    }
    public static function create(
        int $patientId,
        float $totalAmount,
        string $status = 'pending',
        ?string $dueDate = null,
        ?string $billedAt = null,
        ?string $description = null
    ): int {
        $sql = "INSERT INTO billing
              (patient_id, total_amount, status, due_date, billed_at, description)
              VALUES
              (:pid, :amt, :status, :due, :billed, :desc)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':pid' => $patientId,
            ':amt' => $totalAmount,
            ':status' => $status,
            ':due' => $dueDate,
            ':billed' => $billedAt ?: date('Y-m-d H:i:s'),
            ':desc' => $description,
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }
}
