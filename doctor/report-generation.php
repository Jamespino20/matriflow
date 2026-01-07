<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

ob_start();
?>
<div class="card no-print">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Clinical Reports</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Generate summaries of your consultations and patient statistics.</p>
        </div>
    </div>

    <div class="card" style="background:var(--surface-light); border:1px solid var(--border); padding:20px; margin-bottom:24px;">
        <form id="reportForm" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items: flex-end;">
            <div class="form-row" style="margin:0">
                <label class="label">Report Type</label>
                <select name="type" id="reportType" class="input" required style="width:100%" onchange="updateFilters()">
                    <option value="consultations">My Consultations</option>
                    <option value="appointments">My Appointment History</option>
                    <option value="queue_efficiency">My Queue Wait Times</option>
                    <option value="prescriptions">Top Prescribed Medicines</option>
                    <option value="demographics">Patient Demographics (Global)</option>
                </select>
            </div>

            <div class="form-row" style="margin:0; flex-grow:2;">
                <label class="label">Search</label>
                <input type="text" name="q" class="input" placeholder="Patient Name..." style="width:100%">
            </div>

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

            <div class="form-row" id="filter-status" style="margin:0">
                <label class="label">Filter by Status</label>
                <select name="status" class="input" style="width:100%">
                    <option value="">All Statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<div id="reportOutput" class="report-print-container" style="display:none;">
    <div class="report-header">
        <div class="header-main">
            <img src="<?= base_url('/public/assets/images/CHMC-logo.jpg') ?>" alt="CHMC Logo" class="report-logo">
            <div class="clinic-info">
                <h1>Commonwealth Hospital and Medical Center</h1>
                <p>Doctor Professional Report</p>
                <p>Contact: +63 (2) 8930-0000 | Email: contact@commonwealthmed.com.ph</p>
            </div>
        </div>
        <div class="report-meta">
            <h2 id="reportTitle">REPORT TITLE</h2>
            <div class="meta-row">
                <span><strong>Doctor:</strong> Dr. <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></span>
                <span><strong>Date:</strong> <?= date('F j, Y g:i A') ?></span>
            </div>
        </div>
    </div>

    <div id="reportContent" class="report-body"></div>

    <div class="report-footer">
        <div class="signature-block">
            <div class="line"></div>
            <p>Doctor's Signature</p>
        </div>
        <p class="confidential">Confidential Clinical Report - Not for Distribution.</p>
    </div>

    <div class="no-print" style="margin-top:24px; display:flex; justify-content:center; gap:12px;">
        <button class="btn btn-secondary" onclick="window.print()">
            <span class="material-symbols-outlined">print</span> Print
        </button>
        <button class="btn btn-secondary" onclick="exportToCSV()">
            <span class="material-symbols-outlined">download</span> Export CSV
        </button>
        <button class="btn btn-outline" onclick="window.location.reload()">
            <span class="material-symbols-outlined">restart_alt</span> New Report
        </button>
    </div>
</div>

<div id="reportEmpty" class="card" style="text-align:center; padding:60px; border:2px dashed var(--border); border-radius:12px;">
    <span class="material-symbols-outlined" style="font-size:48px; color:var(--text-secondary); opacity:0.3; margin-bottom:16px;">medical_services</span>
    <h3 style="margin:0; color:var(--text-secondary);">Clinical Insights</h3>
    <p style="color:var(--text-secondary); margin-top:8px;">Generate your performance reports here.</p>
</div>

<style>
    /* ... existing print styles ... */
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
        text-align: center;
    }
</style>

<script>
    let currentReportData = [];
    let currentReportType = '';

    function updateFilters() {
        const type = document.getElementById('reportType').value;
        const fStatus = document.getElementById('filter-status');
        const fDates = document.getElementById('filter-dates');

        fStatus.style.display = 'none';
        fDates.style.display = 'contents';

        if (type === 'appointments') {
            fStatus.style.display = 'block';
        } else if (type === 'demographics') {
            fDates.style.display = 'none';
        }
    }
    updateFilters();

    document.getElementById('reportForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = '...';

        const output = document.getElementById('reportOutput');
        const empty = document.getElementById('reportEmpty');
        const content = document.getElementById('reportContent');
        const title = document.getElementById('reportTitle');

        empty.style.display = 'none';
        output.style.display = 'block';
        content.innerHTML = '<div style="text-align:center; padding:40px;"><p>Fetching clinical data...</p></div>';

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
        } finally {
            btn.disabled = false;
            btn.textContent = 'Generate Report';
        }
    });

    function renderReport(type, data, container) {
        if (!data || data.length === 0) {
            container.innerHTML = '<div style="text-align:center; padding:40px;"><p>No records found.</p></div>';
            return;
        }
        let html = '<table class="report-table">';
        if (type === 'appointments') {
            html += '<thead><tr><th>Date</th><th>Patient</th><th>Purpose</th><th>Status</th></tr></thead><tbody>';
            data.forEach(row => html += `<tr>
                <td>${row.appointment_date}</td>
                <td><strong>${row.p_first} ${row.p_last}</strong></td>
                <td>${row.appointment_purpose}</td>
                <td>${row.appointment_status}</td>
            </tr>`);
        } else if (type === 'queue_efficiency') {
            html += '<thead><tr><th>Check-in</th><th>Patient</th><th>Wait Time</th><th>Consult Duration</th></tr></thead><tbody>';
            data.forEach(row => {
                html += `<tr>
                    <td>${row.checked_in_at}</td>
                    <td><strong>${row.p_first} ${row.p_last}</strong></td>
                    <td>${row.wait_time_mins} mins</td>
                    <td>${row.consult_duration_mins} mins</td>
                </tr>`;
            });
        } else if (type === 'consultations') {
            html += '<thead><tr><th>Date</th><th>Patient</th><th>Type</th></tr></thead><tbody>';
            data.forEach(row => html += `<tr>
                <td>${row.created_at}</td>
                <td>${row.p_first} ${row.p_last}</td>
                <td>${row.consultation_type}</td>
            </tr>`);
        } else if (type === 'prescriptions') {
            html += '<thead><tr><th>Medicine Name</th><th>Frequency</th></tr></thead><tbody>';
            data.forEach(row => html += `<tr><td>${row.medicine_name}</td><td>${row.times_prescribed} times</td></tr>`);
        } else if (type === 'demographics') {
            html += '<thead><tr><th>Age Group</th><th>Patient Count</th></tr></thead><tbody>';
            data.forEach(row => html += `<tr><td>${row.age_group}</td><td>${row.count}</td></tr>`);
        }
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function exportToCSV() {
        if (!currentReportData || currentReportData.length === 0) {
            alert('No data to export');
            return;
        }
        const headers = Object.keys(currentReportData[0]);
        let csvContent = "data:text/csv;charset=utf-8," + headers.join(",") + "\n";
        currentReportData.forEach(row => {
            const values = headers.map(header => {
                let cell = row[header] === null ? '' : row[header].toString();
                cell = cell.replace(/"/g, '""');
                if (cell.search(/("|,|\n)/g) >= 0) cell = `"${cell}"`;
                return cell;
            });
            csvContent += values.join(",") + "\n";
        });
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `DrReport_${currentReportType}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'report-generation', [
    'title' => 'Clinical Reports',
    'content' => $content,
]);
