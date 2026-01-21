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
            AuditLogger::log((int) $user['user_id'], 'users', 'LOGIN', (int) $user['user_id'], 'failed_login');
            $errors[] = 'Invalid username/email or password.';
            return $errors;
        }

        User::setLastLogin((int) $user['user_id']);
        Auth::loginUser($user);
        AuditLogger::log((int) $user['user_id'], 'users', 'LOGIN', (int) $user['user_id'], 'success');

        // 2FA gate
        if (Auth::requires2FA($user)) {
            $nextUrl = '/public/verify-2fa.php';
            if ((int) $user['is_2fa_enabled'] === 0 || (int) $user['force_2fa_setup'] === 1) {
                // Check if login was from login.php or index (fallback)
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                // Enforce Email -> Setup flow by showing the "Registered/Check Email" modal
                // unless already coming from the setup page
                if (strpos($referer, 'setup-2fa') === false) {
                    $nextUrl = '/?registered=true';
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

    public static function logout(): void
    {
        Auth::logout();
        header('Location: ' . base_url('/auth/login.php'));
        exit;
    }

    public static function forgot_password(): array
    {
        $errors = [];
        $success = false;

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['errors' => [], 'success' => false];
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            return ['errors' => ['Invalid request (CSRF).'], 'success' => false];
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['errors' => ['Please enter a valid email address.'], 'success' => false];
        }

        return self::handle_password_reset_request((string)$email);
    }

    public static function resend_reset_password_email(): array
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['errors' => [], 'success' => false];
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            return ['errors' => ['Invalid request (CSRF).'], 'success' => false];
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['errors' => ['Invalid email address.'], 'success' => false];
        }

        return self::handle_password_reset_request((string)$email);
    }

    private static function handle_password_reset_request(string $email): array
    {
        $errors = [];
        $success = false;

        try {
            // Check if user exists
            $stmt = db()->prepare("SELECT user_id, first_name FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

                // Insert into password_reset_tokens table
                $stmt = db()->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at, requested_ip, requested_user_agent) VALUES (?, ?, ?, NOW(), ?, ?)");
                $stmt->execute([$user['user_id'], $hash, $expiry, client_ip(), user_agent()]);

                // Send Email
                $resetLink = base_url("/public/reset-password.php?token=$token&email=" . urlencode($email));
                $sent = Mailer::sendPasswordResetEmail($email, $user['first_name'], $resetLink);

                if ($sent) {
                    $success = true;
                } else {
                    $errors[] = "Failed to send reset email. Please try again later.";
                }
            } else {
                // Mimic success to prevent email enumeration
                $success = true;
            }
        } catch (PDOException $e) {
            error_log("Password Reset Error: " . $e->getMessage());
            $errors[] = "An error occurred. Please try again later.";
        } catch (Exception $e) {
            error_log("Password Reset General Error: " . $e->getMessage());
            $errors[] = "An unexpected error occurred.";
        }

        return ['errors' => $errors, 'success' => $success];
    }

    public static function resend_2fa_setup_email(): array
    {
        if (!Auth::check()) {
            return ['errors' => ['Session expired. Please log in again.'], 'success' => false];
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            return ['errors' => ['Invalid request (CSRF).'], 'success' => false];
        }

        $user = Auth::user();
        if (!$user) {
            return ['errors' => ['User not found.'], 'success' => false];
        }

        // Only allow if 2FA is needed
        if ((int)$user['is_2fa_enabled'] === 1 && (int)$user['force_2fa_setup'] === 0) {
            return ['errors' => ['2FA is already enabled for this account.'], 'success' => false];
        }

        try {
            $email = $user['email'];
            $firstName = $user['first_name'];
            $totpSecret = $user['google_2fa_secret'];
            $backupTokens = json_decode((string)$user['backup_tokens'], true) ?: [];
            $needsUpdate = false;

            // If no secret exists, generate one
            if (empty($totpSecret)) {
                $totpSecret = TOTP::generateSecret();
                $needsUpdate = true;
            }

            // If no backup tokens exist, generate them
            if (empty($backupTokens)) {
                $backupTokens = TOTP::generateBackupTokens();
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $stmt = Database::getInstance()->prepare("UPDATE users SET google_2fa_secret = ?, backup_tokens = ? WHERE user_id = ?");
                $stmt->execute([$totpSecret, json_encode($backupTokens), $user['user_id']]);
            }

            $issuer = 'MatriFlow CHMC';
            $otpauthUrl = TOTP::otpauthUrl($issuer, $email, $totpSecret);
            $qrUrl = TOTP::qrUrl($otpauthUrl);

            $sent = Mailer::send2FASetupEmail(
                $email,
                $firstName,
                base_url('/?verify2fa=1'),
                $qrUrl,
                $backupTokens,
                $totpSecret
            );

            if ($sent) {
                return ['success' => true, 'message' => 'Setup email has been resent successfully.'];
            } else {
                return ['errors' => ['Failed to send email. Please try again later.'], 'success' => false];
            }
        } catch (Throwable $e) {
            error_log("Resend 2FA Error: " . $e->getMessage());
            return ['errors' => ['An error occurred.'], 'success' => false];
        }
    }
}
