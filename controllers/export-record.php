<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$patientId = (int)($_GET['patient_id'] ?? 0);
if (!$patientId) {
    exit('Invalid patient ID');
}

$patientData = db()->prepare("SELECT p.*, u.* FROM patient p JOIN user u ON p.user_id = u.user_id WHERE p.patient_id = :pid");
$patientData->execute([':pid' => $patientId]);
$patient = $patientData->fetch();

if (!$patient) {
    exit('Patient not found');
}

$baseline = PrenatalBaseline::findByPatientId($patientId);
$visits = $baseline ? PrenatalVisit::listByBaseline((int)$baseline['prenatal_baseline_id']) : [];
$consultations = Consultation::listByPatient($patientId);
$prescriptions = Prescription::listByPatient($patientId);

// Basic print-friendly HTML export
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Clinical Record - <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?></title>
    <style>
        body {
            font-family: sans-serif;
            padding: 40px;
            color: #333;
            line-height: 1.6;
        }

        .header {
            border-bottom: 2px solid #14457b;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .section {
            margin-bottom: 30px;
        }

        h1 {
            color: #14457b;
            margin: 0;
        }

        h2 {
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #eee;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f9f9f9;
        }

        .footer {
            margin-top: 50px;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>

<body onload="window.print()">
    <div class="header">
        <h1>MatriFlow Clinical Summary</h1>
        <p>Generated on <?= date('M j, Y H:i') ?></p>
    </div>

    <div class="section">
        <h2>Patient Information</h2>
        <p>
            <strong>Name:</strong> <?= e($patient['first_name'] . ' ' . $patient['last_name']) ?><br>
            <strong>Patient ID:</strong> <?= e($patient['identification_number'] ?? 'N/A') ?><br>
            <strong>Email:</strong> <?= e($patient['email']) ?><br>
            <strong>Phone:</strong> <?= e($patient['phone_number'] ?? 'N/A') ?>
        </p>
    </div>

    <?php if ($baseline): ?>
        <div class="section">
            <h2>Prenatal Baseline</h2>
            <p>
                <strong>LMP Date:</strong> <?= date('M j, Y', strtotime($baseline['lmp_date'])) ?><br>
                <strong>Estimated Due Date:</strong> <?= date('M j, Y', strtotime($baseline['estimated_due_date'])) ?><br>
                <strong>G/P:</strong> G<?= $baseline['gravidity'] ?> P<?= $baseline['parity'] ?>
            </p>
        </div>

        <div class="section">
            <h2>Prenatal Visits</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Fundal Ht (cm)</th>
                        <th>FHR (bpm)</th>
                        <th>Fetal Movement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $v): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($v['visit_recorded_at'])) ?></td>
                            <td><?= $v['fundal_height_cm'] ?></td>
                            <td><?= $v['fetal_heart_rate'] ?></td>
                            <td><?= $v['fetal_movement_noted'] ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($visits)): ?>
                        <tr>
                            <td colspan="4">No visits recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>Clinical Consultations</h2>
        <?php if (empty($consultations)): ?>
            <p>No medical consultations recorded.</p>
        <?php else: ?>
            <?php foreach ($consultations as $c): ?>
                <div style="margin-bottom: 20px; border: 1px solid #eee; padding: 15px;">
                    <strong>Date:</strong> <?= date('M j, Y - g:i A', strtotime($c['created_at'])) ?> | <strong>Doctor:</strong> Dr. <?= e($c['doctor_first'] . ' ' . $c['doctor_last']) ?><br>
                    <strong>Subjective:</strong> <?= nl2br(e($c['subjective_notes'])) ?><br>
                    <strong>Objective:</strong> <?= nl2br(e($c['objective_notes'])) ?><br>
                    <strong>Assessment:</strong> <strong><?= e($c['assessment']) ?></strong><br>
                    <strong>Plan:</strong> <?= nl2br(e($c['plan'])) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Prescriptions Issued</h2>
        <?php if (empty($prescriptions)): ?>
            <p>No prescriptions issued.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Medication</th>
                        <th>Dosage / Freq</th>
                        <th>Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $p): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($p['prescribed_at'])) ?></td>
                            <td><strong><?= e($p['medication_name']) ?></strong></td>
                            <td><?= e($p['dosage']) ?> - <?= e($p['frequency']) ?></td>
                            <td><?= e($p['instructions']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="footer">
        Confidential Clinical Record - MatriFlow Maternal Health Management System
    </div>
</body>

</html>