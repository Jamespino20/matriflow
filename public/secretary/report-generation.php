<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

ob_start();
?>
<div class="card no-print">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px;">
        <div>
            <h2 style="margin:0">Clinic Operations Report</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Generate daily schedules and payment summaries.</p>
        </div>
    </div>

    <div class="card" style="background:var(--surface-light); border:1px solid var(--border); padding:20px; margin-bottom:24px;">
        <form id="reportForm" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; align-items: flex-end;">
            <div class="form-row" style="margin:0">
                <label class="label">Report Type</label>
                <select name="type" id="reportType" class="input" required style="width:100%" onchange="updateFilters()">
                    <option value="appointments">Appointment Schedule (Detailed)</option>
                    <option value="queue_efficiency">Queue Efficiency</option>
                    <option value="billing">Daily Collection Summary</option>
                    <option value="demographics">Patient Demographics</option>
                </select>
            </div>

            <div class="form-row" style="margin:0; flex-grow:2;">
                <label class="label">Search (Optional)</label>
                <input type="text" name="q" class="input" placeholder="Patient Name..." style="width:100%">
            </div>

            <div id="filter-dates" style="display:contents">
                <div class="form-row" style="margin:0">
                    <label class="label">Date From</label>
                    <input type="date" name="date_from" class="input" value="<?= date('Y-m-d') ?>">
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
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
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
                <p>Operations & Front Desk Report</p>
                <p>Contact: +63 (2) 8930-0000 | Email: contact@commonwealthmed.com.ph</p>
            </div>
        </div>
        <div class="report-meta">
            <h2 id="reportTitle">REPORT TITLE</h2>
            <div class="meta-row">
                <span><strong>Issued By:</strong> <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?> (Secretary)</span>
                <span><strong>Date:</strong> <?= date('F j, Y g:i A') ?></span>
            </div>
        </div>
    </div>

    <div id="reportContent" class="report-body"></div>

    <div class="report-footer">
        <div class="signature-block">
            <div class="line"></div>
            <p>Receptionist/Secretary Signature</p>
        </div>
        <p class="confidential">Standard Operational Document - For Internal Use Only.</p>
    </div>

    <div class="no-print" style="margin-top:24px; display:flex; justify-content:center; gap:12px;">
        <button class="btn btn-secondary" onclick="window.print()">
            <span class="material-symbols-outlined">print</span> Print
        </button>
        <button class="btn btn-secondary" onclick="exportToCSV()">
            <span class="material-symbols-outlined">download</span> Export CSV
        </button>
        <button class="btn btn-outline" onclick="window.location.reload()">
            <span class="material-symbols-outlined">restart_alt</span> New
        </button>
    </div>
</div>

<div id="reportEmpty" class="card" style="text-align:center; padding:60px; border:2px dashed var(--border); border-radius:12px;">
    <span class="material-symbols-outlined" style="font-size:48px; color:var(--text-secondary); opacity:0.3; margin-bottom:16px;">receipt_long</span>
    <h3 style="margin:0; color:var(--text-secondary);">Operations Summary</h3>
    <p style="color:var(--text-secondary); margin-top:8px;">Generate operational reports here.</p>
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

        if (type === 'appointments' || type === 'billing' || type === 'queue_efficiency') {
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
        const content = document.getElementById('reportContent');
        const title = document.getElementById('reportTitle');

        document.getElementById('reportEmpty').style.display = 'none';
        output.style.display = 'block';
        content.innerHTML = '<p style="text-align:center">Processing...</p>';

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
            content.innerHTML = `<div class="alert alert-danger">Network error.</div>`;
        } finally {
            btn.disabled = false;
            btn.textContent = 'Generate Report';
        }
    });

    function renderReport(type, data, container) {
        if (!data || data.length === 0) {
            container.innerHTML = '<p style="text-align:center">No records found for specified criteria.</p>';
            return;
        }
        let html = '<table class="report-table">';
        if (type === 'appointments') {
            html += '<thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Status</th></tr></thead><tbody>';
            data.forEach(row => html += `<tr>
                <td>${row.appointment_date}</td>
                <td><strong>${row.p_first} ${row.p_last}</strong></td>
                <td>Dr. ${row.doctor_name}</td>
                <td>${row.appointment_status}</td>
            </tr>`);
        } else if (type === 'queue_efficiency') {
            html += '<thead><tr><th>Check-in</th><th>Patient</th><th>Doctor</th><th>Wait Time</th><th>Consult Duration</th></tr></thead><tbody>';
            data.forEach(row => {
                html += `<tr>
                    <td>${row.checked_in_at}</td>
                    <td><strong>${row.p_first} ${row.p_last}</strong></td>
                    <td>Dr. ${row.doctor_name}</td>
                    <td>${row.wait_time_mins} mins</td>
                    <td>${row.consult_duration_mins} mins</td>
                </tr>`;
            });
        } else if (type === 'billing') {
            html += '<thead><tr><th>Status</th><th>Count</th><th>Total Amount</th></tr></thead><tbody>';
            data.forEach(row => html += `<tr><td>${row.payment_status}</td><td>${row.count}</td><td>₱${parseFloat(row.total).toLocaleString()}</td></tr>`);
        } else if (type === 'demographics') {
            html += '<thead><tr><th>Patient Group</th><th>Total Registered</th></tr></thead><tbody>';
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
        link.setAttribute("download", `SecReport_${currentReportType}_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'report-generation', [
    'title' => 'Report Generation',
    'content' => $content,
]);
