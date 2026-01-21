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
    <form method="GET" class="filter-bar">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name or ID...">
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q): ?>
                <a href="<?= base_url('/public/doctor/patients.php') ?>" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Patient Name</th>
                <th>User ID</th>
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
                        <td style="font-family: monospace;"><?= $p['user_id'] ?></td>
                        <td><?= $p['last_visit'] ? date('M j, Y', strtotime($p['last_visit'])) : '<span style="color:var(--text-secondary)">None</span>' ?></td>
                        <td style="text-align: right;">
                            <button class="btn btn-primary btn-sm" onclick="window.location.href='<?= base_url('/public/doctor/records.php?patient_id=' . $p['user_id']) ?>'">Clinical File</button>
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
