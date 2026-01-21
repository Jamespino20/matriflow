<?php
require_once __DIR__ . '/../../bootstrap.php';

if (!Auth::check() || !in_array(Auth::user()['role'], ['doctor', 'admin', 'secretary'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$patientId = (int)($_GET['patient_id'] ?? 0);
if (!$patientId) {
    exit('Invalid patient ID');
}

$patientData = db()->prepare("SELECT * FROM users WHERE user_id = :pid AND role = 'patient'");
$patientData->execute([':pid' => $patientId]);
$patient = $patientData->fetch();

if (!$patient) {
    exit('Patient not found');
}

$baseline = Pregnancy::findActiveByUserId($patientId);
$visits = $baseline ? PrenatalObservation::listByPregnancy((int)$baseline['pregnancy_id']) : [];
$consultations = Consultation::listByPatient($patientId);
$prescriptions = Prescription::listByPatient($patientId);

// Initialize FPDF
require_once __DIR__ . '/../../app/lib/fpdf.php';

class PDF extends FPDF
{
    function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'MatriFlow Clinical Summary', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, 'Generated on ' . date('M j, Y H:i'), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Confidential Clinical Record - MatriFlow Maternal Health Management System - Page ' . $this->PageNo(), 0, 0, 'C');
    }

    function SectionTitle($label)
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(0, 8, $label, 0, 1, 'L', true);
        $this->Ln(2);
    }

    function InfoPair($label, $value)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 6, $label . ':', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $value, 0, 1);
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Patient Info
$pdf->SectionTitle('Patient Information');
$pdf->InfoPair('Name', $patient['first_name'] . ' ' . $patient['last_name']);
$pdf->InfoPair('Patient ID', $patient['identification_number'] ?? 'N/A');
$pdf->InfoPair('Email', $patient['email']);
$pdf->InfoPair('Phone', $patient['contact_number'] ?? $patient['phone_number'] ?? 'N/A');
$pdf->Ln(5);

// Vitals History
$pdf->SectionTitle('Recent Vital Signs');
$vitals = VitalSigns::getRecentForPatient($patientId, 5);
if (empty($vitals)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'No vital signs recorded.', 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(45, 7, 'Date', 1);
    $pdf->Cell(40, 7, 'BP (mmHg)', 1);
    $pdf->Cell(35, 7, 'HR (bpm)', 1);
    $pdf->Cell(35, 7, 'Weight (kg)', 1);
    $pdf->Cell(35, 7, 'Temp (C)', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 9);
    foreach ($vitals as $v) {
        $pdf->Cell(45, 7, date('M j, Y g:i A', strtotime($v['recorded_at'])), 1);
        $pdf->Cell(40, 7, $v['systolic_pressure'] . '/' . $v['diastolic_pressure'], 1);
        $pdf->Cell(35, 7, $v['heart_rate'], 1);
        $pdf->Cell(35, 7, $v['weight_kg'], 1);
        $pdf->Cell(35, 7, $v['temperature_celsius'], 1);
        $pdf->Ln();
    }
}
$pdf->Ln(5);

// Prenatal Baseline
if ($baseline) {
    $pdf->SectionTitle('Prenatal Baseline');
    $pdf->InfoPair('LMP Date', date('M j, Y', strtotime($baseline['lmp_date'])));
    $pdf->InfoPair('Est. Due Date', date('M j, Y', strtotime($baseline['estimated_due_date'])));
    $pdf->InfoPair('Gravidity/Parity', 'G' . $baseline['gravida'] . ' P' . $baseline['para']);
    $pdf->Ln(5);

    // Visits
    $pdf->SectionTitle('Prenatal Visits');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 7, 'Date', 1);
    $pdf->Cell(30, 7, 'Fundal Ht', 1);
    $pdf->Cell(30, 7, 'FHR (bpm)', 1);
    $pdf->Cell(30, 7, 'Movement', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 9);
    foreach ($visits as $v) {
        $pdf->Cell(30, 7, date('M j, Y', strtotime($v['created_at'])), 1);
        $pdf->Cell(30, 7, $v['fundal_height_cm'] . ' cm', 1);
        $pdf->Cell(30, 7, $v['fetal_heart_rate'], 1);
        $pdf->Cell(30, 7, $v['fetal_movement_noted'] ? 'Yes' : 'No', 1);
        $pdf->Ln();
    }
    if (empty($visits)) {
        $pdf->Cell(120, 7, 'No visits recorded.', 1);
        $pdf->Ln();
    }
    $pdf->Ln(5);
}

// Consultations
$pdf->SectionTitle('Clinical Consultations');
if (empty($consultations)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'No medical consultations recorded.', 0, 1);
} else {
    foreach ($consultations as $c) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 6, date('M j, Y - g:i A', strtotime($c['created_at'])) . ' | Dr. ' . ($c['doctor_first'] ?? '') . ' ' . ($c['doctor_last'] ?? ''), 0, 1);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 5, 'Subjective:', 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, $c['subjective_notes']);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 5, 'Objective:', 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, $c['objective_notes']);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 5, 'Assessment:', 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, $c['assessment']);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(25, 5, 'Plan:', 0);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, $c['plan']);
        $pdf->Ln(3);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + 190, $pdf->GetY());
        $pdf->Ln(3);
    }
}
$pdf->Ln(5);

// Prescriptions
$pdf->SectionTitle('Prescriptions Issued');
if (empty($prescriptions)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'No prescriptions issued.', 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(30, 7, 'Date', 1);
    $pdf->Cell(50, 7, 'Medication', 1);
    $pdf->Cell(40, 7, 'Dosage/Freq', 1);
    $pdf->Cell(70, 7, 'Instructions', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 9);
    foreach ($prescriptions as $p) {
        $pdf->Cell(30, 7, date('M j, Y', strtotime($p['prescribed_at'])), 1);
        $pdf->Cell(50, 7, substr($p['medication_name'], 0, 28), 1);
        $pdf->Cell(40, 7, substr($p['dosage'] . ' ' . $p['frequency'], 0, 22), 1);
        $pdf->Cell(70, 7, substr($p['instructions'], 0, 40), 1);
        $pdf->Ln();
    }
}

// Save and Output
$pdfContent = $pdf->Output('S');
$filename = 'PatientRecord_' . $patientId . '_' . date('YmdHis') . '.pdf';
$storageDir = BASE_PATH . '/storage/uploads/documents';
if (!is_dir($storageDir)) mkdir($storageDir, 0755, true);

// Save file
$savedPath = $storageDir . '/' . $filename;
file_put_contents($savedPath, $pdfContent);

// Add to patient documents
try {
    $stmt = db()->prepare("
        INSERT INTO documents (user_id, uploader_user_id, category, file_path, file_name, description, uploaded_at)
        VALUES (:user_id, :uploaded_by, 'record_export', :file_path, :file_name, 'Generated Clinical Record', NOW())
    ");
    $stmt->execute([
        ':user_id' => $patientId,
        ':uploaded_by' => Auth::user()['user_id'],
        ':file_path' => 'storage/uploads/documents/' . $filename,
        ':file_name' => $filename
    ]);
} catch (Throwable $e) {
    // Silent fail on db insert, but file is saved
    error_log("Failed to insert document record: " . $e->getMessage());
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfContent));
echo $pdfContent;
exit;
