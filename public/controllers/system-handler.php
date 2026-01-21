<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin') redirect('/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/public/admin/system.php');
if (!CSRF::validate($_POST['csrf_token'] ?? null)) die('Invalid CSRF');

$systemName = trim($_POST['system_name'] ?? '');
$adminEmail = trim($_POST['admin_email'] ?? '');
$emailFromName = trim($_POST['email_from_name'] ?? '');
$emailFromAddress = trim($_POST['email_from_address'] ?? '');
$policyConsultationDays = (int)($_POST['policy_consultation_days'] ?? 7);
$policyLabDays = (int)($_POST['policy_lab_days'] ?? 7);
$policyMaternityDays = (int)($_POST['policy_maternity_days'] ?? 7);
$policyGyneDays = (int)($_POST['policy_gyne_days'] ?? 7);

// Upsert settings
try {
    $stmt = db()->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['system_name', $systemName]);
    $stmt->execute(['admin_email', $adminEmail]);
    $stmt->execute(['email_from_name', $emailFromName]);
    $stmt->execute(['email_from_address', $emailFromAddress]);
    $stmt->execute(['policy_consultation_days', (string)$policyConsultationDays]);
    $stmt->execute(['policy_lab_days', (string)$policyLabDays]);
    $stmt->execute(['policy_maternity_days', (string)$policyMaternityDays]);
    $stmt->execute(['policy_gyne_days', (string)$policyGyneDays]);

    $logDetails = "Updated System Settings: Name='$systemName', Email='$emailFromAddress', ConsultDays=$policyConsultationDays, LabDays=$policyLabDays, MatDays=$policyMaternityDays, GyneDays=$policyGyneDays";
    AuditLogger::log((int)$u['user_id'], 'system_settings', 'UPDATE', null, $logDetails);

    redirect('/public/admin/system.php?success=1');
} catch (Exception $e) {
    // Should probably log error or show message, but for now just redirect back
    redirect('/public/admin/system.php?error=1');
}
