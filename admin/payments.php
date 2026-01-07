<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

ob_start();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT py.*, b.amount_due, b.payment_status as billing_status, u.first_name, u.last_name 
        FROM payment py
        JOIN billing b ON py.billing_id = b.billing_id
        JOIN patient p ON b.patient_id = p.patient_id
        JOIN user u ON p.user_id = u.user_id
        WHERE 1=1";

$params = [];

if ($q !== '') {
    $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR py.reference_no LIKE :q3)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
    $params[':q3'] = "%$q%";
}
if ($status !== '') {
    $sql .= " AND py.payment_status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY py.paid_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">Financial Transactions</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Monitor all payments and revenue across the system.</p>
        </div>
        <div class="badge badge-success" style="font-size:14px; padding:8px 16px;">
            Total Revenue: ₱<?= number_format(array_sum(array_column($payments, 'amount_paid')), 2) ?>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or reference..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>

        <select name="status" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
            <option value="">All Statuses</option>
            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($q || $status): ?>
            <a href="payments.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Reference</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding:40px; color: var(--text-secondary);">No payments found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td style="font-size:13px;"><?= date('M j, Y H:i', strtotime($p['paid_at'])) ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td style="font-weight:700; color:var(--success);">₱<?= number_format($p['amount_paid'], 2) ?></td>
                        <td><span class="badge badge-outline"><?= strtoupper($p['payment_method']) ?></span></td>
                        <td style="font-family:monospace; font-size:12px;"><?= htmlspecialchars($p['reference_number'] ?? 'N/A') ?></td>
                        <td>
                            <span class="badge badge-<?= $p['payment_status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= ucfirst($p['payment_status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'payments', [
    'title' => 'Financial Transactions',
    'content' => $content,
]);
