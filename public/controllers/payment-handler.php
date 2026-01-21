<?php
require_once __DIR__ . '/../../bootstrap.php';

Auth::requireLogin();
$u = Auth::user();

$action = $_REQUEST['action'] ?? '';
if (!$action) {
    http_response_code(400);
    exit('Invalid action');
}

$billingId = (int)($_REQUEST['billing_id'] ?? 0);
if (!$billingId) exit("Invalid ID");

$bill = Billing::findById($billingId);
if (!$bill) exit("Invoice not found");

// Access check: Admin, Secretary, or the Patient themselves
if (!in_array($u['role'], ['admin', 'secretary']) && (int)$bill['user_id'] !== (int)$u['user_id']) {
    exit("Unauthorized");
}

// Handle POST actions (Refund Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF Token');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'update_policies') {
        if (!in_array($u['role'], ['secretary', 'admin'])) redirect('/');

        $keys = ['policy_maternity_days', 'policy_gyne_days', 'policy_lab_days', 'policy_consultation_days'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                Billing::setPolicy($key, (string)$_POST[$key]);
            }
        }

        AuditLogger::log((int)$u['user_id'], 'system_settings', 'UPDATE', 0, "Payment policies updated by " . $u['role']);
        $_SESSION['success'] = "Payment policies updated successfully.";
        redirect('/public/secretary/payments.php');
    }

    if ($action === 'add_extra_fee') {
        if (!in_array($u['role'], ['secretary', 'admin'])) redirect('/');

        $bid = (int)($_POST['billing_id'] ?? 0);
        $amount = (float)($_POST['fee_amount'] ?? 0);
        $desc = trim((string)($_POST['fee_description'] ?? ''));

        if ($bid && $amount > 0 && $desc) {
            if (Billing::addFee($bid, $amount, $desc)) {
                AuditLogger::log((int)$u['user_id'], 'billing', 'UPDATE', $bid, "Added extra fee: $desc (₱$amount)");
                $_SESSION['success'] = "Additional charge added to invoice INV-" . str_pad((string)$bid, 6, '0', STR_PAD_LEFT);
            } else {
                $_SESSION['error'] = "Failed to add charge.";
            }
        } else {
            $_SESSION['error'] = "Invalid input for additional charge.";
        }
        redirect('/public/secretary/payments.php');
    }

    // Existing refund logic...
    if ($action === 'request_refund') {
        if ($bill['billing_status'] !== 'paid') {
            exit("Invoice is not eligible for refund.");
        }

        db()->prepare("UPDATE billing SET billing_status = 'refund_requested', updated_at = NOW() WHERE billing_id = ?")->execute([$billingId]);
        AuditLogger::log($u['user_id'], 'billing', 'UPDATE', $billingId, 'Requested Refund for Invoice #' . $billingId);

        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

// Fetch user data
$patient = User::findById((int)$bill['user_id']);

require_once __DIR__ . '/../../app/lib/fpdf.php';

class InvoicePDF extends FPDF
{
    function Header()
    {
        // Logo
        $logoPath = __DIR__ . '/../../public/assets/images/matriflow_banner.png';
        if (file_exists($logoPath)) {
            $this->Image($logoPath, 10, 8, 30);
        }

        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(33, 150, 243); // Primary Blue
        $this->Cell(0, 10, 'INVOICE', 0, 1, 'C');

        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(50);
        $this->Cell(0, 6, 'Commonwealth Hospital and Medical Center', 0, 1, 'C');

        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(100);
        $this->Cell(0, 5, 'Lot 3 & 4 Blk. 3 Neopolitan Business Park Regalado Highway Brgy. Greater Lagro, Novaliches, Quezon City, Metro Manila', 0, 1, 'C');
        $this->Cell(0, 5, 'Contact: (064) 421 2340 | Email: contact@commonwealthmed.com.ph', 0, 1, 'C');
        $this->Ln(10);

        $this->SetDrawColor(200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 5, 'Thank you for choosing MatriFlow. Please retain this invoice for your records.', 0, 1, 'C');
        $this->Cell(0, 5, 'This is a computer-generated document. No signature required.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    // Rotation support
    protected $angle = 0;

    function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;
        if ($this->angle != 0) $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    function RotatedText($x, $y, $txt, $angle)
    {
        //Text rotated around its origin
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

$pdf = new InvoicePDF();
$pdf->AddPage();

// Invoice Info
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 8, 'Bill To:', 0, 0);
$pdf->Cell(0, 8, 'Invoice Details:', 0, 1);

$pdf->SetFont('Arial', '', 10);

// Patient Info
$pY = $pdf->GetY();
$pdf->Cell(100, 5, $patient['first_name'] . ' ' . $patient['last_name'], 0, 1);
$pdf->Cell(100, 5, $patient['email'], 0, 1);
if (!empty($patient['contact_number'])) $pdf->Cell(100, 5, $patient['contact_number'], 0, 1);
$pdf->SetY($pY);

// Invoice Meta
$pdf->SetX(110);
$pdf->Cell(40, 5, 'Invoice #:', 0, 0);
$pdf->Cell(0, 5, 'INV-' . str_pad((string)$bill['billing_id'], 6, '0', STR_PAD_LEFT), 0, 1, 'R');

$pdf->SetX(110);
$pdf->Cell(40, 5, 'Date:', 0, 0);
$pdf->Cell(0, 5, date('M j, Y', strtotime($bill['created_at'])), 0, 1, 'R');

$pdf->SetX(110);
$pdf->Cell(40, 5, 'Due Date:', 0, 0);
$pdf->Cell(0, 5, $bill['due_date'] ? date('M j, Y', strtotime($bill['due_date'])) : 'Upon Receipt', 0, 1, 'R');

$pdf->SetX(110);
$pdf->Cell(40, 5, 'Status:', 0, 0);
$statusColor = match ($bill['billing_status']) {
    'paid' => [46, 125, 50], // Green
    'partial' => [255, 152, 0], // Orange
    default => [211, 47, 47] // Red
};
$pdf->SetTextColor(...$statusColor);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, strtoupper($bill['billing_status']), 0, 1, 'R');
$pdf->SetTextColor(0);

$pdf->Ln(20);

// Items Table
$items = Billing::getItems($billingId);

$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(130, 10, 'Description / Service', 1, 0, 'L', true);
$pdf->Cell(60, 10, 'Amount (PHP)', 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 10);
if (empty($items)) {
    // Fallback for legacy records
    $pdf->Cell(130, 10, $bill['service_description'], 1, 0, 'L');
    $pdf->Cell(60, 10, number_format((float)$bill['amount_due'], 2), 1, 1, 'R');
} else {
    foreach ($items as $item) {
        $pdf->Cell(130, 10, $item['description'], 1, 0, 'L');
        $pdf->Cell(60, 10, number_format((float)$item['amount'], 2), 1, 1, 'R');
    }
}

// Totals
$pdf->Ln(5);
$pdf->SetX(120);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 8, 'Total Amount:', 0, 0, 'R');
$pdf->Cell(30, 8, number_format((float)$bill['amount_due'], 2), 0, 1, 'R');

$pdf->SetX(120);
$pdf->Cell(40, 8, 'Amount Paid:', 0, 0, 'R');
$pdf->SetTextColor(46, 125, 50);
$pdf->Cell(30, 8, number_format((float)$bill['amount_paid'], 2), 0, 1, 'R');
$pdf->SetTextColor(0);

$balance = (float)$bill['amount_due'] - (float)$bill['amount_paid'];
$pdf->SetX(120);
$pdf->SetFillColor(255, 235, 238); // Red tint if unpaid
if ($balance <= 0) $pdf->SetFillColor(232, 245, 233); // Green tint if paid

$pdf->Cell(40, 10, 'Balance Due:', 0, 0, 'R', true);
$pdf->Cell(30, 10, number_format(max(0, $balance), 2), 0, 1, 'R', true);

// Payment History
if (!empty($bill['payment_notes'])) {
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Payment History', 0, 1);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->MultiCell(0, 5, $bill['payment_notes'], 1, 'L', true);
}

// Watermark if PAID
if ($balance <= 0) {
    $pdf->SetFont('Arial', 'B', 50);
    $pdf->SetTextColor(200, 230, 200); // Light green
    $pdf->RotatedText(50, 190, 'PAID IN FULL', 45);
}

$pdfContent = $pdf->Output('S');
$filename = 'Invoice_' . $billingId . '_' . date('YmdHis') . '.pdf';
$relativeDir = 'storage/invoices/';
$absDir = __DIR__ . '/../../storage/invoices/';

if (!is_dir($absDir)) {
    mkdir($absDir, 0777, true);
}

file_put_contents($absDir . $filename, $pdfContent);

// Log to documents table
try {
    $stmt = db()->prepare("INSERT INTO documents (uploader_user_id, user_id, file_name, file_path, file_type, category, description, uploaded_at) VALUES (?, ?, ?, ?, 'application/pdf', 'other', ?, NOW())");
    $stmt->execute([
        $u['user_id'],
        $bill['user_id'],
        $filename,
        $relativeDir . $filename,
        "Invoice #{$billingId} - {$bill['service_description']}"
    ]);

    $docId = db()->lastInsertId();
    if ($u['role'] !== 'patient') {
        AuditLogger::log($u['user_id'], 'documents', 'INSERT', $docId, "Generated automated invoice: $filename");
    }
} catch (Throwable $e) {
    error_log("Failed to log invoice document: " . $e->getMessage());
}

$pdf->Output('I', $filename);
