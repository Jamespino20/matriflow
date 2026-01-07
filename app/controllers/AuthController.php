<?php

declare(strict_types=1);

final class AuthController
{
    public static function login(bool $preventRedirect = false): array
    {
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return $errors;

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Invalid request (CSRF).';
            return $errors;
        }

        // Support both 'identity' and 'username' for backward compatibility
        $identity = trim((string) ($_POST['identity'] ?? $_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identity === '' || $password === '') {
            $errors[] = 'All fields are required.';
            return $errors;
        }

        $user = User::findByIdentity($identity);
        if (!$user) {
            $errors[] = 'Invalid username/email or password.';
            return $errors;
        }

        if ((int) $user['is_active'] !== 1) {
            $errors[] = 'Account is inactive.';
            return $errors;
        }

        if (!empty($user['account_locked_until'])) {
            $lock = strtotime((string) $user['account_locked_until']);
            if ($lock && $lock > time()) {
                $errors[] = 'Account temporarily locked. Try again later.';
                return $errors;
            }
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            User::recordFailedLogin((int) $user['user_id']);
            AuditLogger::log((int) $user['user_id'], 'user', 'LOGIN', (int) $user['user_id'], 'failed_login');
            $errors[] = 'Invalid username/email or password.';
            return $errors;
        }

        User::setLastLogin((int) $user['user_id']);
        Auth::loginUser($user);
        AuditLogger::log((int) $user['user_id'], 'user', 'LOGIN', (int) $user['user_id'], 'success');

        // 2FA gate
        if (Auth::requires2FA($user)) {
            $nextUrl = '/public/verify-2fa.php';
            if ((int) $user['is_2fa_enabled'] === 0 || (int) $user['force_2fa_setup'] === 1) {
                // Check if login was from login.php or index (fallback)
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                // Since login.php is gone, we mostly redirect to /?setup2fa=1 for non-ajax
                if (strpos($referer, 'setup-2fa') === false) {
                    $nextUrl = '/?setup2fa=1';
                } else {
                    $nextUrl = '/public/setup-2fa.php';
                }
            } else {
                $nextUrl = '/?verify2fa=1';
            }

            if ($preventRedirect) {
                // Return special error/status for controller to handle
                return ['_redirect' => base_url($nextUrl), '_2fa_required' => true];
            }
            redirect($nextUrl);
        }

        // Route to role-based dashboard
        $role = (string) ($user['role'] ?? 'patient');
        $dashboards = [
            'patient' => '/public/patient/dashboard.php',
            'secretary' => '/public/secretary/dashboard.php',
            'doctor' => '/public/doctor/dashboard.php',
            'admin' => '/public/admin/dashboard.php',
        ];
        $dest = $dashboards[$role] ?? '/public/patient/dashboard.php';

        if ($preventRedirect) {
            return ['_redirect' => base_url($dest), '_success' => true];
        }
        redirect($dest);
    }
}
