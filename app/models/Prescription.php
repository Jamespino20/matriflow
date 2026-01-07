<?php

declare(strict_types=1);

final class Prescription
{
    public static function create(int $patientId, int $doctorId, array $data): int
    {
        $stmt = Database::getInstance()->prepare("INSERT INTO prescription
            (patient_id, doctor_user_id, consultation_id, medication_name, dosage, frequency, duration, instructions, prescribed_at)
            VALUES (:pid, :did, :cid, :m, :dos, :f, :dur, :i, NOW())");

        $stmt->execute([
            ':pid' => $patientId,
            ':did' => $doctorId,
            ':cid' => $data['consultation_id'] ?? null,
            ':m' => $data['medication_name'],
            ':dos' => $data['dosage'] ?? null,
            ':f' => $data['frequency'] ?? null,
            ':dur' => $data['duration'] ?? null,
            ':i' => $data['instructions'] ?? null
        ]);

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function listByPatient(int $patientId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT p.*, u.first_name as doctor_first, u.last_name as doctor_last
                             FROM prescription p 
                             JOIN user u ON p.doctor_user_id = u.user_id 
                             WHERE p.patient_id = ? 
                             ORDER BY p.prescribed_at DESC");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll() ?: [];
    }
}
