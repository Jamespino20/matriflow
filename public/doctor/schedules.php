<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

$schedule = ScheduleController::getSchedule((int)$u['user_id']);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 style="margin: 0;">My Schedule</h1>
        <p style="color: var(--text-secondary); margin: 5px 0 0;">Define your working hours and availability for patient appointments.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modal-edit-schedule').classList.add('show')"><span class="material-symbols-outlined">edit</span> Modify Schedule</button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">Schedule updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 20px;">Failed to update schedule.</div>
<?php endif; ?>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Day</th>
                <th>Working Hours</th>
                <th>Comments</th>
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
                        <span style="font-style: italic; color: var(--text-secondary); font-size: 13px;">
                            <?= e($dayEntry['comments'] ?? '') ?>
                        </span>
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
<div id="modal-edit-schedule" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:800px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-edit-schedule').classList.remove('show')">&times;</button>
        <h3>Modify Weekly Schedule</h3>
        <form action="<?= base_url('/public/controllers/schedule-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="update">

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($days as $day):
                    $dayEntry = array_filter($schedule, fn($s) => $s['day_of_week'] === $day);
                    $dayEntry = reset($dayEntry);
                ?>
                    <div style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr 50px; gap: 15px; align-items: center; padding-bottom: 10px; border-bottom: 1px solid var(--border);">
                        <div style="font-weight: 600; font-size: 14px;"><?= $day ?></div>
                        <div class="form-group" style="margin: 0;"><input type="time" name="schedule[<?= $day ?>][start]" value="<?= $dayEntry ? date('H:i', strtotime($dayEntry['start_time'])) : '08:00' ?>"></div>
                        <div class="form-group" style="margin: 0;"><input type="time" name="schedule[<?= $day ?>][end]" value="<?= $dayEntry ? date('H:i', strtotime($dayEntry['end_time'])) : '17:00' ?>"></div>
                        <div class="form-group" style="margin: 0;"><input type="text" name="schedule[<?= $day ?>][comments]" placeholder="Side comments..." value="<?= e($dayEntry['comments'] ?? '') ?>" style="font-size: 13px;"></div>
                        <div style="text-align: right;">
                            <input type="checkbox" name="schedule[<?= $day ?>][available]" value="1" <?= (!$dayEntry || $dayEntry['is_available']) ? 'checked' : '' ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="document.getElementById('modal-edit-schedule').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 40px; margin-bottom: 12px;">
    <h2 style="margin: 0;">Staff Availability</h2>
    <p style="color: var(--text-secondary); margin: 5px 0 0;">View other doctors and secretaries' current schedules.</p>
</div>

<div class="card">
    <?php
    $allSchedules = ScheduleController::getAllSchedules();
    $grouped = [];
    foreach ($allSchedules as $s) {
        $name = ($s['role'] === 'doctor' ? 'Dr. ' : '') . $s['last_name'] . ', ' . $s['first_name'];
        $grouped[$name][] = $s;
    }
    ?>

    <table class="table">
        <thead>
            <tr>
                <th>Staff Name</th>
                <th>Role</th>
                <th>Availability Overview</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($grouped)): ?>
                <tr>
                    <td colspan="3" style="text-align: center; padding: 40px; color: var(--text-secondary);">No staff schedules found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($grouped as $name => $staffSched): ?>
                    <tr>
                        <td style="font-weight: 600;"><?= e($name) ?></td>
                        <td><span class="badge badge-outline"><?= ucfirst(e($staffSched[0]['role'])) ?></span></td>
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                <?php foreach ($staffSched as $s): ?>
                                    <?php if ($s['is_available']): ?>
                                        <span class="badge badge-success" style="font-size: 10px;" title="<?= e($s['comments']) ?>">
                                            <?= substr($s['day_of_week'], 0, 3) ?>: <?= date('gA', strtotime($s['start_time'])) ?>-<?= date('gA', strtotime($s['end_time'])) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'schedules', [
    'title' => 'My Schedule',
    'content' => $content,
]);
