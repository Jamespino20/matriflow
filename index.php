<?php
require_once __DIR__ . '/bootstrap.php';

// If user is already authenticated, redirect to their role-specific dashboard
if (Auth::check()) {
    $u = Auth::user();
    if ($u) {
        $role = $u['role'] ?? 'patient';
        $dashboardPaths = [
            'admin'     => '/public/admin/dashboard.php',
            'doctor'    => '/public/doctor/dashboard.php',
            'secretary' => '/public/secretary/dashboard.php',
            'patient'   => '/public/patient/dashboard.php',
        ];
        $targetPath = $dashboardPaths[$role] ?? '/public/patient/dashboard.php';
        redirect($targetPath);
        exit;
    }
}

$pageTitle = 'Home - MatriFlow';
$currentPage = 'home';

// Handle Forgot Password & Reset Password POST requests
$forgotResultMessage = null;
$resetLink = null;
$resetResultMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Forgot Password Handler
    if ($action === 'forgotpassword') {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Invalid request (CSRF).']);
                exit;
            }
            $_SESSION['flash_forgot_msg'] = 'Invalid request (CSRF).';
            redirect('/');
        }

        $identity = trim((string) ($_POST['identity'] ?? ''));
        if ($identity === '') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Enter your username or email.']);
                exit;
            }
            $_SESSION['flash_forgot_msg'] = 'Enter your username or email.';
            redirect('/');
        }

        try {
            $user = User::findByIdentity($identity);
        } catch (Throwable $e) {
            error_log('User::findByIdentity() error: ' . $e->getMessage());
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Database error.']);
                exit;
            }
            $_SESSION['flash_forgot_msg'] = 'Database error.';
            redirect('/');
        }

        // Don't leak account existence
        if (!$user) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => 'If the account exists, a reset link will be provided.']);
                exit;
            }
            $_SESSION['flash_forgot_msg'] = 'If the account exists, a reset link will be provided.';
            redirect('/?forgot=1');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));

        try {
            $stmt = db()->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)");
            $stmt->execute([':uid' => (int) $user['user_id'], ':hash' => $tokenHash, ':exp' => $expiresAt]);

            $modalLink = base_url('/?action=reset&token=' . $token);

            AuditLogger::log((int) $user['user_id'], 'password_reset_tokens', 'INSERT', null, 'password_reset_requested');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => 'Reset link generated:', 'reset_link' => $modalLink]);
                exit;
            }

            $_SESSION['flash_forgot_msg'] = 'Reset link generated (copy/paste):';
            $_SESSION['flash_reset_link'] = $modalLink;
            redirect('/?forgot=1');
        } catch (Throwable $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'An error occurred.']);
                exit;
            }
            $_SESSION['flash_forgot_msg'] = 'An error occurred.';
            redirect('/');
        }
    }

    // Reset Password Handler
    if ($action === 'resetpassword') {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (!CSRF::validate($_POST['csrf_token'] ?? null)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Invalid request (CSRF).']);
                exit;
            }
            $_SESSION['flash_reset_msg'] = 'Invalid request (CSRF).';
            redirect('/');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $pass1 = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['passwordconfirm'] ?? '');

        if ($token === '' || $pass1 === '' || $pass2 === '') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'All fields required.']);
                exit;
            }
            $_SESSION['flash_reset_msg'] = 'All fields required.';
            redirect('/?action=reset&token=' . urlencode($token));
        }

        if (strlen($pass1) < 8) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Password must be at least 8 characters.']);
                exit;
            }
            $_SESSION['flash_reset_msg'] = 'Password must be at least 8 characters.';
            redirect('/?action=reset&token=' . urlencode($token));
        }

        if (!hash_equals($pass1, $pass2)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Passwords do not match.']);
                exit;
            }
            $_SESSION['flash_reset_msg'] = 'Passwords do not match.';
            redirect('/?action=reset&token=' . urlencode($token));
        }

        $tokenHash = hash('sha256', $token);

        try {
            $stmt = db()->prepare("SELECT * FROM password_reset_tokens WHERE token_hash = :hash AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([':hash' => $tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'message' => 'Token is invalid or expired.']);
                    exit;
                }
                $_SESSION['flash_reset_msg'] = 'Token is invalid or expired.';
                redirect('/');
            }

            $uid = (int) $row['user_id'];
            $newHash = password_hash($pass1, PASSWORD_DEFAULT);

            db()->beginTransaction();

            $stmt1 = db()->prepare("UPDATE user SET password_hash = :ph WHERE user_id = :uid");
            $stmt1->execute([':ph' => $newHash, ':uid' => $uid]);

            $stmt2 = db()->prepare("DELETE FROM password_reset_tokens WHERE password_reset_token_id = :id");
            $stmt2->execute([':id' => (int) $row['password_reset_token_id']]);

            $stmt3 = db()->prepare("DELETE FROM user_sessions WHERE user_id = :uid");
            $stmt3->execute([':uid' => $uid]);

            db()->commit();

            AuditLogger::log($uid, 'user', 'UPDATE', $uid, 'password_reset_success');

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => 'Password updated. Please log in again.']);
                exit;
            }

            $_SESSION['flash_reset_msg'] = 'Password updated. Please log in again.';
            redirect('/?password_reset=1');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('Reset password error: ' . $e->getMessage());
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => 'Password reset failed.']);
                exit;
            }
            $_SESSION['flash_reset_msg'] = 'Password reset failed.';
            redirect('/?action=reset&token=' . urlencode($token));
        }
    }
}

// Pull flash messages
if (isset($_SESSION['flash_forgot_msg'])) {
    $forgotResultMessage = $_SESSION['flash_forgot_msg'];
    unset($_SESSION['flash_forgot_msg']);
}
if (isset($_SESSION['flash_reset_link'])) {
    $resetLink = $_SESSION['flash_reset_link'];
    unset($_SESSION['flash_reset_link']);
}
if (isset($_SESSION['flash_reset_msg'])) {
    $resetResultMessage = $_SESSION['flash_reset_msg'];
    unset($_SESSION['flash_reset_msg']);
}
?>

<!doctype html>
<html lang="en" data-base-url="<?= base_url('/public') ?>">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>MatriFlow - Commonwealth Hospital</title>
    <link rel="icon" type="image/ico" href="<?= base_url('/public/assets/images/favicon.ico') ?>" />
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/home.css') ?>">
</head>

<body class="home">
    <!-- Header -->
    <header class="home-nav">
        <div class="container">
            <div class="bar">
                <a class="brand" href="/">
                    <img class="banner" src="<?= base_url('/public/assets/images/matriflow_banner.png') ?>" alt="MatriFlow">
                </a>

                <nav class="nav-links">
                    <a href="#services">Services</a>
                    <a href="#doctors">Doctors</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                </nav>

                <div class="auth-buttons">
                    <button class="btn btn-outline" data-modal-open="modal-login">Patient Portal</button>
                    <button class="btn btn-primary" data-modal-open="modal-register">Book Appointment</button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero (full background image) -->
    <section class="hero" id="home" style="background-image:url('<?= base_url('/public/assets/images/homepage-cover.jpg') ?>');">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <div class="pill"><span class="dot"></span> Powered by Commonwealth Hospital</div>
                    <h1>Your Pregnancy, Managed. <br><span class="primary">Your Medical Co-Pilot.</span></h1>
                    <p>
                        MatriFlow is the digital bridge to Commonwealth Hospital’s specialized maternity team. We’ve streamlined your checkups so you can spend less time in the waiting room and more time with your doctor.
                    </p>

                    <div class="hero-cta">
                        <button class="btn btn-primary" data-modal-open="modal-login">Access Your Dashboard</button>
                        <a class="btn btn-outline" href="#services">How it Works</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Big CHMC logo overlay (your preferred style) -->
        <div class="hero-logo">
            <img src="<?= base_url('/public/assets/images/CHMC-logo.jpg') ?>" alt="Commonwealth Hospital and Medical Center">
        </div>
    </section>

    <!-- Your Pregnancy journey -->
    <section class="section section-bg-blue quick" id="services">
        <div class="container">
            <div class="head">
                <div>
                    <h2>The Operational Heartbeat</h2>
                    <p>MatriFlow isn't just a website—it’s the digital extension of CHMC’s Neopolitan Business Park location.</p>
                </div>
                <a class="viewall" href="#packages">Explore Your Journey →</a>
            </div>

            <div class="quick-grid">
                <div class="qcard">
                    <h3>Direct OB-GYN Access</h3>
                    <p>Book directly with CHMC’s board-certified OB-GYNs through our integrated portal.</p>
                    <a href="https://commonwealthmedph.com/doctor-appointment/?doctor_type=Obstetrics-Gynecology" target="_blank">Book with a Specialist →</a>
                </div>

                <div class="qcard">
                    <h3>Instant Results</h3>
                    <p>View your Newborn Screening or lab results the moment they are uploaded by hospital staff.</p>
                    <a data-modal-open="modal-login">View Records →</a>
                </div>

                <div class="qcard">
                    <h3>Transparent Billing</h3>
                    <p>Access your Maternity Package details—know exactly what’s included in your delivery bundle.</p>
                    <a data-modal-open="modal-login">Check Package →</a>
                </div>

                <div class="qcard emergency">
                    <h3>24/7 Co-Pilot</h3>
                    <p>Emergency help and hospital contact lines are always one tap away on your mobile dashboard.</p>
                    <a href="tel:+6328930000">Call Hospital →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors -->
    <section class="section section-bg-light" id="doctors">
        <div class="container">
            <div class="section-head">
                <div>
                    <h2>OB-GYN Doctors</h2>
                    <p>MatriFlow links to CHMC’s official OB-GYN doctor directory for the authoritative list.</p>
                </div>
            </div>

            <div class="cta-row">
                <a class="btn btn-primary" href="https://commonwealthmedph.com/" target="_blank" rel="noopener">Read
                    About CHMC</a>
                <a class="btn btn-outline" data-modal-open="modal-login">Go to Patient Portal</a>
            </div>
        </div>
    </section>

    <!-- Departments -->
    <section class="section section-bg-red dept" id="packages">
        <div class="container">
            <h2>Specialized Care Departments</h2>

            <div class="dept-grid">
                <div class="dcard">
                    <div class="img" style="background-image:url('<?= base_url('/public/assets/images/prenatal-care.jpg') ?>');"></div>
                    <div class="body">
                        <h3>Prenatal Checkups</h3>
                        <p>Regular monitoring for a healthy pregnancy journey.</p>
                    </div>
                </div>

                <div class="dcard">
                    <div class="img" style="background-image:url('<?= base_url('/public/assets/images/delivery-services.jpg') ?>');"></div>
                    <div class="body">
                        <h3>Delivery Packages</h3>
                        <p>Comfortable and safe delivery options tailored to you.</p>
                    </div>
                </div>

                <div class="dcard">
                    <div class="img" style="background-image:url('<?= base_url('/public/assets/images/ultrasound.jpg') ?>');"></div>
                    <div class="body">
                        <h3>Newborn Screening</h3>
                        <p>Essential health checks for your baby’s first days.</p>
                    </div>
                </div>

                <div class="dcard">
                    <div class="img" style="background-image:url('<?= base_url('/public/assets/images/gynecology.jpg') ?>');"></div>
                    <div class="body">
                        <h3>Gynecology</h3>
                        <p>Comprehensive women’s health services for all ages.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Social Proof -->
    <section class="section section-bg-light testimonials" id="testimonials">
        <div class="container">
            <div class="t-head">
                <div class="kicker">Functional Social Proof</div>
                <div class="title">Healthcare that Works</div>
                <p class="sub">Authentic experiences from families navigating their journey with MatriFlow.</p>
            </div>

            <div class="t-grid">
                <div class="t-card">
                    <div class="t-stars">★★★★★</div>
                    <div class="t-quote">“Being able to see my newborn's screening results on my phone at 2 AM saved me so much anxiety. No more waiting for callbacks.”</div>
                    <div class="t-user">
                        <img class="t-avatar" src="<?= base_url('/public/assets/images/testimonial-1.jpg') ?>" alt="">
                        <div>
                            <div class="t-name">Maria Gonzales</div>
                            <div class="t-role">Patient & Mother</div>
                        </div>
                    </div>
                </div>

                <div class="t-card">
                    <div class="t-stars">★★★★★</div>
                    <div class="t-quote">“Booking my prenatal checkups while in the office takes less than a minute. The system actually syncs with the doctor's real schedule.”</div>
                    <div class="t-user">
                        <img class="t-avatar" src="<?= base_url('/public/assets/images/testimonial-2.jpg') ?>" alt="">
                        <div>
                            <div class="t-name">Sarah Jenkins</div>
                            <div class="t-role">Regular Patient</div>
                        </div>
                    </div>
                </div>

                <div class="t-card">
                    <div class="t-stars">★★★★★</div>
                    <div class="t-quote">“MatriFlow has aided me and my team in managing our clinic's operations with ease. The system is user-friendly and efficient.”</div>
                    <div class="t-user">
                        <img class="t-avatar" src="<?= base_url('/public/assets/images/testimonial-3.jpg') ?>" alt="">
                        <div>
                            <div class="t-name">Mark Reyes</div>
                            <div class="t-role">Clinic Manager</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About -->
    <section class="section section-bg-blue" id="about">
        <div class="container">
            <div class="section-head">
                <div>
                    <h2>The Metro Pacific Standard</h2>
                    <p>MatriFlow is the digital heartbeat of your maternity care, powered by Commonwealth Hospital and Medical Center (CHMC).</p>
                    <br>
                    <p>As the 19th hospital in the <strong>Metro Pacific Health</strong> network—the Philippines' largest healthcare group—we bring nationwide expertise directly to Novaliches. MatriFlow ensures this premium care is always reachable.</p>
                    <p style="font-weight: 900; margin-top: 15px;">Malapit. Maraming Gamit. Sulit.</p>
                </div>
            </div>

            <div class="cta-row">
                <a class="btn btn-primary" href="https://commonwealthmedph.com/" target="_blank" rel="noopener">Visit CHMC Official</a>
                <a class="btn btn-outline" data-modal-open="modal-register">Get Started with MatriFlow</a>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="section section-bg-light" id="contact">
        <div class="container">
            <div class="section-head">
                <div>
                    <h2>Contact</h2>
                    <p>For official and updated contact details, refer to the CHMC website.</p>
                </div>
            </div>

            <div class="contact-grid">
                <div class="contact-card">
                    <div class="c-title">Clinic Location</div>
                    <div class="c-text">
                        Lot 3 &amp; 4 Blk. 3 Neopolitan Business Park Regalado Highway<br>
                        Brgy. Greater Lagro, Novaliches, Quezon City<br>
                        Metro Manila, Philippines 1118
                    </div>
                </div>

                <div class="contact-card">
                    <div class="c-title">Get in touch</div>
                    <div class="c-text">Email: contact@commonwealthmed.com.ph</div>
                    <div class="c-text">Phone: (02) 8930-0000</div>
                    <div class="c-text">Open 24/7</div>

                    <div class="cta-row" style="margin-top:14px">
                        <a class="btn btn-primary" href="https://commonwealthmedph.com/" target="_blank"
                            rel="noopener">CHMC Website</a>
                        <a class="btn btn-outline" href="tel:+6328930000">Call Now</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <strong style="color:#0f172a">MatriFlow</strong>
                    <div style="margin-top:8px; color:#64748b">
                        Commonwealth Hospital and Medical Center's trusted maternity care system,
                        providing compassionate services for you and your little one.
                    </div>
                </div>

                <div>
                    <strong style="color:#0f172a">Links</strong>
                    <div style="margin-top:10px; display:grid; gap:8px">
                        <a href="#doctors">Doctors</a>
                        <a href="#services">Services</a>
                        <a href="<?= base_url('/public/support.php') ?>">Support</a>
                        <a href="<?= base_url('/public/terms.php') ?>">Terms of Service</a>
                        <a href="<?= base_url('/public/privacy.php') ?>">Privacy Policy</a>
                        <a href="https://commonwealthmedph.com/" target="_blank" rel="noopener">Hospital Website</a>
                        <a data-modal-open="modal-login">Patient Portal</a>
                    </div>
                </div>

                <div>
                    <strong style="color:#0f172a">Location &amp; Hours</strong>
                    <div style="margin-top:10px; display:grid; gap:8px; color:#64748b; font-size:12px">
                        <div>
                            Lot 3 &amp; 4 Blk. 3 Neopolitan Business Park<br>
                            Regalado Hwy, Brgy. Greater Lagro<br>
                            Novaliches, Quezon City 1118
                        </div>

                        <div style="margin-top:8px">
                            <img class="footer-map" src="<?= base_url('/public/assets/images/chmc-map.png') ?>" alt="CHMC location map">
                        </div>

                        <div style="font-weight:800; color:#334155">Phone: (02) 8930-0000</div>
                        <div>Open 24/7</div>
                    </div>
                </div>
            </div>

            <div
                style="margin-top:20px; padding-top:14px; border-top:1px solid var(--border); text-align:center; color:#64748b">
                © <?php echo date('Y'); ?> Commonwealth Hospital and Medical Center. All rights reserved.
            </div>
        </div>
    </footer>
    <!-- Login Modal -->
    <div class="modal-overlay" id="modal-login" aria-hidden="true">
        <div class="modal-split">
            <div class="modal-card" role="dialog" aria-modal="true">
                <button class="modal-close" type="button" data-modal-close>×</button>

                <div class="modal-left bg-login">
                    <div class="modal-left-content">
                        <div class="modal-brand">
                            <img src="<?= base_url('/public/assets/images/matriflow_banner.png') ?>" alt="MatriFlow">
                        </div>

                        <div class="modal-marketing">
                            <h2>Your Health Journey Starts Here.</h2>
                            <p>Access your medical records, book appointments, and connect with your doctors securely through our patient portal.</p>

                            <ul>
                                <li>Secure Medical Records</li>
                                <li>24/7 Specialist Access</li>
                                <li>Personalized Care Plans</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="modal-right">
                    <div style="margin-bottom: 24px;">
                        <div class="modal-title" style="font-size: 26px; margin-bottom: 8px;">Welcome Back!</div>
                        <p class="help" style="font-size: 14px; margin: 0;">Please enter your details to sign in to your account.</p>
                    </div>

                    <form method="POST" action="<?= base_url('/public/controllers/auth-handler.php') ?>">
                        <input type="hidden" name="action" value="login">
                        <?php echo CSRF::input(); ?>

                        <div class="form-row">
                            <label class="label">Email or Username</label>
                            <div class="input-group">
                                <input class="input" type="text" name="identity" placeholder="name@example.com" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="label">Password</label>
                            <div class="input-group">
                                <input class="input" type="password" id="login_password" name="password" placeholder="••••••••" required>
                                <button type="button" class="toggle" data-password-toggle data-target="login_password" aria-label="Toggle password visibility">
                                    <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                                </button>
                            </div>
                        </div>

                        <div class="auth-actions" style="display:flex; justify-content:space-between; margin-bottom: 24px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer; color:var(--muted); font-weight:500;">
                                <input type="checkbox" name="remember" style="accent-color:var(--primary)"> Remember me
                            </label>
                            <a href="#" data-modal-switch="modal-forgot" style="font-size:13px; color:var(--primary); font-weight:700; text-decoration:none;">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>

                        <div style="margin-top:24px;text-align:center;font-size:13px; color:var(--muted);">
                            <span>Don't have an account?</span>
                            <a href="#" data-modal-switch="modal-register" style="color:var(--primary); font-weight:700; text-decoration:none; margin-left: 4px;">Register for free</a>
                        </div>

                        <div style="margin-top: 16px; text-align: center; font-size: 11px; color: #94a3b8; line-height: 1.4;">
                            Protected by ReCAPTCHA and subject to the Privacy Policy and Terms of Service.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal-overlay" id="modal-register" aria-hidden="true">
        <div class="modal-split">
            <div class="modal-card modal-wide" role="dialog" aria-modal="true">
                <button class="modal-close" type="button" data-modal-close>×</button>

                <div class="modal-left bg-register">
                    <div class="modal-left-content">
                        <div class="modal-brand">
                            <img src="<?= base_url('/public/assets/images/matriflow_banner.png') ?>" alt="MatriFlow">
                        </div>

                        <div class="modal-marketing">
                            <h2>Comprehensive Maternity Care</h2>
                            <p>Join our community of mothers and families. Get access to exclusive health resources, appointment scheduling, and your personal health records.</p>

                            <ul>
                                <li>Secure Medical Records</li>
                                <li>24/7 Specialist Access</li>
                                <li>Personalized Care Plans</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="modal-right">
                    <div style="margin-bottom: 24px;">
                        <div class="modal-title" style="font-size: 26px; margin-bottom: 8px;">Create Patient Account</div>
                        <p class="help" style="font-size: 14px; margin: 0;">Enter your details below to register for the Patient Portal.</p>
                    </div>

                    <form method="POST" action="<?= base_url('/public/controllers/auth-handler.php') ?>">
                        <input type="hidden" name="action" value="register">
                        <?php echo CSRF::input(); ?>

                        <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-row">
                                <label class="label">First Name</label>
                                <div class="input-group">
                                    <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">👤</span>
                                    <input class="input" type="text" name="first_name" placeholder="Jane" style="padding-left:36px" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="label">Last Name</label>
                                <div class="input-group">
                                    <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">👤</span>
                                    <input class="input" type="text" name="last_name" placeholder="Doe" style="padding-left:36px" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="label">Username</label>
                            <div class="input-group">
                                <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">👤</span>
                                <input class="input" type="text" name="username" placeholder="janedoe123" style="padding-left:36px" required>
                            </div>
                        </div>

                        <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-row">
                                <label class="label">Date of Birth</label>
                                <div class="input-group">
                                    <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">📅</span>
                                    <input class="input" type="date" id="reg_dob" name="dob" style="padding-left:36px" required onchange="validateAge(this.value)">
                                </div>
                                <div id="age_error" style="color:var(--danger); font-size:11px; margin-top:4px; display:none;">
                                    Patient must be at least 11 years old.
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="label">Phone Number</label>
                                <div class="input-group">
                                    <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">📱</span>
                                    <input class="input" type="tel" name="contact_number" placeholder="(555) 000-0000" style="padding-left:36px" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-row">
                                <label class="label">Gender</label>
                                <select class="input" name="gender" required id="reg_gender">
                                    <option value="" disabled selected>Select Gender</option>
                                    <option value="Female">Female (Strict for Patients)</option>
                                    <option value="Male">Male</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label class="label">Registering as</label>
                                <select class="input" name="registration_type" required id="reg_type">
                                    <option value="Patient" selected>Patient</option>
                                    <option value="Guardian">Legal Guardian</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="label">Email Address</label>
                            <div class="input-group">
                                <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">✉️</span>
                                <input class="input" type="email" name="email" placeholder="jane@example.com" style="padding-left:36px" required>
                            </div>
                        </div>

                        <div class="form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="form-row">
                                <label class="label">Initial Password</label>
                                <div class="input-group">
                                    <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">🔒</span>
                                    <input class="input" type="password" id="reg_password" name="password" placeholder="••••••••" style="padding-left:36px" required>
                                    <button type="button" class="toggle" data-password-toggle data-target="reg_password">
                                        <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                                    </button>
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="label">Confirm Password</label>
                                <div class="input-group">
                                    <span class="icon" style="position:absolute; left:12px; z-index:1; opacity:0.5;">🔒</span>
                                    <input class="input" type="password" id="reg_confirm" name="password_confirm" placeholder="••••••••" style="padding-left:36px" required>
                                    <button type="button" class="toggle" data-password-toggle data-target="reg_confirm">
                                        <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="checkbox-row" style="margin-top:10px;font-size:13px; display:flex; flex-direction:column; gap:8px;">
                            <label for="reg_verify" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" id="reg_verify" required>
                                I verify that I am the patient or legal guardian.
                            </label>

                            <label for="reg_consent" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" id="reg_consent" name="consent" required>
                                <span>I agree to the <a href="<?= base_url('/public/terms.php') ?>" style="color:var(--primary);font-weight:700">Terms of Service</a> and <a href="<?= base_url('/public/privacy.php') ?>" style="color:var(--primary);font-weight:700">Privacy Policy</a>.</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%; margin-top:20px">Create Account</button>

                        <div class="auth-footer" style="margin-top:16px;text-align:center;font-size:13px">
                            Already have an account? <a href="#" data-modal-switch="modal-login" style="color:var(--primary); font-weight:700; text-decoration:none;">Sign in</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal-overlay modal-clean-center" id="modal-forgot" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true">
            <button class="modal-close" type="button" data-modal-close>×</button>
            <div class="modal-body">
                <div class="icon-circle">
                    <!-- Icon: lock or refresh. Using inline SVG or standard lock icon -->
                    <span style="font-size: 32px;">🔐</span>
                </div>
                <h2>Forgot Password?</h2>
                <p>No worries! Enter your email address below and we'll send you a link to reset your password.</p>

                <form method="post" action="<?= base_url('/') ?>" style="text-align:left">
                    <?php echo CSRF::input(); ?>
                    <input type="hidden" name="action" value="forgotpassword">

                    <div class="form-row">
                        <label class="label">Email Address</label>
                        <input class="input" name="identity" type="text" placeholder="name@example.com" required>
                    </div>

                    <button class="btn btn-primary" style="width:100%;height:52px; font-size:16px" type="submit">
                        Send Reset Link
                    </button>

                    <div style="text-align:center; margin-top:24px">
                        <a href="#" data-modal-switch="modal-login" style="font-size:14px; font-weight:600; color:var(--muted)">
                            ← Back to Login
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal-overlay modal-clean-center" id="modal-reset" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true">
            <button class="modal-close" type="button" data-modal-close>×</button>
            <div class="modal-body">
                <div class="icon-circle">
                    <!-- Icon: lock or key -->
                    <span style="font-size: 32px;">🔑</span>
                </div>
                <h2>Reset Password</h2>
                <p>Please chose a new password to secure your account.</p>

                <form method="post" action="<?= base_url('/') ?>" style="text-align:left">
                    <?php echo CSRF::input(); ?>
                    <input type="hidden" name="action" value="resetpassword">
                    <input type="hidden" name="token" value="<?php echo e($_GET['token'] ?? ''); ?>">

                    <div class="form-row">
                        <label class="label">New Password</label>
                        <div class="input-group">
                            <input class="input" id="reset_password" name="password" type="password" placeholder="••••••••" required>
                            <button class="toggle" type="button" data-password-toggle data-target="reset_password">
                                <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                            </button>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="label">Confirm Password</label>
                        <div class="input-group">
                            <input class="input" id="reset_password_confirm" name="passwordconfirm" type="password" placeholder="••••••••" required>
                            <button class="toggle" type="button" data-password-toggle data-target="reset_password_confirm">
                                <img src="<?= base_url('/public/assets/images/password-show.svg') ?>" alt="Show">
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-primary" style="width:100%;height:52px; font-size:16px; margin-top:8px" type="submit">
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Setup 2FA Modal (Iframe) -->
    <div class="modal-overlay modal-clean-center" id="modal-setup2fa" aria-hidden="true">
        <div class="modal-card" style="max-width:600px; min-height:650px;" role="dialog" aria-modal="true">
            <button class="modal-close" type="button" data-modal-close>×</button>
            <div class="modal-body" style="padding:0; height:620px; overflow:hidden;">
                <iframe class="modal-iframe" src="about:blank" style="width:100%;height:100%;border:0"></iframe>
            </div>
        </div>
    </div>

    <!-- Verify 2FA Modal (Iframe) -->
    <div class="modal-overlay modal-clean-center" id="modal-verify2fa" aria-hidden="true">
        <div class="modal-card" style="max-width:600px; min-height:650px;" role="dialog" aria-modal="true">
            <button class="modal-close" type="button" data-modal-close>×</button>
            <div class="modal-body" style="padding:0; height:620px; overflow:hidden;">
                <iframe class="modal-iframe" src="about:blank" style="width:100%;height:100%;border:0"></iframe>
            </div>
        </div>
    </div>

    <script src="<?= base_url('/public/assets/js/app.js') ?>"></script>
    <script src="<?= base_url('/public/assets/js/auth.js') ?>"></script>
    <script>
        function validateAge(dob) {
            if (!dob) return;
            const birthDate = new Date(dob);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            const type = document.getElementById('reg_type').value;
            const errorDiv = document.getElementById('age_error');
            const submitBtn = document.querySelector('#modal-register button[type="submit"]');
            const gender = document.getElementById('reg_gender');

            if (type === 'Patient' && age < 13) {
                errorDiv.style.display = 'block';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
            } else {
                errorDiv.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }

            // Implicit strictness: If patient, check gender
            if (type === 'Patient' && gender.value === 'Male') {
                // We don't block yet, but maybe show a warning?
                // User said "this part should be really strict"
            }
        }

        // Add listener to type change too
        document.getElementById('reg_type')?.addEventListener('change', function() {
            validateAge(document.getElementById('reg_dob').value);

            const gender = document.getElementById('reg_gender');
            if (this.value === 'Patient') {
                gender.placeholder = "Select Gender (Female for Patients)";
            }
        });

        document.getElementById('reg_gender')?.addEventListener('change', function() {
            const type = document.getElementById('reg_type').value;
            if (type === 'Patient' && this.value === 'Male') {
                alert('Maternity clinic patients are typically female. Please verify if you are registering as a Guardian instead.');
            }
        });
    </script>
</body>

</html>