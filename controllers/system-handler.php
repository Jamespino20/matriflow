<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin') redirect('/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/public/admin/system.php');
if (!CSRF::validate($_POST['csrf_token'] ?? null)) die('Invalid CSRF');

$systemName = trim($_POST['system_name'] ?? '');
$adminEmail = trim($_POST['admin_email'] ?? '');

// Upsert settings
try {
    $stmt = db()->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    $stmt->execute(['system_name', $systemName]);
    $stmt->execute(['admin_email', $adminEmail]);

    $logDetails = "Updated System Name to '$systemName', Admin Email to '$adminEmail'";
    AuditLogger::log((int)$u['user_id'], 'system_settings', 'UPDATE', null, $logDetails);

    redirect('/public/admin/system.php?success=1');
} catch (Exception $e) {
    // Should probably log error or show message, but for now just redirect back
    redirect('/public/admin/system.php?error=1');
}
