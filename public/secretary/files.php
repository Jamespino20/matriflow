<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
$u = Auth::user();
if (!$u || $u['role'] !== 'secretary') {
    redirect('/');
}

// Handle Upload
$uploadError = null;
$uploadSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        $uploadError = "Invalid CSRF token.";
    } else {
        $userId = (int)($_POST['patient_id'] ?? 0);
        $category = $_POST['category'] ?? 'general';
        $description = $_POST['description'] ?? '';

        $file = $_FILES['document'];
        if ($file['error'] === UPLOAD_ERR_OK && $userId) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($ext, $allowed)) {
                $filename = 'doc_' . time() . '_' . uniqid() . '.' . $ext;
                $targetDir = BASE_PATH . '/storage/uploads/documents/';
                if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

                if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
                    $dbPath = 'storage/uploads/documents/' . $filename;
                    $stmt = db()->prepare("INSERT INTO documents (user_id, uploader_user_id, file_name, file_path, category, description, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$userId, $u['user_id'], $file['name'], $dbPath, $category, $description]);
                    $uploadSuccess = "Document uploaded successfully.";
                } else {
                    $uploadError = "Failed to move uploaded file.";
                }
            } else {
                $uploadError = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
            }
        } else {
            $uploadError = "Error uploading file or missing patient.";
        }
    }
}

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">Files & Documents Management</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Manage patient records and uploads.</p>
        </div>
        <button class="btn btn-primary" onclick="openUploadModal()"><span class="material-symbols-outlined">upload_file</span> Upload Document</button>
    </div>

    <?php if ($uploadSuccess): ?><div class="alert alert-success"><?= $uploadSuccess ?></div><?php endif; ?>
    <?php if ($uploadError): ?><div class="alert alert-error"><?= $uploadError ?></div><?php endif; ?>

    <!-- Search & Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" placeholder="Search by file name or description...">
        </div>

        <div class="form-group">
            <select name="category">
                <option value="">All Categories</option>
                <option value="lab_result" <?= ($_GET['category'] ?? '') === 'lab_result' ? 'selected' : '' ?>>Lab Results</option>
                <option value="prescription" <?= ($_GET['category'] ?? '') === 'prescription' ? 'selected' : '' ?>>Prescriptions</option>
                <option value="identification" <?= ($_GET['category'] ?? '') === 'identification' ? 'selected' : '' ?>>Identification</option>
                <option value="phr" <?= ($_GET['category'] ?? '') === 'phr' ? 'selected' : '' ?>>PHR</option>
                <option value="other" <?= ($_GET['category'] ?? '') === 'other' ? 'selected' : '' ?>>Others</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if (!empty($_GET['q']) || !empty($_GET['category'])): ?>
                <a href="files.php" class="btn btn-outline" title="Clear Filters">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php
    $filters = $_GET;
    list($where, $params) = SearchHelper::buildWhere($filters, ['d.file_name', 'd.description', 'u.first_name', 'u.last_name']);

    $query = "SELECT d.*, u.first_name as uploader_first, u.last_name as uploader_last, p_u.first_name as patient_first, p_u.last_name as patient_last 
              FROM documents d 
              JOIN users u ON d.uploader_user_id = u.user_id
              LEFT JOIN users p_u ON d.user_id = p_u.user_id
              $where AND d.deleted_at IS NULL
              ORDER BY d.uploaded_at DESC";

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $docs = $stmt->fetchAll();
    ?>

    <table class="table">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Category</th>
                <th>Patient</th>
                <th>Uploaded By</th>
                <th>Date</th>
                <th style="text-align:right">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($docs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding:40px; color: var(--text-secondary);">
                        <span class="material-symbols-outlined" style="font-size:48px; display:block; margin-bottom:10px; opacity:0.5;">folder_off</span>
                        No documents found matching your criteria.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td style="font-weight:600;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="font-size:20px; color:var(--primary);">description</span>
                                <?= htmlspecialchars((string)$doc['file_name']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-outline" style="font-size:11px;">
                                <?= ucfirst(str_replace('_', ' ', (string)$doc['category'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($doc['patient_first']): ?>
                                <?= htmlspecialchars((string)($doc['patient_first'] . ' ' . $doc['patient_last'])) ?>
                            <?php else: ?>
                                <span style="color:var(--text-secondary); font-size:12px;">System / N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;">
                            <?= htmlspecialchars((string)($doc['uploader_first'] . ' ' . $doc['uploader_last'])) ?>
                        </td>
                        <td style="font-size:13px; color:var(--text-secondary);">
                            <?= date('M j, Y', strtotime((string)$doc['uploaded_at'])) ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="<?= base_url('/public/controllers/file-server.php?id=' . $doc['document_id']) ?>" target="_blank" class="btn btn-icon" title="View">
                                <span class="material-symbols-outlined">visibility</span>
                            </a>
                            <a href="<?= base_url('/public/controllers/file-server.php?id=' . $doc['document_id'] . '&action=download') ?>" class="btn btn-icon" title="Download">
                                <span class="material-symbols-outlined">download</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Upload Modal -->
<div id="modal-upload" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="closeUploadModal()">&times;</button>
        <h3>Upload Document</h3>
        <form method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">

            <div class="form-group">
                <label>Patient</label>
                <input type="text" id="upload-patient-search" placeholder="Search Patient Name..." onkeyup="searchUploadPatients(this.value)" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                <input type="hidden" name="patient_id" id="upload-patient-id" required>
                <div id="upload-patient-results" style="border: 1px solid var(--border); max-height: 150px; overflow-y: auto; display: none;"></div>
                <div id="upload-selected-patient" style="margin-top: 5px; font-weight: 600; color: var(--primary);"></div>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required style="width:100%; padding:10px; border-radius:6px; border:1px solid var(--border);">
                    <option value="lab_result">Lab Result</option>
                    <option value="prescription">Prescription</option>
                    <option value="identification">Identification</option>
                    <option value="phr">PHR / Medical Record</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description (Optional)</label>
                <input type="text" name="description" placeholder="Short description...">
            </div>

            <div class="form-group">
                <label>File</label>
                <input type="file" name="document" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Upload</button>
        </form>
    </div>
</div>

<script>
    function openUploadModal() {
        document.getElementById('modal-upload').classList.add('show');
    }

    function closeUploadModal() {
        document.getElementById('modal-upload').classList.remove('show');
    }

    function searchUploadPatients(q) {
        const results = document.getElementById('upload-patient-results');
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
                        html += `<div style="padding: 10px; border-bottom: 1px solid var(--border); cursor: pointer; background: var(--surface);" 
                             onclick="selectUploadPatient(${p.user_id}, '${p.first_name} ${p.last_name}')">
                        <div style="font-weight: 600;">${p.first_name} ${p.last_name}</div>
                        <div style="font-size: 11px; opacity: 0.7;">ID: ${p.identification_number || 'N/A'}</div>
                    </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                } else {
                    results.style.display = 'none';
                }
            });
    }

    function selectUploadPatient(id, name) {
        document.getElementById('upload-patient-id').value = id;
        document.getElementById('upload-selected-patient').textContent = "Selected: " + name;
        document.getElementById('upload-patient-results').style.display = 'none';
        document.getElementById('upload-patient-search').value = name;
    }
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'files', [
    'title' => 'Files & Documents',
    'content' => $content,
]);
