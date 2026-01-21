<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">Medical Appointments</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Manage and schedule your prenatal check-ups.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-book-appointment').classList.add('show')">
            <span class="material-symbols-outlined">add</span>
            Request Appointment
        </button>
    </div>

    <?php
    $patientId = Patient::getPatientIdForUser((int)$u['user_id']);
    $appointments = $patientId ? Appointment::listByPatient($patientId) : [];
    ?>

    <table class="table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Doctor</th>
                <th>Purpose</th>
                <th>Status</th>
                <th style="text-align:right">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding:40px; color: var(--text-secondary);">
                        <span class="material-symbols-outlined" style="font-size:48px; display:block; margin-bottom:10px; opacity:0.5;">calendar_today</span>
                        No appointments scheduled yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($appointments as $app): ?>
                    <tr>
                        <td style="font-weight:600;">
                            <?= date('M j, Y - g:i A', strtotime($app['appointment_date'])) ?>
                        </td>
                        <td>
                            <?php
                            if ($app['doctor_user_id']) {
                                $doc = User::findById((int)$app['doctor_user_id']);
                                echo $doc ? 'Dr. ' . htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']) : 'Assigned Doctor';
                            } else {
                                echo '<span style="color:var(--text-secondary); italic">Pending Assignment</span>';
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($app['appointment_purpose']) ?></td>
                        <td>
                            <span class="badge badge-<?= $app['appointment_status'] === 'scheduled' ? 'info' : ($app['appointment_status'] === 'completed' ? 'success' : 'warning') ?>">
                                <?= ucfirst($app['appointment_status']) ?>
                            </span>
                        </td>
                        <td style="text-align:right">
                            <?php if ($app['appointment_status'] === 'scheduled' || $app['appointment_status'] === 'pending'): ?>
                                <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                    <input type="hidden" name="appointment_id" value="<?= $app['appointment_id'] ?>">
                                    <input type="hidden" name="action" value="patient_cancel">
                                    <button type="submit" class="btn btn-icon" title="Cancel Appointment" style="color:var(--error)">
                                        <span class="material-symbols-outlined">cancel</span>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-icon" title="Reschedule" style="color:var(--primary)"
                                    onclick="openRescheduleModal(<?= $app['appointment_id'] ?>, '<?= date('Y-m-d', strtotime($app['appointment_date'])) ?>', '<?= date('H:i:s', strtotime($app['appointment_date'])) ?>', <?= $app['doctor_user_id'] ?>)">
                                    <span class="material-symbols-outlined">event_repeat</span>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Booking Modal -->
<div id="modal-book-appointment" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-book-appointment').classList.remove('show')">&times;</button>
        <h2 style="margin-bottom:8px; font-size:20px; color:var(--text-primary);">Request Appointment</h2>
        <p style="margin-bottom:24px; font-size:13px; color:var(--text-secondary); line-height:1.5;"><strong>Note:</strong> All requested appointments may not be guaranteed and can be rescheduled at another future available slot.</p>

        <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="book">

            <div class="form-group">
                <label>Preferred Date</label>
                <input type="date" name="date" id="book_date" required min="<?= date('Y-m-d', strtotime('tomorrow')) ?>" onchange="loadSlots()">
            </div>

            <div class="form-group">
                <label>Preferred Time</label>
                <select name="time" id="book_time" required>
                    <option value="">Select Date/Doctor first</option>
                </select>
            </div>

            <div class="form-group">
                <label>Select Doctor</label>
                <select name="doctor_id" id="book_doctor" required onchange="loadSlots()">
                    <option value="" disabled selected>Choose a Doctor</option>
                    <?php
                    $docs = db()->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'doctor' AND is_active = 1")->fetchAll();
                    foreach ($docs as $d) {
                        echo '<option value="' . $d['user_id'] . '">Dr. ' . htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) . '</option>';
                    }
                    ?>
                </select>
                <div style="font-size:11px; color:var(--text-secondary); margin-top:4px;">Appointments are available based on doctor's hospital schedule.</div>
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
                <label>Purpose of Visit</label>
                <textarea name="purpose" placeholder="e.g. Prenatal Check-up, Ultrasound, Consultation" required style="min-height:80px;"></textarea>
            </div>

            <div style="margin-top:24px; display:flex; gap:12px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="document.getElementById('modal-book-appointment').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Confirm Booking</button>
            </div>
        </form>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="modal-reschedule" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:450px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-reschedule').classList.remove('show')">&times;</button>
        <h2 style="margin-bottom:8px; font-size:20px;">Reschedule Appointment</h2>
        <p style="margin-bottom:24px; font-size:13px; color:var(--text-secondary);">Select a new date and time. Changes must be made at least 24 hours in advance.</p>

        <form action="<?= base_url('/public/controllers/appointment-handler.php') ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="appointment_id" id="resched_id">
            <input type="hidden" name="doctor_id" id="resched_doctor_id">

            <div class="form-group">
                <label>New Date</label>
                <input type="date" name="date" id="resched_date" required min="<?= date('Y-m-d', strtotime('tomorrow')) ?>" onchange="loadSlots('resched')">
            </div>

            <div class="form-group">
                <label>Available Slots</label>
                <select name="time" id="resched_time" required>
                    <option value="">Select Date first</option>
                </select>
            </div>

            <div style="margin-top:24px; display:flex; gap:12px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="document.getElementById('modal-reschedule').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRescheduleModal(id, date, time, doctorId) {
        document.getElementById('resched_id').value = id;
        document.getElementById('resched_date').value = date;
        document.getElementById('resched_doctor_id').value = doctorId;

        // Store initial values for UX logic
        window.initialReschedDate = date;
        window.initialReschedTime = time;

        loadSlots('resched');
        document.getElementById('modal-reschedule').classList.add('show');
    }

    async function loadSlots(context = 'book') {
        const prefix = context === 'book' ? 'book' : 'resched';
        const doctorId = document.getElementById(`${prefix}_doctor`) ? document.getElementById(`${prefix}_doctor`).value : document.getElementById('resched_doctor_id').value;
        const date = document.getElementById(`${prefix}_date`).value;
        const timeSelect = document.getElementById(`${prefix}_time`);
        const excludeId = context === 'resched' ? document.getElementById('resched_id').value : '';

        if (!doctorId || !date) return;

        timeSelect.innerHTML = '<option value="">Loading slots...</option>';

        try {
            const res = await fetch(`<?= base_url('/public/controllers/appointment-ajax.php') ?>?action=get_slots&doctor_id=${doctorId}&date=${date}&exclude_id=${excludeId}`);
            const slots = await res.json();

            timeSelect.innerHTML = '';

            // [UX] If on the same day as the original appointment, the original time should be in the list but disabled
            const isSameDay = (context === 'resched' && date === window.initialReschedDate);

            if (slots.length === 0 && !isSameDay) {
                timeSelect.innerHTML = '<option value="">No available slots for this day</option>';
            } else {
                // If it's the same day, we manually add the current slot if it's missing (it shouldn't be missing with exclude_id)
                // but we make sure the current selection is the initial time.
                slots.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.value;
                    opt.textContent = s.time;

                    if (isSameDay && s.value === window.initialReschedTime) {
                        opt.disabled = true;
                        opt.selected = true;
                        opt.textContent += ' (Current Slot - Choose a different time)';
                    }

                    timeSelect.appendChild(opt);
                });

                // If isSameDay and we don't have the initial time (unlikely), fallback selection
                if (isSameDay && timeSelect.selectedIndex === -1) {
                    // This is a safety fallback
                }
            }
        } catch (err) {
            timeSelect.innerHTML = '<option value="">Error loading slots</option>';
        }
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'patient', 'appointments', [
    'title' => 'Appointments',
    'content' => $content,
]);
