<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<?php
$patientId = (int)($_GET['patient_id'] ?? 0);
$appointmentId = (int)($_GET['appointment_id'] ?? 0);
$patient = null;
$baseline = null;
$visits = [];

if ($patientId) {
    $patientData = db()->prepare("SELECT * FROM users WHERE user_id = :uid AND role = 'patient'");
    $patientData->execute([':uid' => $patientId]);
    $patient = $patientData->fetch();

    if ($patient) {
        $baseline = Pregnancy::findActiveByUserId($patientId);
        if ($baseline) {
            $visits = PrenatalObservation::listByPregnancy((int)$baseline['pregnancy_id']);
        }
        $consultations = Consultation::listByPatient($patientId);
        $prescriptions = Prescription::listByPatient($patientId);
    }
}
?>
<style>
    .tab-pane {
        display: none;
        max-height: 600px;
        overflow-y: auto;
        padding-right: 8px;
    }

    .tab-pane.active {
        display: block;
    }

    /* Custom scrollbar for tab panes */
    .tab-pane::-webkit-scrollbar {
        width: 6px;
    }

    .tab-pane::-webkit-scrollbar-track {
        background: var(--bg-light);
        border-radius: 10px;
    }

    .tab-pane::-webkit-scrollbar-thumb {
        background: var(--border);
        border-radius: 10px;
    }

    .tab-pane::-webkit-scrollbar-thumb:hover {
        background: var(--text-secondary);
    }
</style>

<?php if (!$patient): ?>
    <div class="card">
        <h2 style="margin-bottom: 20px;">Find Patient</h2>
        <div class="search-box" style="margin-bottom: 30px;">
            <input type="text" id="patient-search" placeholder="Enter patient name, email or ID..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border);" onkeyup="searchPatientsForRecords(this.value)">
            <div id="search-results" style="margin-top: 10px; border: 1px solid var(--border); border-radius: 8px; display:none; max-height: 300px; overflow-y: auto;"></div>
        </div>
        <p style="text-align: center; color: var(--text-secondary);">Select a patient to view their medical history and prenatal charts.</p>
    </div>
<?php else: ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <a href="<?= base_url('/public/doctor/patients.php') ?>" class="btn btn-icon" title="Go Back"><span class="material-symbols-outlined">arrow_back</span></a>
            <div>
                <h1 style="margin: 0;"><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
                <div style="font-size: 14px; color: var(--text-secondary);">Patient ID: <?= e($patient['identification_number'] ?? 'Not set') ?> | <?= e($patient['email']) ?></div>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-secondary" onclick="window.location.href='<?= base_url('/public/shared/messages.php?chat=' . $patient['user_id']) ?>'"><span class="material-symbols-outlined">chat</span> Message</button>
            <button class="btn btn-primary" onclick="window.open('<?= base_url('/public/controllers/export-record.php?patient_id=' . $patientId) ?>', '_blank')"><span class="material-symbols-outlined">print</span> Export Record</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 24px;">
        <div class="side-info">
            <div class="card">
                <h3>Vitals Summary</h3>
                <?php
                $vitals = VitalSigns::getRecentForPatient($patientId, 1);
                $latest = $vitals[0] ?? null;
                ?>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Blood Pressure:</span>
                        <span style="font-weight: 600;"><?= ($latest && $latest['systolic_pressure'] && $latest['diastolic_pressure']) ? $latest['systolic_pressure'] . '/' . $latest['diastolic_pressure'] . ' mmHg' : '--' ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Weight:</span>
                        <span style="font-weight: 600;"><?= ($latest && $latest['weight_kg']) ? $latest['weight_kg'] . ' kg' : '--' ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Heart Rate:</span>
                        <span style="font-weight: 600;"><?= ($latest && isset($latest['heart_rate']) && $latest['heart_rate']) ? $latest['heart_rate'] . ' bpm' : '--' ?></span>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h3>Prenatal Baseline</h3>
                <?php if (!$baseline): ?>
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 15px 0;">No active pregnancy record found.</p>
                    <button class="btn btn-primary" style="width: 100%;" onclick="document.getElementById('modal-new-baseline').classList.add('show')">Start New Baseline</button>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 15px; font-size: 14px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">LMP Date:</span>
                            <span style="font-weight: 600;"><?= date('M j, Y', strtotime($baseline['lmp_date'])) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Expected Due:</span>
                            <span style="font-weight: 600; color: var(--primary);"><?= date('M j, Y', strtotime($baseline['estimated_due_date'])) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 10px; margin-top: 5px;">
                            <span>Gravidity/Parity</span>
                            <span style="font-weight: 600;">G<?= $baseline['gravida'] ?> P<?= $baseline['para'] ?></span>
                        </div>
                        <?php if (!empty($baseline['next_visit_due'])): ?>
                            <div style="display: flex; justify-content: space-between; border-top: 1px dotted var(--border); padding-top: 10px; margin-top: 5px;">
                                <span style="color: var(--primary); font-weight: 700;">Next Visit:</span>
                                <span style="font-weight: 700; color: var(--primary);"><?= date('M j, Y', strtotime($baseline['next_visit_due'])) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-record">
            <div class="card">
                <div class="tab-bar">
                    <button class="tab-item active" onclick="switchTab('prenatal')">Prenatal Visits</button>
                    <button class="tab-item" onclick="switchTab('consultations')">General Consults</button>
                    <button class="tab-item" onclick="switchTab('gyne')">Gynecology</button>
                    <button class="tab-item" onclick="switchTab('prescriptions')">Prescriptions</button>
                </div>

                <!-- Prenatal Tab -->
                <div id="tab-prenatal" class="tab-pane active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Visit Progress</h3>
                        <?php if ($baseline): ?>
                            <input type="hidden" name="baseline_id" value="<?= $baseline['pregnancy_id'] ?>">
                            <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-visit').classList.add('show')"><span class="material-symbols-outlined">add</span> New Entry</button>
                        <?php endif; ?>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Fundal Ht (cm)</th>
                                <th>FHR (bpm)</th>
                                <th>Fetal Movt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($visits)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-secondary);">No prenatal visits yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($visits as $v): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><?= date('M j, Y', strtotime($v['recorded_at'])) ?></td>
                                        <td><?= $v['fundal_height_cm'] ?></td>
                                        <td><?= $v['fetal_heart_rate'] ?></td>
                                        <td><?= $v['fetal_movement_noted'] ? '<span class="badge badge-success">Noted</span>' : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Consultations Tab -->
                <div id="tab-consultations" class="tab-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">General Consultations</h3>
                        <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-consult').classList.add('show')"><span class="material-symbols-outlined">add</span> New Consultation</button>
                    </div>
                    <?php
                    // Filter for general
                    $genCons = array_filter($consultations, fn($c) => ($c['consultation_type'] ?? 'general') === 'general');
                    if (empty($genCons)):
                    ?>
                        <p style="text-align: center; padding: 40px; color: var(--text-secondary);">No general consultations recorded.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <?php foreach ($genCons as $c): ?>
                                <div style="background: var(--surface-light); padding: 16px; border-radius: 8px; border: 1px solid var(--border);">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                        <span style="font-weight: 700; color: var(--primary);"><?= date('M j, Y - g:i A', strtotime($c['created_at'])) ?></span>
                                        <span style="font-size: 13px; color: var(--text-secondary);">Dr. <?= e($c['doctor_first'] . ' ' . $c['doctor_last']) ?></span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 100px 1fr; gap: 8px; font-size: 14px;">
                                        <span style="color: var(--text-secondary);">Subjective:</span> <span><?= e($c['subjective_notes']) ?></span>
                                        <span style="color: var(--text-secondary);">Objective:</span> <span><?= e($c['objective_notes']) ?></span>
                                        <span style="color: var(--text-secondary);">Assessment:</span> <span style="font-weight:700; color:var(--text-primary)"><?= e($c['assessment']) ?></span>
                                        <span style="color: var(--text-secondary);">Plan:</span> <span><?= e($c['plan']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Gynecology Tab -->
                <div id="tab-gyne" class="tab-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Gynecologic Records</h3>
                        <button class="btn btn-primary btn-sm" onclick="openConsultModal('gynecology')"><span class="material-symbols-outlined">add</span> New Gyne Checkup</button>
                    </div>
                    <?php
                    // Filter for gynecologic
                    $gyneCons = array_filter($consultations, fn($c) => ($c['consultation_type'] ?? '') === 'gynecology');
                    if (empty($gyneCons)):
                    ?>
                        <p style="text-align: center; padding: 40px; color: var(--text-secondary);">No gynecologic consultations recorded.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <?php foreach ($gyneCons as $c): ?>
                                <div style="background: var(--surface-light); padding: 16px; border-radius: 8px; border: 1px solid var(--border);">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                        <span style="font-weight: 700; color: #e91e63;"><span class="material-symbols-outlined" style="font-size:16px; vertical-align:text-bottom;">female</span> <?= date('M j, Y - g:i A', strtotime($c['created_at'])) ?></span>
                                        <span style="font-size: 13px; color: var(--text-secondary);">Dr. <?= e($c['doctor_first'] . ' ' . $c['doctor_last']) ?></span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 100px 1fr; gap: 8px; font-size: 14px;">
                                        <span style="color: var(--text-secondary);">Complaint:</span> <span><?= e($c['subjective_notes']) ?></span>
                                        <span style="color: var(--text-secondary);">Findings:</span> <span><?= e($c['objective_notes']) ?></span>
                                        <span style="color: var(--text-secondary);">Diagnosis:</span> <span style="font-weight:700; color:var(--text-primary)"><?= e($c['assessment']) ?></span>
                                        <span style="color: var(--text-secondary);">Plan:</span> <span><?= e($c['plan']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Prescriptions Tab -->
                <div id="tab-prescriptions" class="tab-pane">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Current & Past Medications</h3>
                        <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-prescription').classList.add('show')"><span class="material-symbols-outlined">description</span> Issue New</button>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($prescriptions)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No prescriptions issued.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($prescriptions as $p): ?>
                                    <tr>
                                        <td><?= date('M j, Y', strtotime($p['prescribed_at'])) ?></td>
                                        <td style="font-weight: 700;"><?= e($p['medication_name']) ?></td>
                                        <td><?= e($p['dosage']) ?></td>
                                        <td><?= e($p['frequency']) ?></td>
                                        <td><span class="badge badge-success">Active</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modals for Data Entry -->
<div id="modal-new-baseline" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-new-baseline').classList.remove('show')">&times;</button>
        <h3>Start Prenatal Baseline</h3>
        <form action="<?= base_url('/public/controllers/prenatal-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="save_baseline">
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
            <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>Gravidity (G)</label><input type="number" name="gravidity" required min="1"></div>
                <div class="form-group"><label>Parity (P)</label><input type="number" name="parity" required min="0"></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>Abortions</label><input type="number" name="abortion_count" required min="0"></div>
                <div class="form-group"><label>Living Children</label><input type="number" name="living_children" required min="0"></div>
            </div>
            <div class="form-group"><label>LMP Date</label><input type="date" name="lmp_date" required onchange="calculateEDD(this.value)"></div>
            <div class="form-group"><label>Estimated Due Date (EDD)</label><input type="date" id="edd-input" name="edd" required></div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Save Baseline</button>
        </form>
    </div>
</div>

<div id="modal-add-visit" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:100%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-visit').classList.remove('show')">&times;</button>
        <h3>Add Prenatal Visit Entry</h3>
        <form action="<?= base_url('/public/controllers/prenatal-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="add_visit">
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
            <input type="hidden" name="baseline_id" value="<?= $baseline['pregnancy_id'] ?? '' ?>">
            <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">

            <div class="form-group"><label>Fundal Height (cm)</label><input type="number" step="0.1" name="fundal_height" required min="0"></div>
            <div class="form-group"><label>Fetal Heart Rate (bpm)</label><input type="number" name="fhr" required min="0"></div>
            <div class="form-group" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" name="fetal_movement" id="fmov" value="1" style="width:auto;">
                <label for="fmov" style="margin:0;">Fetal Movement Noted?</label>
            </div>

            <div class="form-group" style="margin-top:15px;">
                <label>Next Recommended Visit</label>
                <input type="date" name="next_visit" min="<?= date('Y-m-d') ?>">
                <small style="display:block; font-size:11px; color:var(--text-secondary); margin-top:4px;">This will show on the patient dashboard.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Save Visit</button>
        </form>
    </div>
</div>

<div id="modal-add-consult" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:600px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-consult').classList.remove('show')">&times;</button>
        <h3>Record Clinical Consultation</h3>
        <form action="<?= base_url('/public/controllers/clinical-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="save_consultation">
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">
            <input type="hidden" name="appointment_id" value="<?= $appointmentId ?>">

            <div class="form-group">
                <label>Consultation Type</label>
                <select name="consultation_type" id="consult_type" required>
                    <option value="general">General Consultation</option>
                    <option value="gynecology">Gynecologic Checkup</option>
                    <option value="procedure">Procedure / Surgery</option>
                </select>
            </div>

            <div class="form-group"><label>Chief Complaint (Subjective)</label><textarea name="subjective" placeholder="Patient's primary concerns..." required style="min-height:60px;"></textarea></div>
            <div class="form-group"><label>Physical Findings (Objective)</label><textarea name="objective" placeholder="Clinical observations, vitals impact..." style="min-height:60px;"></textarea></div>
            <div class="form-group"><label>Clinical Diagnosis (Assessment)</label><input type="text" name="assessment" placeholder="Final or working diagnosis" required></div>
            <div class="form-group"><label>Treatment Plan</label><textarea name="plan" placeholder="Medications, tests, or follow-up steps..." style="min-height:60px;"></textarea></div>

            <div class="form-group" style="margin-top:15px;">
                <label>Next Recommended Visit</label>
                <input type="date" name="next_visit" min="<?= date('Y-m-d') ?>">
                <small style="display:block; font-size:11px; color:var(--text-secondary); margin-top:4px;">This will automatically schedule a follow-up appointment.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Save Consultation</button>
        </form>
    </div>
</div>

<style>
    .custom-multiselect {
        position: relative;
        width: 100%;
    }

    .select-box {
        padding: 10px 15px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    }

    .checkboxes-wrapper {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 4px;
        z-index: 100;
        max-height: 250px;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
    }

    .checkboxes-wrapper label {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        cursor: pointer;
        font-size: 13px;
        border-bottom: 1px solid var(--border-light);
    }

    .checkboxes-wrapper label:hover {
        background: var(--bg-light);
    }

    .checkboxes-wrapper input {
        margin-right: 12px;
    }
</style>

<div id="modal-add-prescription" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-add-prescription').classList.remove('show')">&times;</button>
        <h3>Issue Prescription</h3>
        <form action="<?= base_url('/public/controllers/clinical-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="save_prescription">
            <input type="hidden" name="patient_id" value="<?= $patientId ?>">

            <div class="form-group">
                <label>Medication Name(s)</label>
                <div class="custom-multiselect" id="meds-multiselect">
                    <div class="select-box" onclick="toggleMedsDropdown()">
                        <span id="selected-meds-text">Select Medicines...</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </div>
                    <div class="checkboxes-wrapper" id="meds-options">
                        <?php
                        $commonMeds = [
                            'Folic Acid',
                            'Ferrous Sulfate',
                            'Calcium Carbonate',
                            'Multivitamins + Iron',
                            'Paracetamol',
                            'Mefenamic Acid',
                            'Tranexamic Acid',
                            'Isoxsuprine',
                            'Dydrogesterone (Duphaston)',
                            'Metformin',
                            'Aspirin (Low-dose)',
                            'Methyldopa'
                        ];
                        foreach ($commonMeds as $med) : ?>
                            <label><input type="checkbox" name="medication_name[]" value="<?= $med ?>" onchange="updateMedsSelection()"> <?= $med ?></label>
                        <?php endforeach; ?>
                        <div style="padding: 10px; border-top: 1px solid var(--border-light);">
                            <input type="text" id="other-med" placeholder="Other medication..." style="width: 100%; padding: 6px; font-size: 13px; border: 1px solid var(--border); border-radius: 4px;">
                            <button type="button" class="btn btn-sm btn-secondary" style="margin-top: 6px; width: 100%;" onclick="addOtherMed()">Add Other</button>
                        </div>
                    </div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>Dosage</label><input type="text" name="dosage" placeholder="e.g. 500mg"></div>
                <div class="form-group"><label>Frequency</label><input type="text" name="frequency" placeholder="e.g. Twice a day"></div>
            </div>
            <div class="form-group"><label>Duration</label><input type="text" name="duration" placeholder="e.g. 7 days"></div>
            <div class="form-group"><label>Special Instructions</label><textarea name="instructions" placeholder="Take after meals..." style="min-height:60px;"></textarea></div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Issue Prescription</button>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-item').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        const btn = document.querySelector(`.tab-item[onclick="switchTab('${tab}')"]`);
        const pane = document.getElementById(`tab-${tab}`);

        if (btn && pane) {
            btn.classList.add('active');
            pane.classList.add('active');
        }
    }

    function calculateEDD(lmp) {
        if (!lmp) return;
        const lmpDate = new Date(lmp);
        // Naegele's rule: +9 months and +7 days
        const edd = new Date(lmpDate);
        edd.setMonth(edd.getMonth() + 9);
        edd.setDate(edd.getDate() + 7);
        document.getElementById('edd-input').value = edd.toISOString().split('T')[0];
    }

    function searchPatientsForRecords(q) {
        const results = document.getElementById('search-results');
        if (q.length < 2) {
            results.style.display = 'none';
            return;
        }

        fetch('<?= base_url('/public/controllers/message-handler.php') ?>?action=search_users&q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.users.length > 0) {
                    let html = '';
                    json.users.filter(u => u.role === 'patient').forEach(p => {
                        html += `<div style="padding: 12px; border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location.href='?patient_id=${p.user_id}'">
                        <div style="font-weight: 700;">${p.first_name} ${p.last_name}</div>
                        <div style="font-size: 11px; color: var(--text-secondary)">Patient ID: ${p.identification_number || 'N/A'}</div>
                    </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                    results.style.background = 'var(--surface)';
                } else {
                    results.style.display = 'none';
                }
            });
    }

    function openConsultModal(type) {
        document.getElementById('consult_type').value = type || 'general';
        document.getElementById('modal-add-consult').classList.add('show');
    }

    /* Medicines Multi-select Logic */
    function toggleMedsDropdown() {
        const options = document.getElementById('meds-options');
        options.style.display = options.style.display === 'block' ? 'none' : 'block';
    }

    function updateMedsSelection() {
        const checkboxes = document.querySelectorAll('#meds-options input[type="checkbox"]:checked');
        const text = document.getElementById('selected-meds-text');
        const selected = Array.from(checkboxes).map(cb => cb.value);
        text.textContent = selected.length > 0 ? selected.join(', ') : 'Select Medicines...';
    }

    function addOtherMed() {
        const input = document.getElementById('other-med');
        const val = input.value.trim();
        if (!val) return;

        const wrapper = document.getElementById('meds-options');
        const label = document.createElement('label');
        label.className = 'custom-med-label';
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.padding = '10px 15px';
        label.style.cursor = 'pointer';
        label.style.fontSize = '13px';
        label.style.borderBottom = '1px solid var(--border-light)';

        label.innerHTML = `<input type="checkbox" name="medication_name[]" value="${val}" checked onchange="updateMedsSelection()" style="margin-right:12px;"> ${val}`;

        // Insert before the "Other" input area (the last child)
        wrapper.insertBefore(label, wrapper.lastElementChild);

        input.value = '';
        updateMedsSelection();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#meds-multiselect')) {
            const options = document.getElementById('meds-options');
            if (options) options.style.display = 'none';
        }
    });
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'records', [
    'title' => 'Patient Records',
    'content' => $content,
]);
