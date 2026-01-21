<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

$role = $_GET['role'] ?? '';
$day = $_GET['day'] ?? '';

$schedules = ScheduleController::getAllSchedules($role, $day);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

ob_start();
?>
<div style="margin-bottom: 24px;">
    <h1 style="margin: 0;">Doctor Schedules</h1>
    <p style="color: var(--text-secondary); margin: 5px 0 0;">Review all doctor availability across the clinic.</p>
</div>

<div class="card" style="margin-bottom: 24px;">
    <form method="GET" class="filter-bar">
        <div class="form-group" style="flex: 1;">
            <label>Filter by Role</label>
            <select name="role">
                <option value="">All Roles</option>
                <option value="doctor" <?= $role === 'doctor' ? 'selected' : '' ?>>Doctors Only</option>
                <option value="secretary" <?= $role === 'secretary' ? 'selected' : '' ?>>Secretaries Only</option>
            </select>
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Filter by Day</label>
            <select name="day">
                <option value="">All Days</option>
                <?php foreach ($days as $d): ?>
                    <option value="<?= $d ?>" <?= $day === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 8px; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="schedules.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Staff Member</th>
                <th>Role</th>
                <th>Day</th>
                <th>Working Hours</th>
                <th>Comments</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-secondary);">No schedules found.</td>
                </tr>
            <?php else: ?>
                <?php
                $lastDoc = '';
                foreach ($schedules as $s):
                    $docName = ($s['role'] === 'doctor' ? 'Dr. ' : '') . $s['first_name'] . ' ' . $s['last_name'];
                ?>
                    <tr style="<?= ($lastDoc && $lastDoc !== $docName) ? 'border-top: 2px solid var(--border);' : '' ?>">
                        <td style="font-weight: 700; color: var(--primary);">
                            <?= $lastDoc !== $docName ? htmlspecialchars($docName) : '' ?>
                        </td>
                        <td>
                            <span class="badge badge-outline" style="text-transform: capitalize;">
                                <?= $s['role'] ?>
                            </span>
                        </td>
                        <td><?= $s['day_of_week'] ?? '<span style="color:var(--text-secondary)">Not Set</span>' ?></td>
                        <td>
                            <?php if ($s['is_available']): ?>
                                <?= date('g:i A', strtotime($s['start_time'])) ?> - <?= date('g:i A', strtotime($s['end_time'])) ?>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">Offline</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 12px; color: var(--text-secondary);">
                            <?= htmlspecialchars($s['comments'] ?? '--') ?>
                        </td>
                        <td>
                            <?php if ($s['is_available']): ?>
                                <span class="badge badge-success">Available</span>
                            <?php elseif ($s['day_of_week']): ?>
                                <span class="badge badge-error">Closed</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">No Schedule</span>
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
RoleLayout::render($u, 'admin', 'schedules', [
    'title' => 'Doctor Schedules',
    'content' => $content,
]);
