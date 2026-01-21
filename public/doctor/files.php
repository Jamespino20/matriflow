<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Files & Documents</h2>
            <p style="margin:5px 0 0; color:var(--text-secondary);">Access medical documents, lab results, and shared patient files.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-upload-file').classList.add('show')">
            <span class="material-symbols-outlined">upload_file</span> Upload Patient File
        </button>
    </div>

    <?php
    $q = trim((string)($_GET['q'] ?? ''));
    $categoryFilter = $_GET['category'] ?? '';

    // Expanded query:
    // 1. Files uploaded by this doctor
    // 2. Files sent TO this doctor (user_id = doctor_id)
    // 3. Files belonging to patients of this doctor
    $sql = "SELECT d.*, u.first_name, u.last_name, 
                   uploader.first_name as uploader_first, uploader.last_name as uploader_last
            FROM documents d
            LEFT JOIN users u ON d.user_id = u.user_id
            LEFT JOIN users uploader ON d.uploader_user_id = uploader.user_id
            WHERE d.deleted_at IS NULL
            AND (
                d.uploader_user_id = :uid1
                OR d.user_id = :uid2
                OR d.user_id IN (SELECT user_id FROM appointment WHERE doctor_user_id = :uid3 AND deleted_at IS NULL)
            )";

    $params = [':uid1' => $u['user_id'], ':uid2' => $u['user_id'], ':uid3' => $u['user_id']];

    if ($q !== '') {
        $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR d.file_name LIKE :q3 OR d.description LIKE :q4)";
        $params[':q1'] = "%$q%";
        $params[':q2'] = "%$q%";
        $params[':q3'] = "%$q%";
        $params[':q4'] = "%$q%";
    }

    if ($categoryFilter !== '') {
        $sql .= " AND d.category = :category";
        $params[':category'] = $categoryFilter;
    }

    $sql .= " GROUP BY d.document_id ORDER BY d.uploaded_at DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll();
    ?>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient name or file...">
        </div>

        <div class="form-group">
            <select name="category">
                <option value="">All Categories</option>
                <option value="lab_result" <?= $categoryFilter === 'lab_result' ? 'selected' : '' ?>>Lab Results</option>
                <option value="prescription" <?= $categoryFilter === 'prescription' ? 'selected' : '' ?>>Prescriptions</option>
                <option value="identification" <?= $categoryFilter === 'identification' ? 'selected' : '' ?>>IDs</option>
                <option value="phr" <?= $categoryFilter === 'phr' ? 'selected' : '' ?>>PHR</option>
                <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Others</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q || $categoryFilter): ?>
                <a href="files.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Context</th>
                <th>Uploaded By</th>
                <th>Date</th>
                <th>Type</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($files)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">No documents found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($files as $f): ?>
                    <tr>
                        <td style="font-weight:600;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="material-symbols-outlined" style="font-size:20px; color:var(--primary);">description</span>
                                <div title="<?= e($f['description']) ?>"><?= htmlspecialchars((string)$f['file_name']) ?></div>
                            </div>
                        </td>
                        <td>
                            <?php if ($f['user_id']): ?>
                                <?= htmlspecialchars((string)($f['first_name'] . ' ' . $f['last_name'])) ?>
                                <small style="display:block; font-size:10px; opacity:0.7;">(<?= ucfirst($f['role']) ?>)</small>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">General / System</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px;">
                            <?php if ($f['uploader_user_id'] == $u['user_id']): ?>
                                <span style="color: var(--primary); font-weight: 600;">You</span>
                            <?php else: ?>
                                <?= htmlspecialchars((string)($f['uploader_first'] . ' ' . $f['uploader_last'])) ?>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px; color:var(--text-secondary);"><?= date('M j, Y', strtotime((string)$f['uploaded_at'])) ?></td>
                        <td>
                            <span class="badge badge-outline" style="font-size:11px;">
                                <?= ucfirst(str_replace('_', ' ', (string)($f['category'] ?? 'Other'))) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <a href="<?= base_url('/public/controllers/file-server.php?id=' . $f['document_id']) ?>" target="_blank" class="btn btn-icon" title="View Document">
                                <span class="material-symbols-outlined">visibility</span>
                            </a>
                            <a href="<?= base_url('/public/controllers/file-server.php?id=' . $f['document_id'] . '&action=download') ?>" class="btn btn-icon" title="Download">
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
<div id="modal-upload-file" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-upload-file').classList.remove('show')">&times;</button>
        <h3>Upload Document</h3>
        <form action="<?= base_url('/public/controllers/document-handler.php') ?>" method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="upload">

            <div class="form-group">
                <label>Select User / Context</label>
                <div class="search-box" style="position: relative;">
                    <input type="text" id="patient-search" placeholder="Search patients or staff..." onkeyup="searchUsers(this.value)">
                    <input type="hidden" name="user_id" id="selected-patient-id">
                    <div id="search-results" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; display:none; z-index: 100; max-height: 200px; overflow-y: auto;"></div>
                </div>
                <small style="font-size:10px; color:var(--text-secondary);">Leave blank for general system files.</small>
            </div>

            <div class="form-group">
                <label>File Category</label>
                <select name="category" required>
                    <option value="lab_result">Lab Result</option>
                    <option value="prescription">Prescription</option>
                    <option value="phr">PHR / History</option>
                    <option value="identification">Identification</option>
                    <option value="other" selected>Others</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Brief note about the file..." style="min-height: 60px; border: 1px solid var(--border); border-radius: 8px; padding: 10px; width: 100%;"></textarea>
            </div>

            <div class="form-group">
                <label>Choose File</label>
                <input type="file" name="file" required style="border:none; padding:0;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Upload Document</button>
        </form>
    </div>
</div>

<script>
    function searchUsers(q) {
        const results = document.getElementById('search-results');
        if (q.length < 2) {
            results.style.display = 'none';
            return;
        }

        fetch('<?= base_url('/public/controllers/message-handler.php?action=search_users&q=') ?>' + encodeURIComponent(q))
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.users.length > 0) {
                    let html = '';
                    json.users.forEach(p => {
                        html += `<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border-light);" onclick="selectUser(${p.user_id}, '${p.first_name} ${p.last_name} (${p.role})')">
                            <div style="font-weight: 600;">${p.first_name} ${p.last_name}</div>
                            <div style="font-size: 11px; color: var(--text-secondary);">Role: ${p.role}</div>
                        </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                } else {
                    results.style.display = 'none';
                }
            });
    }

    function selectUser(id, name) {
        document.getElementById('selected-patient-id').value = id;
        document.getElementById('patient-search').value = name;
        document.getElementById('search-results').style.display = 'none';
    }

    // Close dropdown
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) {
            document.getElementById('search-results').style.display = 'none';
        }
    });
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'files', [
    'title' => 'Files & Documents',
    'content' => $content,
]);
