<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

$pdo = db();
// Fetch Doctors
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM user WHERE role = 'doctor' ORDER BY last_name ASC");
$stmt->execute();
$doctors = $stmt->fetchAll();

// Fetch Patients
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM user WHERE role = 'patient' ORDER BY last_name ASC");
$stmt->execute();
$patients = $stmt->fetchAll();

ob_start();
?>
<div class="card no-print">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Report Generation</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Analyze clinic performance and patient demographics.</p>
        </div>
    </div>

    <!-- Report Controls -->
    <div class="card" style="background:var(--surface-light); border:1px solid var(--border); padding:20px; margin-bottom:24px;">
        <form id="reportForm" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items: flex-end;">
            <!-- Report Type (Full Width) -->
            <div class="form-row" style="grid-column: 1 / -1; margin-bottom: 20px;">
                <label class="label" style="font-size: 16px; font-weight: 600; color: var(--primary);">Select Report Type</label>
                <select name="type" id="reportType" class="input" required style="width:100%; padding: 12px; font-size: 15px;" onchange="updateFilters()">
                    <option value="appointments">Appointments (Detailed)</option>
                    <option value="billing">Revenue/Collection</option>
                    <option value="queue_efficiency">Queue Efficiency & Wait Times</option>
                    <option value="consultations">Clinical Consultations</option>
                    <option value="prescriptions">Prescription Stats</option>
                    <option value="demographics">Patient Demographics</option>
                    <option value="audit">System Audit Logs</option>
                </select>
                <p style="margin: 5px 0 0; font-size: 13px; color: var(--text-secondary);">Select a report category to reveal specific filters and options.</p>
            </div>

            <!-- Advanced Filters Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; width: 100%;">

                <!-- Date Range -->
                <div id="filter-dates" style="display:contents">
                    <div class="form-row" style="margin:0">
                        <label class="label">Date From</label>
                        <input type="date" name="date_from" class="input" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="form-row" style="margin:0">
                        <label class="label">Date To</label>
                        <input type="date" name="date_to" class="input" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Doctor Filter (Multi-select) -->
                <div class="form-row" id="filter-doctor" style="margin:0; position:relative;">
                    <label class="label">Filter by Doctor</label>
                    <div class="custom-select" onclick="toggleDropdown('doctor-dropdown')">Select Doctors <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span></div>
                    <div id="doctor-dropdown" class="dropdown-content">
                        <?php foreach ($doctors as $d): ?>
                            <label><input type="checkbox" name="doctor_id[]" value="<?= $d['user_id'] ?>"> Dr. <?= htmlspecialchars($d['last_name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Patient Filter (Multi-select) -->
                <div class="form-row" id="filter-patient" style="margin:0; position:relative;">
                    <label class="label">Filter by Patient</label>
                    <div class="custom-select" onclick="toggleDropdown('patient-dropdown')">Select Patients <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span></div>
                    <div id="patient-dropdown" class="dropdown-content">
                        <?php foreach ($patients as $p): ?>
                            <label><input type="checkbox" name="patient_id[]" value="<?= $p['user_id'] ?>"> <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Status Filter (Multi-select) -->
                <div class="form-row" id="filter-status" style="margin:0; position:relative;">
                    <label class="label">Filter by Status</label>
                    <div class="custom-select" onclick="toggleDropdown('status-dropdown')">Select Statuses <span class="material-symbols-outlined" style="font-size:16px">arrow_drop_down</span></div>
                    <div id="status-dropdown" class="dropdown-content">
                        <label><input type="checkbox" name="status[]" value="scheduled"> Scheduled</label>
                        <label><input type="checkbox" name="status[]" value="completed"> Completed</label>
                        <label><input type="checkbox" name="status[]" value="cancelled"> Cancelled</label>
                        <label><input type="checkbox" name="status[]" value="paid"> Paid</label>
                        <label><input type="checkbox" name="status[]" value="unpaid"> Unpaid</label>
                    </div>
                </div>

                <!-- Confidentiality Level -->
                <div class="form-row" style="margin:0; grid-column: 1 / -1;">
                    <label class="label">Confidentiality Level (ISO 27001)</label>
                    <div style="display:flex; gap:20px; flex-wrap:wrap;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="radio" name="confidentiality" value="Public"> Public
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="radio" name="confidentiality" value="Internal Use Only"> Internal Use Only
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="radio" name="confidentiality" value="Confidential" checked> Confidential
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="radio" name="confidentiality" value="Restricted"> Restricted
                        </label>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Report Output Container -->
<div id="reportOutput" class="report-print-container" style="display:none;">
    <!-- Professional Letterhead (Template-driven) -->
    <div class="report-header">
        <div class="header-main">
            <img src="<?= base_url('/public/assets/images/CHMC-logo.jpg') ?>" alt="CHMC Logo" class="report-logo">
            <div class="clinic-info">
                <h1>Commonwealth Hospital and Medical Center</h1>
                <p>Administrative Report</p>
                <p>Contact: +63 (2) 8930-0000 | Email: contact@commonwealthmed.com.ph</p>
            </div>
        </div>
        <div class="report-meta">
            <h2 id="reportTitle">REPORT TITLE</h2>
            <div class="meta-row">
                <span><strong>Generated By:</strong> <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (Admin)</span>
                <span><strong>Date:</strong> <?= date('F j, Y g:i A') ?></span>
            </div>
        </div>
    </div>

    <div id="reportContent" class="report-body">
        <!-- Content will be injected here -->
    </div>

    <div class="report-footer">
        <div class="signature-block">
            <div class="line"></div>
            <p>Authorized Signature</p>
        </div>
        <p class="confidential">This document contains sensitive medical information and is for authorized use only.</p>
    </div>

    <div class="no-print" style="margin-top:24px; display:flex; justify-content:center; gap:12px;">
        <button class="btn btn-secondary" onclick="window.print()">
            <span class="material-symbols-outlined">print</span> Print
        </button>
        <button class="btn btn-secondary" onclick="exportToCSV()">
            <span class="material-symbols-outlined">download</span> Export CSV
        </button>
        <button class="btn btn-secondary" onclick="exportToPDF()">
            <span class="material-symbols-outlined">picture_as_pdf</span> Export PDF
        </button>
        <button class="btn btn-outline" onclick="window.location.reload()">
            <span class="material-symbols-outlined">restart_alt</span> New Report
        </button>
    </div>
</div>

<div id="reportEmpty" class="card" style="text-align:center; padding:60px; border:2px dashed var(--border); border-radius:12px;">
    <span class="material-symbols-outlined" style="font-size:48px; color:var(--text-secondary); opacity:0.3; margin-bottom:16px;">analytics</span>
    <h3 style="margin:0; color:var(--text-secondary);">Ready to Analyze</h3>
    <p style="color:var(--text-secondary); margin-top:8px;">Select report parameters and click Generate.</p>
</div>

<style>
    @media print {
        .no-print {
            display: none !important;
        }

        body {
            background: white !important;
            padding: 0 !important;
        }

        .role-header,
        .role-sidebar,
        .loading-overlay {
            display: none !important;
        }

        .role-container {
            padding: 0 !important;
            margin: 0 !important;
        }

        .report-print-container {
            display: block !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }
    }

    .report-print-container {
        background: white;
        padding: 40px;
        border-radius: 12px;
        border: 1px solid var(--border);
        color: #333;
    }

    .report-header {
        border-bottom: 2px solid var(--primary);
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .header-main {
        display: flex;
        align-items: center;
        gap: 24px;
        margin-bottom: 20px;
    }

    .report-logo {
        height: 80px;
    }

    .clinic-info h1 {
        margin: 0;
        font-size: 22px;
        color: var(--primary);
        letter-spacing: 0.5px;
    }

    .clinic-info p {
        margin: 2px 0;
        font-size: 13px;
        color: #666;
    }

    .report-meta {
        text-align: center;
        background: #f8fbff;
        padding: 15px;
        border-radius: 8px;
    }

    .report-meta h2 {
        margin: 0 0 10px;
        font-size: 20px;
        color: #444;
        text-transform: uppercase;
    }

    .meta-row {
        display: flex;
        justify-content: center;
        gap: 40px;
        font-size: 13px;
        color: #666;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin: 24px 0;
    }

    .report-table th {
        background: #f0f4f8;
        padding: 12px;
        text-align: left;
        font-size: 13px;
        border-bottom: 2px solid #ddd;
    }

    .report-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        font-size: 14px;
    }

    .report-footer {
        margin-top: 50px;
        border-top: 1px solid #eee;
        padding-top: 40px;
    }

    .signature-block {
        width: 250px;
        text-align: center;
        margin-bottom: 30px;
    }

    .signature-block .line {
        border-bottom: 1px solid #000;
        margin-bottom: 8px;
    }

    .confidential {
        font-size: 11px;
        color: #999;
        font-style: italic;
        text-align: center;
    }
</style>

<script>
    let currentReportData = [];
    let currentReportType = '';

    function updateFilters() {
        const type = document.getElementById('reportType').value;
        const fDoctor = document.getElementById('filter-doctor');
        const fPatient = document.getElementById('filter-patient'); // New
        const fStatus = document.getElementById('filter-status');
        const fDates = document.getElementById('filter-dates');

        // Defaults: Hide all specific filters, show dates by default
        fDoctor.style.display = 'none';
        fPatient.style.display = 'none';
        fStatus.style.display = 'none';
        fDates.style.display = 'contents';

        if (type === 'appointments') {
            fDoctor.style.display = 'block';
            fStatus.style.display = 'block';
            fPatient.style.display = 'block'; // Allow filtering by patient too
        } else if (type === 'consultations') {
            fDoctor.style.display = 'block';
            fPatient.style.display = 'block';
        } else if (type === 'billing') {
            fStatus.style.display = 'block';
        } else if (type === 'demographics') {
            fDates.style.display = 'none'; // Demographics is snapshot
        } else if (type === 'queue_efficiency') {
            fDoctor.style.display = 'block';
        }
    }

    /* Custom Dropdown Logic */
    function toggleDropdown(id) {
        document.getElementById(id).classList.toggle("show");
    }

    // Close dropdowns when clicking outside
    window.onclick = function(event) {
        if (!event.target.matches('.custom-select')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    // Initialize logic
    updateFilters();

    document.getElementById('reportForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = '...';

        const output = document.getElementById('reportOutput');
        const empty = document.getElementById('reportEmpty');
        const content = document.getElementById('reportContent');
        const title = document.getElementById('reportTitle');
        const typeSelect = document.querySelector('select[name="type"]');

        empty.style.display = 'none';
        output.style.display = 'block';
        content.innerHTML = '<div style="text-align:center; padding:40px;"><p>Synthesizing Data...</p></div>';

        const params = new URLSearchParams(new FormData(this));
        params.append('action', 'generate_report');

        try {
            const res = await fetch('<?= base_url('/public/controllers/report-handler.php?') ?>' + params.toString());
            const json = await res.json();

            if (json.ok) {
                title.textContent = json.title;
                currentReportData = json.data;
                currentReportType = json.type;
                renderReport(json.type, json.data, content);
            } else {
                content.innerHTML = `<div class="alert alert-danger">${json.message}</div>`;
            }
        } catch (err) {
            content.innerHTML = `<div class="alert alert-danger">Error fetching data.</div>`;
            console.error(err);
        } finally {
            btn.disabled = false;
            btn.textContent = origText;
        }
    });

    function renderReport(type, data, container) {
        if (!data || data.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:40px;"><p>No records found for specified criteria.</p></div>';
            return;
        }

        let html = '<table class="report-table">';

        if (type === 'appointments') {
            html += `<thead>
                                <tr>
                                    <th>Date / Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                html += `<tr>
            <td>${row.appointment_date}</td>
            <td><strong>${row.p_first} ${row.p_last}</strong></td>
            <td>Dr. ${row.doctor_name}</td>
            <td>${row.appointment_purpose}</td>
            <td>${row.appointment_status}</td>
        </tr>`;
            });
        } else if (type === 'queue_efficiency') {
            html += `<thead>
                                <tr>
                                    <th>Check-in Time</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Wait Time</th>
                                    <th>Consult Duration</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                const waitClass = row.wait_time_mins > 30 ? 'color:red' : 'color:green';
                html += `<tr>
            <td>${row.checked_in_at}</td>
            <td><strong>${row.p_first} ${row.p_last}</strong></td>
            <td>Dr. ${row.doctor_name}</td>
            <td style="${waitClass}">${row.wait_time_mins} mins</td>
            <td>${row.consult_duration_mins} mins</td>
        </tr>`;
            });
        } else if (type === 'demographics') {
            html += `<thead>
                                <tr>
                                    <th>Patient Age Group</th>
                                    <th>Total Registered</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                html += `<tr>
            <td>${row.age_group}</td>
            <td>${row.count} Patients</td>
        </tr>`;
            });
        } else if (type === 'billing') {
            html += `<thead>
                                <tr>
                                    <th>Payment Status</th>
                                    <th>Transaction Count</th>
                                    <th>Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                html += `<tr>
            <td>${row.payment_status}</td>
            <td>${row.count}</td>
            <td>₱${parseFloat(row.total).toLocaleString()}</td>
        </tr>`;
            });
        } else if (type === 'consultations') {
            html += `<thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Patient</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                html += `<tr>
            <td>${row.created_at}</td>
            <td>Dr. ${row.doctor_name}</td>
            <td>${row.p_first} ${row.p_last}</td>
            <td>${row.consultation_type}</td>
        </tr>`;
            });
        } else if (type === 'prescriptions') {
            html += `<thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Frequency</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                html += `<tr>
            <td>${row.medicine_name}</td>
            <td>${row.times_prescribed} times</td>
        </tr>`;
            });
        } else if (type === 'audit') {
            html += `<thead>
                                <tr>
                                    <th>Date / Time</th>
                                    <th>User</th>
                                    <th>Operation</th>
                                    <th>Resource</th>
                                </tr>
                            </thead>
                            <tbody>`;
            data.forEach(row => {
                const userDisplay = row.first_name ? `${row.first_name} ${row.last_name} (${row.role})` : 'System';
                html += `<tr>
            <td>${row.logged_at}</td>
            <td>${userDisplay}</td>
            <td>${row.operation}</td>
            <td>${row.table_name} #${row.record_id}</td>
        </tr>`;
            });
        }

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function exportToCSV() {
        if (!currentReportData || currentReportData.length === 0) {
            alert('No data to export');
            return;
        }

        // Compute headers dynamically from the first row keys
        const headers = Object.keys(currentReportData[0]);
        let csvContent = "data:text/csv;charset=utf-8," + headers.join(",") + "\n";

        currentReportData.forEach(row => {
            const values = headers.map(header => {
                let cell = row[header] === null ? '' : row[header].toString();
                // Escape quotes
                cell = cell.replace(/"/g, '""');
                // Wrap in quotes if contains comma, quote or newline
                if (cell.search(/("|,|\n)/g) >= 0) cell = `"${cell}"`;
                return cell;
            });
            csvContent += values.join(",") + "\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `MatriFlow_Report_${currentReportType}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function exportToPDF() {
        // Collect current filter values
        const form = document.getElementById('reportForm');
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);

        // Append action
        params.append('action', 'export_pdf');

        // Open in new tab
        const url = '<?= base_url('/public/controllers/report-handler.php?') ?>' + params.toString();
        window.open(url, '_blank');
    }
</script>

<style>
    /* Multi-select Dropdown CSS */
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
        user-select: none;
    }

    .custom-select:hover {
        background-color: var(--surface-hover);
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--surface);
        min-width: 100%;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
        z-index: 1;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 5px;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-content label {
        color: var(--text-primary);
        padding: 10px 16px;
        text-decoration: none;
        display: block;
        cursor: pointer;
        font-size: 13px;
        border-bottom: 1px solid var(--border-light);
    }

    .dropdown-content label:hover {
        background-color: var(--bg-light);
    }

    .dropdown-content input {
        margin-right: 10px;
    }
</style>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'report-generation', [
    'title' => 'Report Generation',
    'content' => $content,
]);
