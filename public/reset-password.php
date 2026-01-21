<?php
require_once __DIR__ . '/../bootstrap.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? ''; // Optional, for display/prefill

if (!$token) {
    die("Invalid request.");
}

$hash = hash('sha256', $token);
$stmt = db()->prepare("SELECT * FROM password_reset_tokens WHERE token_hash = :hash AND expires_at > NOW() LIMIT 1");
$stmt->execute([':hash' => $hash]);
$tokenRow = $stmt->fetch();

if (!$tokenRow) {
    // Check if it was recently used? No, just invalid.
    $error = "This password reset link is invalid or has expired.";
} else {
    $user = User::findById((int) $tokenRow['user_id']);
    if (!$user) {
        $error = "User account not found.";
    }
}

$success = false;
$msgs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($tokenRow) && $tokenRow) {
    if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
        $msgs[] = 'Invalid request (CSRF).';
    } else {
        $pass = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($pass !== $confirm) {
            $msgs[] = 'Passwords do not match.';
        } else {
            // Validate strength
            $strengthErrors = Auth::validatePassword($pass);
            if (!empty($strengthErrors)) {
                $msgs = array_merge($msgs, $strengthErrors);
            } else {
                // Update Password
                $newHash = password_hash($pass, PASSWORD_ARGON2ID);
                try {
                    db()->beginTransaction();

                    $stmt = db()->prepare("UPDATE users SET password_hash = :ph WHERE user_id = :uid");
                    $stmt->execute([':ph' => $newHash, ':uid' => $user['user_id']]);

                    // Delete token
                    $stmt = db()->prepare("DELETE FROM password_reset_tokens WHERE password_reset_token_id = :id");
                    $stmt->execute([':id' => $tokenRow['password_reset_token_id']]);

                    // Also clear sessions for security? Optional.

                    db()->commit();
                    $success = true;

                    // Log
                    AuditLogger::log((int) $user['user_id'], 'users', 'UPDATE', (int) $user['user_id'], 'password_reset');
                } catch (Throwable $e) {
                    db()->rollBack();
                    error_log("Password reset failed: " . $e->getMessage());
                    $msgs[] = "An error occurred while resetting your password.";
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="en" data-base-url="<?= base_url('/public') ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reset Password - MatriFlow</title>
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f0f2f5;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }

        .card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 420px;
        }

        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 8px;
            background: #14457b;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 15px;
        }

        .btn-primary:hover {
            background: #0f3d6e;
            transform: translateY(-1px);
        }

        .btn-outline {
            display: inline-block;
            width: 100%;
            padding: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            color: #374151;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            text-align: center;
            margin-top: 12px;
        }

        .btn-outline:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .field {
            margin-bottom: 1.25rem;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #4b5563;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 15px;
            transition: border-color 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #14457b;
            box-shadow: 0 0 0 3px rgba(20, 69, 123, 0.1);
        }

        h2 {
            margin-top: 0;
            color: #111827;
            font-size: 24px;
            font-weight: 800;
        }

        p {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .footer-link {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            font-weight: 500;
        }

        .footer-link:hover {
            color: #14457b;
        }
    </style>
</head>

<body>
    <div class="card">
        <?php if ($success): ?>
            <div style="text-align:center">
                <div style="font-size: 64px; margin-bottom: 1.5rem;">✅</div>
                <h2>Success!</h2>
                <p>Your password has been successfully updated. You can now log in with your new credentials.</p>
                <a href="<?= base_url('/') ?>" class="btn-primary" style="display:block; text-decoration:none;">Go to Login</a>
            </div>
        <?php elseif (isset($error)): ?>
            <div style="text-align:center" id="error-view">
                <div style="font-size: 64px; margin-bottom: 1.5rem;">⚠️</div>
                <h2>Link Expired</h2>
                <p>This password reset link is invalid or has expired for your security. Please request a new one.</p>

                <?php if ($email): ?>
                    <button type="button" class="btn-primary" id="resend-btn" onclick="resendEmail('<?= htmlspecialchars($email) ?>')">
                        Resend Reset Email
                    </button>
                <?php endif; ?>

                <a href="<?= base_url('/') ?>" class="btn-outline" style="text-decoration:none">Back to Website</a>
            </div>

            <script>
                async function resendEmail(email) {
                    const btn = document.getElementById('resend-btn');
                    const originalText = btn.textContent;
                    btn.disabled = true;
                    btn.textContent = 'Sending...';

                    const fd = new FormData();
                    fd.append('action', 'resend_forgot_password');
                    fd.append('email', email);
                    fd.append('csrf_token', '<?= CSRF::token() ?>');

                    try {
                        const res = await fetch('<?= base_url('/public/controllers/auth-handler.php') ?>', {
                            method: 'POST',
                            body: fd,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await res.json();
                        if (data.success) {
                            btn.textContent = '✅ Email Sent!';
                            btn.style.background = '#059669';
                            const p = document.querySelector('#error-view p');
                            p.textContent = data.message;
                            p.style.color = '#059669';
                            p.style.fontWeight = '600';
                        } else {
                            alert(data.errors ? data.errors.join('\\n') : 'Failed to resend email.');
                            btn.disabled = false;
                            btn.textContent = originalText;
                        }
                    } catch (e) {
                        alert('Network error.');
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                }
            </script>
        <?php else: ?>
            <h2>Reset Password</h2>
            <p>Please enter a new, strong password to secure your account.</p>

            <div id="alert-container">
                <?php foreach ($msgs as $msg): ?>
                    <div class="alert alert-error">
                        <span class="material-symbols-outlined" style="font-size:18px">error</span>
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post">
                <?php echo CSRF::input(); ?>
                <div class="field">
                    <label>New Password</label>
                    <input type="password" name="password" placeholder="••••••••" required autofocus>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-primary" style="margin-top: 10px;">Update Password</button>
            </form>
            <a href="<?= base_url('/') ?>" class="footer-link">Cancel and return</a>
        <?php endif; ?>
    </div>

    <script src="<?= base_url('/public/assets/js/auth.js') ?>"></script>
</body>

</html>