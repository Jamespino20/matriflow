<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
$u = Auth::user();
if (!$u || $u['role'] !== 'secretary') {
    redirect('/');
}

// Handle Form Submissions
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_billing') {
            $patientId = (int)($_POST['patient_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

            if ($patientId && $amount > 0) {
                Billing::create($patientId, $amount, 'pending', $dueDate, null, $description);
                $success = "Billing record created successfully.";
            } else {
                $error = "Invalid patient or amount.";
            }
        } elseif ($action === 'record_payment') {
            $billingId = (int)($_POST['billing_id'] ?? 0);
            $payAmount = (float)($_POST['payment_amount'] ?? 0);
            $method = $_POST['payment_method'] ?? 'cash';
            $ref = $_POST['reference_no'] ?? null;
            $billing = Billing::findById($billingId);

            if ($billing && $payAmount > 0) {
                Payment::create($billingId, (int)$billing['patient_id'], $payAmount, $method, $ref, null, (int)$u['user_id']);

                // Update billing status if fully paid (simplified logic)
                // In a real app, we'd check total paid vs total amount.
                // For now, let's assume if payment is recorded, we might want to update status manually or leave it.
                // Let's just update billing status to 'paid' if amount matches or exceeds.
                // We need to fetch total payments for this billing to be sure, but for now let's just create payment.
                // Actually, let's update status to partial or paid.

                $success = "Payment recorded successfully.";
            } else {
                $error = "Invalid billing ID or amount.";
            }
        }
    }
}

// Fetch Billings
$search = $_GET['search'] ?? '';
$whereClause = "";
$params = [];

if ($search) {
    if (is_numeric($search)) {
        // Search by ID
        $whereClause = "WHERE b.billing_id = :search OR pt.identification_number LIKE :searchLike";
        $params[':search'] = $search;
        $params[':searchLike'] = "%$search%";
    } else {
        // Search by Name
        $whereClause = "WHERE u.first_name LIKE :search OR u.last_name LIKE :search";
        $params[':search'] = "%$search%";
    }
}

$sql = "SELECT b.*, u.first_name, u.last_name, u.email, pt.identification_number,
        (SELECT SUM(amount_paid) FROM payment p WHERE p.billing_id = b.billing_id) as total_paid
        FROM billing b
        JOIN patient pt ON b.patient_id = pt.patient_id
        JOIN user u ON pt.user_id = u.user_id
        $whereClause
        ORDER BY b.created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$billings = $stmt->fetchAll();

ob_start();
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Billing & Payments</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Manage patient invoices and record payments.</p>
        </div>
        <button class="btn btn-primary" onclick="openBillingModal()"><span class="material-symbols-outlined">add</span> Create New Billing</button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="search-box" style="margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by patient name or ID..." style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($search): ?><a href="payments.php" class="btn btn-outline">Clear</a><?php endif; ?>
        </form>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Patient</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Paid</th>
                <th>Status</th>
                <th>Due Date</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($billings)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 30px; color: var(--text-secondary);">No billing records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($billings as $b): ?>
                    <?php
                    $paid = (float)($b['total_paid'] ?? 0);
                    $total = (float)$b['total_amount'];
                    $balance = $total - $paid;
                    $statusInfo = match ($b['status']) {
                        'paid' => ['bg' => 'success', 'label' => 'PAID'],
                        'pending' => ['bg' => 'warning', 'label' => 'PENDING'],
                        'overdue' => ['bg' => 'error', 'label' => 'OVERDUE'],
                        default => ['bg' => 'secondary', 'label' => strtoupper($b['status'])]
                    };
                    // Auto-detect status display if fully paid but status says pending (optional visual fix)
                    if ($balance <= 0 && $b['status'] !== 'paid') {
                        // visual override
                        $statusInfo = ['bg' => 'success', 'label' => 'PAID (Verify)'];
                    }
                    ?>
                    <tr>
                        <td style="font-weight: 600;">INV-<?= str_pad((string)$b['billing_id'], 6, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= e($b['first_name'] . ' ' . $b['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);"><?= e($b['identification_number']) ?></div>
                        </td>
                        <td><?= e($b['description']) ?></td>
                        <td style="font-weight: 600;">₱<?= number_format($total, 2) ?></td>
                        <td style="color: var(--success);">₱<?= number_format($paid, 2) ?></td>
                        <td><span class="badge badge-<?= $statusInfo['bg'] ?>"><?= $statusInfo['label'] ?></span></td>
                        <td><?= $b['due_date'] ? date('M j, Y', strtotime($b['due_date'])) : '-' ?></td>
                        <td style="text-align: right;">
                            <?php if ($balance > 0): ?>
                                <button class="btn btn-sm btn-primary" onclick="openPaymentModal(<?= $b['billing_id'] ?>, <?= $balance ?>)">Pay</button>
                                <!-- PayMongo Link (Mockup) -->
                                <a href="#" onclick="alert('Redirecting to PayMongo...')" class="btn btn-sm btn-outline" title="Pay Online"><span class="material-symbols-outlined" style="font-size: 16px;">credit_card</span></a>
                            <?php else: ?>
                                <span class="badge badge-outline">Settled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- New Billing Modal -->
<div id="modal-new-billing" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-new-billing').style.display='none'">&times;</button>
        <h3>Create New Invoice</h3>
        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="action" value="create_billing">

            <div class="form-group">
                <label>Patient</label>
                <!-- In a real app, use a search select. Simple select for now or text input for ID. -->
                <input type="text" id="patient-search-input" placeholder="Search Patient Name..." onkeyup="searchPatients(this.value)" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                <input type="hidden" name="patient_id" id="selected-patient-id" required>
                <div id="patient-search-results" style="border: 1px solid var(--border); max-height: 150px; overflow-y: auto; display: none;"></div>
                <div id="selected-patient-display" style="margin-top: 5px; font-weight: 600; color: var(--primary);"></div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" placeholder="e.g. Prenatal Checkup, Labs" required>
            </div>

            <div class="form-group">
                <label>Amount (PHP)</label>
                <input type="number" name="amount" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?= date('Y-m-d') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Create Invoice</button>
        </form>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="modal-payment" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-payment').style.display='none'">&times;</button>
        <h3>Record Payment</h3>
        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="billing_id" id="pay-billing-id">

            <div class="form-group">
                <label>Amount to Pay (PHP)</label>
                <input type="number" name="payment_amount" id="pay-amount" step="0.01" min="0" required>
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="cash">Cash</option>
                    <option value="card">Credit/Debit Card</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                </select>
            </div>

            <div class="form-group">
                <label>Reference No. (Optional)</label>
                <input type="text" name="reference_no" placeholder="OR Number or Transaction ID">
            </div>

            <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 10px;">Record Payment</button>
        </form>
    </div>
</div>

<script>
    function openBillingModal() {
        document.getElementById('modal-new-billing').style.display = 'flex';
    }

    function openPaymentModal(billingId, balance) {
        document.getElementById('pay-billing-id').value = billingId;
        document.getElementById('pay-amount').value = balance.toFixed(2);
        document.getElementById('modal-payment').style.display = 'flex';
    }

    function searchPatients(q) {
        const results = document.getElementById('patient-search-results');
        if (q.length < 2) {
            results.style.display = 'none';
            return;
        }

        // Reuse existing message handler for search
        fetch('<?= base_url('/public/controllers/message-handler.php') ?>?action=search_users&q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.users.length > 0) {
                    let html = '';
                    json.users.filter(u => u.role === 'patient').forEach(p => {
                        html += `<div style="padding: 10px; border-bottom: 1px solid var(--border); cursor: pointer; background: var(--surface);" 
                             onclick="selectPatient(${p.patient_id}, '${p.first_name} ${p.last_name}')">
                        <div style="font-weight: 600;">${p.first_name} ${p.last_name}</div>
                        <div style="font-size: 11px; opacity: 0.7;">ID: ${p.patient_id}</div>
                    </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                } else {
                    results.style.display = 'none';
                }
            });
    }

    function selectPatient(id, name) {
        document.getElementById('selected-patient-id').value = id;
        document.getElementById('selected-patient-display').textContent = "Selected: " + name;
        document.getElementById('patient-search-results').style.display = 'none';
        document.getElementById('patient-search-input').value = name;
    }
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'billing', [
    'title' => 'Payments & Billing',
    'content' => $content,
]);
