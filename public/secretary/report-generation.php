<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

// Fetch doctors for filters
$doctors = db()->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'doctor' AND deleted_at IS NULL ORDER BY last_name ASC")->fetchAll();

ob_start();
?>
<div class="card no-print">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Clinic Operations Report</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">
                Generate comprehensive operational reports including appointment statistics, financial summaries, and queue efficiency metrics.
                Use the filters below to customize the data scope and export secure PDF or CSV files for administrative use.
            </p>
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
                    <label><input type="checkbox" name="type[]" value="appointments" onchange="updateFiltersDisplay()"> Appointments (Detailed)</label>
                    <label><input type="checkbox" name="type[]" value="billing" onchange="updateFiltersDisplay()"> Revenue/Collection</label>
                    <label><input type="checkbox" name="type[]" value="receivables" onchange="updateFiltersDisplay()"> Accounts Receivable</label>
                    <label><input type="checkbox" name="type[]" value="queue_efficiency" onchange="updateFiltersDisplay()"> Queue Efficiency</label>
                    <label><input type="checkbox" name="type[]" value="consultations" onchange="updateFiltersDisplay()"> Consultations</label>
                    <label><input type="checkbox" name="type[]" value="prescriptions" onchange="updateFiltersDisplay()"> Prescriptions</label>
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
                <label>Filter by Doctor</label>
                <div class="custom-select" id="doctor-select-display" onclick="toggleDropdown('doctor-dropdown')">
                    <span>All Doctors</span>
                    <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span>
                </div>
                <div id="doctor-dropdown" class="dropdown-content">
                    <?php foreach ($doctors as $d): ?>
                        <label><input type="checkbox" name="doctor_id[]" value="<?= $d['user_id'] ?>" onchange="updateFiltersDisplay()"> Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></label>
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
                    <span>Multiple Statuses</span>
                    <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span>
                </div>
                <div id="status-dropdown" class="dropdown-content">
                    <label><input type="checkbox" name="status[]" value="scheduled" onchange="updateFiltersDisplay()"> Scheduled</label>
                    <label><input type="checkbox" name="status[]" value="completed" onchange="updateFiltersDisplay()"> Completed</label>
                    <label><input type="checkbox" name="status[]" value="cancelled" onchange="updateFiltersDisplay()"> Cancelled</label>
                    <label><input type="checkbox" name="status[]" value="no_show" onchange="updateFiltersDisplay()"> No-Show</label>
                    <label><input type="checkbox" name="status[]" value="paid" onchange="updateFiltersDisplay()"> Paid</label>
                    <label><input type="checkbox" name="status[]" value="unpaid" onchange="updateFiltersDisplay()"> Unpaid</label>
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
            <div class="form-group" style="flex: 2;"></div>
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
        <button class="btn btn-outline btn-sm" onclick="backToFilters()">
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
    <span class="material-symbols-outlined">insights</span>
    <h3>No Report Generated</h3>
    <p>Use the filters above to compile clinic data into actionable reports.</p>
</div>

<style>
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
        border-radius: 8px;
        padding: 10px 14px;
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
        updateSingleDropdown('doctor-dropdown', 'doctor-select-display', 'Select Doctor');
        updateSingleDropdown('patient-dropdown', 'patient-select-display', 'All Patients');
        updateSingleDropdown('status-dropdown', 'status-select-display', 'Multiple Statuses');
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

        // Presentation Logic: Hide form to focus on results
        form.closest('.card').style.display = 'none';
        dashboard.style.display = 'block';
        pdfIframe.style.display = 'none';
        pdfOverlay.style.display = 'flex';

        const formData = new FormData(this);
        const params = new URLSearchParams(formData);
        params.append('action', 'generate_report');

        document.getElementById('reportEmpty').style.display = 'none';

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
                showToast(json.message, 'error');
                form.closest('.card').style.display = 'block';
                dashboard.style.display = 'none';
            }
        } catch (err) {
            showToast('Network error occurred.', 'error');
            form.closest('.card').style.display = 'block';
            dashboard.style.display = 'none';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined">analytics</span> Generate Report';
        }
    });

    function backToFilters() {
        document.getElementById('reportOutput').style.display = 'none';
        document.querySelector('.card.no-print').style.display = 'block';
        window.scrollTo(0, 0);
    }

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
        link.download = `MatriFlow_Operational_Report_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'report-generation', [
    'title' => 'Report Generation',
    'content' => $content,
]);
