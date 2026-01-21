<?php

declare(strict_types=1);

final class VitalSigns
{
    public static function getRecentForPatient(int $userId, int $limit = 10): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM vital_signs WHERE user_id = :uid ORDER BY recorded_at DESC LIMIT :limit");
        $stmt->execute([':uid' => $userId, ':limit' => $limit]);
        return $stmt->fetchAll();
    }

    public static function getWeightHistory(int $userId, int $limit = 7): array
    {
        $stmt = Database::getInstance()->prepare("SELECT weight_kg, recorded_at FROM vital_signs WHERE user_id = :uid AND weight_kg IS NOT NULL ORDER BY recorded_at ASC LIMIT :limit");
        $stmt->execute([':uid' => $userId, ':limit' => $limit]);
        return $stmt->fetchAll();
    }

    public static function create(int $userId, array $data): bool
    {
        $stmt = Database::getInstance()->prepare("INSERT INTO vital_signs
            (user_id, systolic_pressure, diastolic_pressure, heart_rate, temperature_celsius, weight_kg, recorded_at) 
            VALUES (:uid, :sys, :dia, :hr, :temp, :wt, NOW())");

        return $stmt->execute([
            ':uid' => $userId,
            ':sys' => $data['systolic'] ?? null,
            ':dia' => $data['diastolic'] ?? null,
            ':hr' => $data['heart_rate'] ?? null,
            ':temp' => $data['temperature_celsius'] ?? null,
            ':wt' => $data['weight_kg'] ?? null
        ]);
    }
}
