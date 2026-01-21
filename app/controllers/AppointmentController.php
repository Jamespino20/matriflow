<?php

declare(strict_types=1);

final class AppointmentController
{
    public static function listMine(): array
    {
        $u = Auth::user();
        if (!$u)
            return [];
        $pid = Patient::getPatientIdForUser((int) $u['user_id']);
        if (!$pid)
            return [];
        return Appointment::listByPatient($pid);
    }

    public static function createMine(): array
    {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            return $errors;

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Invalid request (CSRF).';
            return $errors;
        }

        $u = Auth::user();
        if (!$u) {
            $errors[] = 'Not authenticated.';
            return $errors;
        }

        $pid = Patient::getPatientIdForUser((int) $u['user_id']);
        if (!$pid) {
            $errors[] = 'Patient profile missing.';
            return $errors;
        }

        $purpose = trim((string) ($_POST['appointment_purpose'] ?? ''));
        $dt = trim((string) ($_POST['appointment_date'] ?? ''));

        if ($purpose === '' || $dt === '') {
            $errors[] = 'All fields are required.';
            return $errors;
        }

        $ts = strtotime($dt);
        if (!$ts) {
            $errors[] = 'Invalid date.';
            return $errors;
        }
        if ($ts < strtotime('tomorrow 00:00:00')) {
            $errors[] = 'Appointments must be booked at least 1 day in advance.';
            return $errors;
        }

        $id = Appointment::create($pid, $purpose, date('Y-m-d H:i:s', $ts), null);
        AuditLogger::log((int) $u['user_id'], 'appointment', 'INSERT', $id, 'patient_booking');

        return $errors;
    }

    public static function reschedule(int $appointmentId, string $newDateTime, bool $enforceLeadTime = true): array
    {
        $errors = [];
        $ts = strtotime($newDateTime);
        if (!$ts) {
            $errors[] = 'Invalid date format.';
            return $errors;
        }

        // [BUFFER RULE] Enforce 24-hour lead time for patient changes
        if ($enforceLeadTime && $ts < (time() + 86400)) {
            $errors[] = 'Appointments must be rescheduled at least 24 hours in advance.';
            return $errors;
        }

        // Check availability (excluding self)
        $date = date('Y-m-d', $ts);
        $doctorId = self::getDoctorForAppointment($appointmentId);
        $slots = self::getAvailableSlots($doctorId, $date, $appointmentId);

        $requestedTime = date('H:i:s', $ts);
        $isAvailable = false;
        foreach ($slots as $s) {
            if ($s['value'] === $requestedTime) {
                $isAvailable = true;
                break;
            }
        }

        if (!$isAvailable) {
            $errors[] = 'The selected slot is no longer available.';
            return $errors;
        }

        $stmt = Database::getInstance()->prepare("UPDATE appointment SET appointment_date = :dt, updated_at = NOW() WHERE appointment_id = :id");
        $stmt->execute([':dt' => date('Y-m-d H:i:s', $ts), ':id' => $appointmentId]);

        return $errors;
    }

    public static function autoBook(int $patientId, string $date, string $serviceType, bool $force = false): bool
    {
        // Try to find the first available slot on the recommended date for the primary doctor
        $doctorId = self::getPrimaryDoctorForPatient($patientId);
        $slots = self::getAvailableSlots($doctorId, $date);

        $dateTime = null;

        if (!empty($slots)) {
            $firstSlot = $slots[0]['value'];
            $dateTime = $date . ' ' . $firstSlot;
        } elseif ($force) {
            // Fallback to 9 AM if forced (Director/Auto-generated)
            $dateTime = $date . ' 09:00:00';
        } else {
            return false; // Fail gracefully if no slots available and not forced
        }

        $id = Appointment::create($patientId, "Auto-scheduled follow-up ($serviceType)", $dateTime, $doctorId);
        AuditLogger::log(0, 'appointment', 'INSERT', $id, 'auto_booking_followup');
        return $id > 0;
    }

    public static function getAvailableSlots(int $doctorId, string $date, ?int $excludeAppointmentId = null): array
    {
        // [RULE] Patients book tomorrow+, but Secretaries can book for today
        $u = Auth::user();
        $isStaff = in_array($u['role'] ?? '', ['secretary', 'doctor', 'admin']);

        if (!$isStaff && strtotime($date) < strtotime('tomorrow 00:00:00')) {
            return [];
        }

        $dayOfWeek = date('l', strtotime($date));

        $stmt = Database::getInstance()->prepare("SELECT start_time, end_time FROM staff_schedule
                             WHERE user_id = :did AND day_of_week = :day AND is_available = 1");
        $stmt->execute([':did' => $doctorId, ':day' => $dayOfWeek]);
        $schedules = $stmt->fetchAll();

        if (!$schedules) return [];

        $stmt = Database::getInstance()->prepare("SELECT appointment_date FROM appointment
                             WHERE doctor_user_id = :did 
                             AND DATE(appointment_date) = :date 
                             AND appointment_status NOT IN ('cancelled', 'no_show')
                             " . ($excludeAppointmentId ? " AND appointment_id != $excludeAppointmentId" : ""));
        $stmt->execute([':did' => $doctorId, ':date' => $date]);
        $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $slots = [];
        $interval = 30; // Enforce 30-minute interval as requested
        $slotDuration = 30 * 60;

        foreach ($schedules as $sched) {
            $start = strtotime($date . ' ' . $sched['start_time']);
            $end = strtotime($date . ' ' . $sched['end_time']);

            for ($t = $start; $t < $end; $t += $interval * 60) {
                $slotStart = $t;

                $isConflict = false;
                foreach ($bookedTimes as $bt) {
                    $bookedStart = strtotime($bt);
                    $diff = abs($slotStart - $bookedStart);
                    if ($diff < 1800) {
                        $isConflict = true;
                        break;
                    }
                }

                if ($slotStart < time()) continue;

                if (!$isConflict) {
                    $slots[] = [
                        'time' => date('g:i A', $t),
                        'value' => date('H:i:s', $t)
                    ];
                }
            }
        }

        return $slots;
    }

    private static function getDoctorForAppointment(int $id): int
    {
        $stmt = Database::getInstance()->prepare("SELECT doctor_user_id FROM appointment WHERE appointment_id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    private static function getPrimaryDoctorForPatient(int $pid): int
    {
        // Fallback to first doctor found or last assigned
        $stmt = Database::getInstance()->prepare("SELECT doctor_user_id FROM appointment WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$pid]);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) return $id;

        $stmt = Database::getInstance()->prepare("SELECT user_id FROM users WHERE role = 'doctor' LIMIT 1");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
