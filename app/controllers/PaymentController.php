<?php

declare(strict_types=1);

final class PaymentController
{
    public static function listMine(): array
    {
        $u = Auth::user();
        if (!$u)
            return [];
        $pid = Patient::getPatientIdForUser((int) $u['user_id']);
        if (!$pid)
            return [];
        return Payment::listByPatient($pid);
    }

    public static function recordMine(): array
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

        $billingId = (int) ($_POST['billing_id'] ?? 0);
        $amount = (float) ($_POST['amount_paid'] ?? 0);
        $method = trim((string) ($_POST['method'] ?? ''));
        $ref = trim((string) ($_POST['reference_no'] ?? ''));
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));

        if ($billingId <= 0 || $amount <= 0 || $method === '' || $ref === '') {
            $errors[] = 'Billing, amount, method, and reference number are required.';
            return $errors;
        }

        $bill = Billing::findById($billingId);
        if (!$bill || (int) $bill['patient_id'] !== $pid) {
            $errors[] = 'Invalid billing reference.';
            return $errors;
        }

        $ts = $paidAt !== '' ? strtotime($paidAt) : time();
        if (!$ts) {
            $errors[] = 'Invalid payment date.';
            return $errors;
        }

        $paymentId = Payment::create(
            $billingId,
            $pid,
            $amount,
            $method,
            $ref,
            date('Y-m-d H:i:s', $ts),
            (int) $u['user_id'],
            null
        );

        AuditLogger::log((int) $u['user_id'], 'payment', 'INSERT', $paymentId, 'basic_payment_recording');
        return $errors;
    }
    public static function listBillingHistory(int $patientId): array
    {
        $sql = "SELECT b.*, p.amount_paid, p.payment_method, p.paid_at 
                FROM billing b
                LEFT JOIN payment p ON b.billing_id = p.billing_id
                WHERE b.patient_id = :patient_id
                ORDER BY b.created_at DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute([':patient_id' => $patientId]);
        return $stmt->fetchAll();
    }
}
