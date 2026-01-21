<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!$u)
    redirect('/');

if (Auth::is2FAVerifiedThisSession())
    redirect(base_url('/public/dashboard.php'));

$isInModal = !empty($_GET['modal']) || (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'login.php') !== false);

$errors = [];
$secret = (string) ($u['google_2fa_secret'] ?? '');

if ($secret === '' || (int) $u['is_2fa_enabled'] !== 1) {
    if ($isInModal) {
        header('Location: ' . base_url('/public/setup-2fa.php?modal=1'));
    } else {
        redirect(base_url('/public/setup-2fa.php'));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request (CSRF).';
    } else {
        $code = trim((string) ($_POST['otp'] ?? ''));
        if (!TOTP::verify($secret, $code, 1, 6)) {
            $errors[] = 'Invalid code.';
            AuditLogger::log((int) $u['user_id'], 'users', 'LOGIN', (int) $u['user_id'], '2fa_failed');
        } else {
            Auth::mark2FAVerified();
            AuditLogger::log((int) $u['user_id'], 'users', 'LOGIN', (int) $u['user_id'], '2fa_ok');

            // Ensure account is marked active
            db()->prepare("UPDATE users SET account_status = 'active' WHERE user_id = ?")->execute([$u['user_id']]);

            if ($isInModal) {
                // If in modal, signal parent to close and redirect
                header('Content-Type: text/html; charset=utf-8');
                echo '<!doctype html><html><head><meta charset="utf-8"><script>
                    if (window.parent !== window) {
                        window.parent.postMessage({type: "2fa_success", redirect: "' . base_url('/public/dashboard.php') . '"}, "*");
                    } else {
                        window.location.href = "' . base_url('/public/dashboard.php') . '";
                    }
                </script></head><body>Redirecting...</body></html>';
                exit;
            }
            redirect(base_url('/public/dashboard.php'));
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Verify 2FA - MatriFlow</title>
    <link rel="icon" href="<?= base_url('/public/assets/images/favicon.ico') ?>" />
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>" />
    <style>
        body {
            background: transparent;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }

        ::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }
    </style>
</head>

<body>
    <div class="modal-clean-center" style="width:100%">
        <div class="modal-body">
            <div class="icon-circle">
                <span style="font-size: 32px;">🔐</span>
            </div>
            <h2>Two-Factor Verification</h2>
            <p>Please enter the 6-digit code from your authenticator app to continue.</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?php echo e($err); ?></div>
            <?php endforeach; ?>

            <form method="post" action="<?= base_url('/public/verify-2fa.php' . ($isInModal ? '?modal=1' : '')) ?>">
                <?php echo CSRF::input(); ?>

                <div class="otp-container" style="margin: 32px 0 40px;">
                    <input class="input" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                        placeholder="000 000" style="text-align:center; letter-spacing: 8px; font-size: 28px; font-weight: 700; width: 240px; margin: 0 auto; height: 64px;" required autofocus>
                </div>

                <button class="btn btn-primary" style="width:100%; height:52px" type="submit">Verify Identity</button>

                <?php if (!$isInModal): ?>
                    <div style="margin-top:24px; display:flex; gap:16px; justify-content:center">
                        <a href="<?= base_url('/public/logout.php') ?>" style="color:var(--muted); font-size:14px">Logout</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>

</html>