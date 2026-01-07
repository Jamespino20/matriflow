<?php

declare(strict_types=1);

final class ReminderService
{
    /**
     * Process reminders for appointments happening tomorrow (24 hours lookahead)
     */
    public static function processDailyReminders(): array
    {
        $log = [];
        $tomorrow = new DateTime('tomorrow');
        $dateStr = $tomorrow->format('Y-m-d');

        // Find appointments for tomorrow that haven't had a reminder sent
        // Using `reminder_sent` column in appointment table or joining reminder_logs is better.
        // For now, let's use the explicit `reminder_sent` flag from the schema if it exists (it does: reminder_sent tinyint)

        $sql = "SELECT a.*, u.phone_number, u.email, u.first_name 
                FROM appointment a
                JOIN patient p ON a.patient_id = p.patient_id
                JOIN user u ON p.user_id = u.user_id
                WHERE DATE(a.appointment_date) = :tomorrow
                AND a.reminder_sent = 0
                AND a.appointment_status = 'scheduled'";

        $stmt = db()->prepare($sql);
        $stmt->execute([':tomorrow' => $dateStr]);
        $appointments = $stmt->fetchAll();

        foreach ($appointments as $appt) {
            // Simulate sending SMS
            $phone = $appt['phone_number'];
            $time = date('h:i A', strtotime($appt['appointment_date']));
            $msg = "MatriFlow Reminder: Dear {$appt['first_name']}, you have an appointment tomorrow at {$time}.";

            // Log the attempt
            self::logReminder((int)$appt['appointment_id'], 'sms', $phone ?: 'N/A');

            // Mark as sent
            $upd = db()->prepare("UPDATE appointment SET reminder_sent = 1 WHERE appointment_id = ?");
            $upd->execute([$appt['appointment_id']]);

            $log[] = "Sent SMS to {$appt['first_name']} (ID: {$appt['appointment_id']})";
        }

        return $log;
    }

    private static function logReminder(int $apptId, string $type, string $recipient): void
    {
        $stmt = db()->prepare("INSERT INTO reminder_logs (appointment_id, type, recipient, sent_at, status) VALUES (?, ?, ?, NOW(), 'sent')");
        $stmt->execute([$apptId, $type, $recipient]);
    }
}
