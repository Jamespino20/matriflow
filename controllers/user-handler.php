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

        if (User::usernameExists($username)) throw new Exception('Username already taken.');
        if (User::emailExists($email)) throw new Exception('Email already registered.');

        $userId = User::create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role
        ]);

        AuditLogger::log((int)$u['user_id'], 'user', 'INSERT', $userId, "New user created: $username ($role)");

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
        if (!empty($_POST['last_name'])) $updateData['last_name'] = trim($_POST['last_name']);
        if (!empty($_POST['email'])) {
            $email = trim($_POST['email']);
            if ($email !== $targetUser['email'] && User::emailExists($email)) throw new Exception('Email already taken.');
            $updateData['email'] = $email;
        }
        if (!empty($_POST['role'])) $updateData['role'] = $_POST['role'];
        if (isset($_POST['is_active'])) $updateData['is_active'] = (int)$_POST['is_active'];

        if (empty($updateData)) throw new Exception('No changes provided.');

        User::update($userId, $updateData);
        AuditLogger::log((int)$u['user_id'], 'user', 'UPDATE', $userId, "User updated: " . json_encode($updateData));

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

        AuditLogger::log((int)$u['user_id'], 'user', 'UPDATE', $userId, "User status toggled to " . ($newStatus ? 'Active' : 'Inactive'));

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'new_status' => $newStatus]);
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
