<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$userId = (int)$u['user_id'];
$tests = LaboratoryController::listByPatient($userId);

ob_start();
?>
<div class="card">
    <h2>Lab Tests</h2>
    <p>View your laboratory test results.</p>
    <table class="table">
        <thead>
            <tr>
                <th>Test Name</th>
                <th>Date</th>
                <th>Result</th>
                <th>File</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tests)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #637588;">No lab results available</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td><?= e($t['test_name']) ?></td>
                        <td><?= date('M j, Y', strtotime($t['ordered_at'])) ?></td>
                        <td>
                            <?php if ($t['status'] === 'released'): ?>
                                <?= nl2br(e($t['test_result'])) ?>
                            <?php else: ?>
                                <span style="color:var(--text-secondary); font-style:italic;">Result pending doctor review...</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($t['status'] === 'released' && $t['result_file_path']): ?>
                                <a href="<?= base_url('/' . $t['result_file_path']) ?>" target="_blank" class="btn btn-outline btn-sm">
                                    <span class="material-symbols-outlined" style="font-size:16px; vertical-align:middle;">download</span> Download
                                </a>
                            <?php else: ?>
                                <span style="color:var(--text-secondary);">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $t['status'] === 'released' ? 'success' : 'warning' ?>">
                                <?= ucfirst($t['status']) ?>
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
RoleLayout::render($u, 'patient', 'lab-tests', [
    'title' => 'Lab Tests',
    'content' => $content,
]);
