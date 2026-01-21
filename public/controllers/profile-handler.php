<?php
require_once __DIR__ . '/../../bootstrap.php';
$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_POST['ajax']) && (string) $_POST['ajax'] === '1')
);

if (!Auth::check()) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    redirect(base_url() . '/public/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid request method.']);
        exit;
    }
    redirect(base_url() . '/public/profile.php');
}

if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token or session expired.']);
        exit;
    }
    $_SESSION['flash_profile'] = 'Invalid request.';
    redirect(base_url() . '/public/profile.php');
}

$u = Auth::user();
if (!$u) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    redirect('/');
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_avatar') {
        if (empty($_POST['avatar_data'])) throw new Exception('No avatar data provided.');

        $avatarUrl = FileService::saveAvatar((int)$u['user_id'], $_POST['avatar_data']);

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'avatar_url' => base_url($avatarUrl)]);
            exit;
        }
        redirect(base_url() . '/public/' . $u['role'] . '/profile.php');
    }

    if ($action === 'update_profile') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['contact_number'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $dob = trim((string) ($_POST['dob'] ?? ''));
        $emergencyName = trim((string) ($_POST['emergency_contact_name'] ?? ''));
        $emergencyPhone = trim((string) ($_POST['emergency_contact_number'] ?? ''));

        $city = trim((string) ($_POST['city'] ?? ''));
        $province = trim((string) ($_POST['province'] ?? ''));
        $maritalStatus = $_POST['marital_status'] ?? '';
        $occupation = trim((string) ($_POST['occupation'] ?? ''));
        $nationality = trim((string) ($_POST['nationality'] ?? ''));

        if ($maritalStatus !== '' && !in_array($maritalStatus, User::MARITAL_STATUSES)) {
            throw new Exception('Invalid marital status.');
        }

        // Basic validation
        if ($username === '' || $email === '') throw new Exception('Username and Email are required.');

        // Block Birth Date change if already set (Cybersecurity/Policy Requirement)
        if (!empty($u['dob']) && $dob !== (string)$u['dob']) {
            throw new Exception('Updating birth date is not permitted. Please contact support for corrections.');
        }

        // Check if username/email already taken by others
        $stmt = db()->prepare("SELECT user_id FROM users WHERE (username = :u OR email = :e) AND user_id != :id");
        $stmt->execute([':u' => $username, ':e' => $email, ':id' => (int) $u['user_id']]);
        if ($stmt->fetch()) throw new Exception('Username or Email already in use.');

        db()->beginTransaction();

        // Log sensitive email change
        if ($email !== (string)$u['email']) {
            AuditLogger::log((int)$u['user_id'], 'users', 'UPDATE', (int)$u['user_id'], "Email changed from {$u['email']} to $email");
            NotificationService::alertAccountChange((int)$u['user_id'], "email address");
        }

        // Construct full address for user table (compatibility)
        $fullAddress = $address;
        if ($city) $fullAddress .= ', ' . $city;
        if ($province) $fullAddress .= ', ' . $province;

        // Update Users table
        $stmt = db()->prepare('UPDATE users SET username = :u, email = :e, middle_name = :m, last_name = :l, contact_number = :p, address = :a, city = :c, province = :prov, dob = :d, marital_status = :ms, occupation = :occ, nationality = :nat, emergency_contact_name = :en, emergency_contact_number = :ep WHERE user_id = :id');
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':m' => $_POST['middle_name'] ?? '',
            ':l' => $_POST['last_name'] ?? $u['last_name'],
            ':p' => $phone,
            ':a' => $address,
            ':c' => $city,
            ':prov' => $province,
            ':d' => $dob !== '' ? $dob : (empty($u['dob']) ? null : $u['dob']),
            ':ms' => $_POST['marital_status'] ?? ($u['marital_status'] ?? null),
            ':occ' => $occupation,
            ':nat' => $nationality,
            ':en' => $emergencyName,
            ':ep' => $emergencyPhone,
            ':id' => (int) $u['user_id']
        ]);

        // Merged patient update into main users update above

        // Password change logic
        $cur = $_POST['password_current'] ?? '';
        $new = $_POST['password_new'] ?? '';
        $conf = $_POST['password_confirm'] ?? '';
        if ($cur !== '' || $new !== '' || $conf !== '') {
            if ($new === '' || $conf === '') throw new Exception('New password fields required.');
            if ($new !== $conf) throw new Exception('New passwords do not match.');
            if (!password_verify($cur, (string) $u['password_hash'])) throw new Exception('Current password incorrect.');

            $pwdErrors = Auth::validatePassword($new);
            if (!empty($pwdErrors)) throw new Exception(implode(' ', $pwdErrors));

            $h = password_hash($new, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password_hash = :ph WHERE user_id = :id');
            $stmt->execute([':ph' => $h, ':id' => (int) $u['user_id']]);

            AuditLogger::log((int)$u['user_id'], 'users', 'UPDATE', (int)$u['user_id'], "Password changed");
            NotificationService::alertAccountChange((int)$u['user_id'], "password");
        }

        db()->commit();

        // Refresh session
        $_SESSION['user'] = User::findById((int) $u['user_id']);
        $_SESSION['flash_profile'] = 'Profile updated successfully.';

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message' => 'Profile updated.']);
            exit;
        }
        redirect('/public/' . $u['role'] . '/profile.php');
    }

    if ($action === 'backup_account') {
        $userData = [
            'account' => $u,
            'exported_at' => date('Y-m-d H:i:s')
        ];

        // In a real app, you'd fetch all related data (appointments, medical history, etc.)

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="matriflow_backup_' . $u['username'] . '.json"');
        echo json_encode($userData, JSON_PRETTY_PRINT);
        exit;
    }

    if ($action === 'delete_account') {
        $confirm = $_POST['confirm_delete'] ?? '';
        if ($confirm !== 'DELETE') throw new Exception('Please type DELETE to confirm.');

        // Soft delete
        $stmt = db()->prepare("UPDATE users SET is_active = 0 WHERE user_id = :id");
        $stmt->execute([':id' => (int) $u['user_id']]);

        Auth::logout();
        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'redirect' => base_url('/')]);
            exit;
        }
        redirect('/');
    }
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    $_SESSION['flash_profile'] = $e->getMessage();
    redirect('/public/' . $u['role'] . '/profile.php');
}
