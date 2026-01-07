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
        if ($ts < time()) {
            $errors[] = 'Appointment cannot be in the past.';
            return $errors;
        }

        $id = Appointment::create($pid, $purpose, date('Y-m-d H:i:s', $ts), null);
        AuditLogger::log((int) $u['user_id'], 'appointment', 'INSERT', $id, 'patient_booking');

        return $errors;
    }

    public static function getAvailableSlots(int $doctorId, string $date): array
    {
        $dayOfWeek = date('l', strtotime($date));

        // 1. Get schedule for the day
        $stmt = db()->prepare("SELECT start_time, end_time FROM doctor_schedule 
                             WHERE doctor_user_id = :did AND day_of_week = :day AND is_available = 1");
        $stmt->execute([':did' => $doctorId, ':day' => $dayOfWeek]);
        $schedules = $stmt->fetchAll();

        if (!$schedules) return [];

        // 2. Get existing bookings for that day
        $stmt = db()->prepare("SELECT appointment_date FROM appointment 
                             WHERE doctor_user_id = :did 
                             AND DATE(appointment_date) = :date 
                             AND appointment_status NOT IN ('cancelled', 'no_show')");
        $stmt->execute([':did' => $doctorId, ':date' => $date]);
        $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $slots = [];
        $interval = 30; // 30 minute blocks

        foreach ($schedules as $sched) {
            $start = strtotime($date . ' ' . $sched['start_time']);
            $end = strtotime($date . ' ' . $sched['end_time']);

            for ($t = $start; $t < $end; $t += $interval * 60) {
                $timeStr = date('H:i:s', $t);
                $fullDt = date('Y-m-d H:i:s', $t);

                // Check if already booked
                $isBooked = false;
                foreach ($bookedTimes as $bt) {
                    if ($bt === $fullDt) {
                        $isBooked = true;
                        break;
                    }
                }

                if (!$isBooked) {
                    $slots[] = [
                        'time' => date('g:i A', $t),
                        'value' => $timeStr
                    ];
                }
            }
        }

        return $slots;
    }
}
