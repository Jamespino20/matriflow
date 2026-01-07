<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

ob_start();
?>
<?php
$statusFilter = $_GET['status'] ?? 'pending';
$q = trim((string)($_GET['q'] ?? ''));

$sql = "SELECT a.*, p.identification_number, u.first_name, u.last_name, du.last_name as doctor_name 
        FROM appointment a
        JOIN patient p ON a.patient_id = p.patient_id
        JOIN user u ON p.user_id = u.user_id
        LEFT JOIN user du ON a.doctor_user_id = du.user_id
        WHERE 1=1";

if ($statusFilter !== 'all') {
    $sql .= " AND a.appointment_status = " . db()->quote($statusFilter);
}

if ($q !== '') {
    $sql .= " AND (u.first_name LIKE " . db()->quote("%$q%") . " OR u.last_name LIKE " . db()->quote("%$q%") . " OR p.identification_number LIKE " . db()->quote("%$q%") . ")";
}

$sql .= " ORDER BY a.appointment_date ASC";
$appointments = db()->query($sql)->fetchAll();
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Appointments Management</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Manage and schedule patient visits.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-book-any').style.display='flex'">
            <span class="material-symbols-outlined">add</span> Schedule Appointment
        </button>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or ID..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($q): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <div class="filter-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
        <a href="?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending Requests</a>
        <a href="?status=scheduled" class="btn btn-sm <?= $statusFilter === 'scheduled' ? 'btn-primary' : 'btn-secondary' ?>">Scheduled</a>
        <a href="?status=completed" class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-primary' : 'btn-secondary' ?>">Completed</a>
        <a href="?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Purpose</th>
                <th>Status</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">No appointments found for this filter.</td>
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
                        <td><?= $apt['doctor_name'] ? 'Dr. ' . e($apt['doctor_name']) : '<span style="color:var(--text-secondary)">Unassigned</span>' ?></td>
                        <td><small><?= e($apt['appointment_purpose']) ?></small></td>
                        <td>
                            <span class="badge badge-<?= $apt['appointment_status'] === 'pending' ? 'warning' : ($apt['appointment_status'] === 'scheduled' ? 'info' : 'success') ?>">
                                <?= ucfirst($apt['appointment_status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($apt['appointment_status'] === 'pending'): ?>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-primary btn-sm" title="Approve">Approve</button>
                                </form>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Cancel">Deny</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($apt['appointment_status'] === 'scheduled'): ?>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="checkin">
                                    <button type="submit" class="btn btn-outline btn-sm" title="Check In to Queue">Check In</button>
                                </form>
                            <?php endif; ?>
                            <button class="btn btn-icon" title="Details"><span class="material-symbols-outlined">visibility</span></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for Secretary to Book for Any Patient -->
<div id="modal-book-any" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-book-any').style.display='none'">&times;</button>
        <h3>Schedule Appointment</h3>
        <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="book">
            <input type="hidden" name="is_admin_booking" value="1">

            <div class="form-group">
                <label>Select Patient</label>
                <input type="text" id="patient-search-apt" placeholder="Search patient..." style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;" onkeyup="searchPatientsForApt(this.value)">
                <div id="apt-search-results" style="border: 1px solid var(--border); border-radius: 6px; margin-top: 5px; display:none; max-height: 150px; overflow-y: auto; background: var(--surface);"></div>
                <input type="hidden" name="patient_id" id="apt-patient-id" required>
            </div>

            <div class="form-group"><label>Appointment Date & Time</label><input type="datetime-local" name="appointment_date" required></div>
            <div class="form-group"><label>Purpose of Visit</label><textarea name="purpose" required style="width:100%; border:1px solid var(--border); border-radius:6px; padding:10px;"></textarea></div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Schedule Appointment</button>
        </form>
    </div>
</div>

<script>
    function searchPatientsForApt(q) {
        const results = document.getElementById('apt-search-results');
        if (q.length < 2) {
            results.style.display = 'none';
            return;
        }

        fetch('<?= base_url('/public/controllers/message-handler.php') ?>?action=search_patients&q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.patients.length > 0) {
                    let html = '';
                    json.patients.forEach(p => {
                        html += `<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border);" onclick="selectPatientForApt(${p.patient_id}, '${p.first_name} ${p.last_name}')">
                        <div style="font-weight: 700;">${p.first_name} ${p.last_name}</div>
                        <div style="font-size:11px; color:var(--text-secondary)">ID: ${p.identification_number || 'N/A'}</div>
                    </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                } else {
                    results.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">No patients found</div>';
                    results.style.display = 'block';
                }
            });
    }

    function selectPatientForApt(pid, name) {
        if (!pid) {
            alert("This user does not have a linked patient record.");
            return;
        }
        document.getElementById('apt-patient-id').value = pid;
        document.getElementById('patient-search-apt').value = name;
        document.getElementById('apt-search-results').style.display = 'none';
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'appointments', [
    'title' => 'Appointments Management',
    'content' => $content,
]);
