<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!$u || !in_array($u['role'], ['admin', 'doctor', 'secretary'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized.']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'generate_report' || $action === 'export_pdf') {
    // Support both single string and array for type
    $requestedTypes = $_GET['type'] ?? [];
    if (!is_array($requestedTypes)) {
        $requestedTypes = [$requestedTypes];
    }
    $requestedTypes = array_filter($requestedTypes);

    if (empty($requestedTypes)) {
        if ($action === 'generate_report') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Please select at least one report category.']);
            exit;
        } else {
            die("Please select at least one report category.");
        }
    }

    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');

    // Parse filters as arrays
    $doctorIds = isset($_GET['doctor_id']) ? (is_array($_GET['doctor_id']) ? $_GET['doctor_id'] : [$_GET['doctor_id']]) : [];
    $statuses = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [];
    $patientIds = isset($_GET['patient_id']) ? (is_array($_GET['patient_id']) ? $_GET['patient_id'] : [$_GET['patient_id']]) : [];

    $patientIds = array_filter($patientIds);

    $exportId = "CHMC-REP-" . date('YmdHis') . "-" . strtoupper(substr(uniqid(), -4));

    $allReportData = [];
    $allStats = [];
    $titles = [];

    // Helper to get names for narrative
    $getNames = function ($ids, $table) {
        if (empty($ids)) return "All";
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE user_id IN ($placeholders)");
        $stmt->execute($ids);
        return implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN));
    };

    try {
        foreach ($requestedTypes as $type) {
            $reportData = [];
            $title = "";

            if ($type === 'appointments') {
                $title = "Appointments";
                $sql = "SELECT a.appointment_date as 'Date', 
                               CONCAT(u_pat.first_name, ' ', u_pat.last_name) as 'Patient',
                               u_doc.last_name as 'Doctor',
                               a.appointment_purpose as 'Purpose',
                               a.appointment_status as 'Status'
                        FROM appointment a
                        LEFT JOIN users u_doc ON a.doctor_user_id = u_doc.user_id
                        JOIN users u_pat ON a.user_id = u_pat.user_id
                        WHERE a.appointment_date BETWEEN ? AND ?";
                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                if (!empty($doctorIds)) {
                    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
                    $sql .= " AND a.doctor_user_id IN ($placeholders)";
                    $params = array_merge($params, $doctorIds);
                }
                if (!empty($statuses)) {
                    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
                    $sql .= " AND a.appointment_status IN ($placeholders)";
                    $params = array_merge($params, $statuses);
                }
                if (!empty($patientIds)) {
                    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                    $sql .= " AND a.user_id IN ($placeholders)";
                    $params = array_merge($params, $patientIds);
                }
                if ($u['role'] === 'doctor') {
                    $sql .= " AND a.doctor_user_id = ?";
                    $params[] = $u['user_id'];
                }

                $sql .= " ORDER BY a.appointment_date DESC";
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'queue_efficiency') {
                $title = "Queue Efficiency";
                $sql = "SELECT pq.checked_in_at as 'Visited',
                               CONCAT(u_pat.first_name, ' ', u_pat.last_name) as 'Patient',
                               u_doc.last_name as 'Doctor',
                               TIMESTAMPDIFF(MINUTE, pq.checked_in_at, pq.started_at) as 'Wait(m)',
                               TIMESTAMPDIFF(MINUTE, pq.started_at, pq.finished_at) as 'Proc(m)'
                        FROM patient_queue pq
                        LEFT JOIN users u_doc ON pq.doctor_user_id = u_doc.user_id
                        JOIN users u_pat ON pq.user_id = u_pat.user_id
                        WHERE pq.checked_in_at BETWEEN ? AND ?
                        AND pq.started_at IS NOT NULL";

                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                if (!empty($doctorIds)) {
                    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
                    $sql .= " AND pq.doctor_user_id IN ($placeholders)";
                    $params = array_merge($params, $doctorIds);
                }
                if ($u['role'] === 'doctor') {
                    $sql .= " AND pq.doctor_user_id = ?";
                    $params[] = $u['user_id'];
                }

                $sql .= " ORDER BY pq.checked_in_at DESC";
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'demographics') {
                $title = "Demographics";
                $stmt = db()->query("SELECT 
                                            CASE 
                                                WHEN age < 20 THEN 'Under 20'
                                                WHEN age BETWEEN 20 AND 29 THEN '20-29'
                                                WHEN age BETWEEN 30 AND 39 THEN '30-39'
                                                WHEN age BETWEEN 40 AND 49 THEN '40-49'
                                                ELSE '50+' 
                                            END as 'Age Group',
                                            COUNT(*) as 'Total Patients'
                                         FROM (
                                            SELECT TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) as age
                                            FROM users u
                                            WHERE u.role = 'patient' AND u.dob IS NOT NULL
                                         ) age_query
                                         GROUP BY age_query.age"); // Grouping by calculated age or range
                // Refined demographics query for better grouping
                $stmt = db()->query("SELECT AgeGroup as 'Age Group', COUNT(*) as 'Count' FROM (
                    SELECT CASE 
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 18 THEN 'Minor (<18)'
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
                        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) BETWEEN 31 AND 45 THEN '31-45'
                        ELSE 'Senior (>45)'
                    END as AgeGroup
                    FROM users WHERE role = 'patient' AND dob IS NOT NULL
                ) t GROUP BY AgeGroup");
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'billing') {
                $title = "Revenue & Collections";
                // New Logic: Track actual collections via payments table
                $sql = "SELECT p.method as 'Payment Method', 
                               COUNT(*) as 'Tx Count', 
                               SUM(p.amount) as 'Total Collected'
                        FROM payments p
                        JOIN billing b ON p.billing_id = b.billing_id
                        LEFT JOIN consultation c ON b.consultation_id = c.consultation_id
                        WHERE p.paid_at BETWEEN ? AND ?";

                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                // Apply Filters
                if (!empty($patientIds)) {
                    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                    $sql .= " AND b.user_id IN ($placeholders)";
                    $params = array_merge($params, $patientIds);
                }

                if (!empty($doctorIds)) {
                    // Filter by doctor responsible for the consultation
                    // Note: Non-consultation bills (e.g. direct lab) might not have a doctor link in billing table unless we join differently
                    // For now, only filter if consultation exists
                    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
                    $sql .= " AND c.doctor_user_id IN ($placeholders)";
                    $params = array_merge($params, $doctorIds);
                }

                if ($u['role'] === 'doctor') {
                    $sql .= " AND c.doctor_user_id = ?";
                    $params[] = $u['user_id'];
                }

                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Summary stats for billing
                $totalCollected = 0;
                foreach ($reportData as $r) {
                    $totalCollected += (float)($r['Total Collected'] ?? 0);
                }
                $stats['Total Collections'] = number_format($totalCollected, 2);

                // Add a secondary section for Accounts Receivable (Unpaid Bills) if no specific payment method filter is effectively blocking it
                // We append it to the report data as a separate list or handle it? 
                // The current structure expects one array per 'type'.
                // Ideally, we'd separate 'Collections' vs 'Receivables'.
                // For now, let's stick to Collections as the primary "Revenue" metric.

                // If we want to show unpaid bills, maybe add a new row manually?
                // Let's just stick to "Total Collected" for now as that fixes the main "Revenue Report is wrong" issue.
            } elseif ($type === 'consultations') {
                $title = "Consultations";
                $sql = "SELECT c.created_at as 'Date', 
                               CONCAT(u_pat.first_name, ' ', u_pat.last_name) as 'Patient',
                               u_doc.last_name as 'Doctor',
                               c.consultation_type as 'Type'
                        FROM consultation c
                        JOIN users u_doc ON c.doctor_user_id = u_doc.user_id
                        JOIN users u_pat ON c.user_id = u_pat.user_id
                        WHERE c.created_at BETWEEN ? AND ?";
                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                if (!empty($doctorIds)) {
                    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
                    $sql .= " AND c.doctor_user_id IN ($placeholders)";
                    $params = array_merge($params, $doctorIds);
                }
                if ($u['role'] === 'doctor') {
                    $sql .= " AND c.doctor_user_id = ?";
                    $params[] = $u['user_id'];
                }

                $sql .= " ORDER BY c.created_at DESC";
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'audit' && $u['role'] === 'admin') {
                $title = "Audit Logs";
                $sql = "SELECT a.logged_at as 'Timestamp', 
                               u.last_name as 'User',
                               a.operation as 'Action', 
                               a.table_name as 'Module', 
                               a.record_id as 'ID'
                        FROM audit_log a
                        LEFT JOIN users u ON a.user_id = u.user_id
                        WHERE a.logged_at BETWEEN ? AND ?
                        ORDER BY a.logged_at DESC LIMIT 1000";
                $stmt = db()->prepare($sql);
                $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'patient_records' && in_array($u['role'], ['admin', 'doctor', 'secretary'])) {
                $title = "Patient Vitals";
                $sql = "SELECT v.recorded_at as 'Date',
                               CONCAT(u.first_name, ' ', u.last_name) as 'Patient',
                               CONCAT(v.systolic_pressure, '/', v.diastolic_pressure) as 'BP', 
                               v.heart_rate as 'HR', 
                               v.weight_kg as 'Wt(kg)', 
                               v.temperature_celsius as 'Temp'
                        FROM vital_signs v
                        JOIN users u ON v.user_id = u.user_id
                        WHERE v.recorded_at BETWEEN ? AND ?";
                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                if (!empty($patientIds)) {
                    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                    $sql .= " AND v.user_id IN ($placeholders)";
                    $params = array_merge($params, $patientIds);
                }

                $sql .= " ORDER BY v.recorded_at DESC";
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'prescriptions') {
                $title = "Prescriptions";
                $sql = "SELECT p.prescribed_at as 'Date',
                               CONCAT(u_pat.first_name, ' ', u_pat.last_name) as 'Patient',
                               u_doc.last_name as 'Doctor',
                               p.medication_name as 'Medication', 
                               p.dosage as 'Dosage',
                               p.frequency as 'Freq'
                        FROM prescription p
                        JOIN users u_doc ON p.doctor_user_id = u_doc.user_id
                        JOIN users u_pat ON p.user_id = u_pat.user_id
                        WHERE p.prescribed_at BETWEEN ? AND ?";
                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                if (!empty($doctorIds)) {
                    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
                    $sql .= " AND p.doctor_user_id IN ($placeholders)";
                    $params = array_merge($params, $doctorIds);
                }
                if (!empty($patientIds)) {
                    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                    $sql .= " AND p.user_id IN ($placeholders)";
                    $params = array_merge($params, $patientIds);
                }
                if ($u['role'] === 'doctor') {
                    $sql .= " AND p.doctor_user_id = ?";
                    $params[] = $u['user_id'];
                }

                $sql .= " ORDER BY p.prescribed_at DESC";
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'receivables') {
                $title = "Accounts Receivable";
                $sql = "SELECT b.created_at as 'Invoice Date',
                               CONCAT(u.first_name, ' ', u.last_name) as 'Patient',
                               b.service_description as 'Description',
                               b.amount_due as 'Total Due',
                               b.amount_paid as 'Total Paid',
                               (b.amount_due - b.amount_paid) as 'Balance',
                               b.due_date as 'Due Date'
                        FROM billing b
                        JOIN users u ON b.user_id = u.user_id
                        LEFT JOIN consultation c ON b.consultation_id = c.consultation_id
                        WHERE b.billing_status IN ('unpaid', 'partial')
                        AND b.created_at BETWEEN ? AND ?";

                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

                if (!empty($patientIds)) {
                    $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
                    $sql .= " AND b.user_id IN ($placeholders)";
                    $params = array_merge($params, $patientIds);
                }

                if (!empty($doctorIds)) {
                    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
                    $sql .= " AND c.doctor_user_id IN ($placeholders)";
                    $params = array_merge($params, $doctorIds);
                }

                if ($u['role'] === 'doctor') {
                    $sql .= " AND c.doctor_user_id = ?";
                    $params[] = $u['user_id'];
                }

                $sql .= " ORDER BY b.created_at DESC";
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($type === 'financial_summary') {
                $title = "Financial Summary Snapshot";
                // 1. Total Invoiced
                $sqlInv = "SELECT IFNULL(SUM(amount_due),0) FROM billing WHERE created_at BETWEEN ? AND ?";
                $inv = db()->prepare($sqlInv);
                $inv->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $totalInvoiced = $inv->fetchColumn();

                // 2. Total Collected
                $sqlCol = "SELECT IFNULL(SUM(amount),0) FROM payments WHERE paid_at BETWEEN ? AND ?";
                $col = db()->prepare($sqlCol);
                $col->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $totalCollected = $col->fetchColumn();

                // 3. New Receivables (from bills generated in period)
                $sqlRec = "SELECT IFNULL(SUM(amount_due - amount_paid),0) FROM billing WHERE created_at BETWEEN ? AND ?";
                $rec = db()->prepare($sqlRec);
                $rec->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
                $newReceivables = $rec->fetchColumn();

                // Construct a single row for the table
                $reportData = [[
                    'Period' => "$dateFrom to $dateTo",
                    'Total Invoiced' => number_format((float)$totalInvoiced, 2),
                    'Total Collected' => number_format((float)$totalCollected, 2),
                    'New Receivables' => number_format((float)$newReceivables, 2)
                ]];
            }

            if (!empty($reportData)) {
                $allReportData[$type] = $reportData;
                $titles[] = $title;

                // Calculate Stats
                $stats = ['TotalCount' => count($reportData)];
                if ($type === 'appointments') {
                    $counts = [];
                    foreach ($reportData as $r) {
                        $s = $r['Status'] ?? 'Unknown';
                        $counts[$s] = ($counts[$s] ?? 0) + 1;
                    }
                    $stats['Summary'] = $counts;
                    $stats['No-Show Count'] = $counts['no_show'] ?? 0;
                } elseif ($type === 'billing') {
                    // Stats calculated during data fetch
                } elseif ($type === 'financial_summary') {
                    // Snapshots are already summarized
                } elseif ($type === 'queue_efficiency') {
                    $avgWait = 0;
                    $avgProc = 0;
                    foreach ($reportData as $r) {
                        $avgWait += (int)$r['Wait(m)'];
                        $avgProc += (int)$r['Proc(m)'];
                    }
                    $stats['Avg Wait'] = round($avgWait / count($reportData), 1) . 'm';
                    $stats['Avg Proc'] = round($avgProc / count($reportData), 1) . 'm';
                } elseif ($type === 'receivables') {
                    $totalBalance = 0;
                    foreach ($reportData as $r) {
                        $totalBalance += (float)$r['Balance'];
                    }
                    $stats['Total Receivable'] = number_format($totalBalance, 2);

                    $totalOverdue = 0;
                    foreach ($reportData as $r) {
                        if (strtotime($r['Due Date']) < time()) {
                            $totalOverdue += (float)(str_replace(',', '', $r['Balance'] ?? '0'));
                        }
                    }
                    $stats['Total Overdue'] = number_format($totalOverdue, 2);
                }
                $allStats[$type] = $stats;
            }
        }

        $combinedTitle = "MatriFlow Master Report (" . (empty($titles) ? "Empty" : implode(', ', $titles)) . ")";

        if ($action === 'export_pdf') {
            require_once __DIR__ . '/../../app/lib/fpdf.php';

            class CombinedReportPDF extends FPDF
            {
                public $u;
                public $dateFrom;
                public $dateTo;
                public $filters;
                public $confidentiality;

                function Header()
                {
                    $logoPath = __DIR__ . '/../assets/images/CHMC-logo.png';
                    if (file_exists($logoPath)) {
                        $this->Image($logoPath, 10, 8, 28);
                    }

                    $this->SetY(10);
                    $this->SetFont('Helvetica', 'B', 16);
                    $this->SetTextColor(30, 80, 150);
                    $this->Cell(0, 8, 'Commonwealth Hospital and Medical Center', 0, 1, 'C');

                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(70, 70, 70);
                    $this->Cell(0, 5, 'Lot 3 & 4 Blk. 3 Neopolitan Business Park, Regalado Hwy, Brgy. Greater Lagro, Novaliches, Quezon City', 0, 1, 'C');
                    $this->Cell(0, 5, 'Contact: (02) 8930-0000 | Email: contact@commonwealthmed.com.ph', 0, 1, 'C');

                    $this->Ln(4);
                    $this->SetDrawColor(200, 200, 200);
                    $this->Line(10, $this->GetY(), 287, $this->GetY());
                    $this->Ln(2);

                    $this->SetFont('Helvetica', 'B', 8);
                    $this->SetTextColor(100);
                    $this->Cell(0, 5, 'EXPORT ID: ' . $this->exportId, 0, 1, 'R');
                    $this->Ln(3);
                }

                public $exportId;

                function Footer()
                {
                    $this->SetY(-20);
                    $this->SetFont('Helvetica', 'I', 8);
                    $this->SetTextColor(128, 128, 128);

                    // HIPAA / Confidentiality Disclaimer
                    $this->Cell(0, 4, 'CONFIDENTIAL: This document contains sensitive medical and operational information protected by privacy laws.', 0, 1, 'C');
                    $this->Cell(0, 4, 'Unauthorized access, distribution, or copying is strictly prohibited. If found, please return to the system administrator immediately.', 0, 1, 'C');

                    $this->Ln(2);
                    $this->SetTextColor(150);
                    $this->Cell(0, 5, 'Generated on ' . date('Y-m-d H:i') . ' by ' . $this->u['first_name'] . ' ' . $this->u['last_name'] . ' | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
                }

                function NarrativeSummary()
                {
                    $this->SetFont('Helvetica', 'B', 11);
                    $this->SetTextColor(0);
                    $this->Cell(0, 10, "REPORT NARRATIVE & CRITERIA", 0, 1);

                    $this->SetFont('Helvetica', '', 9);
                    $this->SetFillColor(245, 247, 250);

                    $confidentialityText = ucfirst($this->confidentiality) . " Level";

                    $narrative = "This document presents a comprehensive analysis of clinical and operational data extracted from the MatriFlow system for the period from " . $this->dateFrom . " to " . $this->dateTo . ". " .
                        "The data herein reflects verified system snapshots relevant to " . $this->u['role'] . " clinical oversight and is intended solely for authorized administrative review. \n\n" .
                        "This report adheres to data privacy standards and internal hospital protocols. All patient information is anonymized where applicable unless authorized for specific clinical review. " .
                        "The information contained is current as of the generation timestamp and should be validated against live records for critical decision making.\n\n" .
                        "FILTERS APPLIED: \n" .
                        "- Confidentiality: " . $confidentialityText . "\n" .
                        "- Date Range: " . $this->dateFrom . " to " . $this->dateTo . "\n" .
                        "- Doctors: " . $this->filters['doctors'] . "\n" .
                        "- Patients: " . $this->filters['patients'] . "\n" .
                        "- Statuses: " . (empty($this->filters['statuses']) ? "All Available" : implode(', ', $this->filters['statuses']));

                    $this->MultiCell(0, 6, $narrative, 1, 'L', true);
                    $this->Ln(10);
                }

                function SummaryBox($stats, $type)
                {
                    $this->SetFont('Helvetica', 'B', 10);
                    $this->SetFillColor(240, 245, 255);
                    $this->SetDrawColor(30, 80, 150);
                    $this->Cell(0, 8, " SECTION SUMMARY: " . strtoupper(str_replace('_', ' ', $type)), 1, 1, 'L', true);

                    $this->SetFont('Helvetica', '', 9);
                    $this->SetFillColor(255);
                    $lines = ["Total Records: " . $stats['TotalCount']];

                    if (isset($stats['Summary'])) {
                        foreach ($stats['Summary'] as $k => $v) $lines[] = ucfirst($k) . ": " . $v;
                    }
                    if (isset($stats['Total Collections'])) $lines[] = "Total Collections: PHP " . $stats['Total Collections'];
                    if (isset($stats['Total Receivable'])) $lines[] = "Total Receivables: PHP " . $stats['Total Receivable'];
                    if (isset($stats['Total Overdue'])) $lines[] = "Total Overdue: PHP " . $stats['Total Overdue'];
                    if (isset($stats['Total Val.'])) $lines[] = "Total Value: PHP " . $stats['Total Val.'];
                    if (isset($stats['Avg Wait'])) $lines[] = "Efficiency: Avg Wait " . $stats['Avg Wait'] . " / Avg Proc " . $stats['Avg Proc'];

                    $this->MultiCell(0, 6, implode('  |  ', $lines), 1, 'L', true);
                    $this->Ln(5);
                }
            }

            $pdf = new CombinedReportPDF('L', 'mm', 'A4');
            $pdf->u = $u;
            $pdf->exportId = $exportId;
            $pdf->dateFrom = $dateFrom;
            $pdf->dateTo = $dateTo;
            $pdf->confidentiality = $_GET['confidentiality'] ?? 'Restricted';
            $pdf->filters = [
                'doctors' => $getNames($doctorIds, 'users'),
                'patients' => $getNames($patientIds, 'users'),
                'statuses' => $statuses
            ];
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->NarrativeSummary();

            if (empty($allReportData)) {
                $pdf->SetFont('Helvetica', 'B', 12);
                $pdf->Cell(0, 20, "NO RECORDS FOUND FOR THE SELECTED CRITERIA", 0, 1, 'C');
            } else {
                foreach ($allReportData as $tKey => $data) {
                    if ($pdf->GetY() > 170) $pdf->AddPage();

                    $pdf->SetFont('Helvetica', 'B', 12);
                    $pdf->SetTextColor(30, 80, 150);
                    $pdf->Cell(0, 10, strtoupper(str_replace('_', ' ', $tKey)) . " DATA SECTION", 0, 1);
                    $pdf->SetTextColor(0);

                    if (isset($allStats[$tKey])) {
                        $pdf->SummaryBox($allStats[$tKey], $tKey);
                    }

                    // Table Header
                    $pdf->SetFont('Helvetica', 'B', 9);
                    $pdf->SetFillColor(230, 235, 245);
                    $headers = array_keys($data[0]);
                    $colCount = count($headers);
                    $pageWidth = 277;

                    // Simple dynamic column widths
                    $colWidths = [];
                    foreach ($headers as $h) {
                        if (in_array($h, ['Date', 'Timestamp', 'Visited'])) $colWidths[] = 35;
                        elseif (in_array($h, ['Patient', 'User'])) $colWidths[] = 50;
                        elseif ($h === 'Purpose' || $h === 'Medication') $colWidths[] = 60;
                        else $colWidths[] = ($pageWidth - (array_sum($colWidths) ?? 0)) / ($colCount - count($colWidths));
                    }
                    // Normalize if needed
                    $totalW = array_sum($colWidths);
                    $ratio = $pageWidth / $totalW;
                    foreach ($colWidths as &$w) $w *= $ratio;

                    foreach ($headers as $i => $h) {
                        $pdf->Cell($colWidths[$i], 8, strtoupper($h), 1, 0, 'C', true);
                    }
                    $pdf->Ln();

                    // Table Rows
                    $pdf->SetFont('Helvetica', '', 8);
                    $fill = false;
                    foreach ($data as $row) {
                        if ($pdf->GetY() > 185) {
                            $pdf->AddPage();
                            $pdf->SetFont('Helvetica', 'B', 9);
                            foreach ($headers as $i => $h) {
                                $pdf->Cell($colWidths[$i], 8, strtoupper($h), 1, 0, 'C', true);
                            }
                            $pdf->Ln();
                            $pdf->SetFont('Helvetica', '', 8);
                        }

                        $pdf->SetFillColor(250, 250, 250);
                        foreach ($headers as $i => $h) {
                            $val = substr((string)($row[$h] ?? '-'), 0, 80);
                            $pdf->Cell($colWidths[$i], 7, $val, 1, 0, 'L', $fill);
                        }
                        $pdf->Ln();
                        $fill = !$fill;
                    }
                    $pdf->Ln(10);
                }
            }

            while (ob_get_level()) ob_end_clean();

            $pdfOutput = $pdf->Output('S');
            $filename = $exportId . ".pdf";
            $savePath = __DIR__ . '/../../storage/reports/' . $filename;

            // Ensure directory
            if (!is_dir(__DIR__ . '/../../storage/reports')) {
                mkdir(__DIR__ . '/../../storage/reports', 0777, true);
            }

            file_put_contents($savePath, $pdfOutput);

            // Record in documents table
            try {
                $stmt = db()->prepare("INSERT INTO documents (uploader_user_id, file_name, file_path, file_type, category, description, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $u['user_id'],
                    $filename,
                    'storage/reports/' . $filename,
                    'application/pdf',
                    'other',
                    'Generated Report: ' . implode(', ', $titles)
                ]);
                $docId = db()->lastInsertId();

                // Audit Log
                AuditLogger::log($u['user_id'], 'documents', 'INSERT', $docId, "Generated report: $filename");
            } catch (Throwable $dbEx) {
                error_log("Failed to record generated report in DB: " . $dbEx->getMessage());
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            echo $pdfOutput;
            exit;
        } else {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'title' => $combinedTitle,
                'sections' => $allReportData
            ]);
        }
    } catch (Throwable $e) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
    exit;
}
