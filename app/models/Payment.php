<?php

declare(strict_types=1);

final class Payment
{
    public static function create(
        int $billingId,
        int $patientId,
        float $amountPaid,
        string $method,
        ?string $referenceNo,
        ?string $paidAt = null,
        ?int $recordedByUserId = null,
        ?string $notes = null
    ): int {
        $sql = "INSERT INTO payment
      (billing_id, patient_id, amount_paid, paid_at, method, reference_no, recorded_by_user_id, notes, payment_status)
      VALUES
      (:bid, :pid, :amt, :paid_at, :method, :ref, :by, :notes, :status)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':bid' => $billingId,
            ':pid' => $patientId,
            ':amt' => $amountPaid,
            ':paid_at' => $paidAt ?: date('Y-m-d H:i:s'),
            ':method' => $method,
            ':ref' => $referenceNo,
            ':by' => $recordedByUserId,
            ':notes' => $notes,
            ':status' => 'completed'
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }

    public static function listByPatient(int $patientId, int $limit = 50): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM payment
                           WHERE patient_id = :pid
                           ORDER BY paid_at DESC
                           LIMIT " . (int) $limit);
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll() ?: [];
    }
}
