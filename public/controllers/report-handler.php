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
    $type = $_GET['type'] ?? '';
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');

    // Parse filters as arrays
    $doctorIds = isset($_GET['doctor_id']) ? (is_array($_GET['doctor_id']) ? $_GET['doctor_id'] : [$_GET['doctor_id']]) : [];
    $statuses = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [];
    $patientIds = isset($_GET['patient_id']) ? (is_array($_GET['patient_id']) ? $_GET['patient_id'] : [$_GET['patient_id']]) : [];

    // Filter out empty values
    $doctorIds = array_filter($doctorIds);
    $statuses = array_filter($statuses);
    $patientIds = array_filter($patientIds);

    $type = $_GET['type'] ?? '';
    // $q kept for legacy or additional search
    $q = trim((string)($_GET['q'] ?? ''));

    $reportData = [];
    $title = "Report";

    try {
        if ($type === 'appointments') {
            $title = "Detailed Appointment Report";
            $sql = "SELECT a.appointment_date, a.appointment_status, a.appointment_purpose,
                               u_doc.last_name as doctor_name, 
                               u_pat.first_name as p_first, u_pat.last_name as p_last,
                               p.patient_id
                        FROM appointment a
                        LEFT JOIN user u_doc ON a.doctor_user_id = u_doc.user_id
                        JOIN patient p ON a.patient_id = p.patient_id
                        JOIN user u_pat ON p.user_id = u_pat.user_id
                        WHERE a.appointment_date BETWEEN ? AND ?";
            $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            if (!empty($doctorIds)) {
                $placeholders = str_repeat('?,', count($doctorIds) - 1) . '?';
                $sql .= " AND a.doctor_user_id IN ($placeholders)";
                $params = array_merge($params, $doctorIds);
            }
            if (!empty($statuses)) {
                $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
                $sql .= " AND a.appointment_status IN ($placeholders)";
                $params = array_merge($params, $statuses);
            }
            if (!empty($patientIds)) {
                $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
                $sql .= " AND a.patient_id IN (SELECT patient_id FROM patient WHERE user_id IN ($placeholders))";
                $params = array_merge($params, $patientIds);
            }
            if ($u['role'] === 'doctor') {
                $sql .= " AND a.doctor_user_id = ?";
                $params[] = $u['user_id'];
            }
            if ($q) {
                $sql .= " AND (u_pat.first_name LIKE ? OR u_pat.last_name LIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }

            $sql .= " ORDER BY a.appointment_date DESC";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        } elseif ($type === 'queue_efficiency') {
            $title = "Queue Efficiency & Wait Times";
            $sql = "SELECT pq.checked_in_at, pq.started_at, pq.finished_at,
                               u_doc.last_name as doctor_name,
                               u_pat.first_name as p_first, u_pat.last_name as p_last,
                               TIMESTAMPDIFF(MINUTE, pq.checked_in_at, pq.started_at) as wait_time_mins,
                               TIMESTAMPDIFF(MINUTE, pq.started_at, pq.finished_at) as consult_duration_mins
                        FROM patient_queue pq
                        LEFT JOIN user u_doc ON pq.doctor_user_id = u_doc.user_id
                        JOIN patient p ON pq.patient_id = p.patient_id
                        JOIN user u_pat ON p.user_id = u_pat.user_id
                        WHERE pq.checked_in_at BETWEEN ? AND ?
                        AND pq.started_at IS NOT NULL"; // Only analyze served patients

            $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            if (!empty($doctorIds)) {
                $placeholders = str_repeat('?,', count($doctorIds) - 1) . '?';
                $sql .= " AND pq.doctor_user_id IN ($placeholders)";
                $params = array_merge($params, $doctorIds);
            }

            if ($u['role'] === 'doctor') {
                $sql .= " AND pq.doctor_user_id = ?";
                $params[] = $u['user_id'];
            }
            if ($q) {
                $sql .= " AND (u_pat.first_name LIKE ? OR u_pat.last_name LIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }

            $sql .= " ORDER BY pq.checked_in_at DESC";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        } elseif ($type === 'demographics') {
            $title = "Patient Demographics Summary";
            $stmt = db()->query("SELECT 
                                        CASE 
                                            WHEN age < 20 THEN 'Under 20'
                                            WHEN age BETWEEN 20 AND 29 THEN '20-29'
                                            WHEN age BETWEEN 30 AND 39 THEN '30-39'
                                            WHEN age BETWEEN 40 AND 49 THEN '40-49'
                                            ELSE '50+' 
                                        END as age_group,
                                        COUNT(*) as count
                                     FROM (
                                        SELECT COALESCE(TIMESTAMPDIFF(YEAR, u.dob, CURDATE()), p.age_at_registration) as age
                                        FROM patient p
                                        JOIN user u ON p.user_id = u.user_id
                                     ) age_query
                                     WHERE age IS NOT NULL
                                     GROUP BY age_group");
            $reportData = $stmt->fetchAll();
        } elseif ($type === 'billing') {
            $title = "Billing & Revenue Summary";
            $sql = "SELECT payment_status, SUM(amount_due) as total, COUNT(*) as count
                        FROM billing 
                        WHERE created_at BETWEEN ? AND ?";
            $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            if (!empty($statuses)) {
                $placeholders = str_repeat('?,', count($statuses) - 1) . '?';
                $sql .= " AND payment_status IN ($placeholders)";
                $params = array_merge($params, $statuses);
            }

            $sql .= " GROUP BY payment_status";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        } elseif ($type === 'consultations' && in_array($u['role'], ['admin', 'doctor'])) {
            $title = "Clinical Consultation Log";
            $sql = "SELECT c.created_at, u_doc.last_name as doctor_name, 
                               u_pat.first_name as p_first, u_pat.last_name as p_last,
                               c.consultation_type
                        FROM consultation c
                        JOIN user u_doc ON c.doctor_user_id = u_doc.user_id
                        JOIN patient p ON c.patient_id = p.patient_id
                        JOIN user u_pat ON p.user_id = u_pat.user_id
                        WHERE c.created_at BETWEEN ? AND ?";
            $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            if (!empty($doctorIds)) {
                $placeholders = str_repeat('?,', count($doctorIds) - 1) . '?';
                $sql .= " AND c.doctor_user_id IN ($placeholders)";
                $params = array_merge($params, $doctorIds);
            }
            if (!empty($patientIds)) {
                $placeholders = str_repeat('?,', count($patientIds) - 1) . '?';
                $sql .= " AND c.patient_id IN (SELECT patient_id FROM patient WHERE user_id IN ($placeholders))";
                $params = array_merge($params, $patientIds);
            }

            if ($u['role'] === 'doctor') {
                $sql .= " AND c.doctor_user_id = ?";
                $params[] = $u['user_id'];
            }

            if ($q) {
                $sql .= " AND (u_pat.first_name LIKE ? OR u_pat.last_name LIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }

            $sql .= " ORDER BY c.created_at DESC";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        } elseif ($type === 'prescriptions') {
            $title = "Prescription Statistics";
            $sql = "SELECT medication_name as medicine_name, COUNT(*) as times_prescribed
                         FROM prescription 
                         WHERE prescribed_at BETWEEN ? AND ?
                         GROUP BY medication_name
                         ORDER BY times_prescribed DESC LIMIT 20";
            $stmt = db()->prepare($sql);
            $stmt->execute([$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
            $reportData = $stmt->fetchAll();
        } elseif ($type === 'audit' && $u['role'] === 'admin') {
            $title = "System Activity Log";
            $sql = "SELECT a.logged_at, a.operation, a.table_name, a.record_id, 
                               u.first_name, u.last_name, u.role
                        FROM audit_log a
                        LEFT JOIN user u ON a.user_id = u.user_id
                        WHERE a.logged_at BETWEEN ? AND ?";
            $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

            if ($q) {
                $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR a.table_name LIKE ?)";
                $params[] = "%$q%";
                $params[] = "%$q%";
                $params[] = "%$q%";
            }

            $sql .= " ORDER BY a.logged_at DESC LIMIT 500";
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            $reportData = $stmt->fetchAll();
        }

        if ($action === 'export_pdf') {
            require_once __DIR__ . '/../../app/lib/fpdf.php';

            // 1. Determine Orientation
            $orientation = in_array($type, ['appointments', 'consultations', 'billing', 'queue_efficiency', 'audit']) ? 'L' : 'P';

            // 2. Generate Export ID
            $exportId = sprintf("CHMC-%s-%s-%s", strtoupper(substr($type, 0, 3)), $u['user_id'], date('ymdHi'));
            $confidentiality = $_GET['confidentiality'] ?? 'Confidential';

            // 3. Log Export
            try {
                $stmt = db()->prepare("INSERT INTO generated_report_history (export_id, report_type, generated_by, filters_json, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $exportId,
                    $type,
                    $u['user_id'],
                    json_encode(['date_from' => $dateFrom, 'date_to' => $dateTo, 'doctor_ids' => $doctorIds ?? [], 'status' => $statuses ?? []])
                ]);
            } catch (Exception $e) {
                // Squelch logging errors to prevent report failure
            }

            // Narratives (Retained)
            $narratives = [
                'appointments' => "This report is generated to provide a complete and auditable record of all maternity-related patient appointments, including prenatal, postnatal, ultrasound, and obstetric follow-up visits, for the reporting period $dateFrom to $dateTo, by clinic unit and attending provider.\n\nThe report supports:\n- HIPAA compliance by documenting patient encounter scheduling as part of protected health information (PHI) handling\n- RA 10173 (Data Privacy Act of 2012) by ensuring lawful processing and accountability of patient appointment data\n- DOH and internal clinical governance requirements by enabling monitoring of maternal care service delivery and provider utilization",
                'billing' => "This report is generated to summarize maternity clinic financial transactions for the period $dateFrom to $dateTo, including consultation fees, delivery charges, diagnostic services, PhilHealth claims, payments received, and outstanding balances, filtered by payer type and service category.\n\nThe report supports:\n- HIPAA Administrative Safeguards by ensuring controlled access to billing-related PHI\n- RA 10173 by enforcing purpose limitation and financial data protection\n- PhilHealth and DOH financial reporting requirements by maintaining accurate and traceable maternity service billing records",
                'queue_efficiency' => "This report is generated to document patient flow metrics for maternity services, including antenatal check-ups, labor admissions, and diagnostic procedures for the period $dateFrom to $dateTo.\n\nThe report supports:\n- Quality-of-care monitoring under DOH maternal health standards\n- HIPAA Minimum Necessary Rule by reporting operational metrics without unnecessary exposure of identifiable data\n- ISO/IEC 27001 by supporting controlled use of operational data for service optimization",
                'consultations' => "This report is generated to record all maternity-related clinical consultations conducted during the reporting period $dateFrom to $dateTo, categorized by consultation type, provider, and patient classification (e.g., prenatal, postnatal, high-risk pregnancy).\n\nThe report supports:\n- HIPAA Privacy Rule by maintaining documented records of clinical encounters\n- RA 10173 by ensuring transparency and accountability in the processing of sensitive personal information\n- DOH maternal care compliance by enabling audit and review of obstetric service delivery",
                'prescriptions' => "This report is generated to summarize prescription and medication orders related to maternity care for the period $dateFrom to $dateTo, including prenatal supplements, obstetric medications, and controlled drugs, categorized by medication class, prescriber, and dispensing unit.\n\nThe report supports:\n- HIPAA Security Rule by controlling access to medication-related PHI\n- RA 10173 by safeguarding sensitive health and prescription data\n- DOH and FDA Philippines regulations by enabling monitoring of prescribing practices and medication utilization",
                'demographics' => "This report is generated to provide an aggregated demographic profile of maternity patients served during the reporting period $dateFrom to $dateTo, including age group, geographic location, and pregnancy classification.\n\nThe report supports:\n- RA 10173 by ensuring demographic data is processed in aggregated or appropriately protected form\n- HIPAA De-identification Standards where applicable\n- DOH maternal and child health planning requirements by informing service coverage and population health initiatives",
                'audit' => "This report is generated to provide a complete and immutable record of user and system activities within the maternity clinic information system for the period $dateFrom to $dateTo, including user access, action type, affected module, and timestamp.\n\nThe report supports:\n- HIPAA Security Rule – Audit Controls\n- ISO/IEC 27001 Annex A (Logging and Monitoring Controls)\n- RA 10173 and NPC Circulars by enabling accountability, breach investigation, and compliance verification"
            ];

            $pdf = new FPDF($orientation, 'mm', 'A4');
            $pdf->AddPage();

            // Header
            if (file_exists(__DIR__ . '/../assets/images/CHMC-logo.jpg')) {
                // Ensure pure PHP implementation doesn't fail on image errors
                try {
                    $pdf->Image(__DIR__ . '/../assets/images/CHMC-logo.jpg', 10, 10, 20);
                } catch (Exception $e) {
                }
            }

            $pdf->SetFont('Arial', 'B', 14);
            $pdf->SetXY(35, 12);
            $pdf->Cell(0, 5, 'Commonwealth Hospital and Medical Center', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetXY(35, 18);
            $pdf->Cell(0, 5, 'Administrative Report | ' . strtoupper($confidentiality), 0, 1);
            $pdf->SetXY(35, 23);
            $pdf->Cell(0, 5, 'Ref: ' . $exportId . ' | Date: ' . date('Y-m-d H:i'), 0, 1);

            $pdf->SetDrawColor(200, 200, 200);
            $pdf->Line(10, 32, $orientation == 'L' ? 287 : 200, 32);
            $pdf->Ln(15);

            // Title & Narrative
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, strtoupper($title), 0, 1, 'L');

            if (isset($narratives[$type])) {
                $pdf->SetFont('Arial', '', 9);
                $pdf->MultiCell(0, 5, $narratives[$type]);
                $pdf->Ln(5);
            }

            // Table Settings
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 244, 248);
            $pdf->SetTextColor(50, 50, 50);

            // Define Columns
            $w = [];
            $header = [];

            // Adjust widths based on Orientation
            $W_FACTOR = $orientation == 'L' ? 1.4 : 1.0;

            if ($type === 'appointments') {
                $w = [35, 45, 45, 35, 30];
                if ($orientation == 'L') $w = [50, 60, 60, 60, 45]; // Custom L sizing
                $header = ['Date / Time', 'Patient', 'Doctor', 'Purpose', 'Status'];
                $keys = ['appointment_date', 'p_last', 'doctor_name', 'appointment_purpose', 'appointment_status'];
            } elseif ($type === 'queue_efficiency') {
                $w = [35, 40, 40, 30, 30];
                if ($orientation == 'L') $w = [50, 60, 60, 50, 50];
                $header = ['Check-in Time', 'Patient', 'Doctor', 'Wait Time', 'Duration'];
                $keys = ['checked_in_at', 'p_last', 'doctor_name', 'wait_time_mins', 'consult_duration_mins'];
            } elseif ($type === 'demographics') {
                $w = [80, 50];
                $header = ['Age Group', 'Total Patients'];
                $keys = ['age_group', 'count'];
            } elseif ($type === 'billing') {
                $w = [60, 50, 60];
                $header = ['Payment Status', 'Transaction Count', 'Total Revenue'];
                $keys = ['payment_status', 'count', 'total'];
            } elseif ($type === 'consultations') {
                $w = [40, 40, 45, 60];
                if ($orientation == 'L') $w = [50, 60, 60, 100];
                $header = ['Date', 'Doctor', 'Patient', 'Type'];
                $keys = ['created_at', 'doctor_name', 'p_last', 'consultation_type'];
            } elseif ($type === 'prescriptions') {
                $w = [120, 40];
                $header = ['Medicine Name', 'Frequency'];
                $keys = ['medicine_name', 'times_prescribed'];
            } elseif ($type === 'audit') {
                $w = [40, 40, 40, 60];
                if ($orientation == 'L') $w = [50, 50, 50, 120];
                $header = ['Date/Time', 'User', 'Operation', 'Resource'];
                $keys = ['logged_at', 'last_name', 'operation', 'table_name'];
            }

            // Render Header
            foreach ($header as $i => $col)
                $pdf->Cell($w[$i], 8, $col, 1, 0, 'C', true);
            $pdf->Ln();

            // Render Data
            $pdf->SetFont('Arial', '', 9);
            foreach ($reportData as $row) {
                // Determine Page Break
                $pageHeight = $orientation == 'L' ? 190 : 270;
                if ($pdf->GetY() > $pageHeight) {
                    $pdf->AddPage();
                    foreach ($header as $i => $col)
                        $pdf->Cell($w[$i], 8, $col, 1, 0, 'C', true);
                    $pdf->Ln();
                }

                foreach ($keys as $i => $key) {
                    $val = '';
                    if ($key == 'p_last') $val = $row['p_first'] . ' ' . $row['p_last'];
                    elseif ($key == 'last_name') $val = ($row['first_name'] ?? '') . ' ' . $row['last_name'];
                    elseif ($key == 'total') $val = 'P ' . number_format($row['total'], 2);
                    else $val = $row[$key] ?? '';

                    // Basic Truncation
                    $pdf->Cell($w[$i], 8, substr($val, 0, 50), 1);
                }
                $pdf->Ln();
            }

            // Footer / Signature Block
            $pdf->Ln(30);

            // Authorized Signature
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(80, 5, 'Verified Data Report', 0, 1);
            $pdf->Ln(15); // Space for signature
            $pdf->Cell(80, 0, '', 'T'); // Line
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(80, 5, $u['first_name'] . ' ' . $u['last_name'], 0, 1);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(80, 5, 'Authorized Administrator', 0, 1);

            // Disclaimer at bottom
            $pdf->SetY(-20);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->Cell(0, 5, 'Confidential Document - Property of Commonwealth Hospital. Generated ID: ' . $exportId, 0, 0, 'C');

            $pdf->Output('I', 'MatriFlow_Report_' . $exportId . '.pdf');
            exit;
        } else {
            // Default JSON Behavior
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => true,
                'title' => $title,
                'type' => $type,
                'data' => $reportData
            ]);
        }
    } catch (Throwable $e) {
        if ($action === 'export_pdf') {
            die("Error generating PDF: " . $e->getMessage());
        }
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
