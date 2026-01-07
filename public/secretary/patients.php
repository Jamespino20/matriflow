<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

ob_start();
?>
<?php
$q = trim((string)($_GET['q'] ?? ''));
$patients = PatientController::getAll(['q' => $q]);
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Patients Directory</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Lookup patient records and ID numbers.</p>
        </div>
        <!-- Secretaries cannot create accounts as per policy -->
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name or ID..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($q): ?>
            <a href="patients.php" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Patient ID</th>
                <th>Phone</th>
                <th>Last Visit</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No patients found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);"><?= e($p['email']) ?></div>
                        </td>
                        <td style="font-family: monospace;"><?= e($p['identification_number'] ?? 'N/A') ?></td>
                        <td><?= e($p['phone_number'] ?? 'N/A') ?></td>
                        <td><?= $p['last_visit'] ? date('M j, Y', strtotime($p['last_visit'])) : '<span style="color:var(--text-secondary)">None</span>' ?></td>
                        <td style="text-align: right;">
                            <button class="btn btn-secondary btn-sm" onclick="window.location.href='/public/secretary/appointments.php?q=<?= urlencode($p['first_name']) ?>'">Book/Q</button>
                            <button class="btn btn-outline btn-sm" onclick="openVitalsModal(<?= $p['patient_id'] ?>, '<?= e($p['first_name'] . ' ' . $p['last_name']) ?>')">Vitals</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="modal-add-vitals" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-vitals').style.display='none'">&times;</button>
        <h3>Record Vital Signs</h3>
        <p id="vitals-patient-name" style="color:var(--text-secondary); margin-top:-10px; margin-bottom:20px;"></p>

        <form action="/public/controllers/secretary-handler.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="save_vitals">
            <input type="hidden" name="patient_id" id="vitals-patient-id">

            <div class="form-group"><label>Blood Pressure</label><input type="text" name="blood_pressure" placeholder="120/80"></div>
            <div class="form-group"><label>Heart Rate (bpm)</label><input type="number" name="heart_rate"></div>
            <div class="form-group"><label>Temperature (°C)</label><input type="number" step="0.1" name="temperature"></div>
            <div class="form-group"><label>Weight (kg)</label><input type="number" step="0.1" name="weight_kg"></div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Save Vitals</button>
        </form>
    </div>
</div>

<script>
    function openVitalsModal(pid, name) {
        document.getElementById('vitals-patient-id').value = pid;
        document.getElementById('vitals-patient-name').textContent = name;
        document.getElementById('modal-add-vitals').style.display = 'flex';
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'patients', [
    'title' => 'Patients',
    'content' => $content,
]);
