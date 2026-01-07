<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

$system_name = '';
$admin_email = '';

try {
    $stmt = db()->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $system_name = $settings['system_name'] ?? '';
    $admin_email = $settings['admin_email'] ?? '';
} catch (Throwable $e) {
}

ob_start();
?>
<div class="card">
    <h2>System Settings</h2>
    <p>Configure system-wide settings and preferences.</p>
    <form method="POST" action="<?= base_url('/public/controllers/system-handler.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
        <div class="form-group" style="margin-top: 20px;">
            <label>System Name</label>
            <input type="text" name="system_name" value="<?= $system_name ?>">
        </div>
        <div class="form-group">
            <label>Email Configuration</label>
            <input type="email" name="admin_email" value="<?= $admin_email ?>">
        </div>
        <button class="btn btn-primary">Save Settings</button>
    </form>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'system', [
    'title' => 'System Settings',
    'content' => $content,
]);
