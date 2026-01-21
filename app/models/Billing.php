<?php

declare(strict_types=1);

final class Billing
{
    public static function findById(int $billingId): ?array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM billing WHERE billing_id = :bid LIMIT 1");
        $stmt->execute([':bid' => $billingId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listByPatient(int $userId, int $limit = 50): array
    {
        $stmt = Database::getInstance()->prepare("SELECT b.*, h.name as hmo_name, h.short_name as hmo_code,
                           (SELECT COUNT(*) FROM payments WHERE billing_id = b.billing_id AND is_verified = 0) as unverified_count
                           FROM billing b 
                           LEFT JOIN hmo_providers h ON b.hmo_provider_id = h.hmo_provider_id 
                           WHERE b.user_id = :uid
                           ORDER BY b.created_at DESC
                           LIMIT " . (int) $limit);
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function create(
        int $userId,
        float $amountDue,
        string $billingStatus = 'unpaid',
        ?string $description = null,
        ?int $consultationId = null,
        ?string $dueDate = null,
        ?int $appointmentId = null
    ): int {
        $db = Database::getInstance();
        // Default due date to 7 days from now if not provided
        $finalDueDate = $dueDate ?? date('Y-m-d', strtotime('+7 days'));

        try {
            $db->beginTransaction();

            $sql = "INSERT INTO billing
                  (user_id, amount_due, billing_status, service_description, consultation_id, appointment_id, due_date, created_at)
                  VALUES
                  (:uid, :amt, :status, :desc, :cons, :apt, :due, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':uid' => $userId,
                ':amt' => $amountDue,
                ':status' => $billingStatus,
                ':desc' => $description,
                ':cons' => $consultationId,
                ':apt' => $appointmentId,
                ':due' => $finalDueDate
            ]);
            $billingId = (int) $db->lastInsertId();

            // Insert initial line item
            if ($billingId > 0 && $amountDue > 0) {
                $stmtItem = $db->prepare("INSERT INTO billing_items (billing_id, description, amount) VALUES (?, ?, ?)");
                $stmtItem->execute([$billingId, $description ?: 'Medical Service', $amountDue]);
            }

            $db->commit();
            return $billingId;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Error creating billing: " . $e->getMessage());
            return 0;
        }
    }

    public static function recordPayment(int $billingId, array $data): bool
    {
        $db = Database::getInstance();
        $bill = self::findById($billingId);
        if (!$bill) return false;

        $amount = (float)$data['amount_paid'];
        if ($amount <= 0) return false;

        // Verification Status (default 1 for Secretary, 0 for Patient self-report)
        $isVerified = isset($data['is_verified']) ? (int)$data['is_verified'] : 1;

        // Strict Overpayment Validation
        $currentPaid = (float)($bill['amount_paid'] ?? 0);
        $totalDue = (float)$bill['amount_due'];
        $hmoClaim = (float)($bill['hmo_claim_amount'] ?? 0);
        $remaining = $totalDue - ($currentPaid + $hmoClaim);

        if ($amount > $remaining + 0.01) {
            throw new Exception("Payment amount (₱" . number_format($amount, 2) . ") exceeds remaining balance (₱" . number_format($remaining, 2) . ") after accounting for HMO coverage.");
        }

        try {
            $db->beginTransaction();

            // 1. Insert into payments table
            $stmt = $db->prepare("INSERT INTO payments (billing_id, amount, method, reference_number, paid_at, recorded_by, notes, is_verified) 
                                VALUES (:bid, :amt, :method, :ref, :paid_at, :rec, :notes, :verified)");
            $stmt->execute([
                ':bid' => $billingId,
                ':amt' => $amount,
                ':method' => $data['payment_method'],
                ':ref' => $data['transaction_reference'] ?? null,
                ':paid_at' => $data['paid_at'] ?? date('Y-m-d H:i:s'),
                ':rec' => $data['recorded_by_user_id'],
                ':notes' => $data['payment_notes'] ?? null,
                ':verified' => $isVerified
            ]);

            // 2. Sum total payments (We count ALL payments for the balance, but can filter UIs)
            $stmtSum = $db->prepare("SELECT SUM(amount) FROM payments WHERE billing_id = ?");
            $stmtSum->execute([$billingId]);
            $totalPaid = (float)$stmtSum->fetchColumn();

            // 3. Determine Status
            $totalDue = (float)$bill['amount_due'];
            $status = ($totalPaid >= $totalDue - 0.01) ? 'paid' : 'partial';

            // 4. Update Billing Record
            $oldNotes = $bill['payment_notes'] ?? '';
            $statusLabel = $isVerified ? "" : " (PENDING VERIFICATION)";
            $newNoteEntry = sprintf(
                "[%s] %sPaid %s via %s (Ref: %s)%s",
                date('Y-m-d H:i'),
                $isVerified ? "" : "REPORTED: ",
                number_format($amount, 2),
                $data['payment_method'],
                $data['transaction_reference'] ?? 'N/A',
                $statusLabel
            );
            $finalNotes = $oldNotes ? $oldNotes . "\n" . $newNoteEntry : $newNoteEntry;

            $updateSql = "UPDATE billing SET 
                        amount_paid = :total, 
                        billing_status = :status,
                        payment_method = :last_method,
                        transaction_reference = :last_ref,
                        paid_at = NOW(),
                        payment_notes = :notes,
                        updated_at = NOW()
                        WHERE billing_id = :bid";

            $db->prepare($updateSql)->execute([
                ':total' => $totalPaid,
                ':status' => $status,
                ':last_method' => $data['payment_method'],
                ':last_ref' => $data['transaction_reference'] ?? null,
                ':notes' => $finalNotes,
                ':bid' => $billingId
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Payment Record Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Submit an HMO claim for a billing record.
     */
    public static function submitHmoClaim(int $billingId, int $hmoProviderId, float $claimAmount, ?string $reference = null): bool
    {
        $bill = self::findById($billingId);
        if (!$bill) return false;

        $totalDue = (float)$bill['amount_due'];
        $paid = (float)($bill['amount_paid'] ?? 0);
        $maxClaim = $totalDue - $paid;

        if ($claimAmount > $maxClaim + 0.01) {
            $claimAmount = $maxClaim; // Cap it automatically
        }

        $sql = "UPDATE billing SET 
                hmo_provider_id = :hmo,
                hmo_claim_status = 'submitted',
                hmo_claim_amount = :amt,
                hmo_claim_reference = :ref,
                updated_at = NOW()
                WHERE billing_id = :bid";
        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute([
            ':bid' => $billingId,
            ':hmo' => $hmoProviderId,
            ':amt' => $claimAmount,
            ':ref' => $reference
        ]);
    }

    /**
     * Update HMO claim status.
     */
    public static function updateClaimStatus(int $billingId, string $status, ?string $reference = null): bool
    {
        $validStatuses = ['none', 'submitted', 'approved', 'rejected', 'paid'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Invalid HMO claim status: $status");
        }

        $sql = "UPDATE billing SET hmo_claim_status = :status, updated_at = NOW()";
        $params = [':status' => $status, ':bid' => $billingId];

        if ($reference !== null) {
            $sql .= ", hmo_claim_reference = :ref";
            $params[':ref'] = $reference;
        }

        $sql .= " WHERE billing_id = :bid";
        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Add an additional fee/charge to an existing billing record.
     */
    public static function addFee(int $billingId, float $amount, string $description): bool
    {
        $db = Database::getInstance();
        $bill = self::findById($billingId);
        if (!$bill) return false;

        try {
            $db->beginTransaction();

            $newTotal = (float)$bill['amount_due'] + $amount;
            $newDesc = $bill['service_description'] . " + " . $description;

            $sql = "UPDATE billing SET amount_due = :total, service_description = :desc, updated_at = NOW() WHERE billing_id = :bid";
            $db->prepare($sql)->execute([
                ':total' => $newTotal,
                ':desc' => $newDesc,
                ':bid' => $billingId
            ]);

            // Add separate line item
            $stmtItem = $db->prepare("INSERT INTO billing_items (billing_id, description, amount) VALUES (?, ?, ?)");
            $stmtItem->execute([$billingId, $description, $amount]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Error adding fee: " . $e->getMessage());
            return false;
        }
    }

    public static function getItems(int $billingId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT * FROM billing_items WHERE billing_id = ? ORDER BY created_at ASC");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get a setting from system_settings.
     */
    public static function getPolicy(string $key, $default = null): string
    {
        $stmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : (string)$default;
    }

    /**
     * Set a setting in system_settings.
     */
    public static function setPolicy(string $key, string $value): bool
    {
        $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                VALUES (:key, :val, NOW()) 
                ON DUPLICATE KEY UPDATE setting_value = :val, updated_at = NOW()";
        $stmt = Database::getInstance()->prepare($sql);
        return $stmt->execute([':key' => $key, ':val' => $value]);
    }

    /**
     * List billings with HMO claims pending or in progress.
     */
    public static function listPendingHmoClaims(int $limit = 50): array
    {
        $sql = "SELECT b.*, u.first_name, u.last_name, h.short_name AS hmo_name
                FROM billing b
                JOIN users u ON b.user_id = u.user_id
                LEFT JOIN hmo_providers h ON b.hmo_provider_id = h.hmo_provider_id
                WHERE b.hmo_claim_status IN ('submitted', 'approved')
                ORDER BY b.created_at DESC
                LIMIT " . (int)$limit;
        $stmt = Database::getInstance()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function getPayments(int $billingId): array
    {
        $stmt = Database::getInstance()->prepare("SELECT p.*, u.first_name, u.last_name 
                           FROM payments p 
                           LEFT JOIN users u ON p.recorded_by = u.user_id 
                           WHERE p.billing_id = ? 
                           ORDER BY p.paid_at DESC");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function verifyPayment(int $paymentId): bool
    {
        $db = Database::getInstance();
        try {
            return $db->prepare("UPDATE payments SET is_verified = 1 WHERE payment_id = ?")->execute([$paymentId]);
        } catch (Exception $e) {
            error_log("Verification Error: " . $e->getMessage());
            return false;
        }
    }

    public static function getOutstandingBalance(int $billingId): float
    {
        $bill = self::findById($billingId);
        if (!$bill) return 0.0;
        return (float)$bill['amount_due'] - ((float)$bill['amount_paid'] + (float)$bill['hmo_claim_amount']);
    }
}
