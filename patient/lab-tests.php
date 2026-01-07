<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$patientId = Patient::getPatientIdForUser((int)$u['user_id']);
$tests = LaboratoryController::listByPatient($patientId);

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
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tests)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #637588;">No lab results available</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td><?= e($t['test_name']) ?> <small class="text-muted">(<?= e($t['test_type']) ?>)</small></td>
                        <td><?= date('M j, Y', strtotime($t['ordered_at'])) ?></td>
                        <td>
                            <?php if ($t['status'] === 'Completed' && $t['released_to_patient']): ?>
                                <?= nl2br(e($t['test_result'])) ?>
                            <?php else: ?>
                                <span class="text-muted">Waiting for result...</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $t['status'] === 'Completed' ? 'success' : 'warning' ?>">
                                <?= e($t['status']) ?>
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
