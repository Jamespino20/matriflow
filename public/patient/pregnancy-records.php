<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$patientId = Patient::getPatientIdForUser((int)$u['user_id']);
$baseline = PrenatalBaseline::findByPatientId($patientId);
$visits = $baseline ? PrenatalVisit::listByBaseline((int)$baseline['baseline_id']) : [];

ob_start();
?>
<div class="card">
    <h2>Pregnancy Records</h2>
    <p>Track your pregnancy progress with medical records and milestones.</p>

    <?php if ($baseline): ?>
        <div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #e2e8f0;">
            <h4 style="margin-top: 0; color: var(--primary);">Current Pregnancy Baseline</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div>
                    <small style="color: #64748b; font-weight: 600;">LMP Date</small>
                    <div style="font-size: 1.1em;"><?= date('M j, Y', strtotime($baseline['lmp_date'])) ?></div>
                </div>
                <div>
                    <small style="color: #64748b; font-weight: 600;">EDD (Estimated)</small>
                    <div style="font-size: 1.1em;"><?= date('M j, Y', strtotime($baseline['estimated_due_date'])) ?></div>
                </div>
                <div>
                    <small style="color: #64748b; font-weight: 600;">Gravidity</small>
                    <div style="font-size: 1.1em;"><?= e($baseline['gravidity']) ?></div>
                </div>
                <div>
                    <small style="color: #64748b; font-weight: 600;">Parity</small>
                    <div style="font-size: 1.1em;"><?= e($baseline['parity']) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <h4 style="margin-bottom: 15px;">Prenatal Visits</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Week</th>
                <th>Fundal Height</th>
                <th>Fetal Activity</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($visits)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #637588;">No prenatal visit records available</td>
                </tr>
            <?php else: ?>
                <?php foreach ($visits as $v): ?>
                    <?php
                    // Approximate week calculation if not stored
                    $visitDate = new DateTime($v['visit_recorded_at']);
                    $weekLabel = '-';
                    if ($baseline) {
                        $lmp = new DateTime($baseline['lmp_date']);
                        $diff = $visitDate->diff($lmp);
                        $weeks = floor($diff->days / 7);
                        $weekLabel = $weeks . ' Weeks';
                    }
                    ?>
                    <tr>
                        <td><?= $visitDate->format('M j, Y') ?></td>
                        <td><?= $weekLabel ?></td>
                        <td><?= e($v['fundal_height_cm']) ?> cm</td>
                        <td>
                            <div>HR: <?= e($v['fetal_heart_rate']) ?></div>
                            <small class="text-muted">Mov: <?= e($v['fetal_movement_noted']) ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'patient', 'pregnancy-records', [
    'title' => 'Pregnancy Records',
    'content' => $content,
]);
