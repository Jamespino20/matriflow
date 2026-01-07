<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

$schedule = ScheduleController::getDoctorSchedule((int)$u['user_id']);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 style="margin: 0;">My Schedule</h1>
        <p style="color: var(--text-secondary); margin: 5px 0 0;">Define your working hours and availability for patient appointments.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modal-edit-schedule').style.display='flex'"><span class="material-symbols-outlined">edit</span> Modify Schedule</button>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Day</th>
                <th>Working Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($days as $day):
                $dayEntry = array_filter($schedule, fn($s) => $s['day_of_week'] === $day);
                $dayEntry = reset($dayEntry);
            ?>
                <tr>
                    <td style="font-weight: 600;"><?= $day ?></td>
                    <td>
                        <?php if ($dayEntry && $dayEntry['is_available']): ?>
                            <?= date('g:i A', strtotime($dayEntry['start_time'])) ?> - <?= date('g:i A', strtotime($dayEntry['end_time'])) ?>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Offline / Closed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($dayEntry && $dayEntry['is_available']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-error">Not Available</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Edit Schedule -->
<div id="modal-edit-schedule" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:600px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-edit-schedule').style.display='none'">&times;</button>
        <h3>Modify Weekly Schedule</h3>
        <form action="/public/controllers/schedule-handler.php" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="update">

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($days as $day):
                    $dayEntry = array_filter($schedule, fn($s) => $s['day_of_week'] === $day);
                    $dayEntry = reset($dayEntry);
                ?>
                    <div style="display: grid; grid-template-columns: 120px 1fr 1fr 80px; gap: 10px; align-items: center; padding-bottom: 10px; border-bottom: 1px solid var(--border);">
                        <div style="font-weight: 600; font-size: 14px;"><?= $day ?></div>
                        <div class="form-group" style="margin: 0;"><input type="time" name="schedule[<?= $day ?>][start]" value="<?= $dayEntry ? $dayEntry['start_time'] : '08:00' ?>"></div>
                        <div class="form-group" style="margin: 0;"><input type="time" name="schedule[<?= $day ?>][end]" value="<?= $dayEntry ? $dayEntry['end_time'] : '17:00' ?>"></div>
                        <div style="text-align: right;">
                            <input type="checkbox" name="schedule[<?= $day ?>][available]" value="1" <?= (!$dayEntry || $dayEntry['is_available']) ? 'checked' : '' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="document.getElementById('modal-edit-schedule').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'schedules', [
    'title' => 'My Schedule',
    'content' => $content,
]);
