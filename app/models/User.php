<?php

declare(strict_types=1);

final class User
{
    public static function findById(int $userId): ?array
    {
        $stmt = db()->prepare("SELECT * FROM user WHERE user_id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByIdentity(string $identity): ?array
    {
        $stmt = db()->prepare("SELECT * FROM user WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute([':u' => $identity, ':e' => $identity]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function usernameExists(string $username): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM user WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare("SELECT 1 FROM user WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        return (bool) $stmt->fetchColumn();
    }

    public static function createPatient(array $data): int
    {
        $sql = "INSERT INTO user
      (username,password_hash,first_name,last_name,role,email,contact_number,is_active,
       force_2fa_setup,is_2fa_enabled,failed_login_attempts)
      VALUES
      (:username,:phash,:first,:last,'patient',:email,:phone,1,1,0,0)";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':phash' => $data['password_hash'],
            ':first' => $data['first_name'],
            ':last' => $data['last_name'],
            ':email' => $data['email'],
            ':phone' => $data['contact_number'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO user
      (username,password_hash,first_name,last_name,role,email,contact_number,is_active,
       force_2fa_setup,is_2fa_enabled,failed_login_attempts)
      VALUES
      (:username,:phash,:first,:last,:role,:email,:phone,1,1,0,0)";
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':username' => $data['username'],
            ':phash' => $data['password_hash'],
            ':first' => $data['first_name'],
            ':last' => $data['last_name'],
            ':role' => $data['role'],
            ':email' => $data['email'],
            ':phone' => $data['contact_number'] ?? null,
        ]);
        return (int) db()->lastInsertId();
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
        $sql = "UPDATE user SET " . implode(', ', $sets) . " WHERE user_id = :id";
        return db()->prepare($sql)->execute($params);
    }

    public static function deactivate(int $userId): bool
    {
        return db()->prepare("UPDATE user SET is_active = 0 WHERE user_id = ?")->execute([$userId]);
    }

    public static function activate(int $userId): bool
    {
        return db()->prepare("UPDATE user SET is_active = 1 WHERE user_id = ?")->execute([$userId]);
    }

    public static function setLastLogin(int $userId): void
    {
        $stmt = db()->prepare("UPDATE user SET last_login_at = NOW(), failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);
    }

    public static function recordFailedLogin(int $userId): void
    {
        $stmt = db()->prepare("UPDATE user SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = :id");
        $stmt->execute([':id' => $userId]);

        $row = self::findById($userId);
        if (!$row)
            return;

        if ((int) $row['failed_login_attempts'] >= LOGIN_LOCK_AFTER) {
            $stmt2 = db()->prepare("UPDATE user SET account_locked_until = DATE_ADD(NOW(), INTERVAL " . (int) LOGIN_LOCK_MINUTES . " MINUTE) WHERE user_id = :id");
            $stmt2->execute([':id' => $userId]);
        }
    }
}
