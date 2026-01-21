<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient')
    redirect('/');

$userId = (int)$u['user_id'];
$baseline = Pregnancy::findActiveByUserId($userId);
$visits = $baseline ? PrenatalObservation::listByPregnancy((int)$baseline['pregnancy_id']) : [];

ob_start();
?>
<div class="card">
    <h2>Pregnancy Records</h2>
    <p>Track your pregnancy progress with medical records and milestones.</p>

    <?php if ($baseline): ?>
        <div class="card" style="background: var(--bg-light); border: 1px solid var(--border); margin-bottom: 2rem;">
            <h4 style="margin-top: 0; color: var(--primary);">Current Pregnancy Baseline</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">LMP Date</label>
                    <div style="font-size: 1.1em; font-weight: 500;"><?= date('M j, Y', strtotime($baseline['lmp_date'])) ?></div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">EDD (Estimated)</label>
                    <div style="font-size: 1.1em; font-weight: 700; color: var(--primary);"><?= date('M j, Y', strtotime($baseline['estimated_due_date'])) ?></div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Gravidity</label>
                    <div style="font-size: 1.1em; font-weight: 500;">G<?= e($baseline['gravida']) ?></div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 11px; margin-bottom: 4px;">Parity</label>
                    <div style="font-size: 1.1em; font-weight: 500;">P<?= e($baseline['para']) ?></div>
                </div>
                <?php if (!empty($baseline['next_visit_due'])): ?>
                    <div class="form-group" style="margin: 0; padding: 10px; background: var(--surface); border-radius: 8px; border: 1px solid var(--primary-light);">
                        <label style="font-size: 11px; margin-bottom: 4px; color: var(--primary);">Next Visit Date</label>
                        <div style="font-size: 1.1em; font-weight: 700; color: var(--primary);"><?= date('M j, Y', strtotime($baseline['next_visit_due'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <h3 style="margin-bottom: 20px;">Prenatal Visits History</h3>
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
                    <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 40px;">No prenatal visit records available</td>
                </tr>
            <?php else: ?>
                <?php foreach ($visits as $v): ?>
                    <?php
                    // Approximate week calculation if not stored
                    $visitDate = new DateTime($v['recorded_at']);
                    $weekLabel = '-';
                    if ($baseline) {
                        $lmp = new DateTime($baseline['lmp_date']);
                        $diff = $visitDate->diff($lmp);
                        $weeks = floor($diff->days / 7);
                        $weekLabel = $weeks . ' Weeks';
                    }
                    ?>
                    <tr>
                        <td style="font-weight: 600;"><?= $visitDate->format('M j, Y') ?></td>
                        <td><span class="badge badge-info"><?= $weekLabel ?></span></td>
                        <td><?= e($v['fundal_height_cm']) ?> cm</td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <span><small style="color: var(--text-secondary);">FHR:</small> <strong><?= e($v['fetal_heart_rate']) ?></strong> bpm</span>
                                <?php if ($v['fetal_movement_noted']): ?>
                                    <span class="badge badge-success" style="width: fit-content; font-size: 10px;">Movement Noted</span>
                                <?php endif; ?>
                            </div>
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
