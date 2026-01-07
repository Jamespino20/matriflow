<?php

declare(strict_types=1);

final class Consultation
{
    public static function create(int $patientId, int $doctorId, array $data): int
    {
        $stmt = Database::getInstance()->prepare("INSERT INTO consultation
            (patient_id, doctor_user_id, appointment_id, consultation_type, subjective_notes, objective_notes, assessment, plan, created_at)
            VALUES (:pid, :did, :aid, :type, :s, :o, :a, :p, NOW())");

        $stmt->execute([
            ':pid' => $patientId,
            ':did' => $doctorId,
            ':aid' => $data['appointment_id'] ?? null,
            ':type' => $data['consultation_type'] ?? 'general',
            ':s' => $data['subjective'] ?? null,
            ':o' => $data['objective'] ?? null,
            ':a' => $data['assessment'] ?? null,
            ':p' => $data['plan'] ?? null
        ]);

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function listByPatient(int $patientId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT c.*, u.first_name as doctor_first, u.last_name as doctor_last
                             FROM consultation c 
                             JOIN user u ON c.doctor_user_id = u.user_id 
                             WHERE c.patient_id = ? 
                             ORDER BY c.created_at DESC");
        $stmt->execute([$patientId]);
        return $stmt->fetchAll() ?: [];
    }
}
