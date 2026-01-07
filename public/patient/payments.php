<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$patientId = Patient::getPatientIdForUser((int)$u['user_id']);
$billings = PaymentController::listBillingHistory($patientId);

ob_start();
?>
<div class="card">
    <h2>Payments & Billing</h2>
    <p>View your payment history and outstanding invoices.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Amount Due</th>
                <th>Amount Paid</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($billings)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #637588;">No payment records</td>
                </tr>
            <?php else: ?>
                <?php foreach ($billings as $b): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                        <td><?= e($b['billing_description']) ?></td>
                        <td><?= number_format((float)$b['amount_due'], 2) ?></td>
                        <td>
                            <?php if ($b['amount_paid']): ?>
                                <?= number_format((float)$b['amount_paid'], 2) ?>
                                <br><small class="text-muted"><?= e($b['payment_method']) ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['status'] === 'paid'): ?>
                                <span class="badge badge-success">Paid</span>
                            <?php elseif ($b['status'] === 'partial'): ?>
                                <span class="badge badge-warning">Partial</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Unpaid</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'patient', 'payments', [
    'title' => 'Payments & Billing',
    'content' => $content,
]);
