<?php

declare(strict_types=1);

final class ReminderService
{
    /**
     * Process reminders for appointments happening in 48 hours
     */
    public static function processDailyReminders(): array
    {
        $log = [];
        $targetDate = new DateTime('+2 days'); // 48 Hours Lookahead
        $dateStr = $targetDate->format('Y-m-d');

        // Find appointments for target date that haven't had a reminder sent
        $sql = "SELECT a.*, u.contact_number as phone_number, u.email, u.first_name 
                FROM appointment a
                JOIN users u ON a.user_id = u.user_id
                WHERE DATE(a.appointment_date) = :targetDate
                AND a.appointment_status = 'scheduled'
                AND NOT EXISTS (SELECT 1 FROM reminder_logs rl WHERE rl.appointment_id = a.appointment_id AND rl.status = 'sent')";

        $stmt = db()->prepare($sql);
        $stmt->execute([':targetDate' => $dateStr]);
        $appointments = $stmt->fetchAll();

        foreach ($appointments as $appt) {
            $phone = $appt['phone_number'];
            $time = date('h:i A', strtotime($appt['appointment_date']));
            // [DAY 5] "Reminder to confirm"
            $msg = "MatriFlow: Hi {$appt['first_name']}, you have an appointment on {$dateStr} at {$time}. Please reply CONFIRM to keep your slot.";

            if ($phone) {
                // Use local Messaging class for zero-cost logging
                Messaging::sendSMS((int)$appt['user_id'], $phone, $msg);

                // Track in reminder_logs to prevent duplicate sending
                self::logReminder((int)$appt['appointment_id'], 'sms', $phone);

                $log[] = "Sent SMS to {$appt['first_name']} (ID: {$appt['appointment_id']})";
            }
        }

        return $log;
    }

    private static function logReminder(int $apptId, string $type, string $recipient): void
    {
        $stmt = db()->prepare("INSERT INTO reminder_logs (appointment_id, type, recipient, sent_at, status) VALUES (?, ?, ?, NOW(), 'sent')");
        $stmt->execute([$apptId, $type, $recipient]);
    }
}
