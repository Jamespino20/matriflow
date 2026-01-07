<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

ob_start();
?>
<?php
$q = trim((string)($_GET['q'] ?? ''));
$tests = LaboratoryController::listAll(['q' => $q]);
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Laboratory Orders</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Manage patient test orders and record status.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient or test type..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($q): ?>
            <a href="lab-tests.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Test Type</th>
                <th>Ordered On</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tests)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No lab results found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></div>
                        </td>
                        <td><strong><?= e($t['test_type']) ?></strong></td>
                        <td><?= date('M j, Y', strtotime($t['ordered_at'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= strtoupper($t['status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-secondary btn-sm" onclick="openLabModal(<?= htmlspecialchars(json_encode($t)) ?>)">Update</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Update Modal -->
<div id="modal-lab-update" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-lab-update').style.display='none'">&times;</button>
        <h3>Update Lab Order</h3>
        <p id="lab-patient-name" style="font-weight: 700; color: var(--primary); margin-top: 10px;"></p>

        <form id="form-lab-update" action="/public/controllers/lab-handler.php" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="test_id" id="lab-id">

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="lab-status" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Findings (Results)</label>
                <textarea name="test_result" id="lab-result" style="width:100%; height:100px; padding:10px; border:1px solid var(--border); border-radius:8px;"></textarea>
            </div>

            <input type="hidden" name="released" value="0"> <!-- Secretaries usually don't release clinical results directly without MD signoff, but keeping it simple for now -->

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Save Changes</button>
        </form>
    </div>
</div>

<script>
    function openLabModal(test) {
        document.getElementById('lab-id').value = test.test_id;
        document.getElementById('lab-patient-name').innerText = test.first_name + ' ' + test.last_name + ' - ' + test.test_type;
        document.getElementById('lab-result').value = test.test_result || '';
        document.getElementById('lab-status').value = test.status || 'pending';
        document.getElementById('modal-lab-update').style.display = 'flex';
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'lab-tests', [
    'title' => 'Lab Tests',
    'content' => $content,
]);
