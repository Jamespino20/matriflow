<?php

declare(strict_types=1);

final class PaymentController
{
    public static function listMine(): array
    {
        $u = Auth::user();
        if (!$u)
            return [];
        return Billing::listByPatient((int)$u['user_id']);
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

        $userId = (int)$u['user_id'];
        $billingId = (int) ($_POST['billing_id'] ?? 0);
        $amount = (float) ($_POST['amount_paid'] ?? 0);
        $method = trim((string) ($_POST['payment_method'] ?? ''));
        $ref = trim((string) ($_POST['transaction_reference'] ?? ''));
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));

        if ($billingId <= 0 || $amount <= 0 || $method === '' || $ref === '') {
            $errors[] = 'Billing, amount, method, and reference number are required.';
            return $errors;
        }

        $bill = Billing::findById($billingId);
        if (!$bill || (int)$bill['user_id'] !== $userId) {
            $errors[] = 'Invalid billing reference.';
            return $errors;
        }

        $ts = $paidAt !== '' ? strtotime($paidAt) : time();
        if (!$ts) {
            $errors[] = 'Invalid payment date.';
            return $errors;
        }

        $success = Billing::recordPayment($billingId, [
            'amount_paid' => $amount,
            'payment_method' => $method,
            'transaction_reference' => $ref,
            'paid_at' => date('Y-m-d H:i:s', $ts),
            'recorded_by_user_id' => $userId,
            'payment_notes' => $_POST['payment_notes'] ?? null
        ]);

        if ($success) {
            AuditLogger::log($userId, 'billing', 'UPDATE', $billingId, 'payment_recorded');
        } else {
            $errors[] = 'Failed to record payment.';
        }

        return $errors;
    }

    public static function listBillingHistory(int $userId): array
    {
        return Billing::listByPatient($userId);
    }
}
