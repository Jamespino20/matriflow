<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

$q = trim((string)($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$tests = LaboratoryController::listAll(['q' => $q, 'status' => $status]);

ob_start();
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Laboratory Orders</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Manage patient test orders and record status.</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('modal-new-lab').classList.add('show')">
            <span class="material-symbols-outlined">add</span> Order New Test
        </button>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="search-container" style="flex: 2;">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by patient or test type...">
        </div>
        <div class="form-group" style="flex: 1;">
            <label>Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div style="display: flex; gap: 8px; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php $hasFilters = $q || $status; ?>
            <a href="lab-tests.php" class="btn btn-outline <?= !$hasFilters ? 'disabled' : '' ?>" <?= !$hasFilters ? 'onclick="return false;"' : '' ?>>Reset</a>
        </div>
    </form>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;"><?= e($_SESSION['success']);
                                                                        unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" style="margin-bottom: 20px;"><?= e($_SESSION['error']);
                                                                    unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>Patient</th>
                <th>Test Type</th>
                <th>Ordered On</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tests)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No lab results found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></div>
                        </td>
                        <td><strong><?= e((string)($t['test_name'] ?? '')) ?></strong></td>
                        <td><?= date('M j, Y', strtotime($t['ordered_at'])) ?></td>
                        <td>
                            <span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'warning' ?>">
                                <?= strtoupper($t['status']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-secondary btn-sm" onclick="openLabModal(<?= htmlspecialchars(json_encode($t)) ?>)">Update</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Order New Lab Modal -->
<div id="modal-new-lab" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-new-lab').classList.remove('show')">&times;</button>
        <h3>Order New Lab Test</h3>

        <form action="<?= base_url('/public/controllers/lab-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Select Patient</label>
                <div style="position: relative;">
                    <input type="text" id="patient-search" placeholder="Search by name or ID..." onkeyup="searchPatients(this.value)" autocomplete="off">
                    <input type="hidden" name="patient_id" id="selected-patient-id" required>
                    <div id="search-results" class="search-suggestions" style="display: none; position: absolute; width: 100%; z-index: 10; background: var(--surface); border: 1px solid var(--border); border-radius: 4px; max-height: 200px; overflow-y: auto; margin-top: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
                </div>
                <div id="selected-patient-badge" style="display: none; margin-top: 8px;">
                    <span class="badge badge-primary" style="padding: 8px 12px; border-radius: 20px;">
                        Selected: <span id="selected-patient-name"></span>
                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; cursor: pointer; margin-left: 8px;" onclick="clearSelectedPatient()">close</span>
                    </span>
                </div>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Test Type</label>
                <div class="custom-multiselect" id="test-multiselect">
                    <div class="select-box" onclick="toggleTestDropdown()">
                        <span id="selected-test-text">Select Test Type...</span>
                        <span class="material-symbols-outlined">expand_more</span>
                    </div>
                    <div class="checkboxes-wrapper" id="test-options">
                        <?php
                        $commonTests = ['CBC', 'Urinalysis', 'Fecalysis', 'Blood Typing', 'OGTT', 'HBsAg', 'VDRL/RPR', 'HIV Screening', 'Pregnancy Test', 'Ultrasound'];
                        foreach ($commonTests as $test) : ?>
                            <label><input type="checkbox" name="test_type_arr[]" value="<?= $test ?>" onchange="updateTestSelection()"> <?= $test ?></label>
                        <?php endforeach; ?>
                        <div style="padding: 10px; border-top: 1px solid var(--border-light);">
                            <input type="text" id="other-test" placeholder="Other test type..." style="width: 100%; padding: 6px; font-size: 13px; border: 1px solid var(--border); border-radius: 4px;">
                            <button type="button" class="btn btn-sm btn-secondary" style="margin-top: 6px; width: 100%;" onclick="addOtherTest()">Add Other</button>
                        </div>
                    </div>
                    <!-- Hidden input for backward compatibility or simple comma separated string -->
                    <input type="hidden" name="test_type" id="test_type_final">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Create Order</button>
        </form>
    </div>
</div>

<!-- Update Modal -->
<div id="modal-lab-update" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-lab-update').classList.remove('show')">&times;</button>
        <h3>Update Lab Order</h3>
        <p id="lab-patient-name" style="font-weight: 700; color: var(--primary); margin-top: 10px;"></p>

        <form id="form-lab-update" action="<?= base_url('/public/controllers/lab-handler.php') ?>" method="POST" enctype="multipart/form-data" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="test_id" id="lab-id">
            <input type="hidden" name="action" value="update">

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="lab-status" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px;">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Findings (Results)</label>
                <textarea name="test_result" id="lab-result" style="width:100%; height:100px; padding:10px; border:1px solid var(--border); border-radius:8px;"></textarea>
            </div>

            <div class="form-group" style="margin-top: 15px;">
                <label>Attach Result File (PDF, Image)</label>
                <input type="file" name="lab_file" style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 8px;">
                <div id="lab-current-file" style="margin-top: 8px; display: none;">
                    <a id="lab-file-link" href="#" target="_blank" class="badge badge-info" style="text-decoration: none;">View Existing File</a>
                </div>
            </div>

            <input type="hidden" name="released" value="0">

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">Save Changes</button>
        </form>
    </div>
</div>

<script>
    function openLabModal(test) {
        document.getElementById('lab-id').value = test.test_id;
        document.getElementById('lab-patient-name').innerText = test.first_name + ' ' + test.last_name + ' - ' + (test.test_name || '');
        document.getElementById('lab-result').value = test.test_result || '';
        document.getElementById('lab-status').value = test.status || 'pending';

        const fileDiv = document.getElementById('lab-current-file');
        const fileLink = document.getElementById('lab-file-link');
        if (test.result_file_path) {
            fileLink.href = '<?= base_url('/') ?>' + test.result_file_path;
            fileDiv.style.display = 'block';
        } else {
            fileDiv.style.display = 'none';
        }

        document.getElementById('modal-lab-update').classList.add('show');
    }

    function searchPatients(q) {
        const results = document.getElementById('search-results');
        if (q.length < 2) {
            results.style.display = 'none';
            return;
        }

        fetch('<?= base_url('/public/controllers/message-handler.php') ?>?action=search_patients&q=' + encodeURIComponent(q), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(json => {
                if (json.ok && json.patients.length > 0) {
                    let html = '';
                    json.patients.forEach(p => {
                        html += `<div style="padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border);" 
                                onclick="selectPatient(${p.user_id}, '${p.first_name} ${p.last_name}')">
                                <strong>${p.first_name} ${p.last_name}</strong><br>
                                <small style="color: var(--text-secondary)">ID: ${p.identification_number || 'N/A'}</small>
                             </div>`;
                    });
                    results.innerHTML = html;
                    results.style.display = 'block';
                } else {
                    results.style.display = 'none';
                }
            });
    }

    function selectPatient(id, name) {
        document.getElementById('selected-patient-id').value = id;
        document.getElementById('selected-patient-name').innerText = name;
        document.getElementById('selected-patient-badge').style.display = 'block';
        document.getElementById('patient-search').style.display = 'none';
        document.getElementById('search-results').style.display = 'none';
    }

    function clearSelectedPatient() {
        document.getElementById('selected-patient-id').value = '';
        document.getElementById('selected-patient-badge').style.display = 'none';
        document.getElementById('patient-search').style.display = 'block';
        document.getElementById('patient-search').value = '';
        document.getElementById('patient-search').focus();
    }

    function toggleTestDropdown() {
        const options = document.getElementById('test-options');
        options.style.display = options.style.display === 'block' ? 'none' : 'block';
    }

    function updateTestSelection() {
        const checkboxes = document.querySelectorAll('#test-options input[type="checkbox"]:checked');
        const text = document.getElementById('selected-test-text');
        const selected = Array.from(checkboxes).map(cb => cb.value);
        text.textContent = selected.length > 0 ? selected.join(', ') : 'Select Test Type...';
        document.getElementById('test_type_final').value = selected.join(', ');
    }

    function addOtherTest() {
        const input = document.getElementById('other-test');
        const val = input.value.trim();
        if (!val) return;
        const wrapper = document.getElementById('test-options');
        // Check duplicate
        if (!document.querySelector(`input[value="${val}"]`)) {
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" name="test_type_arr[]" value="${val}" checked onchange="updateTestSelection()" style="margin-right:12px;"> ${val}`;
            // Insert before the input container (last child)
            wrapper.insertBefore(label, wrapper.lastElementChild);
            input.value = '';
            updateTestSelection();
        }
    }

    // Close dropdowns
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#test-multiselect')) {
            const options = document.getElementById('test-options');
            if (options) options.style.display = 'none';
        }
    });
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'lab-tests', [
    'title' => 'Lab Tests',
    'content' => $content,
]);
