<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

$settings = [];

try {
    $stmt = db()->prepare("SELECT setting_key, setting_value FROM system_settings");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {
    error_log("Failed to load system settings: " . $e->getMessage());
}

ob_start();
?>
<div class="card">
    <h2>System Settings</h2>
    <p>Configure system-wide settings and preferences.</p>
    <form method="POST" action="<?= base_url('/public/controllers/system-handler.php') ?>">
        <?= CSRF::input() ?>
        <div class="form-group" style="margin-top: 20px;">
            <label>System/Business Name</label>
            <input type="text" name="system_name" value="<?= htmlspecialchars($settings['system_name'] ?? '') ?>" placeholder="MatriFlow - CHMC">
            <small style="color: var(--text-secondary);">Used in page titles and branding</small>
        </div>

        <h3 style="margin-top: 32px; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Email Configuration</h3>

        <div class="form-group">
            <label>Email From Name</label>
            <input type="text" name="email_from_name" value="<?= htmlspecialchars($settings['email_from_name'] ?? '') ?>" placeholder="MatriFlow - CHMC Maternal Health System">
            <small style="color: var(--text-secondary);">Display name for system emails</small>
        </div>

        <div class="form-group">
            <label>Email From Address</label>
            <input type="email" name="email_from_address" value="<?= htmlspecialchars($settings['email_from_address'] ?? '') ?>" placeholder="noreply@matriflow.infinityfreeapp.com">
            <small style="color: var(--text-secondary);">Sender email address for system emails</small>
        </div>

        <div class="form-group">
            <label>Admin Email</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" placeholder="admin@chmc.com">
            <small style="color: var(--text-secondary);">Main administration contact email</small>
        </div>

        <h3 style="margin-top: 32px; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">Payment Policies</h3>

        <div class="form-group">
            <label>Maternity/Prenatal Payment Due (Days)</label>
            <input type="number" name="policy_maternity_days" value="<?= htmlspecialchars($settings['policy_maternity_days'] ?? '7') ?>" min="1" max="90" placeholder="7">
            <small style="color: var(--text-secondary);">Number of days before maternity enrollment/visit fees are due</small>
        </div>

        <div class="form-group">
            <label>Gynecological Services Payment Due (Days)</label>
            <input type="number" name="policy_gyne_days" value="<?= htmlspecialchars($settings['policy_gyne_days'] ?? '7') ?>" min="1" max="90" placeholder="7">
            <small style="color: var(--text-secondary);">Number of days before gynecological service fees are due</small>
        </div>

        <div class="form-group">
            <label>Consultation Payment Due (Days)</label>
            <input type="number" name="policy_consultation_days" value="<?= htmlspecialchars($settings['policy_consultation_days'] ?? '7') ?>" min="1" max="90" placeholder="7">
            <small style="color: var(--text-secondary);">Number of days before consultation fees are due</small>
        </div>

        <div class="form-group">
            <label>Lab Test Payment Due (Days)</label>
            <input type="number" name="policy_lab_days" value="<?= htmlspecialchars($settings['policy_lab_days'] ?? '7') ?>" min="1" max="90" placeholder="7">
            <small style="color: var(--text-secondary);">Number of days before lab test fees are due</small>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 16px;">Save Settings</button>
    </form>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'system', [
    'title' => 'System Settings',
    'content' => $content,
]);
