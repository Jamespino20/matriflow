<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

$allSchedules = ScheduleController::getAllSchedules();
$mySchedule = ScheduleController::getSchedule((int)$u['user_id']);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 style="margin: 0;">Schedules & Availability</h1>
        <p style="color: var(--text-secondary); margin: 5px 0 0;">Manage your schedule and view/update doctor availability.</p>
    </div>
    <button class="btn btn-primary" onclick="openEditModal(<?= $u['user_id'] ?>, 'My Schedule')">
        <span class="material-symbols-outlined">person</span> Manage My Schedule
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">Schedule updated successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 20px;">Failed to update schedule.</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-top: 0;">Doctor Availability</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Doctor</th>
                <th>Day</th>
                <th>Working Hours</th>
                <th>Comments</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allSchedules)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">No doctor schedules found.</td>
                </tr>
            <?php else: ?>
                <?php
                $lastDocId = 0;
                foreach ($allSchedules as $s):
                    if ($s['user_id'] == $u['user_id']) continue; // Skip self in doctors list
                    if ($s['role'] !== 'doctor') continue; // Only show doctors here

                    $docName = 'Dr. ' . $s['first_name'] . ' ' . $s['last_name'];
                ?>
                    <tr style="<?= ($lastDocId && $lastDocId !== $s['user_id']) ? 'border-top: 2px solid var(--border);' : '' ?>">
                        <td style="font-weight: 700; color: var(--primary);">
                            <?= $lastDocId !== $s['user_id'] ? htmlspecialchars($docName) : '' ?>
                        </td>
                        <td><?= $s['day_of_week'] ?? '<span style="color:var(--text-secondary)">--</span>' ?></td>
                        <td>
                            <?php if (!empty($s['day_of_week']) && $s['is_available']): ?>
                                <?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?>
                            <?php elseif (!empty($s['day_of_week'])): ?>
                                <span style="color: var(--text-secondary);">Offline</span>
                            <?php else: ?>
                                <span style="color: var(--warning); font-size:12px;">Not Set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-style: italic; color: var(--text-secondary); font-size: 13px;">
                                <?= e($s['comments'] ?? '') ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($s['is_available']): ?>
                                <span class="badge badge-success">Available</span>
                            <?php else: ?>
                                <span class="badge badge-error">Closed</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($lastDocId !== $s['user_id']): ?>
                                <button class="btn btn-sm btn-outline" onclick='openEditModal(<?= $s['user_id'] ?>, <?= json_encode($docName) ?>)'>Edit</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php
                    $lastDocId = $s['user_id'];
                endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Edit Schedule -->
<div id="modal-edit-schedule" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:850px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-edit-schedule').classList.remove('show')">&times;</button>
        <h3>Edit Schedule - <span id="modal-title-name"></span></h3>
        <form action="<?= base_url('/public/controllers/schedule-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="target_user_id" id="modal-user-id">

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($days as $day): ?>
                    <div style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr 50px; gap: 15px; align-items: center; padding-bottom: 10px; border-bottom: 1px solid var(--border);" data-day="<?= $day ?>">
                        <div style="font-weight: 600; font-size: 14px;"><?= $day ?></div>
                        <div class="form-group" style="margin: 0;"><input type="time" name="schedule[<?= $day ?>][start]" id="start-<?= $day ?>"></div>
                        <div class="form-group" style="margin: 0;"><input type="time" name="schedule[<?= $day ?>][end]" id="end-<?= $day ?>"></div>
                        <div class="form-group" style="margin: 0;"><input type="text" name="schedule[<?= $day ?>][comments]" id="comments-<?= $day ?>" placeholder="Side comments..." style="font-size: 13px;"></div>
                        <div style="text-align: right;">
                            <input type="checkbox" name="schedule[<?= $day ?>][available]" id="available-<?= $day ?>" value="1">
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

<script>
    const allSchedules = <?= json_encode($allSchedules) ?>;
    const mySchedule = <?= json_encode($mySchedule) ?>;
    const days = <?= json_encode($days) ?>;

    function openEditModal(userId, name) {
        document.getElementById('modal-user-id').value = userId;
        document.getElementById('modal-title-name').innerText = name;

        let schedule = (userId == <?= $u['user_id'] ?>) ? mySchedule : allSchedules.filter(s => s.user_id == userId);

        days.forEach(day => {
            const entry = schedule.find(s => s.day_of_week === day);
            document.getElementById('start-' + day).value = entry ? entry.start_time.substring(0, 5) : '08:00';
            document.getElementById('end-' + day).value = entry ? entry.end_time.substring(0, 5) : '17:00';
            document.getElementById('comments-' + day).value = entry ? entry.comments || '' : '';
            document.getElementById('available-' + day).checked = entry ? (parseInt(entry.is_available) === 1) : false;
        });

        document.getElementById('modal-edit-schedule').classList.add('show');
    }
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'schedules', [
    'title' => 'Doctor Schedules',
    'content' => $content,
]);
