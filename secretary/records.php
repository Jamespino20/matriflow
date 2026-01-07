<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

ob_start();
?>
<?php
$patientId = (int)($_GET['patient_id'] ?? 0);
$patient = null;
$baseline = null;
$visits = [];

if ($patientId) {
    // Reusing the query structure but ensuring we just read
    $patientData = db()->prepare("SELECT p.*, u.first_name, u.last_name, u.email, u.contact_number 
                                FROM patient p 
                                JOIN user u ON p.user_id = u.user_id 
                                WHERE p.patient_id = :pid");
    $patientData->execute([':pid' => $patientId]);
    $patient = $patientData->fetch();

    if ($patient) {
        $baseline = PrenatalBaseline::findByPatientId($patientId);
        if ($baseline) {
            $visits = PrenatalVisit::listByBaseline((int)$baseline['prenatal_baseline_id']);
        }
        $consultations = Consultation::listByPatient($patientId);
        $prescriptions = Prescription::listByPatient($patientId);
    }
}
?>
<style>
    .tab-nav {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid var(--border);
        margin-bottom: 20px;
    }

    .tab-btn {
        padding: 10px 20px;
        border: none;
        background: none;
        cursor: pointer;
        color: var(--text-secondary);
        font-weight: 600;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }

    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
    }
</style>

<?php if (!$patient): ?>
    <div class="card">
        <h2 style="margin-bottom: 20px;">Find Patient Record</h2>
        <div class="search-box" style="margin-bottom: 30px;">
            <input type="text" id="patient-search" placeholder="Enter patient name, email or ID..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border);" onkeyup="searchPatientsForRecords(this.value)">
            <div id="search-results" style="margin-top: 10px; border: 1px solid var(--border); border-radius: 8px; display:none; max-height: 300px; overflow-y: auto;"></div>
        </div>
        <p style="text-align: center; color: var(--text-secondary);">Select a patient to view their medical history and prenatal charts.</p>
    </div>
<?php else: ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <a href="<?= base_url('/public/secretary/records.php') ?>" class="btn btn-icon" title="Go Back"><span class="material-symbols-outlined">arrow_back</span></a>
            <div>
                <h1 style="margin: 0;"><?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
                <div style="font-size: 14px; color: var(--text-secondary);">Patient ID: <?= e($patient['identification_number'] ?? 'Not set') ?> | <?= e($patient['email']) ?></div>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
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
                        <span style="font-weight: 600;"><?= ($latest && $latest['systolic_pressure']) ? $latest['systolic_pressure'] . '/' . $latest['diastolic_pressure'] : '--' ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Weight:</span>
                        <span style="font-weight: 600;"><?= ($latest && $latest['weight_kg']) ? $latest['weight_kg'] . ' kg' : '--' ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-secondary);">Heart Rate:</span>
                        <span style="font-weight: 600;"><?= ($latest && $latest['heart_rate_bpm']) ? $latest['heart_rate_bpm'] . ' bpm' : '--' ?></span>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top: 24px;">
                <h3>Prenatal Baseline</h3>
                <?php if (!$baseline): ?>
                    <p style="font-size: 13px; color: var(--text-secondary); margin: 15px 0;">No active pregnancy record found.</p>
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
                            <span style="font-weight: 600;">G<?= $baseline['gravidity'] ?> P<?= $baseline['parity'] ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-record">
            <div class="card">
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="switchTab('prenatal')">Prenatal Visits</button>
                    <button class="tab-btn" onclick="switchTab('consultations')">General Consults</button>
                    <button class="tab-btn" onclick="switchTab('gyne')">Gynecology</button>
                    <button class="tab-btn" onclick="switchTab('prescriptions')">Prescriptions</button>
                </div>

                <!-- Prenatal Tab -->
                <div id="tab-prenatal" class="tab-pane active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;">Visit Progress</h3>
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
                                        <td style="font-weight: 600;"><?= date('M j, Y', strtotime($v['visit_recorded_at'])) ?></td>
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

<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        const btn = document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`);
        const pane = document.getElementById(`tab-${tab}`);

        if (btn && pane) {
            btn.classList.add('active');
            pane.classList.add('active');
        }
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
                        html += `<div style="padding: 12px; border-bottom: 1px solid var(--border); cursor: pointer;" onclick="window.location.href='?patient_id=${p.patient_id}'">
                        <div style="font-weight: 700;">${p.first_name} ${p.last_name}</div>
                        <div style="font-size: 11px; color: var(--text-secondary)">Patient ID: ${p.patient_id || 'N/A'}</div>
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
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'records', [
    'title' => 'Patient Records',
    'content' => $content,
]);
