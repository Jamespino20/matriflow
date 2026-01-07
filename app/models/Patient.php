<?php
declare(strict_types=1);

final class Patient
{
    public static function createForUser(int $userId, ?string $dob, ?int $ageAtReg = null): int
    {
        // Try with dob column first, if it fails, try without it
        try {
            $sql = "INSERT INTO patient (user_id, dob, age_at_registration) VALUES (:uid, :dob, :age)";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                ':uid' => $userId,
                ':dob' => $dob ?: null,
                ':age' => $ageAtReg,
            ]);
            return (int) db()->lastInsertId();
        } catch (Throwable $e) {
            // Fallback if dob column doesn't exist
            $sql = "INSERT INTO patient (user_id, age_at_registration) VALUES (:uid, :age)";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                ':uid' => $userId,
                ':age' => $ageAtReg,
            ]);
            return (int) db()->lastInsertId();
        }
    }

    public static function findById(int $patientId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM patient WHERE patient_id = :pid AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':pid' => $patientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUserId(int $userId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM patient WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getPatientIdForUser(int $userId): ?int
    {
        $stmt = db()->prepare("SELECT patient_id FROM patient WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $pid = $stmt->fetchColumn();
        return $pid ? (int) $pid : null;
    }

    public static function updateProfileBasic(int $patientId, array $data): bool
    {
        $sql = "UPDATE patient
            SET address = :address,
                marital_status = :marital_status,
                occupation = :occupation,
                emergency_contact_name = :ecn,
                emergency_contact_number = :ecn_no,
                medical_history = :mh,
                allergies = :allergies
            WHERE patient_id = :pid AND deleted_at IS NULL";
        $stmt = db()->prepare($sql);
        return $stmt->execute([
            ':address' => $data['address'] ?? null,
            ':marital_status' => $data['marital_status'] ?? null,
            ':occupation' => $data['occupation'] ?? null,
            ':ecn' => $data['emergency_contact_name'] ?? null,
            ':ecn_no' => $data['emergency_contact_number'] ?? null,
            ':mh' => $data['medical_history'] ?? null,
            ':allergies' => $data['allergies'] ?? null,
            ':pid' => $patientId,
        ]);
    }
}
