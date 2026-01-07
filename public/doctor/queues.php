<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

$queue = QueueController::getQueue((int)$u['user_id']);

ob_start();
?>
<div style="margin-bottom: 24px;">
    <h1 style="margin: 0;">Patient Queue</h1>
    <p style="color: var(--text-secondary); margin: 5px 0 0;">Manage your active patients and their consultation status.</p>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Patient Name</th>
                <th>Check-In Time</th>
                <th>Purpose</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($queue)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">No patients currently in queue.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($queue as $q): ?>
                    <tr class="<?= $q['status'] === 'in_consultation' ? 'highlight-row' : '' ?>">
                        <td style="font-weight: 700;"><?= $q['position'] ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($q['first_name'] . ' ' . $q['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= $q['identification_number'] ?></div>
                        </td>
                        <td><?= date('g:i A', strtotime($q['checked_in_at'])) ?></td>
                        <td style="font-size: 13px;"><?= htmlspecialchars($q['appointment_purpose']) ?></td>
                        <td>
                            <span class="badge badge-<?= $q['status'] === 'in_consultation' ? 'info' : 'warning' ?>">
                                <?= str_replace('_', ' ', ucfirst($q['status'])) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <form action="/public/controllers/queue-handler.php" method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                <input type="hidden" name="queue_id" value="<?= $q['queue_id'] ?>">
                                <?php if ($q['status'] === 'waiting'): ?>
                                    <input type="hidden" name="action" value="start">
                                    <button type="submit" class="btn btn-primary btn-sm">Start Consultation</button>
                                <?php elseif ($q['status'] === 'in_consultation'): ?>
                                    <input type="hidden" name="action" value="finish">
                                    <button type="submit" class="btn btn-success btn-sm">Finish</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .highlight-row {
        background: rgba(var(--primary-rgb), 0.05);
    }
</style>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'queues', [
    'title' => 'Queue Management',
    'content' => $content,
]);
