<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

$queue = QueueController::getQueue(); // All doctors
$today = date('Y-m-d');
$appointments = db()->prepare("
    SELECT a.*, u.first_name, u.last_name, p.identification_number
    FROM appointment a
    JOIN patient p ON a.patient_id = p.patient_id
    JOIN user u ON p.user_id = u.user_id
    WHERE DATE(a.appointment_date) = :today1 
    AND a.appointment_status = 'scheduled'
    AND a.appointment_id NOT IN (SELECT appointment_id FROM patient_queue WHERE DATE(checked_in_at) = :today2)
    ORDER BY a.appointment_date ASC
");
$appointments->execute([':today1' => $today, ':today2' => $today]);
$toBeCheckedIn = $appointments->fetchAll();

ob_start();
?>
<div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px;">
    <div>
        <div style="margin-bottom: 24px;">
            <h1 style="margin: 0;">Clinic Queue Monitor</h1>
            <p style="color: var(--text-secondary); margin: 5px 0 0;">Managing the current flow of patients across all doctors.</p>
        </div>

        <div class="card">
            <h3>Live Queue</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Wait</th>
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
                                <td>Dr. <?= $q['doctor_last'] ?></td>
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
    </div>

    <div>
        <div class="card" style="background: var(--surface-hover); border: 2px dashed var(--border);">
            <h3 style="margin-bottom: 20px;">Patient Check-In</h3>
            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">Check-in patients for today's scheduled appointments.</p>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php if (empty($toBeCheckedIn)): ?>
                    <p style="text-align: center; font-size: 12px; color: var(--text-secondary); padding: 20px; background: var(--surface); border-radius: 8px;">No pending check-ins for today.</p>
                <?php else: ?>
                    <?php foreach ($toBeCheckedIn as $app): ?>
                        <div style="background: var(--surface); padding: 15px; border-radius: 8px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px;">
                            <div>
                                <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-secondary);"><?= date('g:i A', strtotime($app['appointment_date'])) ?> | ID: <?= $app['identification_number'] ?></div>
                            </div>
                            <form action="/public/controllers/queue-handler.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                <input type="hidden" name="action" value="checkin">
                                <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Check In</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'queues', [
    'title' => 'Queue Management',
    'content' => $content,
]);
