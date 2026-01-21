<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) die("Invalid CSRF");

    $action = $_POST['action'] ?? '';
    $bid = (int)$_POST['billing_id'];

    if ($action === 'void') {
        db()->prepare("UPDATE billing SET billing_status = 'voided', amount_due = 0 WHERE billing_id = ?")->execute([$bid]);
        AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $bid, 'Voided Invoice');
        redirect('payments.php?success=voided');
    } elseif ($action === 'refund') {
        db()->prepare("UPDATE billing SET billing_status = 'refunded', amount_paid = 0 WHERE billing_id = ?")->execute([$bid]);
        AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $bid, 'Refunded Payment');
        redirect('payments.php?success=refunded');
    } elseif ($action === 'add_fee') {
        $bid = (int)$_POST['billing_id'];
        $feeAmount = (float)($_POST['fee_amount'] ?? 0);
        $feeDesc = trim($_POST['fee_description'] ?? '');

        if ($bid && $feeAmount > 0 && $feeDesc) {
            if (Billing::addFee($bid, $feeAmount, $feeDesc)) {
                AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $bid, "Added Fee: $feeDesc (₱" . number_format($feeAmount, 2) . ")");
                redirect('payments.php?success=fee_added');
            }
        }
    }
}


ob_start();

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT py.*, py.amount_due, py.billing_status, py.hmo_claim_amount, u.first_name, u.last_name
    FROM billing py
    JOIN users u ON py.user_id = u.user_id
    WHERE 1=1";

$params = [];

if ($q !== '') {
    $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR py.transaction_reference LIKE :q3)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
    $params[':q3'] = "%$q%";
}
if ($status !== '') {
    $sql .= " AND py.billing_status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY py.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<div class="card">
    <div style="margin-bottom:24px;">
        <h2 style="margin:0">Financial Transactions</h2>
        <p style="margin:5px 0 24px; font-size:14px; color:var(--text-secondary)">Monitor all payments and revenue across the system.</p>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
            <div style="background:var(--surface-light); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; align-items:center; gap:16px;">
                <div style="width:48px; height:48px; background:rgba(30, 64, 175, 0.1); color:var(--primary); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <span class="material-symbols-outlined">payments</span>
                </div>
                <div>
                    <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Billed</div>
                    <div style="font-size:20px; font-weight:700;">₱<?= number_format(array_sum(array_column($payments, 'amount_due')), 2) ?></div>
                </div>
            </div>
            <div style="background:var(--surface-light); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; align-items:center; gap:16px;">
                <div style="width:48px; height:48px; background:rgba(34, 197, 94, 0.1); color:var(--success); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
                <div>
                    <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Collected</div>
                    <div style="font-size:20px; font-weight:700; color:var(--success);">₱<?= number_format(array_sum(array_column($payments, 'amount_paid')), 2) ?></div>
                </div>
            </div>
            <div style="background:var(--surface-light); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; align-items:center; gap:16px;">
                <div style="width:48px; height:48px; background:rgba(239, 68, 68, 0.1); color:var(--error); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <span class="material-symbols-outlined">pending_actions</span>
                </div>
                <div>
                    <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Outstanding</div>
                    <?php
                    $totalDue = array_sum(array_column($payments, 'amount_due'));
                    $totalPaid = array_sum(array_column($payments, 'amount_paid'));
                    $outstanding = $totalDue - $totalPaid;
                    ?>
                    <div style="font-size:20px; font-weight:700; color:var(--error);">₱<?= number_format($outstanding, 2) ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" style="display:flex; gap:12px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
            <div style="flex:1; position:relative;">
                <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or reference..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
            </div>

            <select name="status" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
                <option value="">All Statuses</option>
                <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>

            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($q || $status): ?>
                <a href="payments.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Total Due</th>
                    <th>Paid</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding:40px; color: var(--text-secondary);">No records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td style="font-family:monospace; font-size:12px;">INV-<?= str_pad((string)$p['billing_id'], 6, '0', STR_PAD_LEFT) ?></td>
                            <td style="font-size:13px;"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                            <td style="font-weight:600;"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td style="font-weight:700;">
                                ₱<?= number_format($p['amount_due'], 2) ?>
                                <?php if ((float)($p['hmo_claim_amount'] ?? 0) > 0): ?>
                                    <div style="font-size: 10px; color: var(--info);">HMO: ₱<?= number_format($p['hmo_claim_amount'], 2) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--success);">₱<?= number_format($p['amount_paid'], 2) ?></td>
                            <td>
                                <?php
                                $sColor = match ($p['billing_status']) {
                                    'paid' => 'success',
                                    'partial' => 'warning',
                                    'refunded' => 'secondary',
                                    'voided' => 'dark',
                                    default => 'error'
                                };
                                ?>
                                <span class="badge badge-<?= $sColor ?>">
                                    <?= ucfirst($p['billing_status'] ?? 'unpaid') ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="<?= base_url('/public/controllers/payment-handler.php?action=generate_invoice&billing_id=' . $p['billing_id']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Print Invoice">
                                    <span class="material-symbols-outlined" style="font-size:16px;">print</span>
                                </a>
                                <?php if ($p['billing_status'] === 'unpaid' || $p['billing_status'] === 'partial'): ?>
                                    <!-- HMO Claim Button -->
                                    <?php
                                    $hmoClaim = (float)($p['hmo_claim_amount'] ?? 0);
                                    $balance = (float)$p['amount_due'] - ((float)$p['amount_paid'] + $hmoClaim);
                                    ?>
                                    <button class="btn btn-sm btn-outline" style="color:var(--info); border-color:var(--info);" onclick='openHmoModal(<?= json_encode(["id" => $p["billing_id"], "balance" => $balance]) ?>)' title="File HMO Claim">
                                        <span class="material-symbols-outlined" style="font-size:16px;">health_and_safety</span>
                                    </button>
                                <?php endif; ?>
                                <?php if (in_array($p['billing_status'], ['unpaid', 'partial', 'paid'])): ?>
                                    <button class="btn btn-sm btn-outline" style="color:var(--primary); border-color:var(--primary);" onclick="openAddFeeModal(<?= $p['billing_id'] ?>)" title="Add Charge/Fee">
                                        <span class="material-symbols-outlined" style="font-size:16px;">add_circle</span>
                                    </button>
                                <?php endif; ?>
                                <?php if ($p['billing_status'] === 'paid' || $p['billing_status'] === 'partial'): ?>
                                    <!-- Refund Button -->
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to REFUND this payment? This will reset the paid amount to 0.');">
                                        <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                        <input type="hidden" name="action" value="refund">
                                        <input type="hidden" name="billing_id" value="<?= $p['billing_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-text" style="color:var(--warning);" title="Refund Payment">
                                            <span class="material-symbols-outlined" style="font-size:16px;">undo</span>
                                        </button>
                                    </form>
                                <?php elseif ($p['billing_status'] === 'unpaid'): ?>
                                    <!-- Void Button -->
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to VOID this invoice?');">
                                        <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                        <input type="hidden" name="action" value="void">
                                        <input type="hidden" name="billing_id" value="<?= $p['billing_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-text" style="color:var(--text-secondary);" title="Void Invoice">
                                            <span class="material-symbols-outlined" style="font-size:16px;">block</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- HMO Claim submission Modal -->
<div id="modal-hmo-claim" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:450px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-hmo-claim').classList.remove('show')">&times;</button>
        <h3>File HMO Claim</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Submit an insurance claim for this billing record. This will flag the invoice as 'Pending HMO Claim'.</p>

        <?php $hmoProviders = HmoProvider::listActive(); ?>
        <form action="<?= base_url('/public/controllers/hmo-handler.php') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="submit_claim">
            <input type="hidden" name="billing_id" id="hmo-billing-id">

            <div class="form-group">
                <label>HMO Provider</label>
                <select name="hmo_provider_id" required>
                    <option value="">-- Select Provider --</option>
                    <?php foreach ($hmoProviders as $hp): ?>
                        <option value="<?= $hp['hmo_provider_id'] ?>"><?= htmlspecialchars($hp['short_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Claim Amount (₱)</label>
                <input type="number" step="0.01" name="claim_amount" id="hmo-claim-amount" required min="0.01">
            </div>

            <div class="form-group">
                <label>HMO Member ID / Policy # (Optional)</label>
                <input type="text" name="reference" placeholder="e.g. MAXI-123456">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Submit Claim</button>
        </form>
    </div>
</div>

<!-- Add Fee Modal -->
<div id="modal-add-fee" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-fee').classList.remove('show')">&times;</button>
        <h3>Add Additional Charge</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Add missing fees or surgical supplies for Admin auditing.</p>

        <form method="POST" action="" style="margin-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="add_fee">
            <input type="hidden" name="billing_id" id="fee_billing_id">

            <div class="form-group">
                <label>Description of Charge</label>
                <input type="text" name="fee_description" placeholder="e.g. Surgical Kit, Oxygen Supply" required>
            </div>

            <div class="form-group">
                <label>Amount (₱)</label>
                <input type="number" step="0.01" name="fee_amount" required min="1">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Add Charge</button>
        </form>
    </div>
</div>

<script>
    function openHmoModal(data) {
        document.getElementById('hmo-billing-id').value = data.id;
        document.getElementById('hmo-claim-amount').value = data.balance.toFixed(2);
        document.getElementById('modal-hmo-claim').classList.add('show');
    }

    function openAddFeeModal(bid) {
        document.getElementById('fee_billing_id').value = bid;
        document.getElementById('modal-add-fee').classList.add('show');
    }
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'payments', [
    'title' => 'Financial Transactions',
    'content' => $content,
]);
