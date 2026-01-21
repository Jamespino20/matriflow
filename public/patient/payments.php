<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$userId = (int)$u['user_id'];

// Handle Payment Reporting
$success = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_payment') {
    if (CSRF::validate($_POST['csrf_token'] ?? '')) {
        $bid = (int)$_POST['billing_id'];
        $amount = (float)$_POST['amount'];
        $ref = trim($_POST['reference'] ?? '');
        $method = $_POST['method'] ?? 'Unknown';
        $paymentData = [
            'amount_paid' => $amount,
            'payment_method' => strtolower($method),
            'transaction_reference' => $ref,
            'recorded_by_user_id' => $userId,
            'is_verified' => 0, // Mark for verification
            'payment_notes' => "Patient self-reported payment."
        ];

        try {
            if (Billing::recordPayment($bid, $paymentData)) {
                AuditLogger::log($userId, 'billing', 'UPDATE', $bid, "Patient reported $method payment" . ($ref ? " ($ref)" : ""));
                $success = "Payment report submitted. Your balance has been updated, pending final verification by the secretary.";
            } else {
                $error = "Failed to submit payment report. Please check the amount or balance.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Invalid security token. Please refresh and try again.";
    }
}

// Handle Per-Item Payment Reporting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_item_payment') {
    if (CSRF::validate($_POST['csrf_token'] ?? '')) {
        $itemId = (int)$_POST['item_id'];
        $amount = (float)$_POST['amount'];
        $ref = trim($_POST['reference'] ?? '');
        $method = $_POST['method'] ?? 'Unknown';

        try {
            // Find the item and its billing record
            $stmt = db()->prepare("SELECT bi.*, b.billing_id, b.user_id, b.amount_due, b.amount_paid as billing_paid 
                                   FROM billing_items bi 
                                   JOIN billing b ON bi.billing_id = b.billing_id 
                                   WHERE bi.item_id = ?");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();

            if (!$item || (int)$item['user_id'] !== $userId) {
                throw new Exception("Item not found or access denied.");
            }

            $itemBalance = (float)$item['amount'] - (float)$item['paid_amount'];
            if ($amount > $itemBalance) {
                throw new Exception("Amount exceeds item balance (₱" . number_format($itemBalance, 2) . ").");
            }

            // Update item paid_amount
            $newItemPaid = (float)$item['paid_amount'] + $amount;
            db()->prepare("UPDATE billing_items SET paid_amount = ? WHERE item_id = ?")->execute([$newItemPaid, $itemId]);

            // Update parent billing's amount_paid and recalc status
            $newBillingPaid = (float)$item['billing_paid'] + $amount;
            $billingStatus = ($newBillingPaid >= (float)$item['amount_due']) ? 'paid' : ($newBillingPaid > 0 ? 'partial' : 'unpaid');
            db()->prepare("UPDATE billing SET amount_paid = ?, billing_status = ?, updated_at = NOW() WHERE billing_id = ?")
                ->execute([$newBillingPaid, $billingStatus, $item['billing_id']]);

            // Record in payments table for audit
            db()->prepare("INSERT INTO payments (billing_id, amount, method, reference_number, is_verified, notes, paid_at) VALUES (?, ?, ?, ?, 0, ?, NOW())")
                ->execute([$item['billing_id'], $amount, strtolower($method), $ref, "Per-item payment for: " . $item['description']]);

            AuditLogger::log($userId, 'billing_item', 'UPDATE', $itemId, "Paid ₱$amount for item: {$item['description']}");
            $success = "Payment for '{$item['description']}' submitted successfully.";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Invalid security token. Please refresh and try again.";
    }
}

// Fetch billings AFTER POST handler so updates are reflected
$billings = Billing::listByPatient($userId);
$hmoProviders = HmoProvider::listActive();

$highlightId = (int)($_GET['billing_id'] ?? 0);

ob_start();
?>
<style>
    .payment-row {
        transition: background-color 0.2s ease, transform 0.1s ease;
        cursor: pointer;
    }

    .payment-row:hover {
        background-color: var(--surface-hover) !important;
    }

    .payment-row.highlighted {
        background-color: rgba(20, 69, 123, 0.05) !important;
        border-left: 4px solid var(--primary);
    }

    .payment-row.is-new {
        animation: pulse-border 2s infinite;
    }

    @keyframes pulse-border {
        0% {
            border-left-color: var(--primary);
        }

        50% {
            border-left-color: var(--info);
        }

        100% {
            border-left-color: var(--primary);
        }
    }
</style>
<div class="card">
    <h2>Payments & Billing</h2>
    <p>View your payment history and outstanding invoices.</p>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Service</th>
                <th>Amount Due</th>
                <th>HMO Coverage</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($billings)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: #637588;">No payment records</td>
                </tr>
            <?php else: ?>
                <?php foreach ($billings as $b): ?>
                    <?php
                    $total = (float)$b['amount_due'];
                    $paid = (float)($b['amount_paid'] ?? 0);
                    $hmo = (float)($b['hmo_claim_amount'] ?? 0);
                    $rawBalance = $total - ($paid + $hmo);
                    $balance = max(0, $rawBalance);
                    $credit = $rawBalance < 0 ? abs($rawBalance) : 0;

                    $isRecent = (strtotime($b['created_at']) > strtotime('-24 hours'));
                    $isHighlighted = ($highlightId === (int)$b['billing_id']);

                    // Fetch line items for this invoice
                    $items = Billing::getItems((int)$b['billing_id']);
                    $hasMultipleItems = count($items) > 1;
                    ?>
                    <tr class="payment-row <?= $isHighlighted ? 'highlighted' : '' ?> <?= $isRecent ? 'is-new' : '' ?>"
                        onclick="toggleItemsRow(<?= $b['billing_id'] ?>)" style="cursor: pointer;">
                        <td>
                            <?= date('M j, Y', strtotime($b['created_at'])) ?>
                            <?php if ($isRecent): ?>
                                <div style="font-size:9px; color:var(--primary); font-weight:700; margin-top:2px;">RECENT</div>
                            <?php endif; ?>
                            <?php if ($hasMultipleItems): ?>
                                <div style="font-size:9px; color:var(--info); margin-top:2px;">
                                    <span class="material-symbols-outlined" style="font-size:12px; vertical-align:middle;">expand_more</span> <?= count($items) ?> items
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($b['service_description']) ?></td>
                        <td style="font-weight:600;">
                            ₱<?= number_format($total, 2) ?>
                        </td>
                        <td style="color:var(--info);">
                            ₱<?= number_format($hmo, 2) ?>
                            <?php if (!empty($b['hmo_name'])): ?>
                                <div style="font-size:10px; opacity:0.8;"><?= e($b['hmo_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="color:var(--success); font-weight:600;">₱<?= number_format($paid, 2) ?></div>
                            <?php if (($b['unverified_count'] ?? 0) > 0): ?>
                                <div style="font-size:10px; color:var(--warning); margin-top:2px; display:flex; align-items:center; gap:2px;">
                                    <span class="material-symbols-outlined" style="font-size:12px;">history</span> Pending Verification
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight:700; color:<?= $balance > 0 ? 'var(--error)' : ($credit > 0 ? 'var(--success)' : 'var(--text-primary)') ?>;">
                            ₱<?= number_format($balance, 2) ?>
                            <?php if ($credit > 0): ?>
                                <div style="font-size:10px; color:var(--success); margin-top:2px;">Credit: ₱<?= number_format($credit, 2) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['billing_status'] === 'paid'): ?>
                                <span class="badge badge-success">Paid</span>
                            <?php elseif ($b['billing_status'] === 'partial'): ?>
                                <span class="badge badge-warning">Partial</span>
                            <?php elseif ($b['billing_status'] === 'refunded'): ?>
                                <span class="badge badge-secondary">Refunded</span>
                            <?php elseif ($b['billing_status'] === 'voided'): ?>
                                <span class="badge badge-dark">Voided</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Unpaid</span>
                            <?php endif; ?>

                            <?php if (!empty($b['hmo_claim_status']) && $b['hmo_claim_status'] !== 'none'): ?>
                                <div style="margin-top:4px;">
                                    <?php
                                    $hmoStatusColor = match ($b['hmo_claim_status']) {
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected' => 'error',
                                        'paid' => 'success',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge badge-<?= $hmoStatusColor ?>" style="font-size:10px;">HMO: <?= ucfirst($b['hmo_claim_status']) ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;" onclick="event.stopPropagation();">
                            <a href="<?= base_url('/public/controllers/payment-handler.php?action=generate_invoice&billing_id=' . $b['billing_id']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Download Invoice">
                                <span class="material-symbols-outlined" style="font-size:16px;">download</span> PDF
                            </a>
                            <?php if ($b['billing_status'] === 'paid'): ?>
                                <form action="<?= base_url('/public/controllers/payment-handler.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to request a refund? Admin will review this request.')">
                                    <input type="hidden" name="action" value="request_refund">
                                    <input type="hidden" name="billing_id" value="<?= $b['billing_id'] ?>">
                                    <?= CSRF::input() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Request Refund">
                                        <span class="material-symbols-outlined" style="font-size:16px;">undo</span> Refund
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($balance > 0 && $b['billing_status'] !== 'voided' && $b['billing_status'] !== 'refunded'): ?>
                                <button type="button" class="btn btn-sm btn-primary" onclick="openReportModal(<?= $b['billing_id'] ?>, <?= $balance ?>, '<?= e($b['service_description']) ?>')" title="Report Multi-payment">
                                    <span class="material-symbols-outlined" style="font-size:16px;">payments</span> Pay All
                                </button>
                                <?php if (empty($b['hmo_claim_status']) || $b['hmo_claim_status'] === 'none' || $b['hmo_claim_status'] === 'rejected'): ?>
                                    <button type="button" class="btn btn-sm btn-outline" style="color:var(--info); border-color:var(--info);" onclick='openHmoModal(<?= json_encode(["id" => $b["billing_id"], "balance" => $balance]) ?>)' title="File HMO Claim">
                                        <span class="material-symbols-outlined" style="font-size:16px;">health_and_safety</span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable Items Row -->
                    <?php if ($hasMultipleItems): ?>
                        <tr id="items-row-<?= $b['billing_id'] ?>" class="items-row" style="display:none; background:var(--surface-hover);">
                            <td colspan="8" style="padding:0;">
                                <div style="padding:12px 20px; border-left:4px solid var(--primary);">
                                    <div style="font-weight:600; margin-bottom:8px; color:var(--text-secondary); font-size:12px;">INVOICE ITEMS</div>
                                    <table style="width:100%; font-size:13px;">
                                        <thead>
                                            <tr style="color:var(--text-secondary);">
                                                <th style="text-align:left; padding:4px 8px;">Service</th>
                                                <th style="text-align:right; padding:4px 8px;">Amount</th>
                                                <th style="text-align:right; padding:4px 8px;">Paid</th>
                                                <th style="text-align:right; padding:4px 8px;">Balance</th>
                                                <th style="text-align:right; padding:4px 8px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item):
                                                $itemAmt = (float)$item['amount'];
                                                $itemPaid = (float)($item['paid_amount'] ?? 0);
                                                $itemBalance = $itemAmt - $itemPaid;
                                            ?>
                                                <tr>
                                                    <td style="padding:4px 8px;"><?= e($item['description']) ?></td>
                                                    <td style="text-align:right; padding:4px 8px;">₱<?= number_format($itemAmt, 2) ?></td>
                                                    <td style="text-align:right; padding:4px 8px; color:var(--success);">₱<?= number_format($itemPaid, 2) ?></td>
                                                    <td style="text-align:right; padding:4px 8px; font-weight:600; color:<?= $itemBalance > 0 ? 'var(--error)' : 'var(--text-primary)' ?>;">
                                                        ₱<?= number_format($itemBalance, 2) ?>
                                                    </td>
                                                    <td style="text-align:right; padding:4px 8px;">
                                                        <?php if ($itemBalance > 0): ?>
                                                            <button type="button" class="btn btn-xs btn-outline" style="font-size:10px; padding:2px 6px;"
                                                                onclick="openItemPayModal(<?= $item['item_id'] ?>, <?= $itemBalance ?>, '<?= e($item['description']) ?>')">
                                                                Pay This
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge badge-success" style="font-size:9px;">Paid</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Report Payment Modal -->
<div id="modal-report-payment" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-report-payment').classList.remove('show')">&times;</button>
        <h3>Report Payment</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">If you paid via GCash, Maya, or Bank Transfer, please enter your reference number below for clinic verification.</p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="report_payment">
            <input type="hidden" name="billing_id" id="report-billing-id">

            <div class="form-group">
                <label>Amount Paid (₱)</label>
                <div id="quick-pay-options" style="display:flex; gap:8px; margin-bottom:8px;">
                    <!-- Populate dynamically via JS -->
                </div>
                <input type="number" step="0.01" name="amount" id="report-amount" required min="1">
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="method" id="report-method" required onchange="toggleReferenceRequired()">
                    <option value="Cash">Cash (at Counter)</option>
                    <option value="Card">Credit/Debit Card</option>
                    <option value="GCash">GCash</option>
                    <option value="Maya">Maya</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="form-group" id="reference-group">
                <label>Reference Number <span id="ref-optional" style="font-size:11px; color:var(--text-secondary);">(optional for Cash)</span></label>
                <input type="text" name="reference" id="report-reference" placeholder="e.g. 123 456 789">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Submit for Verification</button>
        </form>
    </div>
</div>

</div>
</div>

<!-- HMO Claim Modal -->
<div id="modal-hmo-claim" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-hmo-claim').classList.remove('show')">&times;</button>
        <h3>File HMO Claim</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Select your HMO provider and enter the covered amount.</p>

        <form action="<?= base_url('/public/controllers/hmo-handler.php') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="submit_claim">
            <input type="hidden" name="billing_id" id="hmo-billing-id">

            <div class="form-group">
                <label>HMO Provider</label>
                <select name="hmo_provider_id" required class="form-control">
                    <option value="">Select Provider</option>
                    <?php foreach ($hmoProviders as $hp): ?>
                        <option value="<?= $hp['hmo_provider_id'] ?>"><?= e($hp['short_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Claim Amount (₱)</label>
                <input type="number" name="claim_amount" id="hmo-claim-amount" step="0.01" min="0" required class="form-control">
                <div style="font-size:11px; color:var(--text-secondary); margin-top:4px;">Max claimable: ₱<span id="hmo-max-amount">0.00</span></div>
            </div>

            <div class="form-group">
                <label>Member ID / Reference (Optional)</label>
                <input type="text" name="reference" placeholder="Card No. or Approval Code" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Submit Claim</button>
        </form>
    </div>
</div>

<script>
    function openHmoModal(data) {
        document.getElementById('hmo-billing-id').value = data.id;
        const maxClaim = Math.max(0, data.balance);
        document.getElementById('hmo-claim-amount').max = maxClaim;
        document.getElementById('hmo-claim-amount').value = maxClaim.toFixed(2);
        document.getElementById('hmo-max-amount').textContent = maxClaim.toFixed(2);
        document.getElementById('modal-hmo-claim').classList.add('show');
    }

    function openReportModal(bid, balance, description = '') {
        document.getElementById('report-billing-id').value = bid;
        document.getElementById('report-amount').value = balance.toFixed(2);

        // Handle Quick-Pay Options
        const quickOptions = document.getElementById('quick-pay-options');
        quickOptions.innerHTML = '';

        // Try to parse percentage from description (e.g., "(Min. Down-Payment: 20%)")
        const match = description.match(/(\d+)%/);
        if (match && balance > 0) {
            const percentage = parseInt(match[1]) / 100;
            const fullAmountElement = document.querySelector(`tr:has(button[onclick*="'${bid}'"]) td:nth-child(3)`);
            // Note: Since we don't have a direct data-total, we rely on the full balance for this helper
            // or we could pass the original total to this function.
            // For now, let's just use the balance as "Full" and calculate min if balance == total.

            // Extract total billed from the row (column 3 in our table)
            const row = document.querySelector(`button[onclick*="openReportModal(${bid},"]`).closest('tr');
            const totalBilledValue = parseFloat(row.cells[2].innerText.replace(/[^\d.]/g, ''));
            const minAmount = totalBilledValue * percentage;

            if (balance > minAmount) {
                quickOptions.innerHTML += `<button type="button" class="btn btn-sm btn-secondary" style="font-size:10px; padding:4px 8px;" onclick="document.getElementById('report-amount').value='${minAmount.toFixed(2)}'">Pay Min (₱${minAmount.toFixed(2)})</button>`;
            }
            quickOptions.innerHTML += `<button type="button" class="btn btn-sm btn-outline" style="font-size:10px; padding:4px 8px;" onclick="document.getElementById('report-amount').value='${balance.toFixed(2)}'">Pay Full (₱${balance.toFixed(2)})</button>`;
        }

        document.getElementById('modal-report-payment').classList.add('show');
        toggleReferenceRequired(); // Reset on open
    }

    function toggleReferenceRequired() {
        const method = document.getElementById('report-method').value;
        const refInput = document.getElementById('report-reference');
        const optionalLabel = document.getElementById('ref-optional');

        if (method === 'Cash') {
            refInput.removeAttribute('required');
            optionalLabel.style.display = 'inline';
            refInput.placeholder = 'Receipt # (optional)';
        } else {
            refInput.setAttribute('required', 'required');
            optionalLabel.style.display = 'none';
            refInput.placeholder = method === 'Card' ? 'Last 4 digits or approval code' : 'e.g. 123 456 789';
        }
    }

    function toggleItemsRow(billingId) {
        const row = document.getElementById('items-row-' + billingId);
        if (row) {
            row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
        }
    }

    function openItemPayModal(itemId, balance, description) {
        document.getElementById('item-pay-id').value = itemId;
        document.getElementById('item-pay-amount').value = balance.toFixed(2);
        document.getElementById('item-pay-description').textContent = description;

        // Quick pay option for items
        const itemQuickPay = document.getElementById('item-quick-pay-options');
        itemQuickPay.innerHTML = '';
        const minAmount = balance * 0.2;
        if (balance > minAmount) {
            itemQuickPay.innerHTML = `<button type="button" class="btn btn-sm btn-secondary" style="font-size:10px; padding:4px 8px;" onclick="document.getElementById('item-pay-amount').value='${minAmount.toFixed(2)}'">Pay 20% Min (₱${minAmount.toFixed(2)})</button>`;
            itemQuickPay.innerHTML += `<button type="button" class="btn btn-sm btn-outline" style="font-size:10px; padding:4px 8px;" onclick="document.getElementById('item-pay-amount').value='${balance.toFixed(2)}'">Pay Full (₱${balance.toFixed(2)})</button>`;
        }

        document.getElementById('modal-item-payment').classList.add('show');
        toggleItemReferenceRequired();
    }

    function toggleItemReferenceRequired() {
        const method = document.getElementById('item-pay-method').value;
        const refInput = document.getElementById('item-pay-reference');
        if (method === 'Cash') {
            refInput.removeAttribute('required');
        } else {
            refInput.setAttribute('required', 'required');
        }
    }
</script>

<!-- Per-Item Payment Modal -->
<div id="modal-item-payment" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-item-payment').classList.remove('show')">&times;</button>
        <h3>Pay Specific Item</h3>
        <p id="item-pay-description" style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;"></p>

        <form method="POST" action="">
            <?= CSRF::input() ?>
            <input type="hidden" name="action" value="report_item_payment">
            <input type="hidden" name="item_id" id="item-pay-id">

            <div class="form-group">
                <label>Amount Paid (₱)</label>
                <div id="item-quick-pay-options" style="display:flex; gap:8px; margin-bottom:8px;"></div>
                <input type="number" step="0.01" name="amount" id="item-pay-amount" required min="1">
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select name="method" id="item-pay-method" required onchange="toggleItemReferenceRequired()">
                    <option value="Cash">Cash (at Counter)</option>
                    <option value="Card">Credit/Debit Card</option>
                    <option value="GCash">GCash</option>
                    <option value="Maya">Maya</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference" id="item-pay-reference" placeholder="e.g. 123 456 789">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Submit Payment</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'patient', 'payments', [
    'title' => 'Payments & Billing',
    'content' => $content,
]);
