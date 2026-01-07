<?php require_once __DIR__ . '/../bootstrap.php'; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Terms of Service - MatriFlow</title>
    <link rel="icon" href="<?= base_url('/public/assets/images/favicon.ico') ?>" />
    <link rel="stylesheet" href="<?= base_url('/public/assets/css/app.css') ?>" />
    <style>
        .legal-content h3 {
            margin-top: 24px;
            color: var(--primary);
        }

        .legal-content p {
            line-height: 1.6;
            color: var(--text-secondary);
        }

        .legal-content ul {
            padding-left: 20px;
            color: var(--text-secondary);
        }

        .legal-content li {
            margin-bottom: 8px;
        }
    </style>
</head>

<body style="background:var(--bg)">
    <div class="container" style="max-width: 800px; padding: 40px 20px">
        <div class="card" style="padding: 40px">
            <div style="display:flex; justify-content:space-between; align-items: center; gap:10px; flex-wrap:wrap; margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 20px;">
                <h1 style="margin:0; font-weight:900; color:var(--primary); font-size:28px">Terms of Service</h1>
                <a class="btn btn-outline" href="<?= base_url('/') ?>">Back to Home</a>
            </div>

            <div class="legal-content">
                <p><strong>Effective Date:</strong> January 3, 2026</p>
                <p><strong>Operated by:</strong> Commonwealth Hospital and Medical Center (CHMC), Quezon City, Philippines</p>

                <h3>1) Acceptance of Terms</h3>
                <p>By accessing or using MatriFlow, you agree to these Terms and all policies referenced here (including the Privacy Policy). If you do not agree, do not use the Platform.</p>

                <h3>2) What MatriFlow Does</h3>
                <p>MatriFlow is a clinic information management platform that supports patient registration, appointment scheduling, consultations, prenatal monitoring, laboratory test tracking, billing/HMO claim tracking, reporting, and a patient portal for viewing records that are released/approved by the Clinic.</p>

                <h3>3) Medical Disclaimer (Important)</h3>
                <p>MatriFlow does not provide emergency services and is <strong>not</strong> a substitute for professional medical judgment. If you have an emergency, go to the nearest emergency room or call local emergency hotlines.</p>

                <h3>4) Eligibility and Accounts</h3>
                <p>You must provide accurate information when creating an account and keep your login credentials confidential. Account access is role-based (e.g., Patient, Doctor, Secretary, Administrator) and actions may be logged for accountability and security.</p>

                <h3>5) Appointments, Messaging, and Follow-Ups</h3>
                <p>Appointment availability and confirmation depend on the Clinic’s schedule and approval processes. Automated reminders may be sent (e.g., SMS/email) when enabled, but you remain responsible for attending scheduled visits and following clinical advice.</p>

                <h3>6) Billing, Payments, and Claims</h3>
                <p>If MatriFlow includes billing features, posted charges reflect services recorded by the Clinic, and payment status depends on actual receipt/verification of payment. HMO/insurance claims (if used) may be tracked, but approval timelines and coverage decisions are controlled by the insurer/HMO and/or hospital processes, not MatriFlow.</p>

                <h3>7) Acceptable Use</h3>
                <p>You agree not to:</p>
                <ul>
                    <li>Attempt unauthorized access to accounts, records, or systems.</li>
                    <li>Upload malware, scrape data, or disrupt service availability.</li>
                    <li>Misuse medical information or impersonate another person.</li>
                </ul>

                <h3>8) Data Accuracy and Record Integrity</h3>
                <p>Clinical records depend on information entered by authorized users and/or provided by patients. If you believe a record is inaccurate, contact the Clinic promptly to request review and correction in accordance with applicable policy and law.</p>

                <h3>9) Availability and Changes</h3>
                <p>MatriFlow may be updated, modified, or temporarily unavailable due to maintenance, hosting limitations, or technical issues. Features may change over time (including future mobile versions).</p>

                <h3>10) Termination</h3>
                <p>We may suspend or terminate access for security reasons, policy violations, or suspected misuse. Patients may request account deactivation subject to record-retention obligations.</p>

                <h3>11) Limitation of Liability</h3>
                <p>To the extent allowed by law, MatriFlow and the Clinic are not liable for indirect or consequential damages arising from use or inability to use the Platform (e.g., connectivity issues, device failure), and are not responsible for third-party services.</p>

                <h3>12) Governing Law</h3>
                <p>These Terms are governed by the laws of the Republic of the Philippines. Disputes should first be raised through the Support process before any formal action.</p>

                <h3>13) Contact</h3>
                <p>For Terms questions: <strong>support@commonwealthmed.com.ph</strong> | <strong>(02) 8930-0000</strong></p>
            </div>
        </div>
    </div>
</body>

</html>