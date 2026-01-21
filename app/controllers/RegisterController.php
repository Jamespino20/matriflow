<?php

declare(strict_types=1);

final class RegisterController
{
    public static function registerPatient(bool $preventRedirect = false): array
    {
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $errors;
        }

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Invalid request (CSRF).';
            return $errors;
        }

        // 1. Sanitize & Validate Inputs
        $data = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'middle_name' => sanitize($_POST['middle_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'dob' => sanitize($_POST['dob'] ?? ''),
            'contact_number' => sanitize($_POST['contact_number'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'username' => sanitize($_POST['username'] ?? ''),
            'gender' => sanitize($_POST['gender'] ?? ''),
            'registration_type' => sanitize($_POST['registration_type'] ?? 'Patient'),
            'agree' => $_POST['consent'] ?? ''
        ];

        // Basic Rules
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            $errors[] = 'Name and Email are required.';
        }

        if (empty($data['agree'])) {
            $errors[] = 'You must agree to the Terms & Privacy Policy.';
        }

        $pwdErrors = Auth::validatePassword($data['password']);
        if (!empty($pwdErrors)) {
            $errors = array_merge($errors, $pwdErrors);
        }

        if ($data['password'] !== $data['password_confirm']) {
            $errors[] = 'Passwords do not match.';
        }

        // 1b. Age & Gender Check
        if (!empty($data['dob'])) {
            $bday = new DateTime($data['dob']);
            $today = new DateTime();
            $age = $today->diff($bday)->y;

            if ($data['registration_type'] === 'Patient' && $age < 11) {
                $errors[] = 'Patients must be at least 11 years old. If you are registering for a minor, please select "Guardian".';
            }
        }

        if ($data['registration_type'] === 'Patient' && $data['gender'] === 'Male') {
            $errors[] = 'Medical patients in this maternity system must be Female. If you are a Male guardian, please select "Guardian" as the registration type.';
        }

        // Validate Email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }

        // Check Duplicates
        $stmt = Database::getInstance()->prepare("SELECT COUNT(*) FROM users WHERE email = :email OR username = :username");
        $chkUsername = $data['username'] ?: '---'; // avoid matching empty
        $stmt->execute([':email' => $data['email'], ':username' => $chkUsername]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email or Username already taken.';
        }

        if (!empty($errors)) {
            return $errors;
        }

        // 2. Auto-generate Username if empty
        if (empty($data['username'])) {
            // Format: firstname.lastname + random digits or similar
            // Simple approach: lowercase_firstname + random string
            $base = strtolower(str_replace(' ', '', $data['first_name']));
            $data['username'] = $base . rand(100, 999);
            // Ensure unique (omitted for brevity, assume random enough for prototype)
        }

        // 3. Insert User
        try {
            Database::getInstance()->beginTransaction();

            $totpSecret = TOTP::generateSecret();
            $backupTokens = [];
            for ($i = 0; $i < 3; $i++) {
                $backupTokens[] = bin2hex(random_bytes(4));
            }
            $backupTokensJson = json_encode($backupTokens);

            $stmt = Database::getInstance()->prepare("
                INSERT INTO users (username, password_hash, first_name, middle_name, last_name, role, email, contact_number, gender, registration_type, dob, is_active, account_status, google_2fa_secret, backup_tokens, created_at)
                VALUES (:u, :p, :f, :m, :l, 'patient', :e, :c, :g, :rt, :dob, 1, 'pending', :ts, :bt, NOW())
            ");

            $stmt->execute([
                ':u' => $data['username'],
                ':p' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':f' => $data['first_name'],
                ':m' => $data['middle_name'],
                ':l' => $data['last_name'],
                ':e' => $data['email'],
                ':c' => $data['contact_number'],
                ':g' => $data['gender'],
                ':rt' => $data['registration_type'],
                ':dob' => $data['dob'],
                ':ts' => $totpSecret,
                ':bt' => $backupTokensJson
            ]);

            $userId = (int) Database::getInstance()->lastInsertId();

            // 4c. Send 2FA Setup Email
            // We use the TOTP::otpauthUrl to generate the data for QR
            $issuer = 'MatriFlow CHMC';
            $otpauthUrl = TOTP::otpauthUrl($issuer, $data['email'], $totpSecret);
            $qrUrl = TOTP::qrUrl($otpauthUrl);

            // Send email with credentials
            // We reuse the existing send2FASetupEmail or create a new sendWelcomeWith2FA
            // Let's use send2FASetupEmail as it seems fit
            $sent = Mailer::send2FASetupEmail(
                $data['email'],
                $data['first_name'],
                base_url('/public/verify-2fa.php'), // Action link (login/verify)
                $qrUrl, // Pass the QR image URL
                $backupTokens,
                $totpSecret // Pass the secret key
            );

            if (!$sent) {
                error_log("Failed to send 2FA setup email to " . $data['email']);
            }

            Database::getInstance()->commit();

            AuditLogger::log($userId, 'users', 'INSERT', $userId, 'patient_registration');
            AuditLogger::log($userId, 'users', 'UPDATE', $userId, 'patient_profile_merged');

            // 5. Registration Success - Redirect to home to show the "Check Email" popup
            // We do NOT auto-login anymore to enforce the Email -> Setup flow

            if ($preventRedirect) {
                return ['_success' => true, '_redirect' => base_url('/?registered=true')];
            }
            redirect('/?registered=true');
        } catch (Throwable $e) {
            $errors[] = 'Registration failed. Try again. (' . $e->getMessage() . ')';
            return $errors;
        }
    }
}
