<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<div class="card">
    <?php
    // Fetch real payment data from database
    // Fetch all billings for patients who have ever consulted with this doctor
    $stmt = db()->prepare("
        SELECT b.created_at as date, 
               CONCAT(u.first_name, ' ', u.last_name) as patient,
               b.service_description as service, 
               b.amount_due, 
               b.amount_paid, 
               b.billing_status as status
        FROM billing b
        JOIN users u ON b.user_id = u.user_id
        WHERE b.user_id IN (
            SELECT DISTINCT user_id FROM consultation WHERE doctor_user_id = :doctor_id
        )
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([':doctor_id' => $u['user_id']]);
    $payments = $stmt->fetchAll();

    // Calculate total earnings (sum of amounts paid for doctor's consults)
    // Note: This assumes 100% of consult fee goes to doctor for now vs hospital share
    $totalEarnings = 0;
    foreach ($payments as $p) {
        $totalEarnings += (float)($p['amount_paid'] ?? 0);
    }
    ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">Professional Fees & Payments</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Track your clinical earnings and payout status.</p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Earnings</div>
            <div style="font-size:24px; font-weight:700; color:var(--success);">₱ <?= number_format($totalEarnings, 2) ?></div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Service Type</th>
                <th>Amount Due</th>
                <th>Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #637588;">No payment records found.</td>
                </tr>
                <?php else:
                foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($p['date'])) ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($p['patient']) ?></td>
                        <td><?= ucfirst($p['service'] ?? 'Consultation') ?></td>
                        <td>₱ <?= number_format((float)($p['amount_due'] ?? 0), 2) ?></td>
                        <td style="color:var(--success)">₱ <?= number_format((float)($p['amount_paid'] ?? 0), 2) ?></td>
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
