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

        if (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
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
        $stmt = db()->prepare("SELECT COUNT(*) FROM user WHERE email = :email OR username = :username");
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
            db()->beginTransaction();

            $stmt = db()->prepare("
                INSERT INTO user (username, password_hash, first_name, last_name, role, email, contact_number, gender, registration_type, is_active, account_status, created_at)
                VALUES (:u, :p, :f, :l, 'patient', :e, :c, :g, :rt, 1, 'pending', NOW())
            ");

            $stmt->execute([
                ':u' => $data['username'],
                ':p' => password_hash($data['password'], PASSWORD_DEFAULT),
                ':f' => $data['first_name'],
                ':l' => $data['last_name'],
                ':e' => $data['email'],
                ':c' => $data['contact_number'],
                ':g' => $data['gender'],
                ':rt' => $data['registration_type']
            ]);

            $userId = (int) db()->lastInsertId();

            // 4. Create Patient Profile
            $stmt = db()->prepare("INSERT INTO patient (user_id, dob, created_at) VALUES (:uid, :dob, NOW())");
            $stmt->execute([
                ':uid' => $userId,
                ':dob' => $data['dob']
            ]);

            db()->commit();

            AuditLogger::log($userId, 'user', 'INSERT', $userId, 'patient_registration');
            AuditLogger::log($userId, 'patient', 'INSERT', 0, 'patient_profile_created');

            // 5. Automatically log in to allow 2FA setup
            $userRow = User::findById($userId);
            if ($userRow) {
                Auth::loginUser($userRow);
                $_SESSION['newuser_2fa'] = true;
            }

            // Redirect or Return - Send to 2FA Setup first
            if ($preventRedirect) {
                return ['_success' => true, '_redirect' => base_url('/?setup2fa=1&newuser=1')];
            }
            redirect('/?setup2fa=1&newuser=1');
        } catch (Throwable $e) {
            $errors[] = 'Registration failed. Try again. (' . $e->getMessage() . ')';
            return $errors;
        }
    }
}
