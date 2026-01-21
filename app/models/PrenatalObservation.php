<?php

declare(strict_types=1);

final class PrenatalObservation
{
    /**
     * List observations for a pregnancy
     */
    public static function listByPregnancy(int $pregnancyId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM prenatal_observations WHERE pregnancy_id = :pid ORDER BY recorded_at DESC");
        $stmt->execute([':pid' => $pregnancyId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Create a new observation record
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO prenatal_observations 
                (user_id, pregnancy_id, fundal_height_cm, fetal_heart_rate, fetal_movement_noted) 
                VALUES 
                (:uid, :pid, :fh, :fhr, :fm)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':uid' => $data['user_id'],
            ':pid' => $data['pregnancy_id'],
            ':fh' => $data['fundal_height_cm'],
            ':fhr' => $data['fetal_heart_rate'],
            ':fm' => $data['fetal_movement_noted']
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }
}
