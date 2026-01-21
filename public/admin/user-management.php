<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

ob_start();
?>
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h2 style="margin:0">User Management</h2>
            <p style="margin:5px 0 0; font-size:14px; color:var(--text-secondary)">Create, edit, and manage user accounts.</p>
        </div>
        <a href="#" class="btn btn-primary" style="display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined" style="font-size:18px">add</span>
            Add New User
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="search-container">
            <span class="material-symbols-outlined">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" placeholder="Search by name, email, or username...">
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="is_active">
                <option value="">All Statuses</option>
                <option value="1" <?= ($_GET['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= ($_GET['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role">
                <option value="">All Roles</option>
                <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="doctor" <?= ($_GET['role'] ?? '') === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                <option value="secretary" <?= ($_GET['role'] ?? '') === 'secretary' ? 'selected' : '' ?>>Secretary</option>
                <option value="patient" <?= ($_GET['role'] ?? '') === 'patient' ? 'selected' : '' ?>>Patient</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php $hasFilters = !empty($_GET['q']) || !empty($_GET['role']) || !empty($_GET['is_active']); ?>
            <a href="<?= base_url('/public/admin/user-management.php') ?>" class="btn btn-outline <?= !$hasFilters ? 'disabled' : '' ?>" style="text-decoration:none" <?= !$hasFilters ? 'onclick="return false;"' : '' ?>>Reset</a>
        </div>
    </form>

    <?php
    // Whitelist filters to prevent random URL params (like 'success') from breaking SearchHelper
    $allowedFilters = ['q', 'role', 'is_active', 'date_from', 'date_to'];
    $filters = array_intersect_key($_GET, array_flip($allowedFilters));

    list($where, $params) = SearchHelper::buildWhere($filters, ['u.first_name', 'u.last_name', 'u.email', 'u.username']);

    $query = "SELECT u.* FROM `users` u $where ORDER BY u.created_at DESC";
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    ?>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th style="text-align:right">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding:40px; color: var(--text-secondary);">
                        <span class="material-symbols-outlined" style="font-size:48px; display:block; margin-bottom:10px; opacity:0.5;">search_off</span>
                        No users found matching your criteria.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td style="display:flex; align-items:center; gap:10px;">
                            <img src="<?= FileService::getAvatarUrl((int)$user['user_id']) ?>"
                                onerror="this.onerror=null; this.src='<?= base_url('/public/assets/images/default-avatar.png') ?>'"
                                style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars((string)($user['first_name'] . ' ' . $user['last_name'])) ?></div>
                                <div style="font-size:12px; color:var(--text-secondary);">@<?= htmlspecialchars((string)$user['username']) ?></div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)$user['email']) ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower((string)$user['role']) ?>">
                                <?= ucfirst((string)$user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int)$user['is_active'] === 1): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-error">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:13px; color:var(--text-secondary);"><?= date('M j, Y', strtotime((string)$user['created_at'])) ?></td>
                        <td style="text-align:right;">
                            <button class="btn btn-icon" title="Edit User" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <button class="btn btn-icon" style="color:<?= (int)$user['is_active'] === 1 ? 'var(--danger)' : 'var(--success)' ?>" title="<?= (int)$user['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> User" onclick="toggleUserStatus(<?= $user['user_id'] ?>)">
                                <span class="material-symbols-outlined"><?= (int)$user['is_active'] === 1 ? 'block' : 'check_circle' ?></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modals -->
<div id="modal-user" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:95%; max-width:900px; max-height:90vh; overflow-y:auto; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-user').classList.remove('show')">&times;</button>
        <h3 id="modal-title">Add New User</h3>
        <form id="user-form" action="<?= base_url('/public/controllers/user-handler.php') ?>" method="POST" style="margin-top: 20px;" onsubmit="return validateAge()">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" id="form-action" value="create_user">
            <input type="hidden" name="target_user_id" id="target-user-id" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>First Name</label><input type="text" name="first_name" id="f-first" required></div>
                <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" id="f-middle"></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="last_name" id="f-last" required></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>Username</label><input type="text" name="username" id="f-username" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="f-email" required></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="f-role" required onchange="togglePatientFields(this.value)">
                        <option value="patient">Patient</option>
                        <option value="secretary">Secretary</option>
                        <option value="doctor">Doctor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group"><label>Gender</label>
                    <select name="gender" id="f-gender">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group"><label>Marital Status</label>
                    <select name="marital_status" id="f-marital">
                        <option value="">Select</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                        <option value="Separated">Separated</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" id="f-dob" onchange="checkAgeInline()">
                    <div id="age-warning" style="color:var(--error); font-size:11px; margin-top:4px; display:none;"></div>
                </div>
                <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" id="f-contact"></div>
            </div>

            <div id="guardian-fields" style="display: none; background: var(--surface-light); padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid var(--border);">
                <p style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--primary);">Emergency Contact Information (Required for Minors)</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group"><label>Contact Name</label><input type="text" name="emergency_contact_name" id="f-emergency-name"></div>
                    <div class="form-group"><label>Contact Number</label><input type="text" name="emergency_contact_number" id="f-emergency-number"></div>
                </div>
            </div>

            <div class="form-group"><label>Address</label><input type="text" name="address" id="f-address"></div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>City</label><input type="text" name="city" id="f-city"></div>
                <div class="form-group"><label>Province</label><input type="text" name="province" id="f-province"></div>
            </div>

            <div class="form-group"><label>Occupation</label><input type="text" name="occupation" id="f-occupation"></div>

            <div class="form-group" id="pass-group">
                <label>Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="f-pass" required style="padding-right:40px;">
                    <button type="button" class="toggle" data-password-toggle data-target="f-pass">
                        <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Save User</button>
            <div id="reset-2fa-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border); display:none;">
                <p style="font-size: 12px; color: var(--text-secondary); margin-bottom: 10px;">If the user lost their 2FA device, you can reset it. They will be forced to set it up again upon next login.</p>
                <button type="button" class="btn btn-outline" style="width:100%; color:var(--danger); border-color:var(--danger);" onclick="reset2FA()">Reset Two-Factor Authentication</button>
            </div>
        </form>
    </div>
</div>

<form id="reset-2fa-form" action="<?= base_url('/public/controllers/user-handler.php') ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
    <input type="hidden" name="action" value="reset_2fa">
    <input type="hidden" name="user_id" id="reset-2fa-user-id">
</form>

<form id="toggle-status-form" action="<?= base_url('/public/controllers/user-handler.php') ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggle-user-id">
</form>

<!-- Status Confirmation Modal -->
<div id="modal-status-confirm" class="modal-overlay modal-clean-center">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:24px; position:relative;">
        <h3 style="margin-top:0;">Confirm Action</h3>
        <p id="status-confirm-msg" style="color:var(--text-secondary); margin:10px 0 20px;">Are you sure?</p>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-status-confirm').classList.remove('show')">Cancel</button>
            <button type="button" class="btn btn-primary" id="status-confirm-btn">Confirm</button>
        </div>
    </div>
</div>

<script>
    function editUser(user) {
        document.getElementById('modal-title').innerText = 'Edit User: ' + user.username;
        document.getElementById('form-action').value = 'update_user';
        document.getElementById('target-user-id').value = user.user_id;
        document.getElementById('f-first').value = user.first_name;
        document.getElementById('f-middle').value = user.middle_name || '';
        document.getElementById('f-last').value = user.last_name;
        document.getElementById('f-username').value = user.username;
        document.getElementById('f-username').disabled = true;
        document.getElementById('f-email').value = user.email;
        document.getElementById('f-role').value = user.role;
        document.getElementById('f-gender').value = user.gender || 'Female';
        document.getElementById('f-marital').value = user.marital_status || '';
        document.getElementById('f-dob').value = user.dob || '';
        document.getElementById('f-contact').value = user.contact_number || '';
        document.getElementById('f-address').value = user.address || '';
        document.getElementById('f-occupation').value = user.occupation || '';
        document.getElementById('f-city').value = user.city || '';
        document.getElementById('f-province').value = user.province || '';
        document.getElementById('f-emergency-name').value = user.emergency_contact_name || '';
        document.getElementById('f-emergency-number').value = user.emergency_contact_number || '';

        checkAgeInline();
        document.getElementById('pass-group').style.display = 'none';
        document.getElementById('f-pass').required = false;
        document.getElementById('reset-2fa-section').style.display = (user.is_2fa_enabled == 1) ? 'block' : 'none';
        document.getElementById('modal-user').classList.add('show');
    }

    function checkAgeInline() {
        const dob = document.getElementById('f-dob').value;
        const role = document.getElementById('f-role').value;
        const warningEl = document.getElementById('age-warning');
        const guardianSection = document.getElementById('guardian-fields');

        if (!dob) {
            warningEl.style.display = 'none';
            guardianSection.style.display = 'none';
            return;
        }

        const birthDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        let warning = '';
        let showGuardian = false;

        if (role === 'patient') {
            if (age < 13) {
                warning = 'Patients must be at least 13 years old.';
            } else if (age < 18) {
                showGuardian = true;
            }
        } else {
            if (age < 21) {
                warning = 'Staff and Admin accounts require users to be at least 21 years old.';
            }
        }

        if (warning) {
            warningEl.innerText = warning;
            warningEl.style.display = 'block';
        } else {
            warningEl.style.display = 'none';
        }

        guardianSection.style.display = showGuardian ? 'block' : 'none';

        // Reset requirements if not shown
        if (!showGuardian) {
            document.getElementById('f-emergency-name').required = false;
            document.getElementById('f-emergency-number').required = false;
        } else {
            document.getElementById('f-emergency-name').required = true;
            document.getElementById('f-emergency-number').required = true;
        }
    }

    function togglePatientFields(role) {
        checkAgeInline();
    }

    function reset2FA() {
        const id = document.getElementById('target-user-id').value;
        if (confirm('Are you sure you want to reset 2FA for this user? This cannot be undone.')) {
            document.getElementById('reset-2fa-user-id').value = id;
            document.getElementById('reset-2fa-form').submit();
        }
    }

    function showAddUser() {
        document.getElementById('modal-title').innerText = 'Add New User';
        document.getElementById('form-action').value = 'create_user';
        document.getElementById('target-user-id').value = '';
        document.getElementById('user-form').reset();
        document.getElementById('f-username').disabled = false;
        document.getElementById('pass-group').style.display = 'block';
        document.getElementById('f-pass').required = true;
        document.getElementById('reset-2fa-section').style.display = 'none';
        checkAgeInline();
        document.getElementById('modal-user').classList.add('show');
    }

    function toggleUserStatus(id) {
        document.getElementById('toggle-user-id').value = id;
        document.getElementById('status-confirm-msg').innerText = 'Are you sure you want to change the status of this user?';
        document.getElementById('status-confirm-btn').onclick = function() {
            document.getElementById('toggle-status-form').submit();
        };
        document.getElementById('modal-status-confirm').classList.add('show');
    }

    function validateAge() {
        const warningEl = document.getElementById('age-warning');
        if (warningEl.style.display === 'block') {
            return false;
        }
        return true;
    }

    // Hook up the "Add New User" button
    document.querySelector('.btn-primary[href="#"]').addEventListener('click', (e) => {
        e.preventDefault();
        showAddUser();
    });
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'user-management', [
    'title' => 'User Management',
    'content' => $content,
]);
