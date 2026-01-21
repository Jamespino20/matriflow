<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();
Auth::enforce2FA();

$u = Auth::user();
if (!$u)
    redirect('/');

$role = strtolower((string) ($u['role'] ?? ''));
$dashboardPath = base_url('/public/' . $role . '/dashboard.php');

// Redirect to role-specific dashboard
if (file_exists(__DIR__ . '/' . $role . '/dashboard.php')) {
    redirect($dashboardPath);
}

// Fallback: show a friendly message if no role-specific dashboard exists
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard - MatriFlow</title>
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/dashboard.css') ?>">
</head>

<body style="background:var(--bg)">
    <div class="container" style="padding:30px">
        <div class="card" style="max-width:720px;margin:0 auto">
            <h2 style="margin:0 0 8px 0">Dashboard unavailable</h2>
            <div class="help">A dashboard for role <strong><?= e($role) ?></strong> is not available. Please contact the
                administrator to request access or development.</div>
            <div style="margin-top:12px;display:flex;gap:8px">
                <a class="btn btn-outline" href="<?= base_url('/public/logout.php') ?>">Logout</a>
            </div>
        </div>
    </div>
</body>

</html>
<?php
exit;
