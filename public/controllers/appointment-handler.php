<?php
require_once __DIR__ . '/../../bootstrap.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
    (isset($_GET['ajax']) || isset($_POST['ajax']));

if (!Auth::check()) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Session expired.']);
        exit;
    }
    redirect('/public/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid method.']);
        exit;
    }
    redirect('/');
}

if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    $_SESSION['flash_appointment_error'] = 'Invalid CSRF token or session expired. Please retry.';
    redirect('/public/patient/appointments.php');
}

$u = Auth::user();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'book') {
        $isAdminBooking = !empty($_POST['is_admin_booking']);

        if ($isAdminBooking) {
            // Secretary or admin booking for a patient
            if (!in_array($u['role'], ['secretary', 'admin'])) {
                throw new Exception('Only staff can use admin booking.');
            }

            $patientId = (int)($_POST['patient_id'] ?? 0);
            if (!$patientId) throw new Exception('Please select a patient.');

            $dateTime = $_POST['appointment_date'] ?? '';
            $purpose = trim((string)($_POST['purpose'] ?? ''));

            if (!$dateTime || !$purpose) throw new Exception('Please fill in all required fields.');

            // Create as scheduled (approved)
            $stmt = db()->prepare("INSERT INTO appointment (patient_id, appointment_purpose, appointment_date, appointment_status) VALUES (?, ?, ?, 'scheduled')");
            $stmt->execute([$patientId, $purpose, $dateTime]);
            $appointmentId = (int)db()->lastInsertId();

            AuditLogger::log((int)$u['user_id'], 'appointment', 'INSERT', $appointmentId, "Staff scheduled appointment for patient #$patientId");

            redirect('/public/secretary/appointments.php?success=booked');
        } else {
            // Patient booking for themselves
            if ($u['role'] !== 'patient') throw new Exception('Only patients can request appointments.');

            $patient = Patient::findByUserId((int)$u['user_id']);
            if (!$patient) throw new Exception('Patient profile not found.');

            $date = $_POST['date'] ?? '';
            $time = $_POST['time'] ?? '';
            $purpose = trim((string)($_POST['purpose'] ?? ''));
            $doctorId = $_POST['doctor_id'] ? (int)$_POST['doctor_id'] : null;

            if (!$date || !$time || !$purpose) throw new Exception('Please fill in all required fields.');

            $dateTime = $date . ' ' . $time;
            $appointmentId = Appointment::create((int)$patient['patient_id'], $purpose, $dateTime, $doctorId);

            AuditLogger::log((int)$u['user_id'], 'appointment', 'INSERT', $appointmentId, "Appointment booked for $dateTime");

            if ($isAjax) {
                while (ob_get_level()) ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => 'Appointment scheduled successfully!', 'id' => $appointmentId]);
                exit;
            }
            redirect('/public/patient/appointments.php?success=1');
        }
    }

    if ($action === 'approve' || $action === 'cancel') {
        if (!CSRF::validate($_POST['csrf_token'] ?? null)) throw new Exception('Invalid CSRF.');
        if (Auth::user()['role'] !== 'secretary' && Auth::user()['role'] !== 'admin') throw new Exception('Unauthorized.');

        $aptId = (int)($_POST['appointment_id'] ?? 0);
        $status = ($action === 'approve') ? 'scheduled' : 'cancelled';

        $stmt = db()->prepare("UPDATE appointment SET appointment_status = :status WHERE appointment_id = :id");
        $stmt->execute([':status' => $status, ':id' => $aptId]);

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Appointment status changed to $status");

        // Notify patient
        $apt = db()->prepare("SELECT p.user_id FROM appointment a JOIN patient p ON a.patient_id = p.patient_id WHERE a.appointment_id = :id");
        $apt->execute([':id' => $aptId]);
        $row = $apt->fetch();
        if ($row) {
            NotificationService::create((int)$row['user_id'], 'Appointment Updated', "Your appointment has been " . ($action === 'approve' ? 'approved' : 'cancelled') . ".", 'info', '/public/patient/appointments.php');
        }

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/secretary/appointments.php?success=1');
    }

    if ($action === 'patient_cancel') {
        if ($u['role'] !== 'patient') throw new Exception('Only patients can cancel their own appointments.');

        $aptId = (int)($_POST['appointment_id'] ?? 0);

        // Verify patient owns this appointment
        $patient = Patient::findByUserId((int)$u['user_id']);
        if (!$patient) throw new Exception('Patient profile not found.');

        $apt = db()->prepare("SELECT * FROM appointment WHERE appointment_id = :id AND patient_id = :pid");
        $apt->execute([':id' => $aptId, ':pid' => $patient['patient_id']]);
        $row = $apt->fetch();

        if (!$row) throw new Exception('Appointment not found or you do not have permission to cancel it.');
        if (!in_array($row['appointment_status'], ['pending', 'scheduled'])) {
            throw new Exception('This appointment cannot be cancelled.');
        }

        $stmt = db()->prepare("UPDATE appointment SET appointment_status = 'cancelled' WHERE appointment_id = :id");
        $stmt->execute([':id' => $aptId]);

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Patient cancelled appointment");

        redirect('/public/patient/appointments.php?success=cancelled');
    }

    if ($action === 'checkin') {
        if (!in_array($u['role'], ['secretary', 'admin'])) throw new Exception('Unauthorized.');
        $aptId = (int)($_POST['appointment_id'] ?? 0);

        require_once APP_PATH . '/controllers/QueueController.php';
        if (QueueController::checkIn($aptId)) {
            // Update appointment status to 'checked_in' (if necessary, or QueueController handles status)
            // But usually we keep appointment 'scheduled' or make it 'in-progress'. 
            // QueueController moves it to queue table.
            // Let's assume CheckIn implies "Arrived".

            // Log it
            AuditLogger::log((int)$u['user_id'], 'queue', 'INSERT', $aptId, "Patient checked in to queue");
            redirect('/public/secretary/appointments.php?success=checked_in');
        } else {
            throw new Exception("Failed to check in patient.");
        }
    }

    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
    $_SESSION['flash_appointment_error'] = $e->getMessage();
    redirect('/public/patient/appointments.php');
}
