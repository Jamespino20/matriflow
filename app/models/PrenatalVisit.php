<?php

declare(strict_types=1);

final class PrenatalVisit
{
    public static function listByBaseline(int $baselineId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM prenatal_visit WHERE prenatal_baseline_id = :bid ORDER BY visit_recorded_at DESC");
        $stmt->execute([':bid' => $baselineId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO prenatal_visit (patient_id, prenatal_baseline_id, fundal_height_cm, fetal_heart_rate, fetal_movement_noted) 
                VALUES (:pid, :bid, :fh, :fhr, :fm)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':pid' => $data['patient_id'],
            ':bid' => $data['prenatal_baseline_id'],
            ':fh' => $data['fundal_height_cm'],
            ':fhr' => $data['fetal_heart_rate'],
            ':fm' => $data['fetal_movement_noted']
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }
}
