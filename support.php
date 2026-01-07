<?php require_once __DIR__ . '/../bootstrap.php'; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Contact Support - MatriFlow</title>
    <link rel="icon" href="<?= base_url('/public/assets/images/favicon.ico') ?>" />
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>" />
    <style>
        .support-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        @media (max-width: 600px) {
            .support-grid {
                grid-template-columns: 1fr;
            }
        }

        .support-section h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }

        .support-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .support-card p {
            margin: 8px 0;
            font-size: 14px;
        }
    </style>
</head>

<body style="background:var(--bg)">
    <div class="container" style="max-width: 900px; padding: 40px 20px">
        <div class="card" style="padding: 40px">
            <div style="display:flex; justify-content:space-between; align-items: center; gap:10px; flex-wrap:wrap; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 20px;">
                <h1 style="margin:0; font-weight:900; color:var(--primary); font-size:28px">Support Help Center</h1>
                <a class="btn btn-outline" href="<?= base_url('/') ?>">Back to Home</a>
            </div>

            <div class="support-card" style="margin-bottom: 30px; background: var(--surface-light)">
                <p><strong>Support Email:</strong> support@commonwealthmed.com.ph</p>
                <p><strong>Clinic Contact Number:</strong> (02) 8930-0000</p>
                <p><strong>Office Hours:</strong> Mon–Sat, 9:00 AM–5:00 PM</p>
            </div>

            <div class="support-grid">
                <div class="support-section">
                    <h3>1) What Support Covers</h3>
                    <p>Support can help with:</p>
                    <ul style="font-size: 14px; color: var(--text-secondary)">
                        <li>Account access (login, password reset)</li>
                        <li>Appointment booking guidance</li>
                        <li>Patient portal concerns</li>
                        <li>Billing/payment posting questions</li>
                        <li>Bug reports and technical issues</li>
                    </ul>
                </div>

                <div class="support-section">
                    <h3>2) How to Request Help</h3>
                    <p>Send an email with:</p>
                    <ul style="font-size: 14px; color: var(--text-secondary)">
                        <li>Full name + Role</li>
                        <li>Registered email/username</li>
                        <li>Screenshot of the issue</li>
                        <li>Exact error message</li>
                        <li>Device & Browser details</li>
                    </ul>
                </div>
            </div>

            <div class="support-section" style="margin-top: 30px;">
                <h3>3) Target Response Times</h3>
                <ul style="font-size: 14px; color: var(--text-secondary)">
                    <li>Account lockout: within <strong>24–48 hours</strong></li>
                    <li>General questions: within <strong>2–3 business days</strong></li>
                    <li>Bug reports: acknowledgment within <strong>3 business days</strong></li>
                </ul>
            </div>

            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center;">
                <p style="color: var(--danger); font-weight: 700;">Safety Note: Support cannot provide medical advice. For urgent symptoms or emergencies, seek immediate medical care.</p>
                <div style="margin-top: 20px; display:flex; gap:10px; justify-content:center">
                    <a class="btn btn-primary" href="<?= base_url('index.php') ?>">Go to Patient Portal</a>
                    <a class="btn btn-outline" href="https://commonwealthmedph.com/" target="_blank" rel="noopener">CHMC Website</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>