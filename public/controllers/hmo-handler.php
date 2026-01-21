<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!in_array($u['role'], ['admin', 'secretary', 'patient'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (isset($_GET['ajax']) || isset($_POST['ajax']));

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'update_claim_status') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $billingId = (int)($_POST['billing_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $reference = $_POST['reference'] ?? null;

        if (!$billingId || !$status) throw new Exception('Missing required fields.');

        Billing::updateClaimStatus($billingId, $status, $reference);
        AuditLogger::log((int)$u['user_id'], 'billing', 'UPDATE', $billingId, "HMO claim status updated to: $status");

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/' . $u['role'] . '/hmo-claims.php?success=1');
    }

    if ($action === 'submit_claim') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $billingId = (int)($_POST['billing_id'] ?? 0);
        $hmoId = (int)($_POST['hmo_provider_id'] ?? 0);
        $amount = (float)($_POST['claim_amount'] ?? 0);
        $ref = $_POST['reference'] ?? null;

        if (!$billingId || !$hmoId || $amount <= 0) throw new Exception('Invalid claim details.');

        // Ownership check for patients
        if ($u['role'] === 'patient') {
            $bill = Billing::findById($billingId);
            if (!$bill || (int)$bill['user_id'] !== (int)$u['user_id']) {
                throw new Exception('Unauthorized invoice access.');
            }
        }

        Billing::submitHmoClaim($billingId, $hmoId, $amount, $ref);
        AuditLogger::log((int)$u['user_id'], 'billing', 'UPDATE', $billingId, "HMO claim submitted for amount: $amount");

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        $redirectUrl = ($u['role'] === 'patient') ? '/public/patient/payments.php?success=hmo_submitted' : '/public/' . $u['role'] . '/hmo-claims.php?success=submitted';
        redirect($redirectUrl);
    }

    if ($action === 'create_provider') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');
        if ($u['role'] !== 'admin') throw new Exception('Unauthorized.');

        $name = trim($_POST['name'] ?? '');
        $shortName = trim($_POST['short_name'] ?? '');
        if (!$name || !$shortName) throw new Exception('Name and Short Name are required.');

        HmoProvider::create($name, $shortName);
        AuditLogger::log((int)$u['user_id'], 'hmo_providers', 'CREATE', 0, "Created HMO Provider: $shortName");

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/admin/hmo-claims.php?tab=providers&success=1');
    }

    if ($action === 'toggle_provider') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');
        if ($u['role'] !== 'admin') throw new Exception('Unauthorized.');

        $hmoId = (int)($_POST['hmo_provider_id'] ?? 0);
        if (!$hmoId) throw new Exception('Invalid provider ID.');

        HmoProvider::toggleActive($hmoId);
        AuditLogger::log((int)$u['user_id'], 'hmo_providers', 'UPDATE', $hmoId, "Toggled HMO Provider status");

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/admin/hmo-claims.php?tab=providers&success=1');
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    $role = $u['role'] ?? 'admin';
    redirect("/public/{$role}/hmo-claims.php?error=" . urlencode($e->getMessage()));
}
