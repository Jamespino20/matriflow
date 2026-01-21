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

$sql = "SELECT a.*, u.identification_number, u.first_name, u.last_name, u.contact_number, du.last_name as doctor_name,
        (SELECT billing_status FROM billing WHERE appointment_id = a.appointment_id ORDER BY billing_id ASC LIMIT 1) as billing_status,
        (SELECT SUM(amount_due - amount_paid) FROM billing WHERE appointment_id = a.appointment_id) as appointment_balance
        FROM appointment a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN users du ON a.doctor_user_id = du.user_id
        WHERE 1=1";

if ($statusFilter !== 'all') {
    $sql .= " AND a.appointment_status = " . db()->quote($statusFilter);
}

if ($q !== '') {
    $sql .= " AND (u.first_name LIKE " . db()->quote("%$q%") . " OR u.last_name LIKE " . db()->quote("%$q%") . " OR u.identification_number LIKE " . db()->quote("%$q%") . ")";
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
        <button class="btn btn-primary" onclick="document.getElementById('modal-book-any').classList.add('show')">
            <span class="material-symbols-outlined">add</span> Schedule Appointment
        </button>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or ID...">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php $hasSearch = !empty($q); ?>
            <a href="?status=<?= urlencode($statusFilter) ?>" class="btn btn-outline <?= !$hasSearch ? 'disabled' : '' ?>" <?= !$hasSearch ? 'onclick="return false;"' : '' ?>>Reset</a>
        </div>
    </form>

    <div class="filter-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px; flex-wrap: wrap;">
        <a href="?status=all" class="btn btn-sm <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">All</a>
        <a href="?status=pending" class="btn btn-sm <?= $statusFilter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">Pending</a>
        <a href="?status=scheduled" class="btn btn-sm <?= $statusFilter === 'scheduled' ? 'btn-primary' : 'btn-secondary' ?>">Scheduled</a>
        <a href="?status=checked_in" class="btn btn-sm <?= $statusFilter === 'checked_in' ? 'btn-primary' : 'btn-secondary' ?>">Checked In</a>
        <a href="?status=in_consultation" class="btn btn-sm <?= $statusFilter === 'in_consultation' ? 'btn-primary' : 'btn-secondary' ?>">In Consultation</a>
        <a href="?status=completed" class="btn btn-sm <?= $statusFilter === 'completed' ? 'btn-primary' : 'btn-secondary' ?>">Completed</a>
        <a href="?status=cancelled" class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?>">Cancelled</a>
        <a href="?status=no_show" class="btn btn-sm <?= $statusFilter === 'no_show' ? 'btn-primary' : 'btn-secondary' ?>">No-Show</a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Patient</th>
                <th>Doctor</th>
                <th>Purpose</th>
                <th>Payment</th>
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
                            <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= e($apt['identification_number'] ?? 'N/A') ?> | <?= e($apt['contact_number'] ?? 'No Phone') ?></div>
                        </td>
                        <td><?= $apt['doctor_name'] ? 'Dr. ' . e($apt['doctor_name']) : '<span style="color:var(--text-secondary)">Unassigned</span>' ?></td>
                        <td><small><?= e($apt['appointment_purpose']) ?></small></td>
                        <td>
                            <?php if ($apt['billing_status']): ?>
                                <?php if ($apt['appointment_balance'] > 0): ?>
                                    <span class="badge badge-warning">Unpaid: ₱<?= number_format((float)$apt['appointment_balance'], 2) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success">Paid</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-secondary); font-size:11px;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $apt['appointment_status'] ?>">
                                <?= ucfirst($apt['appointment_status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right; display: flex; gap: 5px; justify-content: flex-end;">
                            <?php if ($apt['appointment_status'] === 'pending'): ?>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-primary btn-sm" title="Approve">Approve</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($apt['appointment_status'], ['pending', 'scheduled'])): ?>
                                <button type="button" class="btn btn-outline btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($apt)) ?>)">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">edit</span> Edit
                                </button>
                            <?php endif; ?>

                            <?php if ($apt['appointment_status'] === 'scheduled'): ?>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="checkin">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Check In to Queue">Check In</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($apt['appointment_status'] === 'scheduled' || $apt['appointment_status'] === 'pending'): ?>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display: inline;" onsubmit="return confirm('Cancel this appointment?');">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $apt['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="btn btn-icon" title="Cancel Appointment" style="color:var(--error);"><span class="material-symbols-outlined">cancel</span></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for Secretary to Book for Any Patient -->
<div id="modal-book-any" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-book-any').classList.remove('show')">&times;</button>
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

            <div class="form-group">
                <label>Select Doctor</label>
                <select name="doctor_id" required onchange="loadSlots('book')" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    <option value="">-- Select Doctor --</option>
                    <?php
                    $doctors = db()->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'doctor' AND is_active = 1 ORDER BY last_name")->fetchAll();
                    foreach ($doctors as $doc):
                    ?>
                        <option value="<?= $doc['user_id'] ?>">Dr. <?= e($doc['first_name'] . ' ' . $doc['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Service Type</label>
                <div style="display:flex; gap:12px; margin-top:5px;">
                    <label style="font-weight:normal; display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="service_type" value="prenatal" checked> Prenatal
                    </label>
                    <label style="font-weight:normal; display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="service_type" value="gynecology"> Gynecology
                    </label>
                    <label style="font-weight:normal; display:flex; align-items:center; gap:6px;">
                        <input type="radio" name="service_type" value="online"> Teleconsult
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label>Appointment Date</label>
                <input type="date" name="date" id="book_date" required min="<?= date('Y-m-d') ?>" onchange="loadSlots('book')" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
            </div>
            <div class="form-group">
                <label>Available Slots</label>
                <select name="time" id="book_time" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    <option value="">Select Date/Doctor first</option>
                </select>
            </div>
            <div class="form-group"><label>Purpose of Visit</label><textarea name="purpose" required style="width:100%; border:1px solid var(--border); border-radius:6px; padding:10px;"></textarea></div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Schedule Appointment</button>
        </form>
    </div>
</div>

<!-- Modal for Secretary to Edit Appointment -->
<div id="modal-edit-apt" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-edit-apt').classList.remove('show')">&times;</button>
        <h3>Edit Appointment</h3>
        <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="appointment_id" id="edit-apt-id">

            <div class="form-group">
                <label>Patient</label>
                <input type="text" id="edit-apt-patient-name" readonly style="width:100%; background:var(--bg-light); border:1px solid var(--border); border-radius:6px; padding:10px;">
            </div>

            <div class="form-group">
                <label>Appointment Date</label>
                <input type="date" name="date" id="edit-apt-date-only" required min="<?= date('Y-m-d') ?>" onchange="loadSlots('edit')" style="width:100%; border:1px solid var(--border); border-radius:6px; padding:10px;">
            </div>

            <div class="form-group">
                <label>Available Slots</label>
                <select name="time" id="edit-apt-time" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    <option value="">Select Date first</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="appointment_status" id="edit-apt-status" required style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                    <option value="pending">Pending</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_show">No-Show</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Save Changes</button>
        </form>
    </div>
</div>

<script>
    function openEditModal(apt) {
        document.getElementById('edit-apt-id').value = apt.appointment_id;
        document.getElementById('edit-apt-patient-name').value = apt.first_name + ' ' + apt.last_name;

        const fullDate = apt.appointment_date.split(' ');
        document.getElementById('edit-apt-date-only').value = fullDate[0];

        // Load slots and SET the current time as selected if possible
        loadSlots('edit', fullDate[1]);

        document.getElementById('edit-apt-status').value = apt.appointment_status;
        document.getElementById('modal-edit-apt').classList.add('show');
    }

    async function loadSlots(context = 'book', selectValue = null) {
        let doctorId, date, timeSelect, excludeId;

        if (context === 'book') {
            doctorId = document.querySelector('#modal-book-any select[name="doctor_id"]').value;
            date = document.getElementById('book_date').value;
            timeSelect = document.getElementById('book_time');
            excludeId = '';
        } else {
            // For editing, we need the stored doctor ID from the row data
            // Since we don't have doctor_id in the simple JSON, let's ensure it's passed or stored
            // Assuming the simple JSON 'apt' had doctor_user_id. Checking records...
            // Yes, a.* includes doctor_user_id.

            // Note: For simplicity in the demo, we assume the same doctor. 
            // If secretary changes doctor, they should use the booking modal or we add doctor select here.
            // For now, let's fetch doctor_id from the global state or the apt object
            const aptRows = <?= json_encode($appointments) ?>;
            const currentApt = aptRows.find(a => a.appointment_id == document.getElementById('edit-apt-id').value);
            doctorId = currentApt ? currentApt.doctor_user_id : '';

            date = document.getElementById('edit-apt-date-only').value;
            timeSelect = document.getElementById('edit-apt-time');
            excludeId = document.getElementById('edit-apt-id').value;
        }

        if (!doctorId || !date) return;

        timeSelect.innerHTML = '<option value="">Loading slots...</option>';

        try {
            const res = await fetch(`<?= base_url('/public/controllers/appointment-ajax.php') ?>?action=get_slots&doctor_id=${doctorId}&date=${date}&exclude_id=${excludeId}`);
            const slots = await res.json();

            timeSelect.innerHTML = '';
            if (slots.length === 0) {
                timeSelect.innerHTML = '<option value="">No available slots for this day</option>';
            } else {
                slots.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.value;
                    opt.textContent = s.time;
                    if (selectValue && s.value === selectValue) opt.selected = true;
                    timeSelect.appendChild(opt);
                });
            }
        } catch (err) {
            timeSelect.innerHTML = '<option value="">Error loading slots</option>';
        }
    }

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
                        html += `<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border);" onclick="selectPatientForApt(${p.user_id}, '${p.first_name} ${p.last_name}')">
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
