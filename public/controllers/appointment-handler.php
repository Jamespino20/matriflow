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
            $serviceType = $_POST['service_type'] ?? 'general';

            if (!$dateTime || !$purpose) throw new Exception('Please fill in all required fields.');

            // [VALIDATION] Strict Future Date (Tomorrow onwards)
            $apptTimestamp = strtotime($dateTime);
            $tomorrowTimestamp = strtotime('tomorrow 00:00:00');
            if ($apptTimestamp < $tomorrowTimestamp) {
                throw new Exception('Appointments can only be booked for tomorrow onwards.');
            }

            // Create as scheduled (approved)
            $appointmentId = Appointment::create($patientId, $purpose, $dateTime, null);

            AuditLogger::log((int)$u['user_id'], 'appointment', 'INSERT', $appointmentId, "Staff scheduled appointment for patient #$patientId");

            // [ANTI-SABOTAGE] Charge Down Payment with Service Type
            PricingService::chargeAppointmentBooking($patientId, $appointmentId, $serviceType);

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
            $serviceType = $_POST['service_type'] ?? 'general'; // [SERVICE TYPE]

            if (!$date || !$time || !$purpose) throw new Exception('Please fill in all required fields.');

            $dateTime = $date . ' ' . $time;

            // [VALIDATION] Strict Future Date (Tomorrow onwards)
            $apptTimestamp = strtotime($dateTime);
            $tomorrowTimestamp = strtotime('tomorrow 00:00:00');
            if ($apptTimestamp < $tomorrowTimestamp) {
                throw new Exception('Appointments can only be booked for tomorrow onwards.');
            }
            $appointmentId = Appointment::create((int)$patient['user_id'], $purpose, $dateTime, $doctorId);

            AuditLogger::log((int)$u['user_id'], 'appointment', 'INSERT', $appointmentId, "Appointment booked for $dateTime");

            // [ANTI-SABOTAGE] Charge Down Payment with Service Type
            PricingService::chargeAppointmentBooking((int)$patient['user_id'], $appointmentId, $serviceType);

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

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Staff changed appointment status to $status");

        // [ANTI-SABOTAGE] Void bill if cancelled
        if ($action === 'cancel') {
            try {
                $stmtBill = db()->prepare("UPDATE billing SET billing_status = 'voided' WHERE appointment_id = :id AND billing_status = 'unpaid'");
                $stmtBill->execute([':id' => $aptId]);
            } catch (Throwable $e) {
                error_log("Failed to void bill for apt $aptId: " . $e->getMessage());
            }
        }

        // Notify patient
        $apt = db()->prepare("SELECT a.user_id FROM appointment a JOIN users p ON a.user_id = p.user_id WHERE a.appointment_id = :id");
        $apt->execute([':id' => $aptId]);
        $row = $apt->fetch();
        if ($row) {
            NotificationService::create(
                (int)$row['user_id'],
                'general',
                "Appointment Updated: Your appointment has been " . ($action === 'approve' ? 'approved' : 'cancelled') . ".",
                $aptId
            );
        }

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect($_SERVER['HTTP_REFERER'] ?: '/public/secretary/appointments.php?success=1');
    }

    if ($action === 'confirm_recommendation') {
        if ($u['role'] !== 'patient') throw new Exception('Only patients can confirm recommendations.');

        $date = $_POST['date'] ?? '';
        $serviceType = $_POST['service_type'] ?? 'prenatal';

        if (!$date) throw new Exception('Missing recommended date.');

        // Use autoBook with force=true to ensure it works even if schedule is not perfectly set
        $success = AppointmentController::autoBook((int)$u['user_id'], $date, $serviceType, true);

        if (!$success) throw new Exception('Failed to create the appointment. Please contact the clinic.');

        AuditLogger::log((int)$u['user_id'], 'appointment', 'INSERT', 0, "Patient confirmed recommendation for $date");

        redirect('/public/patient/appointments.php?success=booked');
    }

    if ($action === 'reschedule') {
        $aptId = (int)($_POST['appointment_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';

        if (!$aptId || !$date || !$time) throw new Exception('Missing required fields.');

        $newDateTime = $date . ' ' . $time;
        // Patients have 24hr lead time, staff/admins don't (enforceLeadTime = true for patients)
        $enforceLeadTime = !in_array($u['role'], ['secretary', 'admin']);

        $errors = AppointmentController::reschedule($aptId, $newDateTime, $enforceLeadTime);
        if (!empty($errors)) throw new Exception(implode(' ', $errors));

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Rescheduled to $newDateTime");

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'message' => 'Appointment rescheduled!']);
            exit;
        }

        $redirectUrl = ($u['role'] === 'patient') ? '/public/patient/appointments.php?success=rescheduled' : '/public/secretary/appointments.php?success=rescheduled';
        redirect($redirectUrl);
    }

    if ($action === 'patient_cancel') {
        if ($u['role'] !== 'patient') throw new Exception('Only patients can cancel their own appointments.');

        $aptId = (int)($_POST['appointment_id'] ?? 0);

        // Verify patient owns this appointment
        $apt = db()->prepare("SELECT * FROM appointment WHERE appointment_id = :id AND user_id = :uid");
        $apt->execute([':id' => $aptId, ':uid' => (int)$u['user_id']]);
        $row = $apt->fetch();

        if (!$row) throw new Exception('Appointment not found or you do not have permission to cancel it.');
        if (!in_array($row['appointment_status'], ['pending', 'scheduled'])) {
            throw new Exception('This appointment cannot be cancelled.');
        }

        $stmt = db()->prepare("UPDATE appointment SET appointment_status = 'cancelled' WHERE appointment_id = :id");
        $stmt->execute([':id' => $aptId]);

        // [ANTI-SABOTAGE] Void/Cancel the associated Bill
        // Only cancel if it hasn't been paid yet.
        try {
            $stmtBill = db()->prepare("UPDATE billing SET billing_status = 'voided' WHERE appointment_id = :id AND billing_status = 'unpaid'");
            $stmtBill->execute([':id' => $aptId]);
        } catch (Throwable $e) {
            // Ignore if 'cancelled' is not a valid enum, but strictly we should add it.
            // Assuming schema allows valid strings. If not, we might need a migration.
            error_log("Failed to cancel bill for apt $aptId: " . $e->getMessage());
        }

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Patient cancelled appointment");

        redirect('/public/patient/appointments.php?success=cancelled');
    }

    if ($action === 'checkin') {
        if (!in_array($u['role'], ['secretary', 'admin'])) throw new Exception('Unauthorized.');
        $aptId = (int)($_POST['appointment_id'] ?? 0);

        require_once APP_PATH . '/controllers/QueueController.php';
        if (QueueController::checkIn($aptId)) {
            // Update appointment status to 'checked_in' to reflect in the new system
            $stmt = db()->prepare("UPDATE appointment SET appointment_status = 'checked_in' WHERE appointment_id = :id");
            $stmt->execute([':id' => $aptId]);
            // Let's assume CheckIn implies "Arrived".

            // Log it
            AuditLogger::log((int)$u['user_id'], 'queue', 'INSERT', $aptId, "Patient checked in to queue");
            redirect('/public/secretary/appointments.php?success=checked_in');
        } else {
            throw new Exception("Failed to check in patient.");
        }
    }

    if ($action === 'edit') {
        if (!in_array($u['role'], ['secretary', 'admin'])) throw new Exception('Unauthorized.');

        $aptId = (int)($_POST['appointment_id'] ?? 0);
        $newDate = $_POST['appointment_date'] ?? '';
        $newStatus = $_POST['appointment_status'] ?? '';

        if (!$aptId || !$newDate || !$newStatus) {
            throw new Exception('Missing required fields for update.');
        }

        // [VALIDATION] No past dates allowed ONLY if status is 'scheduled' or 'pending'
        // If we are marking it as 'completed' or 'cancelled', past dates are fine.
        $isActiveStatus = in_array($newStatus, ['pending', 'scheduled']);
        if ($isActiveStatus && strtotime($newDate) < time()) {
            throw new Exception('Cannot schedule an active appointment in the past.');
        }

        $allowedStatuses = ['pending', 'scheduled', 'checked_in', 'in_consultation', 'completed', 'cancelled', 'no_show'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception('Invalid status.');
        }

        $stmt = db()->prepare("UPDATE appointment SET appointment_date = :d, appointment_status = :s WHERE appointment_id = :id");
        $stmt->execute([':d' => $newDate, ':s' => $newStatus, ':id' => $aptId]);

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Updated appointment to $newDate with status $newStatus");

        // [ANTI-SABOTAGE] Charge Penalty if No-Show
        if ($newStatus === 'no_show') {
            // Need patient ID. Fetch it.
            $pCheck = db()->query("SELECT user_id FROM appointment WHERE appointment_id = $aptId")->fetch();
            if ($pCheck) {
                PricingService::chargeNoShowPenalty((int)$pCheck['user_id'], $aptId);
            }
        }

        $_SESSION['success'] = "Appointment updated successfully.";
        redirect('/public/secretary/appointments.php');
    }

    if ($action === 'update_appointment_status') {
        if (!in_array($u['role'], ['secretary', 'admin', 'doctor'])) throw new Exception('Unauthorized.');

        $aptId = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';

        $allowedStatuses = ['pending', 'scheduled', 'checked_in', 'in_consultation', 'completed', 'cancelled', 'no_show'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception('Invalid status provided.');
        }

        // Update
        $stmt = db()->prepare("UPDATE appointment SET appointment_status = :s WHERE appointment_id = :id");
        $stmt->execute([':s' => $newStatus, ':id' => $aptId]);

        AuditLogger::log((int)$u['user_id'], 'appointment', 'UPDATE', $aptId, "Force updated status to $newStatus");

        // [ANTI-SABOTAGE] Charge Penalty if No-Show
        if ($newStatus === 'no_show') {
            // Need patient ID. Fetch it.
            $pCheck = db()->query("SELECT user_id FROM appointment WHERE appointment_id = $aptId")->fetch();
            if ($pCheck) {
                PricingService::chargeNoShowPenalty((int)$pCheck['user_id'], $aptId);
            }
        }

        if ($isAjax) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
        redirect('/public/admin/appointments.php?success=1');
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

    // Role-aware redirect on error
    if (isset($u) && ($u['role'] === 'secretary' || $u['role'] === 'admin')) {
        redirect('/public/secretary/appointments.php');
    } else {
        redirect('/public/patient/appointments.php');
    }
}
