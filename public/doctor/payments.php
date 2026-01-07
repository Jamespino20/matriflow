<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">Professional Fees & Payments</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Track your clinical earnings and payout status.</p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Pending Payout</div>
            <div style="font-size:24px; font-weight:700; color:var(--success);">₱ 12,450.00</div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Service Type</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch real payment data from database
            $stmt = db()->prepare("
                SELECT c.created_at as date, CONCAT(u.first_name, ' ', u.last_name) as patient,
                       c.consultation_type as service, py.amount_paid as amount, py.payment_status as status
                FROM consultation c
                JOIN patient pt ON c.patient_id = pt.patient_id
                JOIN user u ON pt.user_id = u.user_id
                LEFT JOIN billing b ON c.consultation_id = b.consultation_id
                LEFT JOIN payment py ON b.billing_id = py.billing_id
                WHERE c.doctor_user_id = :doctor_id
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([':doctor_id' => $u['user_id']]);
            $payments = $stmt->fetchAll();

            if (empty($payments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #637588;">No payment records found.</td>
                </tr>
                <?php else:
                foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($p['date'])) ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($p['patient']) ?></td>
                        <td><?= htmlspecialchars($p['service'] ?? 'Consultation') ?></td>
                        <td>₱ <?= number_format((float)($p['amount'] ?? 0), 2) ?></td>
                        <td>
                            <span class="badge badge-<?= ($p['status'] ?? '') === 'paid' ? 'success' : 'warning' ?>">
                                <?= ucfirst($p['status'] ?? 'pending') ?>
                            </span>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'payments', [
    'title' => 'Payments',
    'content' => $content,
]);
