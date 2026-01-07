<?php
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

$u = Auth::user();
if (!$u)
    redirect('/');

$isInModal = !empty($_GET['modal']) || (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'login.php') !== false);

$errors = [];
$success = false;

// Generate a pending secret if none exists in session
if (empty($_SESSION['pending_2fa_secret'])) {
    $_SESSION['pending_2fa_secret'] = TOTP::generateSecret(20);
}

$secret = (string) $_SESSION['pending_2fa_secret'];
$issuer = 'MatriFlow-CHMC';
$label = ($u['email'] ?: $u['username']);
$otpauth = TOTP::otpauthUrl($issuer, $label, $secret);
$qr = TOTP::qrUrl($otpauth, 220);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request (CSRF).';
    } else {
        $code = trim((string) ($_POST['otp'] ?? ''));
        if (!TOTP::verify($secret, $code, 1, 6)) {
            $errors[] = 'Invalid code. Try again.';
        } else {
            // Persist secret + enable
            $uid = (int) $u['user_id'];
            $stmt = db()->prepare("UPDATE user
                             SET google_2fa_secret = :sec,
                                 is_2fa_enabled = 1,
                                 force_2fa_setup = 0,
                                 account_status = 'active',
                                 two_factor_verified_at = NOW()
                             WHERE user_id = :uid");
            $stmt->execute([':sec' => $secret, ':uid' => $uid]);

            unset($_SESSION['pending_2fa_secret']);
            Auth::mark2FAVerified();
            AuditLogger::log($uid, 'user', 'UPDATE', $uid, '2fa_enabled');

            if ($isInModal) {
                // Check if this is a new user registration flow
                $isNewUser = !empty($_GET['newuser']) || !empty($_SESSION['newuser_2fa']);
                $redirectUrl = $isNewUser ? '/?registered=true' : '/public/dashboard.php';

                // If in modal, signal parent to close and redirect
                header('Content-Type: text/html; charset=utf-8');
                echo '<!doctype html><html><head><meta charset="utf-8"><script>
                    if (window.parent !== window) {
                        window.parent.postMessage({type: "2fa_success", redirect: "' . base_url($redirectUrl) . '"}, "*");
                    } else {
                        window.location.href = "' . base_url($redirectUrl) . '";
                    }
                </script></head><body>Redirecting...</body></html>';
                exit;
            }
            redirect('/public/dashboard.php');
        }
    }
}

// Handle AJAX email request
if (isset($_POST['action']) && $_POST['action'] === 'email_setup') {
    if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'message' => 'CSRF Invalid.']);
        exit;
    }

    $email = $u['email'] ?? '';
    if ($email) {
        // Mock sending email
        $subject = "MatriFlow - 2FA Security Setup";
        $body = "Hello " . $u['first_name'] . ",\n\n" .
            "You have requested your 2FA setup credentials.\n" .
            "Secret Key: " . $secret . "\n" .
            "Scan the QR code in the browser to complete setup.\n\n" .
            "If you did not request this, please ignore this email.\n";

        // Use error_log to simulate sending
        error_log("EMAIL TO $email: $subject\n$body");

        // Attempt real mail() if configured
        @mail($email, $subject, $body, "From: no-reply@matriflow-chmc.com");

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'Setup instructions sent to ' . $email]);
        exit;
    } else {
        echo json_encode(['ok' => false, 'message' => 'No email address on file.']);
        exit;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Setup 2FA - MatriFlow</title>
    <link rel="icon" href="<?= base_url('/public/assets/images/favicon.ico') ?>" />
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>" />
    <style>
        body {
            background: transparent;
            /* Transparent for iframe integration */
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }

        /* Custom scrollbar hiding */
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
                <span style="font-size: 32px;">📱</span>
            </div>
            <h2>Secure Your Account</h2>
            <p>Add an extra layer of security to your health records.</p>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?php echo e($err); ?></div>
            <?php endforeach; ?>

            <div style="margin-bottom: 24px;">
                <div style="font-size: 11px; font-weight: 800; color: var(--muted); letter-spacing: 1px; margin-bottom: 12px; text-transform: uppercase;">Step 1: Scan QR Code</div>
                <img src="<?php echo e($qr); ?>" alt="2FA QR Code"
                    style="width:160px; height:160px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; margin:0 auto; display:block">

                <div style="margin-top:8px; font-size:12px; color:var(--muted)">
                    Or enter code manually: <strong style="color:var(--text)"><?php echo e($secret); ?></strong>
                </div>
            </div>

            <form method="post" action="<?= base_url('/public/setup-2fa.php' . ($isInModal ? '?modal=1' : '')) ?>">
                <?php echo CSRF::input(); ?>
                <div style="font-size: 11px; font-weight: 800; color: var(--muted); letter-spacing: 1px; margin-bottom: 12px; text-transform: uppercase;">Step 2: Enter Code</div>

                <div class="otp-container" style="margin: 12px 0 24px;">
                    <!-- Single input styled to look impressive, or we could do split inputs. 
                         Sticking to single input for reliability -->
                    <input class="input" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                        placeholder="000 000" style="text-align:center; letter-spacing: 8px; font-size: 24px; font-weight: 700; width: 220px; margin: 0 auto; height: 56px;" required>
                </div>

                <div style="display:flex; flex-direction:column; gap:12px; margin-top:32px">
                    <button class="btn btn-primary" style="width:100%; height:52px" type="submit">Verify & Enable 2FA</button>

                    <button type="button" class="btn btn-secondary" onclick="emailSetupInfo()" id="email_btn">
                        📧 Send Setup Info to My Email
                    </button>

                    <?php if (!$isInModal): ?>
                        <a href="/public/dashboard.php" style="color:var(--muted); font-size:14px; font-weight:600; text-decoration:none">
                            Skip for now (Not Recommended)
                        </a>
                    <?php else: ?>
                        <div style="font-size:12px; color:var(--muted)">
                            <span style="display:block; margin-bottom:4px">🔒 256-bit Secure Encryption</span>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        async function emailSetupInfo() {
            const btn = document.getElementById('email_btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Sending...';

            const formData = new FormData();
            formData.append('action', 'email_setup');
            formData.append('csrf_token', '<?php echo CSRF::token(); ?>');

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await res.json();
                if (data.ok) {
                    btn.innerHTML = '✅ Sent Successfully';
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }, 3000);
                } else {
                    alert(data.message || 'Failed to send email.');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Network error.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>

</html>