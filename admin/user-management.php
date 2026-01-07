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
    <form method="GET" class="filter-bar" style="display:flex; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="flex:1; position:relative;">
            <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" placeholder="Search by name, email, or username..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>

        <select name="is_active" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
            <option value="">All Statuses</option>
            <option value="1" <?= ($_GET['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= ($_GET['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>

        <select name="role" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
            <option value="">All Roles</option>
            <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="doctor" <?= ($_GET['role'] ?? '') === 'doctor' ? 'selected' : '' ?>>Doctor</option>
            <option value="secretary" <?= ($_GET['role'] ?? '') === 'secretary' ? 'selected' : '' ?>>Secretary</option>
            <option value="patient" <?= ($_GET['role'] ?? '') === 'patient' ? 'selected' : '' ?>>Patient</option>
        </select>

        <div style="display:flex; align-items:center; gap:8px;">
            <input type="date" name="date_from" value="<?= htmlspecialchars((string)($_GET['date_from'] ?? '')) ?>" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
            <span style="font-size:12px; color:var(--text-secondary);">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars((string)($_GET['date_to'] ?? '')) ?>" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
        </div>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (!empty($_GET)): ?>
            <a href="<?= base_url('/public/admin/user-management.php') ?>" class="btn btn-outline" title="Clear Filters">Clear</a>
        <?php endif; ?>
    </form>

    <?php
    // Whitelist filters to prevent random URL params (like 'success') from breaking SearchHelper
    $allowedFilters = ['q', 'role', 'is_active', 'date_from', 'date_to'];
    $filters = array_intersect_key($_GET, array_flip($allowedFilters));

    list($where, $params) = SearchHelper::buildWhere($filters, ['u.first_name', 'u.last_name', 'u.email', 'u.username']);

    $query = "SELECT u.* FROM `user` u $where ORDER BY u.created_at DESC";
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
                            <img src="<?= base_url('/public/assets/images/avatars/' . (int)$user['user_id'] . '.png?t=' . time()) ?>"
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
                                <span style="display:inline-flex; align-items:center; gap:4px; color:var(--success); font-size:13px; font-weight:600;">
                                    <span style="width:8px; height:8px; background:var(--success); border-radius:50%;"></span>
                                    Active
                                </span>
                            <?php else: ?>
                                <span style="display:inline-flex; align-items:center; gap:4px; color:var(--danger); font-size:13px; font-weight:600;">
                                    <span style="width:8px; height:8px; background:var(--danger); border-radius:50%;"></span>
                                    Inactive
                                </span>
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
<div id="modal-user" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
        <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-user').style.display='none'">&times;</button>
        <h3 id="modal-title">Add New User</h3>
        <form id="user-form" action="<?= base_url('/public/controllers/user-handler.php') ?>" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
            <input type="hidden" name="action" id="form-action" value="create_user">
            <input type="hidden" name="target_user_id" id="target-user-id" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group"><label>First Name</label><input type="text" name="first_name" id="f-first" required></div>
                <div class="form-group"><label>Last Name</label><input type="text" name="last_name" id="f-last" required></div>
            </div>
            <div class="form-group"><label>Username</label><input type="text" name="username" id="f-username" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" id="f-email" required></div>
            <div class="form-group" id="pass-group"><label>Password</label><input type="password" name="password" id="f-pass" required></div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" id="f-role" required>
                    <option value="patient">Patient</option>
                    <option value="secretary">Secretary</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Save User</button>
        </form>
    </div>
</div>

<form id="toggle-status-form" action="<?= base_url('/public/controllers/user-handler.php') ?>" method="POST" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggle-user-id">
</form>

<script>
    function editUser(user) {
        document.getElementById('modal-title').innerText = 'Edit User: ' + user.username;
        document.getElementById('form-action').value = 'update_user';
        document.getElementById('target-user-id').value = user.user_id;
        document.getElementById('f-first').value = user.first_name;
        document.getElementById('f-last').value = user.last_name;
        document.getElementById('f-username').value = user.username;
        document.getElementById('f-username').disabled = true;
        document.getElementById('f-email').value = user.email;
        document.getElementById('f-role').value = user.role;
        document.getElementById('pass-group').style.display = 'none';
        document.getElementById('f-pass').required = false;
        document.getElementById('modal-user').style.display = 'flex';
    }

    function showAddUser() {
        document.getElementById('modal-title').innerText = 'Add New User';
        document.getElementById('form-action').value = 'create_user';
        document.getElementById('target-user-id').value = '';
        document.getElementById('user-form').reset();
        document.getElementById('f-username').disabled = false;
        document.getElementById('pass-group').style.display = 'block';
        document.getElementById('f-pass').required = true;
        document.getElementById('modal-user').style.display = 'flex';
    }

    function toggleUserStatus(id) {
        if (confirm('Are you sure you want to change this user\'s status?')) {
            document.getElementById('toggle-user-id').value = id;
            document.getElementById('toggle-status-form').submit();
        }
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
