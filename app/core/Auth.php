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
        $sql = "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at)
            VALUES (:sid, :uid, :ip, :ua, :exp)";
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute([
            ':sid' => session_id(),
            ':uid' => (int) $userRow['user_id'],
            ':ip' => client_ip(),
            ':ua' => user_agent(),
            ':exp' => date('Y-m-d H:i:s', time() + SESSION_ABSOLUTE_SECONDS),
        ]);
    }

    public static function logout(): void
    {
        $uid = self::check() ? (int) $_SESSION['user_id'] : null;

        if ($uid) {
            $stmt = Database::getInstance()->prepare("DELETE FROM user_sessions WHERE session_id = :sid");
            $stmt->execute([':sid' => session_id()]);
        }

        SessionManager::destroy();
    }

    public static function requireLogin(): void
    {
        // Prevent browser caching of authenticated pages
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

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
        $stmt = Database::getInstance()->prepare("UPDATE users SET two_factor_verified_at = NOW() WHERE user_id = :uid");
        $stmt->execute([':uid' => $uid]);
    }

    public static function requires2FA(array $userRow): bool
    {
        // Force setup for first-time login or if explicit flag set
        if ((int) $userRow['force_2fa_setup'] === 1)
            return true;

        // If enabled, require verify (with inactivity check)
        if ((int) $userRow['is_2fa_enabled'] === 1) {
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

        // Mandatory 2FA for ALL roles (Admin, Doctor, Secretary, Patient)
        // If we reach here, it means is_2fa_enabled is 0 and force_2fa_setup is 0.
        // We still return true to ensure they are pushed to the setup flow.
        return true;
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
        $stmt = Database::getInstance()->prepare("UPDATE users SET last_activity_at = NOW() WHERE user_id = :uid");
        $stmt->execute([':uid' => $uid]);
    }

    /**
     * Validate password strength with spam/common password checking
     * Returns array of error messages (empty if valid)
     */
    public static function validatePassword(string $p): array
    {
        $errors = [];

        if (strlen($p) < 10) $errors[] = 'Password must be at least 10 characters long.';
        if (!preg_match('/[A-Z]/', $p)) $errors[] = 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[a-z]/', $p)) $errors[] = 'Password must contain at least one lowercase letter.';
        if (!preg_match('/[0-9]/', $p)) $errors[] = 'Password must contain at least one number.';
        if (!preg_match('/[^A-Za-z0-9]/', $p)) $errors[] = 'Password must contain at least one special character.';

        // Common/weak password blacklist
        $commonPasswords = [
            'password',
            'password123',
            '12345678',
            'qwerty',
            'abc123',
            'Password1!',
            'Welcome1!',
            'Admin123!',
            'letmein',
            'monkey',
            'dragon',
            'master',
            'sunshine',
            'princess',
            'football',
            'iloveyou',
            'shadow',
            'ashley',
            'superman',
            'qwertyuiop',
            '123456',
            '123456789',
            '12345',
            '1234567890',
            '1234567',
            '111111',
            '000000',
            '123123',
            '654321',
            '11111111',
            '123321',
            'qwerty123',
            'qwerty1',
            '1q2w3e',
            '1q2w3e4r',
            '1qaz2wsx',
            'asdfghjkl',
            'asdf',
            'admin',
            'secret',
            'test',
            'welcome',
            'hello',
            'charlie',
            'soccer',
            'baseball',
            'basketball',
            'hockey',
            'starwars',
            'batman',
            'pokemon',
            'jordan',
            'summer',
            'michael',
            'daniel',
            'jessica',
            'andrew',
            'eva',
            'alex',
            'anna',
            'password1',
            'iloveyou1',
            '123456a'
        ];

        $lowerPassword = strtolower($p);
        foreach ($commonPasswords as $common) {
            if ($lowerPassword === strtolower($common)) {
                $errors[] = 'This password is too common. Please choose a more unique password.';
                break;
            }
        }

        // Check for repeated characters (e.g., "aaaa", "1111")
        if (preg_match('/(.)\1{3,}/', $p)) {
            $errors[] = 'Password should not contain repeated characters (e.g., "aaaa").';
        }

        // Check for sequential characters (e.g., "1234", "abcd")
        if (preg_match('/(?:0123|1234|2345|3456|4567|5678|6789|abcd|bcde|cdef|defg|efgh|fghi|ghij|hijk|ijkl|jklm|klmn|lmno|mnop|nopq|opqr|pqrs|qrst|rstu|stuv|tuvw|uvwx|vwxy|wxyz)/i', $p)) {
            $errors[] = 'Password should not contain sequential characters (e.g., "1234", "abcd").';
        }

        return $errors;
    }
}
