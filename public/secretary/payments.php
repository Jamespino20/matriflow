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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['ajax_action'] ?? '') === 'get_payments') {
    $bid = (int)($_GET['billing_id'] ?? 0);
    $payments = Billing::getPayments($bid);
    $out = [];
    foreach ($payments as $p) {
        $out[] = [
            'payment_id' => $p['payment_id'],
            'amount' => $p['amount'],
            'method' => $p['method'],
            'reference_number' => $p['reference_number'],
            'paid_at' => $p['paid_at'],
            'is_verified' => $p['is_verified']
        ];
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_billing') {
            $userId = (int)($_POST['patient_id'] ?? 0);
            $items = $_POST['items'] ?? []; // Array of {desc, amount}
            $dueDate = $_POST['due_date'] ?? date('Y-m-d');

            // Calculate total
            $totalAmount = 0;
            $cleanItems = [];
            foreach ($items as $itm) {
                $amt = (float)($itm['amount'] ?? 0);
                $desc = trim($itm['description'] ?? '');
                if ($amt > 0 && $desc !== '') {
                    $totalAmount += $amt;
                    $cleanItems[] = ['description' => $desc, 'amount' => $amt];
                }
            }

            if ($userId && $totalAmount > 0 && count($cleanItems) > 0) {
                if (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
                    $error = "Due date cannot be in the past.";
                } else {
                    try {
                        $db = db();
                        $db->beginTransaction();

                        // Create parent billing - use EXACT total from items
                        $billingId = Billing::create($userId, $totalAmount, 'unpaid', $cleanItems[0]['description'] . (count($cleanItems) > 1 ? " (+" . (count($cleanItems) - 1) . " items)" : ""), null, $dueDate);

                        if ($billingId > 0) {
                            // Billing::create inserts one default item, we need to remove it and insert our cleanItems
                            $db->prepare("DELETE FROM billing_items WHERE billing_id = ?")->execute([$billingId]);

                            $stmtItem = $db->prepare("INSERT INTO billing_items (billing_id, description, amount) VALUES (?, ?, ?)");
                            foreach ($cleanItems as $itm) {
                                $stmtItem->execute([$billingId, $itm['description'], $itm['amount']]);
                            }

                            // Ensure header total is exactly the sum (in case Billing::create did something else)
                            $db->prepare("UPDATE billing SET amount_due = ? WHERE billing_id = ?")->execute([$totalAmount, $billingId]);

                            $db->commit();
                            AuditLogger::log($u['user_id'], 'billing', 'CREATE', $billingId, "Created itemized invoice for patient ID: $userId, Total: ₱" . number_format($totalAmount, 2));
                            $success = "Invoice created successfully with " . count($cleanItems) . " items.";
                        } else {
                            throw new Exception("Failed to initialize billing record.");
                        }
                    } catch (Exception $e) {
                        if (db()->inTransaction()) db()->rollBack();
                        $error = "Failed to create invoice: " . $e->getMessage();
                    }
                }
            } else {
                $error = "Invalid patient or empty items list.";
            }
        } elseif ($action === 'verify_payment') {
            $paymentId = (int)($_POST['payment_id'] ?? 0);
            if (Billing::verifyPayment($paymentId)) {
                AuditLogger::log($u['user_id'], 'payments', 'UPDATE', $paymentId, 'Verified Patient Payment');
                $_SESSION['success'] = "Payment verified successfully.";
                redirect('payments.php');
            } else {
                $error = "Failed to verify payment.";
            }
        } elseif ($action === 'record_payment') {
            // ... (rest of the file stays same until the HTML part)
            // ... I will skip the unchanged handler parts and jump to the VIEW ...
            $billingId = (int)($_POST['billing_id'] ?? 0);
            $payAmount = (float)($_POST['payment_amount'] ?? 0);
            $method = $_POST['payment_method'] ?? 'cash';
            $ref = $_POST['reference_no'] ?? null;
            $billing = Billing::findById($billingId);

            if ($billing && $payAmount > 0) {
                try {
                    Billing::recordPayment($billingId, [
                        'amount_paid' => $payAmount,
                        'payment_method' => $method,
                        'transaction_reference' => $ref,
                        'recorded_by_user_id' => (int)$u['user_id'],
                        'payment_notes' => trim($_POST['secretary_notes'] ?? '') ?: 'Recorded from secretary panel'
                    ]);
                    $_SESSION['success'] = "Payment recorded successfully.";
                    redirect('payments.php');
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            } else {
                $error = "Invalid billing ID or amount.";
            }
        } elseif ($action === 'void') {
            $bid = (int)$_POST['billing_id'];
            if (db()->prepare("UPDATE billing SET billing_status = 'voided', amount_due = 0 WHERE billing_id = ? AND billing_status = 'unpaid'")->execute([$bid])) {
                AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $bid, 'Voided Invoice');
                $_SESSION['success'] = "Invoice voided successfully.";
                redirect('payments.php');
            } else {
                $error = "Failed to void invoice.";
            }
        } elseif ($action === 'refund') {
            $bid = (int)$_POST['billing_id'];
            if (db()->prepare("UPDATE billing SET billing_status = 'refunded', amount_paid = 0 WHERE billing_id = ?")->execute([$bid])) {
                AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $bid, 'Refunded Payment');
                $_SESSION['success'] = "Payment refunded successfully.";
                redirect('payments.php');
            } else {
                $error = "Failed to refund payment.";
            }
        } elseif ($action === 'add_fee') {
            $billingId = (int)($_POST['billing_id'] ?? 0);
            $feeAmount = (float)($_POST['fee_amount'] ?? 0);
            $feeDesc = trim($_POST['fee_description'] ?? '');

            if ($billingId && $feeAmount > 0 && $feeDesc) {
                if (Billing::addFee($billingId, $feeAmount, $feeDesc)) {
                    AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $billingId, "Added Fee: $feeDesc (₱" . number_format($feeAmount, 2) . ")");
                    $_SESSION['success'] = "Additional fee of ₱" . number_format($feeAmount, 2) . " added successfully.";
                    redirect('payments.php');
                } else {
                    $error = "Failed to add fee.";
                }
            } else {
                $error = "Invalid fee details.";
            }
        }
    }
}

// Fetch session messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? $error;
unset($_SESSION['success'], $_SESSION['error']);


// Fetch Billings
$search = $_GET['search'] ?? '';
$whereClause = "";
$params = [];

if (is_numeric($search)) {
    // Search by ID
    $whereClause = "WHERE b.billing_id = :search OR u.identification_number LIKE :searchLike";
    $params[':search'] = $search;
    $params[':searchLike'] = "%$search%";
} else {
    // Search by Name
    $whereClause = "WHERE u.first_name LIKE :search1 OR u.last_name LIKE :search2";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
}

$sql = "SELECT b.*, u.first_name, u.last_name, u.email, u.identification_number, h.name as hmo_name, h.short_name as hmo_code,
        (SELECT COUNT(*) FROM payments WHERE billing_id = b.billing_id AND is_verified = 0) as unverified_count
        FROM billing b
        JOIN users u ON b.user_id = u.user_id
        LEFT JOIN hmo_providers h ON b.hmo_provider_id = h.hmo_provider_id
        $whereClause
        ORDER BY b.created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$billings = $stmt->fetchAll();

// [NEW] Fetch HMO Providers for the modal
$stmtHmo = db()->prepare("SELECT * FROM hmo_providers WHERE is_active = 1 ORDER BY short_name ASC");
$stmtHmo->execute();
$hmoProviders = $stmtHmo->fetchAll();

// [NEW] Calculate filtered aggregates (Exclude Voided and Refunded from financial stats)
$activeBillings = array_filter($billings, fn($b) => !in_array($b['billing_status'], ['voided', 'refunded']));
$totalBilled = array_sum(array_column($activeBillings, 'amount_due'));
$totalCollected = array_sum(array_column($activeBillings, 'amount_paid'));
$outstanding = $totalBilled - $totalCollected;

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Payments & Billing</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Manage patient invoices and collection records.</p>
        </div>
        <div style="display:flex; gap:12px;">
            <button class="btn btn-outline" onclick="document.getElementById('modal-policies').classList.add('show')">
                <span class="material-symbols-outlined">policy</span> Payment Policies
            </button>
            <button class="btn btn-primary" onclick="openBillingModal()">
                <span class="material-symbols-outlined">add</span> Create Invoice
            </button>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; margin-bottom:24px;">
        <!-- ... (Stats cards remain same) ... -->
        <div style="background:var(--surface-light); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; align-items:center; gap:16px;">
            <div style="width:48px; height:48px; background:rgba(30, 64, 175, 0.1); color:var(--primary); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                <span class="material-symbols-outlined">payments</span>
            </div>
            <div>
                <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Billed</div>
                <div style="font-size:20px; font-weight:700;">₱<?= number_format($totalBilled, 2) ?></div>
            </div>
        </div>
        <div style="background:var(--surface-light); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; align-items:center; gap:16px;">
            <div style="width:48px; height:48px; background:rgba(34, 197, 94, 0.1); color:var(--success); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                <span class="material-symbols-outlined">account_balance_wallet</span>
            </div>
            <div>
                <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Total Collected</div>
                <div style="font-size:20px; font-weight:700; color:var(--success);">₱<?= number_format($totalCollected, 2) ?></div>
            </div>
        </div>
        <div style="background:var(--surface-light); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; align-items:center; gap:16px;">
            <div style="width:48px; height:48px; background:rgba(239, 68, 68, 0.1); color:var(--error); border-radius:12px; display:flex; align-items:center; justify-content:center;">
                <span class="material-symbols-outlined">pending_actions</span>
            </div>
            <div>
                <div style="font-size:12px; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Outstanding</div>
                <div style="font-size:20px; font-weight:700; color:var(--error);">₱<?= number_format($outstanding, 2) ?></div>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- ... (Search and Table sections remain same) ... -->
    <div class="search-box" style="margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by patient name or ID..." style="flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
            <button type="submit" class="btn btn-secondary">Search</button>
            <?php if ($search): ?><a href="payments.php" class="btn btn-outline">Clear</a><?php endif; ?>
        </form>
    </div>

    <table class="table">
        <!-- ... (Table Content remains same until Modals) ... -->
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
                    $paid = (float)($b['amount_paid'] ?? 0);
                    $hmo = (float)($b['hmo_claim_amount'] ?? 0);
                    $total = (float)$b['amount_due'];
                    $rawBalance = $total - ($paid + $hmo);
                    $balance = max(0, $rawBalance);
                    $credit = $rawBalance < 0 ? abs($rawBalance) : 0;
                    // ... (Status match logic remains same) ...
                    $statusInfo = match ($b['billing_status']) {
                        'paid' => ['bg' => 'success', 'label' => 'PAID'],
                        'unpaid' => ['bg' => 'error', 'label' => 'UNPAID'],
                        'partial' => ['bg' => 'warning', 'label' => 'PARTIAL'],
                        'refunded' => ['bg' => 'secondary', 'label' => 'REFUNDED'],
                        'voided' => ['bg' => 'secondary', 'label' => 'VOIDED'],
                        default => ['bg' => 'secondary', 'label' => strtoupper($b['billing_status'])]
                    };
                    ?>
                    <tr>
                        <td style="font-weight: 600;">INV-<?= str_pad((string)$b['billing_id'], 6, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= e((string)($b['first_name'] . ' ' . $b['last_name'])) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= e((string)($b['identification_number'] ?? 'N/A')) ?></div>
                        </td>
                        <td><?= e((string)($b['service_description'] ?? '')) ?></td>
                        <td style="font-weight: 600;">
                            ₱<?= number_format($total, 2) ?>
                            <?php if ($hmo > 0): ?>
                                <div style="font-size: 10px; color: var(--info); white-space: nowrap;">HMO: ₱<?= number_format($hmo, 2) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--success);">₱<?= number_format($paid, 2) ?></td>
                        <td>
                            <span class="badge badge-<?= $statusInfo['bg'] ?>"><?= $statusInfo['label'] ?></span>
                            <?php if (($b['unverified_count'] ?? 0) > 0): ?>
                                <div style="margin-top:4px;">
                                    <span class="badge badge-warning" style="font-size:9px; cursor:pointer;" onclick="openPaymentModal(<?= $b['billing_id'] ?>, <?= $balance ?>)">VERIFY PENDING</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= $b['due_date'] ? date('M j, Y', strtotime($b['due_date'])) : '-' ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="<?= base_url('/public/controllers/payment-handler.php?action=generate_invoice&billing_id=' . $b['billing_id']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Print Invoice">
                                <span class="material-symbols-outlined" style="font-size:16px;">print</span>
                            </a>
                            <?php if ($b['billing_status'] === 'unpaid'): ?>
                                <button class="btn btn-sm btn-primary" onclick="openPaymentModal(<?= $b['billing_id'] ?>, <?= $balance ?>)" title="Record Payment">
                                    <span class="material-symbols-outlined" style="font-size:16px;">payments</span>
                                </button>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Void this invoice?');">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="action" value="void">
                                    <input type="hidden" name="billing_id" value="<?= $b['billing_id'] ?>">
                                    <button class="btn btn-sm btn-outline" style="color:var(--text-secondary);" title="Void Invoice">
                                        <span class="material-symbols-outlined" style="font-size:16px;">block</span>
                                    </button>
                                </form>
                                <?php if ($b['billing_status'] === 'unpaid' || $b['billing_status'] === 'partial'): ?>
                                    <button class="btn btn-sm btn-outline" style="color:var(--info); border-color:var(--info);" onclick='openHmoModal(<?= json_encode(["id" => $b["billing_id"], "balance" => $balance]) ?>)' title="File HMO Claim">
                                        <span class="material-symbols-outlined" style="font-size:16px;">health_and_safety</span>
                                    </button>
                                <?php endif; ?>
                            <?php elseif ($b['billing_status'] === 'paid' || $b['billing_status'] === 'partial'): ?>
                                <?php if ($balance > 0): ?>
                                    <button class="btn btn-sm btn-primary" onclick="openPaymentModal(<?= $b['billing_id'] ?>, <?= $balance ?>)">Pay Bal</button>
                                <?php endif; ?>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Refund this payment?');">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="action" value="refund">
                                    <input type="hidden" name="billing_id" value="<?= $b['billing_id'] ?>">
                                    <button class="btn btn-sm btn-text" style="color:var(--warning);" title="Refund">
                                        <span class="material-symbols-outlined" style="font-size:16px;">undo</span>
                                    </button>
                                </form>
                            <?php elseif ($b['billing_status'] === 'voided'): ?>
                                <span class="badge badge-dark">Voided</span>
                            <?php elseif ($b['billing_status'] === 'refunded'): ?>
                                <span class="badge badge-outline">Refunded</span>
                            <?php endif; ?>

                            <?php if (in_array($b['billing_status'], ['unpaid', 'partial', 'paid'])): ?>
                                <button class="btn btn-sm btn-outline" style="color:var(--primary); border-color:var(--primary);" onclick="openAddFeeModal(<?= $b['billing_id'] ?>)" title="Add Charge/Fee">
                                    <span class="material-symbols-outlined" style="font-size:16px;">add_circle</span>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- New Billing Modal (Multi-Item) -->
<div id="modal-new-billing" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:95%; max-width:650px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="closeBillingModal()">&times;</button>
        <h3>Create New Invoice</h3>
        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="create_billing">

            <div class="form-group">
                <label>Patient</label>
                <input type="text" id="patient-search-input" placeholder="Search Patient Name..." onkeyup="searchPatients(this.value)" class="form-control" autocomplete="off">
                <input type="hidden" name="patient_id" id="selected-patient-id" required>
                <div id="patient-search-results" style="border:1px solid var(--border); max-height:150px; overflow-y:auto; display:none; border-radius:6px; margin-top:4px;"></div>
                <div id="selected-patient-display" style="margin-top: 5px; font-weight: 600; color: var(--primary);"></div>
            </div>

            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?= date('Y-m-d') ?>" class="form-control">
            </div>

            <div class="form-group" style="margin-top:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <label>Invoice Items</label>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addInvoiceItem()">+ Add Item</button>
                </div>
                <div id="invoice-items-container" style="margin-top:10px; display:flex; flex-direction:column; gap:8px;">
                    <!-- Dynamically added items -->
                </div>
                <div style="margin-top:12px; text-align:right; font-weight:700; font-size:18px;">
                    Total: ₱<span id="invoice-total-display">0.00</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Create Invoice</button>
        </form>
    </div>
</div>

<!-- Policy Management Modal -->
<div id="modal-policies" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:450px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-policies').classList.remove('show')">&times;</button>
        <h3>Payment Period Policies</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Set the default number of days patients have to settle their dues.</p>

        <form action="<?= base_url('/public/controllers/payment-handler.php') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="update_policies">

            <div class="form-group">
                <label>Maternity/Prenatal (Days)</label>
                <input type="number" name="policy_maternity_days" value="<?= Billing::getPolicy('policy_maternity_days', '7') ?>" required min="1" class="form-control">
            </div>

            <div class="form-group">
                <label>Gynecological Services (Days)</label>
                <input type="number" name="policy_gyne_days" value="<?= Billing::getPolicy('policy_gyne_days', '7') ?>" required min="1" class="form-control">
            </div>

            <div class="form-group">
                <label>Laboratory Tests (Days)</label>
                <input type="number" name="policy_lab_days" value="<?= Billing::getPolicy('policy_lab_days', '7') ?>" required min="1" class="form-control">
            </div>

            <div class="form-group">
                <label>General Consultations (Days)</label>
                <input type="number" name="policy_consultation_days" value="<?= Billing::getPolicy('policy_consultation_days', '7') ?>" required min="1" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Update Policies</button>
        </form>
    </div>
</div>

<script>
    // Initialize with one item
    document.addEventListener('DOMContentLoaded', () => {
        addInvoiceItem();
    });

    function addInvoiceItem() {
        const container = document.getElementById('invoice-items-container');
        const index = container.children.length;
        const row = document.createElement('div');
        row.style.cssText = 'display:flex; gap:8px; align-items:center; background:var(--bg-light); padding:8px; border-radius:8px;';
        row.innerHTML = `
            <div style="flex:2;">
                <input type="text" name="items[${index}][description]" id="item-desc-${index}" placeholder="Description (e.g. Consult)" class="form-control" required>
            </div>
            <div style="flex:1; display:flex; gap:4px; align-items:center;">
                <input type="number" name="items[${index}][amount]" id="item-amt-${index}" placeholder="Amt" class="form-control amount-input" step="0.01" min="0" oninput="calculateInvoiceTotal()" required>
                <button type="button" class="btn btn-xs btn-outline" style="font-size:9px; padding:2px 4px;" title="Apply 20% Downpayment" onclick="applyDownpaymentHelper(${index})">20%</button>
            </div>
            <button type="button" class="btn btn-sm btn-text" style="color:var(--error);" onclick="this.parentElement.remove(); calculateInvoiceTotal();"><span class="material-symbols-outlined">delete</span></button>
        `;
        container.appendChild(row);
    }

    function applyDownpaymentHelper(idx) {
        const amtInput = document.getElementById(`item-amt-${idx}`);
        const descInput = document.getElementById(`item-desc-${idx}`);
        let currentVal = parseFloat(amtInput.value || 0);
        if (currentVal > 0) {
            const dp = currentVal * 0.2;
            amtInput.value = dp.toFixed(2);
            if (!descInput.value.includes('Down Payment')) {
                descInput.value += ' Down Payment (20%)';
            }
            calculateInvoiceTotal();
        }
    }

    function calculateInvoiceTotal() {
        let total = 0;
        document.querySelectorAll('.amount-input').forEach(input => {
            total += parseFloat(input.value || 0);
        });
        document.getElementById('invoice-total-display').textContent = total.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ... (rest of usage) ...
</script>

<!-- Record Payment Modal -->
<div id="modal-payment" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="closePaymentModal()">&times;</button>
        <h3>Record Payment</h3>

        <div id="payment-verify-section" style="display:none; margin: 15px 0; padding:12px; background:rgba(255, 193, 7, 0.1); border-left:4px solid #ffc107; border-radius:4px;">
            <strong style="font-size:12px; color:#856404;">Pending Verification:</strong>
            <div id="unverified-payments-list" style="margin-top:8px;"></div>
        </div>

        <form method="POST" action="" style="margin-top: 10px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="billing_id" id="pay-billing-id">

            <div class="form-group">
                <label>Amount to Pay (PHP)</label>
                <input type="number" name="payment_amount" id="pay-amount" step="0.01" min="0" class="form-control" required>
                <div id="pay-max-note" style="font-size:10px; color:var(--text-secondary); margin-top:4px;"></div>
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="Cash">Cash</option>
                    <option value="Card">Credit/Debit Card</option>
                    <option value="GCash">GCash</option>
                    <option value="Maya">Maya</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Reference No. (Optional)</label>
                <input type="text" name="reference_no" class="form-control" placeholder="OR Number or Transaction ID">
            </div>

            <div class="form-group">
                <label>Secretary Notes (Optional)</label>
                <textarea name="secretary_notes" class="form-control" style="height:60px;" placeholder="Add verification details or internal notes..."></textarea>
            </div>

            <button type="submit" class="btn btn-success btn-block" style="margin-top: 20px;">Record Payment</button>
        </form>
    </div>
</div>

<script>
    function openBillingModal() {
        document.getElementById('modal-new-billing').classList.add('show');
    }

    function closeBillingModal() {
        document.getElementById('modal-new-billing').classList.remove('show');
    }

    function openPaymentModal(billingId, balance, notes = '') {
        document.getElementById('pay-billing-id').value = billingId;
        const maxPay = Math.max(0, balance);
        document.getElementById('pay-amount').value = maxPay.toFixed(2);
        document.getElementById('pay-amount').max = maxPay;
        document.getElementById('pay-max-note').textContent = "Max allowed to settle: ₱" + maxPay.toFixed(2);

        const verifySection = document.getElementById('payment-verify-section');
        const list = document.getElementById('unverified-payments-list');
        list.innerHTML = '<div style="text-align:center; padding:10px; font-size:11px; opacity:0.6;">Loading history...</div>';

        // Fetch payment history via AJAX
        fetch(`payments.php?ajax_action=get_payments&billing_id=${billingId}`)
            .then(res => res.json())
            .then(payments => {
                const unverified = payments.filter(p => p.is_verified == 0);
                if (unverified.length > 0) {
                    verifySection.style.display = 'block';
                    list.innerHTML = unverified.map(p => `
                        <div style="background:var(--surface); border:1px solid #ffeeba; padding:8px; border-radius:4px; margin-bottom:6px; font-size:11px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <strong>₱${parseFloat(p.amount).toLocaleString()}</strong> via ${p.method}<br>
                                    <span style="opacity:0.7;">Ref: ${p.reference_number || 'N/A'}</span>
                                </div>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="action" value="verify_payment">
                                    <input type="hidden" name="payment_id" value="${p.payment_id}">
                                    <button type="submit" class="btn btn-sm btn-success" style="padding:2px 8px; font-size:10px;">Verify</button>
                                </form>
                            </div>
                        </div>
                    `).join('');
                } else {
                    verifySection.style.display = 'none';
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                verifySection.style.display = 'none';
            });

        document.getElementById('modal-payment').classList.add('show');
    }

    function closePaymentModal() {
        document.getElementById('modal-payment').classList.remove('show');
    }

    function searchPatients(q) {
        const results = document.getElementById('patient-search-results');
        if (q.length < 2) {
            results.style.display = 'none';
            return;
        }

        results.innerHTML = '<div style="padding:15px; text-align:center; color:var(--text-secondary);"><span class="material-symbols-outlined" style="display:block; font-size:24px; margin-bottom:5px;">sync</span>Searching...</div>';
        results.style.display = 'block';

        fetch('<?= base_url('/public/controllers/message-handler.php') ?>?action=search_users&q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.users.length > 0) {
                    const patients = json.users.filter(u => u.role === 'patient');
                    if (patients.length === 0) {
                        results.innerHTML = '<div style="padding:15px; text-align:center; color:var(--text-secondary);">No patients found.</div>';
                        return;
                    }

                    let html = '';
                    patients.forEach(p => {
                        html += `<div style="padding: 12px; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: background 0.2s;" 
                             class="search-result-item"
                             onclick="selectPatient(${p.user_id}, '${p.first_name} ${p.last_name}')"
                             onmouseover="this.style.background='var(--bg-light)'"
                             onmouseout="this.style.background='var(--surface)'">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div style="font-weight: 600; color:var(--text-primary);">${p.first_name} ${p.last_name}</div>
                                    <div style="font-size: 11px; color: var(--text-secondary);">ID: ${p.identification_number || 'N/A'}</div>
                                </div>
                                <span class="material-symbols-outlined" style="font-size:18px; color:var(--primary);">add_circle</span>
                            </div>
                        </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                } else {
                    results.innerHTML = '<div style="padding:15px; text-align:center; color:var(--text-secondary);">No results found.</div>';
                }
            })
            .catch(err => {
                results.innerHTML = '<div style="padding:15px; text-align:center; color:var(--error);">Error searching patients.</div>';
            });
    }

    function selectPatient(id, name) {
        document.getElementById('selected-patient-id').value = id;
        document.getElementById('selected-patient-display').textContent = "Selected: " + name;
        document.getElementById('patient-search-results').style.display = 'none';
        document.getElementById('patient-search-input').value = name;
    }

    function openAddFeeModal(bid) {
        document.getElementById('fee_billing_id').value = bid;
        document.getElementById('modal-add-fee').classList.add('show');
    }
</script>


<!-- HMO Claim submission Modal -->
<div id="modal-hmo-claim" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:450px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-hmo-claim').classList.remove('show')">&times;</button>
        <h3>File HMO Claim</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Submit an insurance claim for this billing record. This will flag the invoice as 'Pending HMO Claim'.</p>

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

<script>
    function openHmoModal(data) {
        document.getElementById('hmo-billing-id').value = data.id;
        const maxClaim = Math.max(0, data.balance);
        document.getElementById('hmo-claim-amount').value = maxClaim.toFixed(2);
        document.getElementById('hmo-claim-amount').max = maxClaim;
        document.getElementById('modal-hmo-claim').classList.add('show');
    }
</script>

<!-- Add Fee Modal -->
<div id="modal-add-fee" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-fee').classList.remove('show')">&times;</button>
        <h3>Add Additional Charge</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Use this to add missing fees or surgical supplies to an existing invoice.</p>

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

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Add to Invoice</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'payments', [
    'title' => 'Payments & Billing',
    'content' => $content,
]);
