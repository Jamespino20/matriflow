<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (isset($_GET['ajax']) || isset($_POST['ajax']));

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_user') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $role = $_POST['role'] ?? 'patient';

        if (!$username || !$email || !$password || !$firstName || !$lastName) {
            throw new Exception('All fields are required.');
        }

        $maritalStatus = $_POST['marital_status'] ?? null;
        if ($maritalStatus && !in_array($maritalStatus, User::MARITAL_STATUSES)) {
            throw new Exception('Invalid marital status.');
        }

        if (User::usernameExists($username)) throw new Exception('Username already taken.');
        if (User::emailExists($email)) throw new Exception('Email already registered.');

        // Age Validation Logic
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        if ($dob) {
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;

            if ($role === 'patient') {
                if ($age < 13) throw new Exception('Patients must be at least 13 years old.');
            } else {
                if ($age < 21) throw new Exception('Staff and Admin accounts require users to be at least 21 years old.');
            }
        }

        $userId = User::create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'middle_name' => trim((string)($_POST['middle_name'] ?? '')),
            'last_name' => $lastName,
            'role' => $role,
            'gender' => $_POST['gender'] ?? null,
            'marital_status' => $_POST['marital_status'] ?? null,
            'dob' => !empty($_POST['dob']) ? $_POST['dob'] : null,
            'contact_number' => $_POST['contact_number'] ?? null,
            'address' => $_POST['address'] ?? null,
            'identification_number' => !empty($_POST['identification_number']) ? trim($_POST['identification_number']) : null,
            'occupation' => !empty($_POST['occupation']) ? trim($_POST['occupation']) : null,
            'city' => !empty($_POST['city']) ? trim($_POST['city']) : null,
            'province' => !empty($_POST['province']) ? trim($_POST['province']) : null,
            'emergency_contact_name' => !empty($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name']) : null,
            'emergency_contact_number' => !empty($_POST['emergency_contact_number']) ? trim($_POST['emergency_contact_number']) : null
        ]);

        // Auto-generate Identification Number if not specified (redundant field fix)
        User::update($userId, [
            'identification_number' => 'MF-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT)
        ]);

        AuditLogger::log((int)$u['user_id'], 'users', 'INSERT', $userId, "New user created: $username ($role)");

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'user_id' => $userId]);
            exit;
        }
        redirect('/public/admin/user-management.php?success=1');
    }

    if ($action === 'update_user') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $userId = (int)($_POST['target_user_id'] ?? 0);
        if (!$userId) throw new Exception('Invalid user ID.');

        $targetUser = User::findById($userId);
        if (!$targetUser) throw new Exception('User not found.');

        $updateData = [];
        if (!empty($_POST['first_name'])) $updateData['first_name'] = trim($_POST['first_name']);
        if (isset($_POST['middle_name'])) $updateData['middle_name'] = trim($_POST['middle_name']);
        if (!empty($_POST['last_name'])) $updateData['last_name'] = trim($_POST['last_name']);
        if (!empty($_POST['email'])) {
            $email = trim($_POST['email']);
            if ($email !== $targetUser['email'] && User::emailExists($email)) throw new Exception('Email already taken.');
            $updateData['email'] = $email;
        }
        if (!empty($_POST['role'])) $updateData['role'] = $_POST['role'];
        if (isset($_POST['is_active'])) $updateData['is_active'] = (int)$_POST['is_active'];

        // New fields
        if (isset($_POST['gender'])) $updateData['gender'] = $_POST['gender'];
        if (isset($_POST['marital_status'])) {
            $ms = $_POST['marital_status'];
            if ($ms !== '' && !in_array($ms, User::MARITAL_STATUSES)) throw new Exception('Invalid marital status.');
            $updateData['marital_status'] = $ms === '' ? null : $ms;
        }
        if (isset($_POST['dob'])) {
            $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
            if ($dob) {
                // Determine effective role for validation
                $effectiveRole = $_POST['role'] ?? $targetUser['role'];
                $birthDate = new DateTime($dob);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;

                if ($effectiveRole === 'patient') {
                    if ($age < 13) throw new Exception('Patients must be at least 13 years old.');
                } else {
                    if ($age < 21) throw new Exception('Staff and Admin accounts require users to be at least 21 years old.');
                }
            }
            $updateData['dob'] = $dob;
        }
        if (isset($_POST['contact_number'])) $updateData['contact_number'] = $_POST['contact_number'];
        if (isset($_POST['address'])) $updateData['address'] = $_POST['address'];
        if (isset($_POST['identification_number'])) {
            $val = trim($_POST['identification_number']);
            $updateData['identification_number'] = $val === '' ? null : $val;
        }
        if (isset($_POST['occupation'])) {
            $val = trim($_POST['occupation']);
            $updateData['occupation'] = $val === '' ? null : $val;
        }
        if (isset($_POST['city'])) {
            $val = trim($_POST['city']);
            $updateData['city'] = $val === '' ? null : $val;
        }
        if (isset($_POST['province'])) {
            $val = trim($_POST['province']);
            $updateData['province'] = $val === '' ? null : $val;
        }
        if (isset($_POST['emergency_contact_name'])) {
            $val = trim($_POST['emergency_contact_name']);
            $updateData['emergency_contact_name'] = $val === '' ? null : $val;
        }
        if (isset($_POST['emergency_contact_number'])) {
            $val = trim($_POST['emergency_contact_number']);
            $updateData['emergency_contact_number'] = $val === '' ? null : $val;
        }

        if (empty($updateData)) throw new Exception('No changes provided.');

        User::update($userId, $updateData);
        AuditLogger::log((int)$u['user_id'], 'users', 'UPDATE', $userId, "User updated: " . json_encode($updateData));

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/admin/user-management.php?success=1');
    }

    if ($action === 'toggle_status') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$userId) throw new Exception('Invalid user ID.');
        if ($userId === (int)$u['user_id']) throw new Exception('Cannot deactivate yourself.');

        $targetUser = User::findById($userId);
        if (!$targetUser) throw new Exception('User not found.');

        $newStatus = (int)$targetUser['is_active'] === 1 ? 0 : 1;
        User::update($userId, ['is_active' => $newStatus]);

        AuditLogger::log((int)$u['user_id'], 'users', 'UPDATE', $userId, "User status toggled to " . ($newStatus ? 'Active' : 'Inactive'));

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'new_status' => $newStatus]);
            exit;
        }
        redirect('/public/admin/user-management.php?success=1');
    }

    if ($action === 'reset_2fa') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$userId) throw new Exception('Invalid user ID.');

        $targetUser = User::findById($userId);
        if (!$targetUser) throw new Exception('User not found.');

        User::update($userId, [
            'google_2fa_secret' => null,
            'is_2fa_enabled' => 0,
            'force_2fa_setup' => 1
        ]);

        AuditLogger::log((int)$u['user_id'], 'users', 'UPDATE', $userId, "Two-factor authentication reset for user: " . $targetUser['username']);

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/admin/user-management.php?success=1');
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    redirect('/public/admin/user-management.php?error=' . urlencode($e->getMessage()));
}
