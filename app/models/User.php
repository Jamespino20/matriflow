<?php

declare(strict_types=1);

final class User
{
    public const MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];

    public static function findById(int $userId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE user_id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByIdentity(string $identity): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM users WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute([':u' => $identity, ':e' => $identity]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function usernameExists(string $username): bool
    {
        $stmt = Database::getInstance()->prepare("SELECT 1 FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public static function emailExists(string $email): bool
    {
        $stmt = Database::getInstance()->prepare("SELECT 1 FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    public static function createPatient(array $data): int
    {
        $sql = "INSERT INTO users
      (username,password_hash,first_name,middle_name,last_name,role,email,contact_number,is_active,
       force_2fa_setup,is_2fa_enabled,failed_login_attempts)
      VALUES
      (:username,:phash,:first,:middle,:last,'patient',:email,:phone,1,1,0,0)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':phash' => $data['password_hash'],
            ':first' => $data['first_name'],
            ':middle' => $data['middle_name'] ?? '',
            ':last' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['contact_number'],
        ]);
        return (int) Database::getInstance()->lastInsertId();
    }

    public static function create(array $data): int
    {
        $fields = [
            'username',
            'password_hash',
            'first_name',
            'middle_name',
            'last_name',
            'role',
            'email',
            'gender',
            'dob',
            'contact_number',
            'address',
            'identification_number',
            'occupation',
            'city',
            'province',
            'marital_status',
            'emergency_contact_name',
            'emergency_contact_number'
        ];

        $insertFields = [];
        $placeholders = [];
        $params = [];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $insertFields[] = $field;
                $placeholders[] = ":$field";
                $params[":$field"] = $data[$field];
            }
        }

        // Add defaults
        if (!in_array('is_active', $insertFields)) {
            $insertFields[] = 'is_active';
            $placeholders[] = '1';
        }
        if (!in_array('force_2fa_setup', $insertFields)) {
            $insertFields[] = 'force_2fa_setup';
            $placeholders[] = '1';
        }
        if (!in_array('is_2fa_enabled', $insertFields)) {
            $insertFields[] = 'is_2fa_enabled';
            $placeholders[] = '0';
        }

        $sql = "INSERT INTO users (" . implode(',', $insertFields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        return (int) Database::getInstance()->lastInsertId();
    }

    public static function update(int $userId, array $data): bool
    {
        $sets = [];
        $params = [':id' => $userId];
        foreach ($data as $key => $val) {
            $sets[] = "$key = :$key";
            $params[":$key"] = $val;
        }
        if (empty($sets)) return false;
        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE user_id = :id";
        return Database::getInstance()->prepare($sql)->execute($params);
    }

    public static function deactivate(int $userId): bool
    {
        return Database::getInstance()->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?")->execute([$userId]);
    }

    public static function activate(int $userId): bool
    {
        return Database::getInstance()->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?")->execute([$userId]);
    }

    public static function setLastLogin(int $userId): void
    {
        $stmt = Database::getInstance()->prepare("UPDATE users SET last_login_at = NOW(), failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
    }

    public static function recordFailedLogin(int $userId): void
    {
        $stmt = Database::getInstance()->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);

        $row = self::findById($userId);
        if (!$row)
            return;

        if ((int) $row['failed_login_attempts'] >= LOGIN_LOCK_AFTER) {
            $stmt2 = Database::getInstance()->prepare("UPDATE users SET account_locked_until = DATE_ADD(NOW(), INTERVAL " . (int) LOGIN_LOCK_MINUTES . " MINUTE) WHERE user_id = :id");
            $stmt2->execute([':id' => $userId]);
        }
    }
}
