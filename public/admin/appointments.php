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

$sql = "SELECT a.*, p.identification_number, u.first_name, u.last_name,
        doc.first_name as doctor_first, doc.last_name as doctor_last
        FROM appointment a
        JOIN patient p ON a.patient_id = p.patient_id
        JOIN user u ON p.user_id = u.user_id
        LEFT JOIN user doc ON a.doctor_user_id = doc.user_id
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

$sql .= " ORDER BY a.appointment_date DESC LIMIT 100";
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
    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="">All</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
            <label class="label">From</label>
            <input type="date" name="date_from" class="input" value="<?= $dateFrom ?>">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
            <label class="label">To</label>
            <input type="date" name="date_to" class="input" value="<?= $dateTo ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="appointments.php" class="btn btn-secondary">Reset</a>
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
                            <span class="badge badge-<?=
                                                        $a['appointment_status'] === 'completed' ? 'success' : ($a['appointment_status'] === 'scheduled' ? 'info' : ($a['appointment_status'] === 'pending' ? 'warning' : 'error')) ?>">
                                <?= ucfirst($a['appointment_status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'appointments', [
    'title' => 'Appointments',
    'content' => $content,
]);
