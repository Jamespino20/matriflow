<?php

declare(strict_types=1);

final class PrenatalBaseline
{
    public static function findByPatientId(int $patientId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM prenatal_baseline WHERE patient_id = :pid ORDER BY baseline_recorded_at DESC LIMIT 1");
        $stmt->execute([':pid' => $patientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO prenatal_baseline (patient_id, gravidity, parity, abortion_count, living_children, lmp_date, estimated_due_date) 
                VALUES (:pid, :g, :p, :a, :l, :lmp, :edd)";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':pid' => $data['patient_id'],
            ':g' => $data['gravidity'],
            ':p' => $data['parity'],
            ':a' => $data['abortion_count'],
            ':l' => $data['living_children'],
            ':lmp' => $data['lmp_date'],
            ':edd' => $data['estimated_due_date']
        ]);
        return (int) db()->lastInsertId();
    }
}
