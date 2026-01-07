<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<?php
$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$sql = "SELECT a.*, u.first_name, u.last_name, p.identification_number 
        FROM appointment a
        JOIN patient p ON a.patient_id = p.patient_id
        JOIN user u ON p.user_id = u.user_id
        WHERE a.doctor_user_id = :uid AND a.deleted_at IS NULL";

$params = [':uid' => $u['user_id']];

if ($q !== '') {
    $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR p.identification_number LIKE :q3)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
    $params[':q3'] = "%$q%";
}
if ($status !== '') {
    $sql .= " AND a.appointment_status = :status";
    $params[':status'] = $status;
}

$sql .= " ORDER BY a.appointment_date ASC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">My Appointments</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Manage your scheduled patient visits and clinical schedule.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or ID..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>

        <select name="status" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
            <option value="">All Statuses</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($q || $status): ?>
            <a href="<?= base_url('/public/doctor/appointments.php') ?>" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Purpose</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No medical appointments scheduled.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                    <tr>
                        <td style="font-weight: 600;">
                            <div><?= date('M j, Y', strtotime($apt['appointment_date'])) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);"><?= date('h:i A', strtotime($apt['appointment_date'])) ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600;"><?= e($apt['first_name'] . ' ' . $apt['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= e($apt['identification_number'] ?? 'N/A') ?></div>
                        </td>
                        <td><small><?= e($apt['appointment_purpose']) ?></small></td>
                        <td>
                            <span class="badge badge-<?= $apt['appointment_status'] === 'scheduled' ? 'info' : ($apt['appointment_status'] === 'completed' ? 'success' : 'warning') ?>">
                                <?= ucfirst($apt['appointment_status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?= base_url('/public/doctor/records.php?patient_id=' . $apt['patient_id']) ?>" class="btn btn-sm btn-outline">View Records</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'appointments', [
    'title' => 'My Appointments',
    'content' => $content,
]);
