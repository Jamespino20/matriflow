<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || Auth::user()['role'] !== 'doctor') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_baseline') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $pid = (int)($_POST['patient_id'] ?? 0);
        if (!$pid) throw new Exception('Patient ID is required.');

        $baselineId = PrenatalBaseline::create([
            'patient_id' => $pid,
            'gravidity' => (int)($_POST['gravidity'] ?? 0),
            'parity' => (int)($_POST['parity'] ?? 0),
            'abortion_count' => (int)($_POST['abortion_count'] ?? 0),
            'living_children' => (int)($_POST['living_children'] ?? 0),
            'lmp_date' => $_POST['lmp_date'],
            'estimated_due_date' => $_POST['edd']
        ]);

        AuditLogger::log((int)Auth::user()['user_id'], 'prenatal_baseline', 'INSERT', (int)$baselineId, 'Prenatal baseline started for patient ID ' . $pid);

        if ($isAjax) {
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/doctor/records.php?patient_id=' . $pid . '&success=1');
    }

    if ($action === 'add_visit') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $pid = (int)($_POST['patient_id'] ?? 0);
        $bid = (int)($_POST['baseline_id'] ?? 0);
        if (!$bid) throw new Exception('Active pregnancy baseline not found.');

        $visitId = PrenatalVisit::create([
            'patient_id' => $pid,
            'prenatal_baseline_id' => $bid,
            'fundal_height_cm' => (float)($_POST['fundal_height'] ?? 0),
            'fetal_heart_rate' => (int)($_POST['fhr'] ?? 0),
            'fetal_movement_noted' => isset($_POST['fetal_movement']) ? 1 : 0
        ]);

        AuditLogger::log((int)Auth::user()['user_id'], 'prenatal_visit', 'INSERT', (int)$visitId, 'Prenatal visit recorded');

        if ($isAjax) {
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/doctor/records.php?patient_id=' . $pid . '&success=1');
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    redirect('/public/doctor/records.php?patient_id=' . ($_POST['patient_id'] ?? '') . '&error=' . urlencode($e->getMessage()));
}
