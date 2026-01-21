<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

ob_start();

$claims = Billing::listPendingHmoClaims();
$activeProviders = HmoProvider::listActive();
$allProviders = HmoProvider::listAll();
$tab = $_GET['tab'] ?? 'claims';
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">HMO Administration</h2>
            <p style="margin:5px 0 0; color:var(--text-secondary);">Manage insurance claims and accredited HMO centers.</p>
        </div>
        <?php if ($tab === 'providers'): ?>
            <button class="btn btn-primary" onclick="document.getElementById('modal-add-provider').classList.add('show')">
                <span class="material-symbols-outlined">add</span> Add Provider
            </button>
        <?php endif; ?>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav" style="display:flex; gap:24px; border-bottom:1px solid var(--border); margin-bottom:24px;">
        <a href="?tab=claims" class="tab-link <?= $tab === 'claims' ? 'active' : '' ?>" style="padding:12px 4px; color:<?= $tab === 'claims' ? 'var(--primary)' : 'var(--text-secondary)' ?>; border-bottom:2px solid <?= $tab === 'claims' ? 'var(--primary)' : 'transparent' ?>; text-decoration:none; font-weight:600;">Claims Tracking</a>
        <a href="?tab=providers" class="tab-link <?= $tab === 'providers' ? 'active' : '' ?>" style="padding:12px 4px; color:<?= $tab === 'providers' ? 'var(--primary)' : 'var(--text-secondary)' ?>; border-bottom:2px solid <?= $tab === 'providers' ? 'var(--primary)' : 'transparent' ?>; text-decoration:none; font-weight:600;">Accredited Providers</a>
    </div>

    <?php if ($tab === 'claims'): ?>
        <!-- Claims Tracker Tab -->
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Patient</th>
                        <th>HMO Provider</th>
                        <th>Claim Amount</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($claims)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding:40px; color: var(--text-secondary);">No pending HMO claims found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($claims as $c): ?>
                            <tr>
                                <td style="font-family:monospace;">INV-<?= str_pad((string)$c['billing_id'], 6, '0', STR_PAD_LEFT) ?></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                <td><?= htmlspecialchars($c['hmo_name']) ?></td>
                                <td style="font-weight:700;">₱<?= number_format($c['hmo_claim_amount'], 2) ?></td>
                                <td>
                                    <?php
                                    $sColor = match ($c['hmo_claim_status']) {
                                        'submitted' => 'warning',
                                        'approved' => 'info',
                                        'paid' => 'success',
                                        'rejected' => 'error',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge badge-<?= $sColor ?>">
                                        <?= ucfirst($c['hmo_claim_status']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <button class="btn btn-sm btn-primary" onclick="openUpdateModal(<?= htmlspecialchars(json_encode($c)) ?>)">Update Status</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Providers Management Tab -->
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Short Name</th>
                        <th>Full Name / Entity</th>
                        <th>System Code</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allProviders as $p): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($p['short_name']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td style="font-family:monospace;"><?= htmlspecialchars($p['short_code']) ?></td>
                            <td>
                                <span class="badge badge-<?= $p['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $p['is_active'] ? 'Active' : 'Deactivated' ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" action="<?= base_url('/public/controllers/hmo-handler.php') ?>" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="action" value="toggle_provider">
                                    <input type="hidden" name="hmo_provider_id" value="<?= $p['hmo_provider_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $p['is_active'] ? 'btn-outline' : 'btn-primary' ?>">
                                        <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Update Status Modal -->
<div id="modal-update-claim" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-update-claim').classList.remove('show')">&times;</button>
        <h3>Update Claim Status</h3>
        <form action="<?= base_url('/public/controllers/hmo-handler.php') ?>" method="POST" style="margin-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="update_claim_status">
            <input type="hidden" name="billing_id" id="update-billing-id">

            <div class="form-group">
                <label>New Status</label>
                <select name="status" id="update-status" required>
                    <option value="submitted">Pending (Submitted)</option>
                    <option value="approved">Approved / In-Progress</option>
                    <option value="paid">Claimed / Paid</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>

            <div class="form-group">
                <label>HMO Reference # (Optional)</label>
                <input type="text" name="reference" id="update-reference" placeholder="Reference from provider...">
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Save Changes</button>
        </form>
    </div>
</div>

<!-- Add Provider Modal -->
<div id="modal-add-provider" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-provider').classList.remove('show')">&times;</button>
        <h3>Add Accredited HMO</h3>
        <form action="<?= base_url('/public/controllers/hmo-handler.php') ?>" method="POST" style="margin-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="create_provider">

            <div class="form-group">
                <label>Full Name / Description</label>
                <input type="text" name="name" placeholder="e.g. Maxicare HealthCare Corp." required>
            </div>

            <div class="form-group">
                <label>Short Name (Display Name)</label>
                <input type="text" name="short_name" placeholder="e.g. Maxicare" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Add Provider</button>
        </form>
    </div>
</div>

<script>
    function openUpdateModal(claim) {
        document.getElementById('update-billing-id').value = claim.billing_id;
        document.getElementById('update-status').value = claim.hmo_claim_status;
        document.getElementById('update-reference').value = claim.hmo_claim_reference || '';
        document.getElementById('modal-update-claim').classList.add('show');
    }
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'hmo-claims', [
    'title' => 'HMO Claims',
    'content' => $content,
]);
