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
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT a.*, u.first_name, u.last_name, u.contact_number, u.identification_number 
        FROM appointment a
        JOIN users u ON a.user_id = u.user_id
        WHERE a.doctor_user_id = :uid AND a.deleted_at IS NULL";

$params = [':uid' => $u['user_id']];

if ($q !== '') {
    $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR u.identification_number LIKE :q3)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
    $params[':q3'] = "%$q%";
}
if ($status !== '') {
    $sql .= " AND a.appointment_status = :status";
    $params[':status'] = $status;
}
if ($dateFrom !== '') {
    $sql .= " AND a.appointment_date >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $sql .= " AND a.appointment_date <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
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

    <form method="GET" class="filter-bar">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or ID...">
        </div>

        <div class="form-group">
            <label>Date From</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label>Date To</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="in_consultation" <?= $status === 'in_consultation' ? 'selected' : '' ?>>In Consultation</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="no_show" <?= $status === 'no_show' ? 'selected' : '' ?>>No-Show</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php $hasFilters = $q || $status || $dateFrom || $dateTo; ?>
            <a href="<?= base_url('/public/doctor/appointments.php') ?>" class="btn btn-outline <?= !$hasFilters ? 'disabled' : '' ?>" <?= !$hasFilters ? 'onclick="return false;"' : '' ?>>Reset</a>
        </div>
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
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= e($apt['identification_number'] ?? 'N/A') ?> | <?= e($apt['contact_number'] ?? 'No Phone') ?></div>
                        </td>
                        <td><small><?= e($apt['appointment_purpose']) ?></small></td>
                        <td>
                            <span class="badge badge-<?= $apt['appointment_status'] ?>">
                                <?= ucfirst($apt['appointment_status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                            <?php if ($apt['appointment_status'] === 'scheduled' || $apt['appointment_status'] === 'checked_in'): ?>
                                <button class="btn btn-primary btn-sm" onclick="updateStatus(<?= $apt['appointment_id'] ?>, 'in_consultation')">Start Consult</button>
                                <button class="btn btn-outline btn-sm" onclick="markNoShow(<?= $apt['appointment_id'] ?>)">No-Show</button>
                            <?php elseif ($apt['appointment_status'] === 'in_consultation'): ?>
                                <button class="btn btn-success btn-sm" onclick="updateStatus(<?= $apt['appointment_id'] ?>, 'completed')">Finish</button>
                            <?php endif; ?>
                            <a href="<?= base_url('/public/doctor/records.php?patient_id=' . $apt['user_id']) ?>" class="btn btn-sm btn-primary">Clinical Records</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    async function updateStatus(id, status) {
        if (!confirm(`Are you sure you want to set this appointment to ${status}?`)) return;

        try {
            const fd = new FormData();
            fd.append('action', 'update_appointment_status');
            fd.append('appointment_id', id);
            fd.append('status', status);
            fd.append('csrf_token', '<?= CSRF::token() ?>');

            const res = await fetch('<?= base_url('/public/controllers/appointment-handler.php') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
            });
            const json = await res.json();
            if (json.ok) location.reload();
            else alert(json.message || 'Error updating status');
        } catch (e) {
            console.error(e);
            alert('An error occurred.');
        }
    }

    async function markNoShow(id) {
        if (!confirm('Are you sure you want to mark this appointment as No-Show?')) return;

        try {
            const fd = new FormData();
            fd.append('action', 'update_appointment_status');
            fd.append('appointment_id', id);
            fd.append('status', 'no_show');
            fd.append('csrf_token', '<?= CSRF::token() ?>');

            const res = await fetch('<?= base_url('/public/controllers/appointment-handler.php') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: fd
            });
            const json = await res.json();
            if (json.ok) location.reload();
            else alert(json.message || 'Error updating status');
        } catch (e) {
            console.error(e);
            alert('An error occurred. Please try again.');
        }
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'appointments', [
    'title' => 'My Appointments',
    'content' => $content,
]);
