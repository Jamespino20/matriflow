<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

// Filters
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$sql = "SELECT a.*, u.identification_number, u.first_name, u.last_name,
        doc.first_name as doctor_first, doc.last_name as doctor_last
        FROM appointment a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN users doc ON a.doctor_user_id = doc.user_id
        WHERE a.deleted_at IS NULL";

$params = [];
if ($status) {
    $sql .= " AND a.appointment_status = :status";
    $params[':status'] = $status;
}
if ($dateFrom) {
    $sql .= " AND DATE(a.appointment_date) >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo) {
    $sql .= " AND DATE(a.appointment_date) <= :date_to";
    $params[':date_to'] = $dateTo;
}

$sql .= " ORDER BY a.appointment_date ASC LIMIT 100";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

ob_start();
?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h1 style="margin: 0;">All Appointments</h1>
        <p style="color: var(--text-secondary); margin: 5px 0 0;">System-wide appointment overview.</p>
    </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <form method="GET" class="filter-bar">
        <div class="form-group" style="flex: 1; min-width: 200px;">
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
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label>From Date</label>
            <input type="date" name="date_from" value="<?= $dateFrom ?>">
        </div>
        <div class="form-group" style="flex: 1; min-width: 150px;">
            <label>To Date</label>
            <input type="date" name="date_to" value="<?= $dateTo ?>">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php $hasFilters = !empty($_GET['status']) || !empty($_GET['date_from']) || !empty($_GET['date_to']); ?>
            <a href="appointments.php" class="btn btn-secondary <?= !$hasFilters ? 'disabled' : '' ?>" <?= !$hasFilters ? 'onclick="return false;"' : '' ?>>Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Date & Time</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No appointments found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= $a['identification_number'] ?></div>
                        </td>
                        <td><?= $a['doctor_first'] ? 'Dr. ' . $a['doctor_last'] : '<span style="color:var(--text-secondary)">Unassigned</span>' ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($a['appointment_date'])) ?></td>
                        <td style="font-size: 13px;"><?= htmlspecialchars($a['appointment_purpose'] ?? '--') ?></td>
                        <td>
                            <span class="badge badge-<?= $a['appointment_status'] ?>">
                                <?= ucfirst($a['appointment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!in_array($a['appointment_status'], ['completed', 'cancelled', 'no_show'])): ?>
                                <button class="btn btn-outline btn-sm" onclick="forceStatus(<?= $a['appointment_id'] ?>, 'no_show')">Mark No-Show</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    async function forceStatus(id, status) {
        if (!confirm(`Force change appointment #${id} to ${status}?`)) return;

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
            alert('Error updating status. Please try again.');
        }
    }
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'appointments', [
    'title' => 'Appointments',
    'content' => $content,
]);
