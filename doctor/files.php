<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<div class="card">
    <h2>Files & Documents</h2>
    <p>Access medical documents and patient files.</p>
    <table class="table">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Patient</th>
                <th>Date</th>
                <th>Type</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = db()->prepare("
                SELECT d.*, u.first_name, u.last_name
                FROM documents d
                JOIN patient pt ON d.patient_id = pt.patient_id
                JOIN user u ON pt.user_id = u.user_id
                JOIN appointment a ON pt.patient_id = a.patient_id
                WHERE a.doctor_user_id = :doctor_id
                AND d.deleted_at IS NULL
                GROUP BY d.document_id
                ORDER BY d.uploaded_at DESC
            ");
            $stmt->execute([':doctor_id' => $u['user_id']]);
            $files = $stmt->fetchAll();

            if (empty($files)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #637588;">No files found.</td>
                </tr>
                <?php else:
                foreach ($files as $f): ?>
                    <tr>
                        <td><?= htmlspecialchars($f['file_name']) ?></td>
                        <td><?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?></td>
                        <td><?= date('M j, Y H:i', strtotime($f['uploaded_at'])) ?></td>
                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $f['category'] ?? 'Other'))) ?></td>
                        <td>
                            <a href="<?= htmlspecialchars($f['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
            <?php endforeach;
            endif; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'files', [
    'title' => 'Files & Documents',
    'content' => $content,
]);
