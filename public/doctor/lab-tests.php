<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
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
            <h2 style="margin: 0;">Laboratory Review</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Analyze and release patient test results.</p>
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
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No lab records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></div>
                        </td>
                        <td><strong><?= e((string)($t['test_name'] ?? '')) ?></strong></td>
                        <td><?= date('M j, Y', strtotime($t['ordered_at'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= strtoupper($t['status']) ?>
                            </span>
                            <?php if ($t['status'] === 'released'): ?>
                                <span class="badge badge-info" style="font-size: 9px;">RELEASED</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-secondary btn-sm" onclick="openLabModal(<?= htmlspecialchars(json_encode($t)) ?>)">Review</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Review Modal -->
<div id="modal-lab-review" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:600px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-lab-review').classList.remove('show')">&times;</button>
        <h3>Review Lab Result</h3>
        <p id="lab-patient-name" style="font-weight: 700; color: var(--primary); margin-top: 10px;"></p>

        <form id="form-lab-review" action="<?= base_url('/public/controllers/lab-handler.php') ?>" method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="test_id" id="lab-id">

            <div class="form-group">
                <label>Test Result / Findings</label>
                <textarea name="test_result" id="lab-result" style="width:100%; height:150px; padding:12px; border:1px solid var(--border); border-radius:8px;" required></textarea>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Attach/Update Result File (PDF, Image)</label>
                <input type="file" name="lab_file" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;">
                <div id="lab-current-file" style="margin-top: 8px; display: none;">
                    <a id="lab-file-link" href="#" target="_blank" class="badge badge-info" style="text-decoration: none;">View Attached File</a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="lab-status" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                        <option value="ordered">Ordered</option>
                        <option value="completed">Completed</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="released">Released</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Release to Patient?</label>
                    <select name="released" id="lab-released" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                        <option value="0">Draft Only</option>
                        <option value="1">Release Now</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px; height: 45px;">Save & Update Record</button>
        </form>
    </div>
</div>

<script>
    function openLabModal(test) {
        document.getElementById('lab-id').value = test.test_id;
        document.getElementById('lab-patient-name').innerText = test.first_name + ' ' + test.last_name + ' - ' + (test.test_name || '');
        document.getElementById('lab-result').value = test.test_result || '';
        document.getElementById('lab-status').value = test.status || 'ordered';
        document.getElementById('lab-released').value = (test.status === 'released' ? '1' : '0');

        const fileDiv = document.getElementById('lab-current-file');
        const fileLink = document.getElementById('lab-file-link');
        if (test.result_file_path) {
            fileLink.href = '<?= base_url('/') ?>' + test.result_file_path;
            fileDiv.style.display = 'block';
        } else {
            fileDiv.style.display = 'none';
        }

        document.getElementById('modal-lab-review').classList.add('show');
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'lab-tests', [
    'title' => 'Lab Tests',
    'content' => $content,
]);
