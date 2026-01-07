<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
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
            <h2 style="margin: 0;">Clinical Patient Directory</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">View clinical records and history.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name or ID..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($q): ?>
            <a href="<?= base_url('/public/doctor/patients.php') ?>" class="btn btn-outline">Clear</a>
        <?php endif; ?>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>ID Number</th>
                <th>Last Visit</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($patients)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-secondary);">No clinical records found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">Joined: <?= date('M Y', strtotime($p['created_at'])) ?></div>
                        </td>
                        <td style="font-family: monospace;"><?= e($p['identification_number'] ?? 'N/A') ?></td>
                        <td><?= $p['last_visit'] ? date('M j, Y', strtotime($p['last_visit'])) : '<span style="color:var(--text-secondary)">None</span>' ?></td>
                        <td style="text-align: right;">
                            <button class="btn btn-primary btn-sm" onclick="window.location.href='<?= base_url('/public/doctor/records.php?patient_id=' . $p['patient_id']) ?>'">Clinical File</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'patients', [
    'title' => 'My Patients',
    'content' => $content,
]);
