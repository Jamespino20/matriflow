<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient') redirect('/');

$q = trim((string)($_GET['q'] ?? ''));
$categoryFilter = $_GET['category'] ?? '';

$sql = "SELECT * FROM documents WHERE user_id = :uid AND deleted_at IS NULL";
$params = [':uid' => $u['user_id']];

if ($q !== '') {
    $sql .= " AND (file_name LIKE :q1 OR description LIKE :q2)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
}

if ($categoryFilter !== '') {
    $sql .= " AND category = :category";
    $params[':category'] = $categoryFilter;
}

$sql .= " ORDER BY uploaded_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll();
$userId = (int)$u['user_id'];
?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">My Documents</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Manage your lab results, prescriptions, and health records.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-upload').classList.add('show')" style="display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px">upload_file</span>
            Upload Document
        </button>
    </div>

    <!-- Standard Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by file name or description...">
        </div>

        <div class="form-group">
            <select name="category">
                <option value="">All Categories</option>
                <option value="lab_result" <?= $categoryFilter === 'lab_result' ? 'selected' : '' ?>>Lab Results</option>
                <option value="prescription" <?= $categoryFilter === 'prescription' ? 'selected' : '' ?>>Prescriptions</option>
                <option value="identification" <?= $categoryFilter === 'identification' ? 'selected' : '' ?>>IDs</option>
                <option value="phr" <?= $categoryFilter === 'phr' ? 'selected' : '' ?>>Health Records</option>
                <option value="other" <?= $categoryFilter === 'other' ? 'selected' : '' ?>>Others</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($q || $categoryFilter): ?>
                <a href="files.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (empty($docs)): ?>
        <div style="text-align:center; padding:60px 20px; border:2px dashed var(--border); border-radius:12px;">
            <span class="material-symbols-outlined" style="font-size:64px; color:var(--text-secondary); opacity:0.3; margin-bottom:16px;">folder_open</span>
            <h3 style="margin:0; color:var(--text-secondary)">No documents found</h3>
            <p style="color:var(--text-secondary); font-size:14px; margin-top:8px;">Upload your first document to get started.</p>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px;">
            <?php foreach ($docs as $doc): ?>
                <div class="card" style="padding:16px; border:1px solid var(--border); position:relative; overflow:hidden;">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div style="width:48px; height:48px; border-radius:8px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center;">
                            <?php
                            $icon = 'description';
                            $fType = $doc['file_type'] ?? '';
                            if (strpos($fType, 'image') !== false) $icon = 'image';
                            if (strpos($fType, 'pdf') !== false) $icon = 'picture_as_pdf';
                            ?>
                            <span class="material-symbols-outlined"><?= $icon ?></span>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($doc['file_name']) ?>">
                                <?= htmlspecialchars($doc['file_name']) ?>
                            </div>
                            <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">
                                <?= ucfirst(str_replace('_', ' ', $doc['category'])) ?> • <?= round($doc['file_size'] / 1024, 1) ?> KB
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-icon btn-sm" onclick="toggleDropdown(this)">
                                <span class="material-symbols-outlined">more_vert</span>
                            </button>
                            <div class="dropdown-content" style="right:0">
                                <a href="<?= base_url('/public/controllers/file-server.php?id=' . $doc['document_id']) ?>" target="_blank">View</a>
                                <a href="<?= base_url('/public/controllers/file-server.php?id=' . $doc['document_id'] . '&action=download') ?>">Download</a>
                                <a href="#" style="color:var(--danger)" onclick="deleteDoc(<?= $doc['document_id'] ?>)">Delete</a>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:12px; font-size:13px; color:var(--text-secondary); line-height:1.4;">
                        <?= htmlspecialchars($doc['description'] ?: 'No description provided.') ?>
                    </div>

                    <div style="margin-top:16px; padding-top:12px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px; color:var(--text-secondary);">Uploaded <?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></span>
                        <span class="badge badge-sm badge-success" style="font-size:10px;">Secure</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="modal-upload">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <div class="modal-head">
            <h3 class="modal-title">Upload Document</h3>
            <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-upload').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="uploadForm" action="<?= base_url('/public/controllers/document-handler.php') ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">

                <div class="form-group">
                    <label>Choose File</label>
                    <input type="file" name="file" required>
                    <p style="font-size:11px; color:var(--text-secondary); margin-top:4px;">Max 5MB. PDF, JPG, PNG supported.</p>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="lab_result">Lab Result</option>
                        <option value="prescription">Prescription</option>
                        <option value="identification">Identification (ID)</option>
                        <option value="phr">Health Record (PHR)</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Share With (Optional)</label>
                    <div class="custom-select" id="staff-multi-select">
                        <div class="select-box" onclick="toggleStaffDropdown()" style="display: flex; justify-content: space-between; align-items: center; padding: 10px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; font-size: 14px;">
                            <span id="selected-staff-text">Keep Private</span>
                            <span class="material-symbols-outlined">expand_more</span>
                        </div>
                        <div class="dropdown-content" id="staff-options" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; z-index: 100; max-height: 200px; overflow-y: auto;">
                            <?php
                            $staff = db()->query("SELECT user_id, first_name, last_name, role FROM users WHERE role IN ('doctor', 'secretary') AND is_active = 1 ORDER BY role, last_name")->fetchAll();
                            foreach ($staff as $s):
                            ?>
                                <label style="display: flex; align-items: center; padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border-light); margin: 0; font-size: 13px;">
                                    <input type="checkbox" name="shared_with[]" value="<?= $s['user_id'] ?>" style="margin-right: 12px;" onchange="updateStaffText()">
                                    <span><?= ($s['role'] === 'doctor' ? 'Dr. ' : '') . e($s['last_name'] . ', ' . $s['first_name']) ?> (<?= ucfirst($s['role']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Describe this document..." style="width:100%; min-height:60px;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:20px;">
                    <span class="material-symbols-outlined">cloud_upload</span> Start Upload
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function deleteDoc(id) {
        if (!confirm('Are you sure you want to delete this document?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('document_id', id);
        fd.append('csrf_token', '<?= CSRF::token() ?>');

        fetch('<?= base_url('/public/controllers/document-handler.php') ?>', {
            method: 'POST',
            body: fd
        }).then(res => res.json()).then(data => {
            if (data.ok) location.reload();
            else alert(data.message);
        });
    }

    function toggleStaffDropdown() {
        const content = document.getElementById('staff-options');
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    }

    function updateStaffText() {
        const checkboxes = document.querySelectorAll('#staff-options input:checked');
        const text = document.getElementById('selected-staff-text');
        if (checkboxes.length === 0) {
            text.textContent = 'Keep Private';
        } else if (checkboxes.length === 1) {
            text.textContent = checkboxes[0].parentElement.textContent.trim();
        } else {
            text.textContent = checkboxes.length + ' staff members';
        }
    }

    function toggleDropdown(btn) {
        const content = btn.nextElementSibling;
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
        document.addEventListener('click', function close(e) {
            if (!btn.contains(e.target)) {
                content.style.display = 'none';
                document.removeEventListener('click', close);
            }
        });
    }

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#staff-multi-select')) {
            const options = document.getElementById('staff-options');
            if (options) options.style.display = 'none';
        }
    });

    document.getElementById('uploadForm').addEventListener('submit', async function(e) {
        // Normal form submit to document-handler.php
    });
</script>

<style>
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--surface);
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.1);
        z-index: 100;
        border: 1px solid var(--border);
        border-radius: 6px;
    }

    .dropdown-content a {
        color: var(--text);
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        font-size: 13px;
    }

    .dropdown-content a:hover {
        background-color: var(--surface-light);
    }
</style>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'patient', 'files', [
    'title' => 'My Documents',
    'content' => $content,
]);
