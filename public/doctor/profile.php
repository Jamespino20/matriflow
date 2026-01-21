<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'doctor')
    redirect('/');

$flash = $_SESSION['flash_profile'] ?? null;
unset($_SESSION['flash_profile']);

ob_start();
?>
<div class="profile-container" style="max-width: 900px; margin: 0 auto;">
    <?php if ($flash): ?>
        <div class="alert alert-success" style="margin-bottom: 24px;">
            <span class="material-symbols-outlined">check_circle</span>
            <div class="alert-content">
                <strong>Success</strong>
                <p><?= htmlspecialchars($flash) ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: start;">
        <!-- Left Column: Avatar & Quick Stats -->
        <div style="position: sticky; top: 24px;">
            <div class="card" style="text-align: center; padding: 32px 24px;">
                <div style="position: relative; display: inline-block; margin-bottom: 16px;">
                    <img src="<?= FileService::getAvatarUrl((int)$u['user_id']) ?>"
                        alt="Avatar"
                        class="profile-avatar-img"
                        onerror="this.onerror=null; this.src='<?= base_url('/public/assets/images/default-avatar.png') ?>'"
                        style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border-light); box-shadow: var(--shadow-md);">
                    <button type="button" data-avatar-trigger class="btn btn-secondary" style="position: absolute; bottom: 0; right: 0; padding: 8px; border-radius: 50%; min-width: auto;">
                        <span class="material-symbols-outlined" style="font-size: 18px;">photo_camera</span>
                    </button>
                </div>
                <h2 style="margin: 0; font-size: 20px;"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></h2>
                <p style="color: var(--text-secondary); font-size: 14px; margin-top: 4px;"><?= ucfirst($u['role']) ?></p>

                <div style="margin-top: 24px; text-align: left; background: var(--bg-light); padding: 16px; border-radius: 12px;">
                    <label style="font-size: 10px; opacity: 0.6; display: block; margin-bottom: 4px;">Staff Since</label>
                    <span style="font-size: 13px; font-weight: 500;"><?= date('F Y', strtotime($u['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Right Column: Forms -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <form id="profile-form" action="<?= base_url('/public/controllers/profile-handler.php') ?>" method="POST" class="card" style="padding: 32px;">
                <input type="hidden" name="action" value="update_profile">
                <?= CSRF::input() ?>

                <h3 style="margin-bottom: 24px; border-bottom: 1px solid var(--border-light); padding-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined">person</span> Personal Information
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>First Name (Locked)</label>
                        <input type="text" value="<?= htmlspecialchars((string)($u['first_name'] ?? '')) ?>" disabled style="background: var(--bg-light); cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars((string)($u['middle_name'] ?? '')) ?>" placeholder="Middle Name">
                    </div>
                    <div class="form-group">
                        <label>Last Name (Locked)</label>
                        <input type="text" value="<?= htmlspecialchars((string)($u['last_name'] ?? '')) ?>" disabled style="background: var(--bg-light); cursor: not-allowed;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars((string)($u['username'] ?? '')) ?>" required placeholder="Enter unique username">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars((string)($u['email'] ?? '')) ?>" required placeholder="name@example.com">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" value="<?= htmlspecialchars((string)($u['dob'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Marital Status</label>
                        <select name="marital_status" class="input">
                            <option value="">Select Status</option>
                            <?php foreach (['Single', 'Married', 'Divorced', 'Widowed', 'Separated'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($u['marital_status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Occupation / Specialization</label>
                        <input type="text" name="occupation" value="<?= htmlspecialchars((string)($u['occupation'] ?? '')) ?>" placeholder="e.g. OB-GYN">
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" name="nationality" value="<?= htmlspecialchars((string)($u['nationality'] ?? '')) ?>" placeholder="e.g. Filipino">
                    </div>
                </div>

                <div class="form-group">
                    <label>Contact Number (Own)</label>
                    <input type="tel" name="contact_number" value="<?= htmlspecialchars((string)($u['contact_number'] ?? '')) ?>" placeholder="+63 9xx xxx xxxx">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_contact_name" value="<?= htmlspecialchars((string)($u['emergency_contact_name'] ?? '')) ?>" placeholder="Contact Person">
                    </div>
                    <div class="form-group">
                        <label>Emergency Contact Number</label>
                        <input type="tel" name="emergency_contact_number" value="<?= htmlspecialchars((string)($u['emergency_contact_number'] ?? '')) ?>" placeholder="+63 9xx xxx xxxx">
                    </div>
                </div>

                <div class="form-group">
                    <label>Street / Barangay</label>
                    <textarea name="address" placeholder="Unit/Street, Barangay"><?= htmlspecialchars((string)($u['address'] ?? '')) ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>City / Municipality</label>
                        <input type="text" name="city" value="<?= htmlspecialchars((string)($u['city'] ?? '')) ?>" placeholder="City">
                    </div>
                    <div class="form-group">
                        <label>Province</label>
                        <input type="text" name="province" value="<?= htmlspecialchars((string)($u['province'] ?? '')) ?>" placeholder="Province">
                    </div>
                </div>

                <div style="margin-top: 32px; border-top: 1px solid var(--border-light); padding-top: 24px;">
                    <h3 style="margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
                        <span class="material-symbols-outlined">security</span> Security
                    </h3>

                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="input-group">
                            <input type="password" id="doc_p_curr" name="password_current" placeholder="Required for password changes" style="padding-right:40px;">
                            <button type="button" class="toggle" data-password-toggle data-target="doc_p_curr">
                                <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                            </button>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-group">
                                <input type="password" id="doc_p_new" name="password_new" placeholder="Min 8 characters" style="padding-right:40px;">
                                <button type="button" class="toggle" data-password-toggle data-target="doc_p_new">
                                    <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" id="doc_p_confirm" name="password_confirm" placeholder="Repeat new password" style="padding-right:40px;">
                                <button type="button" class="toggle" data-password-toggle data-target="doc_p_confirm">
                                    <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 32px; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="reset" class="btn btn-secondary">Discard Changes</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <div class="card" style="padding: 32px;">
                <h3 style="margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined">settings</span> Preferences & Actions
                </h3>

                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-light); padding: 16px; border-radius: 12px;">
                    <div>
                        <label style="margin: 0; text-transform: none; font-size: 14px;">Dark Mode</label>
                        <p style="font-size: 11px; color: var(--text-secondary); margin: 0;">Switch to high-contrast dark theme</p>
                    </div>
                    <button type="button" data-theme-toggle class="btn btn-secondary" style="min-width: auto; padding: 8px 16px;">
                        <span class="material-symbols-outlined">dark_mode</span>
                    </button>
                </div>

                <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 12px;">
                    <form action="<?= base_url('/public/controllers/profile-handler.php') ?>" method="POST" style="display:inline;">
                        <?= CSRF::input() ?>
                        <input type="hidden" name="action" value="backup_account">
                        <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: flex-start; gap: 12px;">
                            <span class="material-symbols-outlined">download</span> Download Data Backup (JSON)
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-secondary);
        text-transform: uppercase;
        margin-bottom: 8px;
        letter-spacing: 0.05em;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--surface);
        color: var(--text-primary);
        font-size: 14px;
        transition: all 0.2s;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(20, 69, 123, 0.1);
    }

    .form-group textarea {
        min-height: 80px;
        resize: vertical;
    }

    .btn {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        border: 1px solid transparent;
        transition: all 0.2s;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-light);
    }

    .btn-secondary {
        background: var(--surface);
        border-color: var(--border);
        color: var(--text-primary);
    }

    .btn-secondary:hover {
        background: var(--surface-hover);
    }
</style>

<?php
$content = ob_get_clean();
RoleLayout::render($u, 'doctor', 'profile', [
    'title' => 'My Profile',
    'content' => $content,
]);
