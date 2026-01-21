<?php
require_once __DIR__ . '/../../bootstrap.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (isset($_GET['ajax']) || isset($_POST['ajax'])) ||
    (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if (!Auth::check()) {
    if ($isAjax) {
        echo json_encode(['ok' => false, 'message' => 'Session expired.']);
        exit;
    }
    redirect('/public/login.php');
}

$u = Auth::user();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'send') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF token.');

        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $body = trim((string)($_POST['message'] ?? ''));

        if (!$receiverId || !$body) throw new Exception('Receiver and message body are required.');

        $msgId = MessageService::sendMessage((int)$u['user_id'], $receiverId, $body);

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message_id' => $msgId]);
            exit;
        }
        redirect('/public/shared/messages.php?chat=' . $receiverId);
    }

    if ($action === 'fetch_history') {
        $otherId = (int)($_GET['with'] ?? 0);
        if (!$otherId) throw new Exception('Specify a contact.');

        $history = MessageService::getConversation((int)$u['user_id'], $otherId);
        MessageService::markAsRead((int)$u['user_id'], $otherId);

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'history' => $history]);
        exit;
    }

    if ($action === 'fetch_new') {
        $otherId = (int)($_GET['with'] ?? 0);
        $lastId = (int)($_GET['last_id'] ?? 0);
        if (!$otherId) throw new Exception('Specify a contact.');

        $newMessages = MessageService::getNewMessages((int)$u['user_id'], $otherId, $lastId);
        if (!empty($newMessages)) {
            MessageService::markAsRead((int)$u['user_id'], $otherId);
        }

        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'messages' => $newMessages]);
        exit;
    }

    if ($action === 'search_users') {
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 2) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'users' => []]);
            exit;
        }

        $stmt = db()->prepare("SELECT u.user_id, u.first_name, u.last_name, u.role FROM users u 
                               WHERE (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR u.username LIKE :q3 OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q4) 
                               AND u.is_active = 1 AND u.user_id != :uid LIMIT 15");
        $stmt->execute([':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%", ':uid' => (int)$u['user_id']]);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'users' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'search_patients') {
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 2) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'patients' => []]);
            exit;
        }

        $stmt = db()->prepare("SELECT u.user_id, u.first_name, u.last_name, u.identification_number 
                               FROM users u
                               WHERE u.role = 'patient' AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 
                                      OR u.identification_number LIKE :q3 
                                      OR CONCAT(u.first_name, ' ', u.last_name) LIKE :q4) 
                               AND u.is_active = 1 LIMIT 15");
        $stmt->execute([':q1' => "%$q%", ':q2' => "%$q%", ':q3' => "%$q%", ':q4' => "%$q%"]);
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'patients' => $stmt->fetchAll()]);
        exit;
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    redirect('/public/shared/messages.php?error=' . urlencode($e->getMessage()));
}
