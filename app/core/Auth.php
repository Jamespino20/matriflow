<?php

declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id']))
            return null;
        $u = User::findById((int) $_SESSION['user_id']);
        return $u ?: null;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function loginUser(array $userRow): void
    {
        SessionManager::regenerate();

        $_SESSION['user_id'] = (int) $userRow['user_id'];
        $_SESSION['role'] = (string) $userRow['role'];
        $_SESSION['two_factor_ok'] = 0; // must be verified if required
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_seen'] = time();

        // DB session tracking
        try {
            $sql = "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at)
              VALUES (:sid, :uid, :ip, :ua, :exp)";
            $stmt = db()->prepare($sql);
            $stmt->execute([
                ':sid' => session_id(),
                ':uid' => (int) $userRow['user_id'],
                ':ip' => client_ip(),
                ':ua' => user_agent(),
                ':exp' => date('Y-m-d H:i:s', time() + SESSION_ABSOLUTE_SECONDS),
            ]);
        } catch (Throwable $e) {
        }
    }

    public static function logout(): void
    {
        $uid = self::check() ? (int) $_SESSION['user_id'] : null;

        if ($uid) {
            try {
                $stmt = db()->prepare("DELETE FROM user_sessions WHERE session_id = :sid");
                $stmt->execute([':sid' => session_id()]);
            } catch (Throwable $e) {
            }
            AuditLogger::log($uid, null, 'LOGOUT', null, null);
        }

        SessionManager::destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check())
            redirect('/');
    }

    public static function is2FAVerifiedThisSession(): bool
    {
        return !empty($_SESSION['two_factor_ok']);
    }

    public static function mark2FAVerified(): void
    {
        $_SESSION['two_factor_ok'] = 1;
        $uid = (int) $_SESSION['user_id'];
        try {
            $stmt = db()->prepare("UPDATE user SET two_factor_verified_at = NOW() WHERE user_id = :uid");
            $stmt->execute([':uid' => $uid]);
        } catch (Throwable $e) {
        }
    }

    public static function requires2FA(array $userRow): bool
    {
        // Force setup for first-time login
        if ((int) $userRow['force_2fa_setup'] === 1)
            return true;

        // If enabled, require verify
        if ((int) $userRow['is_2fa_enabled'] === 1) {
            // inactivity check
            if (!empty($userRow['last_activity_at'])) {
                $last = strtotime((string) $userRow['last_activity_at']);
                if ($last > 0) {
                    $days = (time() - $last) / 86400;
                    if ($days >= INACTIVITY_FORCE_2FA_DAYS)
                        return true;
                }
            }
            return true;
        }

        // Force 2FA for admins
        if ($userRow['role'] === 'admin')
            return true;

        return false;
    }

    public static function enforce2FA(): void
    {
        self::requireLogin();
        $u = self::user();
        if (!$u)
            redirect('/');

        if (!self::requires2FA($u))
            return;

        if (self::is2FAVerifiedThisSession())
            return;

        // Decide where to go
        if ((int) $u['is_2fa_enabled'] === 0 || (int) $u['force_2fa_setup'] === 1) {
            redirect('/public/setup-2fa.php');
        }
        redirect('/public/verify-2fa.php');
    }

    public static function touchActivity(): void
    {
        if (!self::check())
            return;
        $uid = (int) $_SESSION['user_id'];
        try {
            $stmt = db()->prepare("UPDATE user SET last_activity_at = NOW() WHERE user_id = :uid");
            $stmt->execute([':uid' => $uid]);
        } catch (Throwable $e) {
        }
    }
}
