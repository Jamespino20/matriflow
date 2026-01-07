<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

$queue = QueueController::getQueue(); // All doctors

ob_start();
?>
<div style="margin-bottom: 24px;">
    <h1 style="margin: 0;">Clinic Queue Monitor</h1>
    <p style="color: var(--text-secondary); margin: 5px 0 0;">Real-time view of patient flow across all doctors.</p>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Status</th>
                <th>Wait Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($queue)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">Queue is currently empty.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($queue as $q): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= $q['position'] ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($q['first_name'] . ' ' . $q['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= $q['identification_number'] ?></div>
                        </td>
                        <td>Dr. <?= $q['doctor_last'] ?? 'Unassigned' ?></td>
                        <td>
                            <span class="badge badge-<?= $q['status'] === 'in_consultation' ? 'info' : ($q['status'] === 'waiting' ? 'warning' : 'success') ?>">
                                <?= str_replace('_', ' ', ucfirst($q['status'])) ?>
                            </span>
                        </td>
                        <td style="font-size: 12px; color: var(--text-secondary);">
                            <?php
                            $checkIn = new DateTime($q['checked_in_at']);
                            $now = new DateTime();
                            $diff = $now->diff($checkIn);
                            echo $diff->h > 0 ? $diff->h . 'h ' . $diff->i . 'm' : $diff->i . 'm';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'queues', [
    'title' => 'Queue Monitor',
    'content' => $content,
]);
