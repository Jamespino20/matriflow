<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'patient') redirect('/');

// Get patient ID
$stmt = db()->prepare("SELECT patient_id FROM patient WHERE user_id = ?");
$stmt->execute([$u['user_id']]);
$patient = $stmt->fetch();
$patientId = $patient ? (int)$patient['patient_id'] : 0;

// Fetch documents
$stmt = db()->prepare("SELECT * FROM documents WHERE patient_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC");
$stmt->execute([$patientId]);
$docs = $stmt->fetchAll();

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">My Documents</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Manage your lab results, prescriptions, and health records.</p>
        </div>
        <button class="btn btn-primary" data-modal-open="modal-upload" style="display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px">upload_file</span>
            Upload Document
        </button>
    </div>

    <!-- Category Filters -->
    <div style="display:flex; gap:10px; margin-bottom:24px; overflow-x:auto; padding-bottom:8px;">
        <button class="btn btn-outline btn-sm active">All Files</button>
        <button class="btn btn-outline btn-sm">Lab Results</button>
        <button class="btn btn-outline btn-sm">Prescriptions</button>
        <button class="btn btn-outline btn-sm">ID / Legal</button>
    </div>

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
                            if (strpos($doc['file_type'], 'image') !== false) $icon = 'image';
                            if (strpos($doc['file_type'], 'pdf') !== false) $icon = 'picture_as_pdf';
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
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">View / Download</a>
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
<div class="modal-overlay" id="modal-upload" style="display: none;">
    <div class="modal-card">
        <div class="modal-head">
            <h3 class="modal-title">Upload Document</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                <input type="hidden" name="patient_id" value="<?= $patientId ?>">

                <div class="form-group">
                    <label class="label">Choose File</label>
                    <input type="file" name="document" required class="input-file">
                    <p class="help">Max 5MB. PDF, JPG, PNG, DOCX supported.</p>
                </div>

                <div class="form-group">
                    <label class="label">Category</label>
                    <select name="category" class="input" required>
                        <option value="lab_result">Lab Result</option>
                        <option value="prescription">Prescription</option>
                        <option value="identification">Identification (ID)</option>
                        <option value="phr">Health Record (PHR)</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="label">Description (Optional)</label>
                    <textarea name="description" class="input" rows="3" placeholder="Describe this document..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:12px;">
                    <span class="material-symbols-outlined">cloud_upload</span>
                    Start Upload
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function deleteDoc(id) {
        if (!confirm('Are you sure you want to delete this document? This action cannot be undone.')) return;

        const fd = new FormData();
        fd.append('action', 'delete_document');
        fd.append('document_id', id);
        fd.append('csrf_token', '<?= CSRF::token() ?>');

        fetch('/public/controllers/file-handler.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: fd
        }).then(res => res.json()).then(data => {
            if (data.ok) location.reload();
            else alert(data.message);
        });
    }

    document.getElementById('uploadForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Uploading...';

        try {
            const res = await fetch('/public/controllers/file-handler.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(this)
            });
            const data = await res.json();
            if (data.ok) location.reload();
            else alert(data.message);
        } catch (err) {
            alert('Upload failed. Connection error.');
        } finally {
            btn.disabled = false;
            btn.textContent = origText;
        }
    });

    function toggleDropdown(btn) {
        const content = btn.nextElementSibling;
        content.style.display = content.style.display === 'block' ? 'none' : 'block';

        // Close when clicking outside
        document.addEventListener('click', function close(e) {
            if (!btn.contains(e.target)) {
                content.style.display = 'none';
                document.removeEventListener('click', close);
            }
        });
    }
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
