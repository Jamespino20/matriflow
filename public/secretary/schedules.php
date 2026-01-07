<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

$schedules = ScheduleController::getAllSchedules();
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

ob_start();
?>
<div style="margin-bottom: 24px;">
    <h1 style="margin: 0;">Doctor Availability</h1>
    <p style="color: var(--text-secondary); margin: 5px 0 0;">View schedules to assist in booking appointments.</p>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Day</th>
                <th>Working Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-secondary);">No active schedules found.</td>
                </tr>
            <?php else: ?>
                <?php
                $lastDoc = '';
                foreach ($schedules as $s):
                    $docName = 'Dr. ' . $s['first_name'] . ' ' . $s['last_name'];
                ?>
                    <tr style="<?= ($lastDoc && $lastDoc !== $docName) ? 'border-top: 2px solid var(--border);' : '' ?>">
                        <td style="font-weight: 700; color: var(--primary);">
                            <?= $lastDoc !== $docName ? htmlspecialchars($docName) : '' ?>
                        </td>
                        <td><?= $s['day_of_week'] ?></td>
                        <td>
                            <?php if ($s['is_available']): ?>
                                <?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">Offline</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['is_available']): ?>
                                <span class="badge badge-success">Available</span>
                            <?php else: ?>
                                <span class="badge badge-error">Closed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                    $lastDoc = $docName;
                endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'schedules', [
    'title' => 'Doctor Schedules',
    'content' => $content,
]);
