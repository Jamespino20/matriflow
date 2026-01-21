<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'secretary'])) {
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

        $pregnancyId = Pregnancy::create([
            'user_id' => $pid,
            'gravidity' => (int)($_POST['gravidity'] ?? 0),
            'parity' => (int)($_POST['parity'] ?? 0),
            'abortion_count' => (int)($_POST['abortion_count'] ?? 0),
            'living_children' => (int)($_POST['living_children'] ?? 0),
            'lmp_date' => $_POST['lmp_date'],
            'estimated_due_date' => $_POST['edd']
        ]);

        AuditLogger::log((int)Auth::user()['user_id'], 'pregnancies', 'INSERT', (int)$pregnancyId, 'New pregnancy episode started for user ID ' . $pid);

        // Automated Billing for Baseline/Enrollment
        PricingService::chargePrenatalService($pid, 'enrollment', !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null);

        if ($isAjax) {
            echo json_encode(['ok' => true]);
            exit;
        }
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?: '/public/doctor/records.php';
        $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
        redirect($redirectUrl . $separator . 'success=1');
    }

    if ($action === 'add_visit') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $pid = (int)($_POST['patient_id'] ?? 0);
        $bid = (int)($_POST['baseline_id'] ?? 0);
        if (!$bid) throw new Exception('Active pregnancy baseline not found.');

        // [ANTI-DUPLICATE] Check if identical data was added for this baseline in the last 60 seconds
        $fh = (float)($_POST['fundal_height'] ?? 0);
        $fhr = (int)($_POST['fhr'] ?? 0);

        $duplicateCheck = db()->prepare("SELECT observation_id FROM prenatal_observations 
                                        WHERE pregnancy_id = ? AND fundal_height_cm = ? AND fetal_heart_rate = ? 
                                        AND recorded_at >= (NOW() - INTERVAL 1 MINUTE)");
        $duplicateCheck->execute([$bid, $fh, $fhr]);
        if ($duplicateCheck->fetch()) {
            // Silently ignore or redirect as success (it's already there)
            error_log("Ignored duplicate prenatal visit submission for baseline #$bid");
        } else {
            $visitId = PrenatalObservation::create([
                'user_id' => $pid,
                'pregnancy_id' => $bid,
                'fundal_height_cm' => $fh,
                'fetal_heart_rate' => $fhr,
                'fetal_movement_noted' => isset($_POST['fetal_movement']) ? 1 : 0
            ]);

            if (!empty($_POST['next_visit'])) {
                Pregnancy::updateNextVisit($bid, $_POST['next_visit']);
                // [NEW] Auto-schedule next visit
                AppointmentController::autoBook($pid, $_POST['next_visit'], 'prenatal');
            }

            AuditLogger::log((int)Auth::user()['user_id'], 'prenatal_observations', 'INSERT', (int)$visitId, 'Prenatal observation recorded');

            // Automated Billing for Routine Visit
            PricingService::chargePrenatalService($pid, 'visit', !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null);
        }

        if ($isAjax) {
            echo json_encode(['ok' => true]);
            exit;
        }

        $defaultPage = Auth::user()['role'] === 'secretary' ? '/public/secretary/records.php' : '/public/doctor/records.php';
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?: base_url($defaultPage . "?patient_id=$pid");

        // Clean up success/error/action from referer to prevent growth
        $redirectUrl = preg_replace('/[?&](success|error|action)=[^&]*/', '', $redirectUrl);
        $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
        redirect($redirectUrl . $separator . 'success=1');
    }

    if ($action === 'save_vitals') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');

        $pid = (int)($_POST['patient_id'] ?? 0);
        if (!$pid) throw new Exception('Patient ID is required.');

        $data = [
            'systolic' => (int)($_POST['systolic'] ?? 0),
            'diastolic' => (int)($_POST['diastolic'] ?? 0),
            'heart_rate' => (int)($_POST['heart_rate'] ?? 0),
            'weight_kg' => (float)($_POST['weight'] ?? 0),
            'temperature_celsius' => (float)($_POST['temp'] ?? 0)
        ];

        VitalSigns::create($pid, $data);

        AuditLogger::log((int)Auth::user()['user_id'], 'vital_signs', 'INSERT', $pid, "Vitals recorded for patient ID $pid");

        if ($isAjax) {
            echo json_encode(['ok' => true]);
            exit;
        }
        $defaultPage = Auth::user()['role'] === 'secretary' ? '/public/secretary/records.php' : '/public/doctor/records.php';
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?: base_url($defaultPage . "?patient_id=$pid");
        $redirectUrl = preg_replace('/[?&](success|error|action)=[^&]*/', '', $redirectUrl);
        $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
        redirect($redirectUrl . $separator . 'success=vitals');
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    $pId = (int)($_POST['patient_id'] ?? 0);
    $defaultPage = Auth::user()['role'] === 'secretary' ? '/public/secretary/records.php' : '/public/doctor/records.php';
    $redirectUrl = $_SERVER['HTTP_REFERER'] ?: base_url($defaultPage . ($pId ? "?patient_id=$pId" : ""));
    $redirectUrl = preg_replace('/[?&](success|error|action)=[^&]*/', '', $redirectUrl);
    $separator = (strpos($redirectUrl, '?') !== false) ? '&' : '?';
    redirect($redirectUrl . $separator . 'error=' . urlencode($e->getMessage()));
}
