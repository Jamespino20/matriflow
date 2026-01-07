<?php

declare(strict_types=1);

final class VitalSigns
{
    public static function getRecentForPatient(int $patientId, int $limit = 10): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM vital_signs WHERE patient_id = :pid ORDER BY recorded_at DESC LIMIT :limit");
        $stmt->execute([':pid' => $patientId, ':limit' => $limit]);
        return $stmt->fetchAll();
    }

    public static function getWeightHistory(int $patientId, int $limit = 7): array
    {
        $stmt = Database::getInstance()->prepare("SELECT weight_kg, recorded_at FROM vital_signs WHERE patient_id = :pid AND weight_kg IS NOT NULL ORDER BY recorded_at ASC LIMIT :limit");
        $stmt->execute([':pid' => $patientId, ':limit' => $limit]);
        return $stmt->fetchAll();
    }

    public static function create(int $patientId, array $data): bool
    {
        $stmt = Database::getInstance()->prepare("INSERT INTO vital_signs
            (patient_id, blood_pressure, heart_rate, temperature, weight_kg, recorded_at) 
            VALUES (:pid, :bp, :hr, :temp, :wt, NOW())");

        return $stmt->execute([
            ':pid' => $patientId,
            ':bp' => $data['blood_pressure'] ?? null,
            ':hr' => $data['heart_rate'] ?? null,
            ':temp' => $data['temperature'] ?? null,
            ':wt' => $data['weight_kg'] ?? null
        ]);
    }
}
