<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">Files & Documents Management</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Monitor and manage all system-wide patient documents and uploads.</p>
        </div>
        <button class="btn btn-primary" onclick="showUploadModal()">
            <span class="material-symbols-outlined" style="margin-right:8px;">upload_file</span>
            Upload Document
        </button>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" class="filter-bar" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" placeholder="Search by file name or description..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>

        <select name="category" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
            <option value="">All Categories</option>
            <option value="lab_result" <?= ($_GET['category'] ?? '') === 'lab_result' ? 'selected' : '' ?>>Lab Results</option>
            <option value="prescription" <?= ($_GET['category'] ?? '') === 'prescription' ? 'selected' : '' ?>>Prescriptions</option>
            <option value="identification" <?= ($_GET['category'] ?? '') === 'identification' ? 'selected' : '' ?>>Identification</option>
            <option value="phr" <?= ($_GET['category'] ?? '') === 'phr' ? 'selected' : '' ?>>PHR</option>
            <option value="other" <?= ($_GET['category'] ?? '') === 'other' ? 'selected' : '' ?>>Others</option>
        </select>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php $hasFilters = !empty($_GET['q']) || !empty($_GET['category']); ?>
            <a href="files.php" class="btn btn-outline <?= !$hasFilters ? 'disabled' : '' ?>" <?= !$hasFilters ? 'onclick="return false;"' : '' ?>>Reset</a>
        </div>
    </form>

    <?php
    $filters = $_GET;
    // Note: SearchHelper used for general filtering
    list($where, $params) = SearchHelper::buildWhere($filters, ['d.file_name', 'd.description', 'u.first_name', 'u.last_name']);

    // Joint query to get uploader info
    $query = "SELECT d.*, u.first_name as uploader_first, u.last_name as uploader_last, p_u.first_name as patient_first, p_u.last_name as patient_last 
              FROM documents d 
              JOIN users u ON d.uploader_user_id = u.user_id
              LEFT JOIN users p_u ON d.user_id = p_u.user_id
              $where AND d.deleted_at IS NULL
              ORDER BY d.uploaded_at DESC";

    // Fetch patients for upload dropdown
    $patStmt = db()->query("SELECT u.user_id as patient_id, u.first_name, u.last_name 
                            FROM users u 
                            WHERE u.role = 'patient'
                            ORDER BY u.last_name ASC");
    $patients = $patStmt->fetchAll();

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
        <h3 style="margin-bottom:20px;">Upload Document</h3>
        <form id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="upload_document">

            <div class="form-group">
                <label>File (PDF, JPG, PNG)</label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="lab_result">Lab Result</option>
                    <option value="prescription">Prescription</option>
                    <option value="identification">Identification</option>
                    <option value="phr">PHR / Medical Certificate</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Select User / Context</label>
                <div class="search-box" style="position: relative;">
                    <input type="text" id="user-search-admin" placeholder="Search patients or staff..." onkeyup="searchUsersAdmin(this.value)" autocomplete="off">
                    <input type="hidden" name="patient_id" id="selected-user-id-admin">
                    <div id="search-results-admin" style="position: absolute; top: 100%; left: 0; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; display:none; z-index: 100; max-height: 200px; overflow-y: auto; box-shadow: var(--shadow);"></div>
                </div>
                <small style="font-size:10px; color:var(--text-secondary);">Leave blank for general system files.</small>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Brief description..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:10px;">Upload File</button>
        </form>
    </div>
</div>

<script>
    function showUploadModal() {
        document.getElementById('modal-upload').classList.add('show');
    }

    function closeUploadModal() {
        document.getElementById('modal-upload').classList.remove('show');
    }

    function searchUsersAdmin(q) {
        const results = document.getElementById('search-results-admin');
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
                        html += `<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border-light);" onclick="selectUserAdmin(${p.user_id}, '${p.first_name} ${p.last_name} (${p.role})')">
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

    function selectUserAdmin(id, name) {
        document.getElementById('selected-user-id-admin').value = id;
        document.getElementById('user-search-admin').value = name;
        document.getElementById('search-results-admin').style.display = 'none';
    }

    // Close dropdown
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-box')) {
            const el = document.getElementById('search-results-admin');
            if (el) el.style.display = 'none';
        }
    });

    document.getElementById('uploadForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Uploading...';

        const formData = new FormData(this);

        try {
            const res = await fetch('<?= base_url('/public/controllers/file-handler.php') ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const json = await res.json();

            if (json.ok) {
                alert('File uploaded successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + (json.message || 'Unknown error'));
            }
        } catch (err) {
            alert('Upload failed. Please try again.');
            console.error(err);
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    });
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'files', [
    'title' => 'Files & Documents',
    'content' => $content,
]);
