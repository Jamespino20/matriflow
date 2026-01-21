<?php

declare(strict_types=1);

final class Patient
{
    public static function createForUser(int $userId, ?string $dob, ?int $ageAtReg = null): bool
    {
        // In the new schema, we update the existing users record instead of inserting into a new table
        $sql = "UPDATE users SET dob = :dob, age_at_registration = :age WHERE user_id = :uid";
        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute([
            ':uid' => $userId,
            ':dob' => $dob ?: null,
            ':age' => $ageAtReg,
        ]);
    }

    public static function findById(int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE user_id = :uid AND role = 'patient' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByUserId(int $userId): ?array
    {
        return self::findById($userId);
    }

    public static function getPatientIdForUser(int $userId): ?int
    {
        // In consolidated schema, user_id IS the patient_id
        return $userId;
    }

    public static function updateProfileBasic(int $userId, array $data): bool
    {
        $sql = "UPDATE users
            SET address = :address,
                marital_status = :marital_status,
                occupation = :occupation,
                emergency_contact_name = :ecn,
                emergency_contact_number = :ecn_no,
                medical_history = :mh,
                allergies = :allergies
            WHERE user_id = :uid AND role = 'patient' AND deleted_at IS NULL";
        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute([
            ':address' => $data['address'] ?? null,
            ':marital_status' => $data['marital_status'] ?? null,
            ':occupation' => $data['occupation'] ?? null,
            ':ecn' => $data['emergency_contact_name'] ?? null,
            ':ecn_no' => $data['emergency_contact_number'] ?? null,
            ':mh' => $data['medical_history'] ?? null,
            ':allergies' => $data['allergies'] ?? null,
            ':uid' => $userId,
        ]);
    }
}
