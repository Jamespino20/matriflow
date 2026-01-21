<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

// Fetch doctors (relevant if they want to collaborate or see staff)
$doctors = db()->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'doctor' AND deleted_at IS NULL ORDER BY last_name ASC")->fetchAll();

ob_start();
?>
<div class="card no-print">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Clinical Reports</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Generate summaries of your consultations and patient statistics.</p>
        </div>
    </div>

    <form id="reportForm" class="report-filter-system">
        <!-- Row 1: Categories & Dates -->
        <div class="filter-row">
            <div class="form-group" style="flex: 2; position:relative;">
                <label>Report Categories</label>
                <div class="custom-select" id="type-select-display" onclick="toggleDropdown('type-dropdown')">
                    <span>Select Categories</span>
                    <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span>
                </div>
                <div id="type-dropdown" class="dropdown-content">
                    <label><input type="checkbox" name="type[]" value="consultations" onchange="updateFiltersDisplay()" checked> My Consultations</label>
                    <label><input type="checkbox" name="type[]" value="appointments" onchange="updateFiltersDisplay()"> My Appointments</label>
                    <label><input type="checkbox" name="type[]" value="queue_efficiency" onchange="updateFiltersDisplay()"> Queue Performance</label>
                    <label><input type="checkbox" name="type[]" value="prescriptions" onchange="updateFiltersDisplay()"> My Prescriptions</label>
                    <label><input type="checkbox" name="type[]" value="demographics" onchange="updateFiltersDisplay()"> Patient Demographics</label>
                    <label><input type="checkbox" name="type[]" value="patient_records" onchange="updateFiltersDisplay()"> Clinical Vitals Summary</label>
                </div>
            </div>
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="date_from" id="date_from" value="<?= date('Y-m-01') ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="date_to" id="date_to" value="<?= date('Y-m-d') ?>" class="form-control">
            </div>
        </div>

        <!-- Row 2: Filter By's -->
        <div class="filter-row">
            <div class="form-group" style="position:relative;">
                <label>Filter by Staff (Optional)</label>
                <div class="custom-select" id="doctor-select-display" onclick="toggleDropdown('doctor-dropdown')">
                    <span>Dr. <?= htmlspecialchars($u['last_name']) ?> (Me)</span>
                    <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span>
                </div>
                <div id="doctor-dropdown" class="dropdown-content">
                    <label><input type="checkbox" name="doctor_id[]" value="<?= $u['user_id'] ?>" onchange="updateFiltersDisplay()" checked> Dr. <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (Me)</label>
                    <?php foreach ($doctors as $d): ?>
                        <?php if ($d['user_id'] != $u['user_id']): ?>
                            <label><input type="checkbox" name="doctor_id[]" value="<?= $d['user_id'] ?>" onchange="updateFiltersDisplay()"> Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group" style="position:relative;">
                <label>Filter by Patient</label>
                <div class="custom-select" id="patient-select-display" onclick="toggleDropdown('patient-dropdown')">
                    <span>All Patients</span>
                    <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span>
                </div>
                <div id="patient-dropdown" class="dropdown-content">
                    <div style="padding: 10px;"><input type="text" class="multi-select-search" placeholder="Search patients..." onkeyup="searchDropdownPatients(this.value)"></div>
                    <div id="patient-list-container">
                        <p style="padding:10px; font-size:12px; color:var(--text-muted)">Search to find patients...</p>
                    </div>
                </div>
            </div>
            <div class="form-group" style="position:relative;">
                <label>Filter by Status</label>
                <div class="custom-select" id="status-select-display" onclick="toggleDropdown('status-dropdown')">
                    <span>Completed</span>
                    <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span>
                </div>
                <div id="status-dropdown" class="dropdown-content">
                    <label><input type="checkbox" name="status[]" value="scheduled" onchange="updateFiltersDisplay()"> Scheduled</label>
                    <label><input type="checkbox" name="status[]" value="completed" onchange="updateFiltersDisplay()" checked> Completed</label>
                    <label><input type="checkbox" name="status[]" value="cancelled" onchange="updateFiltersDisplay()"> Cancelled</label>
                    <label><input type="checkbox" name="status[]" value="no_show" onchange="updateFiltersDisplay()"> No-Show</label>
                </div>
            </div>
        </div>

        <!-- Row 3: Presets -->
        <div class="filter-row">
            <div class="form-group" style="flex: 1;">
                <label>Confidentiality Level</label>
                <select name="confidentiality">
                    <option value="all" selected>All Levels</option>
                    <option value="public">Public / General</option>
                    <option value="restricted">Restricted Staff Only</option>
                    <option value="confidential">Confidential</option>
                </select>
            </div>
            <div class="form-group" style="flex: 2;">
                <label>Quick Date Presets</label>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="setDateRange('this_month')">This Month</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="setDateRange('this_year')">This Year</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="setDateRange('today')">Today</button>
                </div>
            </div>
        </div>

        <!-- Row 4: Actions -->
        <div class="filter-row" style="border-top: 1px solid var(--border-light); padding-top: 15px; margin-top: 5px;">
            <div style="display:flex; gap:12px; width: 100%; justify-content: flex-end;">
                <button type="reset" class="btn btn-outline" onclick="window.location.reload()">Clear All</button>
                <button type="submit" class="btn btn-primary" style="min-width: 180px;">
                    <span class="material-symbols-outlined">analytics</span> Generate Report
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Report Output Container -->
<div id="reportOutput" class="report-dashboard" style="display:none;">
    <div style="display:flex; justify-content:flex-end; gap:10px; margin-bottom:15px;">
        <button class="btn btn-outline btn-sm" onclick="document.getElementById('reportOutput').style.display='none'; document.getElementById('reportForm').style.display='block'; window.scrollTo(0,0);">
            <span class="material-symbols-outlined">refresh</span> Generate New Report
        </button>
        <button class="btn btn-secondary btn-sm" onclick="exportToCSV()">
            <span class="material-symbols-outlined">csv</span> Export CSV
        </button>
        <button class="btn btn-secondary btn-sm" onclick="exportToPDF()">
            <span class="material-symbols-outlined">download</span> Export PDF
        </button>
    </div>

    <div class="report-section card" style="height: 80vh;">
        <div class="section-header">
            <h3 id="reportTitle">Official Report Preview</h3>
            <span class="badge badge-primary">Confidential Analytics</span>
        </div>
        <div class="pdf-viewport" style="height: calc(100% - 60px);">
            <div id="pdf-loading-overlay">
                <span class="material-symbols-outlined spinning" style="font-size: 48px;">sync</span>
                <p>Formatting clinical narratives and rendering document...</p>
            </div>
            <iframe id="pdf-iframe" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    </div>
</div>

<!-- Hidden result area for CSV processing -->
<div id="reportContent" style="display:none;"></div>

<div id="reportEmpty" class="card empty-state">
    <span class="material-symbols-outlined">medical_services</span>
    <h3>Clinical Insights</h3>
    <p>Use the filters above to compile your clinical data into actionable reports.</p>
</div>

<style>
    .report-filter-system {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .filter-row {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .report-dashboard {
        margin-top: 24px;
    }

    .report-layout-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        align-items: start;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-light);
    }

    .section-header h3 {
        margin: 0;
        font-size: 16px;
        color: var(--primary);
    }

    .report-scroll-container {
        max-height: 700px;
        overflow-y: auto;
    }

    .pdf-viewport {
        height: 700px;
        background: #f8fafc;
        border-radius: 8px;
        position: relative;
        border: 1px solid var(--border);
    }

    #pdf-iframe {
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 8px;
        display: none;
    }

    #pdf-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        gap: 12px;
    }

    .spinning {
        animation: rotate 2s linear infinite;
    }

    @keyframes rotate {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .empty-state {
        text-align: center;
        padding: 80px 40px;
        border: 2px dashed var(--border);
        color: var(--text-secondary);
    }

    .empty-state .material-symbols-outlined {
        font-size: 64px;
        opacity: 0.2;
        margin-bottom: 16px;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
    }

    .report-table th {
        background: var(--bg-light);
        padding: 12px;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .report-filter-system .filter-row {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        align-items: flex-end;
    }

    .report-filter-system .form-group {
        flex: 1;
        min-width: 0;
    }

    .report-filter-system .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 13px;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .custom-select {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-height: 42px;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s;
    }

    .custom-select:hover {
        border-color: var(--primary);
    }

    .custom-select span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 90%;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--surface);
        min-width: 100%;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        z-index: 1000;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 5px;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-content label {
        display: flex !important;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        cursor: pointer;
        font-size: 13px;
        border-bottom: 1px solid var(--border-light);
        text-transform: none !important;
        margin: 0 !important;
        color: var(--text-main) !important;
        letter-spacing: 0 !important;
    }

    .dropdown-content label:hover {
        background: var(--bg-light);
    }

    .multi-select-search {
        width: 100%;
        background: var(--bg-light);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 13px;
    }

    .pdf-viewport {
        background: #f1f5f9;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
    }

    .report-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border-light);
        font-size: 13px;
    }

    .custom-select {
        background-color: var(--surface);
        border: 1px solid var(--border);
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
        min-height: 40px;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--surface);
        min-width: 100%;
        max-height: 250px;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
        z-index: 100;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 5px;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-content label {
        display: block;
        padding: 10px 16px;
        cursor: pointer;
        font-size: 13px;
        border-bottom: 1px solid var(--border-light);
        text-transform: none;
    }

    .dropdown-content label:hover {
        background: var(--bg-light);
    }

    .multi-select-search {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px;
    }
</style>

<script>
    function setDateRange(range) {
        const from = document.getElementById('date_from');
        const to = document.getElementById('date_to');
        const today = new Date();
        let start, end;

        if (range === 'this_month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        } else if (range === 'this_year') {
            start = new Date(today.getFullYear(), 0, 1);
            end = new Date(today.getFullYear(), 11, 31);
        } else if (range === 'today') {
            start = today;
            end = today;
        }

        const formatDate = (d) => d.toISOString().split('T')[0];
        if (start && end) {
            from.value = formatDate(start);
            to.value = formatDate(end);
        }
    }

    function toggleDropdown(id) {
        document.querySelectorAll(".dropdown-content").forEach(d => {
            if (d.id !== id) d.classList.remove('show');
        });
        document.getElementById(id).classList.toggle("show");
    }

    async function searchDropdownPatients(q) {
        const container = document.getElementById('patient-list-container');
        if (q.length < 2) return;

        try {
            const res = await fetch('<?= base_url('/public/controllers/message-handler.php') ?>?action=search_users&q=' + encodeURIComponent(q));
            const json = await res.json();
            if (json.ok) {
                let html = '';
                json.users.filter(u => u.role === 'patient').forEach(p => {
                    html += `<label><input type="checkbox" name="patient_id[]" value="${p.user_id}" onchange="updateFiltersDisplay()"> ${p.first_name} ${p.last_name} </label>`;
                });
                container.innerHTML = html || '<p style="padding:10px; font-size:12px;">No results found</p>';
            }
        } catch (e) {}
    }

    function updateFiltersDisplay() {
        updateSingleDropdown('type-dropdown', 'type-select-display', 'Select Categories');
        updateSingleDropdown('doctor-dropdown', 'doctor-select-display', 'Select Staff');
        updateSingleDropdown('patient-dropdown', 'patient-select-display', 'All Patients');
        updateSingleDropdown('status-dropdown', 'status-select-display', 'Multiple Statuses');
    }

    function updateSingleDropdown(dropdownId, displayId, defaultText) {
        const checkboxes = document.querySelectorAll(`#${dropdownId} input[type="checkbox"]:checked`);
        const display = document.getElementById(displayId).querySelector('span');
        if (checkboxes.length === 0) {
            display.textContent = defaultText;
        } else if (checkboxes.length === 1) {
            display.textContent = checkboxes[0].parentNode.textContent.trim();
        } else {
            display.textContent = checkboxes.length + " Selected";
        }
    }

    window.onclick = function(event) {
        if (!event.target.closest('.form-group')) {
            document.querySelectorAll(".dropdown-content").forEach(d => d.classList.remove('show'));
        }
    }

    document.getElementById('reportForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined spinning">sync</span> Processing...';

        const dashboard = document.getElementById('reportOutput');
        const form = document.getElementById('reportForm');
        const pdfIframe = document.getElementById('pdf-iframe');
        const pdfOverlay = document.getElementById('pdf-loading-overlay');

        form.style.display = 'none';
        dashboard.style.display = 'block';
        pdfIframe.style.display = 'none';
        pdfOverlay.style.display = 'flex';

        const formData = new FormData(this);
        const params = new URLSearchParams(formData);
        params.append('action', 'generate_report');

        try {
            const res = await fetch('<?= base_url('/public/controllers/report-handler.php?') ?>' + params.toString());
            const json = await res.json();

            if (json.ok) {
                window.currentReportSections = json.sections;

                const pdfParams = new URLSearchParams(formData);
                pdfParams.append('action', 'export_pdf');
                pdfIframe.src = '<?= base_url('/public/controllers/report-handler.php?') ?>' + pdfParams.toString();
                pdfIframe.onload = () => {
                    pdfOverlay.style.display = 'none';
                    pdfIframe.style.display = 'block';
                };
            } else {
                alert(json.message);
                form.style.display = 'block';
                dashboard.style.display = 'none';
            }
        } catch (err) {
            alert('Network error occurred.');
            form.style.display = 'block';
            dashboard.style.display = 'none';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined">analytics</span> Generate Report';
        }
    });

    function exportToPDF() {
        const params = new URLSearchParams(new FormData(document.getElementById('reportForm')));
        params.append('action', 'export_pdf');
        window.open('<?= base_url('/public/controllers/report-handler.php?') ?>' + params.toString(), '_blank');
    }

    function exportToCSV() {
        const sections = window.currentReportSections;
        if (!sections || Object.keys(sections).length === 0) return alert('No data to export');

        let csv = '';
        for (const [type, data] of Object.entries(sections)) {
            if (data.length === 0) continue;
            csv += `--- ${type.toUpperCase()} ---\n`;
            const headers = Object.keys(data[0]);
            csv += headers.join(",") + "\n";
            data.forEach(row => {
                csv += headers.map(h => {
                    let cell = row[h] === null ? '' : row[h].toString().replace(/"/g, '""');
                    return /[, "\n]/.test(cell) ? `"${cell}"` : cell;
                }).join(",") + "\n";
            });
            csv += "\n";
        }
        const blob = new Blob([csv], {
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `MatriFlow_Clinical_Report_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'report-generation', [
    'title' => 'Clinical Reports',
    'content' => $content,
]);
