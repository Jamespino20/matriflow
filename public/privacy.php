<?php require_once __DIR__ . '/../bootstrap.php'; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Privacy Policy - MatriFlow</title>
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
                <h1 style="margin:0; font-weight:900; color:var(--primary); font-size:28px">Privacy Policy</h1>
                <a class="btn btn-outline" href="<?= base_url('/') ?>">Back to Home</a>
            </div>

            <div class="legal-content">
                <p><strong>Effective Date:</strong> January 3, 2026</p>
                <p>This Privacy Policy explains how MatriFlow collects, uses, shares, and protects personal data, consistent with the Philippine Data Privacy Act of 2012 (RA 10173).</p>

                <h3>1) Information We Collect</h3>
                <p>Depending on your role and how you use MatriFlow, we may collect:</p>
                <ul>
                    <li><strong>Account information:</strong> name, username, password hash, email, contact number, role, account status.</li>
                    <li><strong>Patient profile data:</strong> demographics, address, emergency contact, identification details.</li>
                    <li><strong>Health and maternity data (sensitive):</strong> medical history, allergies, prenatal baseline data, vital signs, consultation notes, diagnoses, treatment plans, prescriptions, and laboratory tests/results.</li>
                    <li><strong>Billing/claims data:</strong> invoices, amounts due, payment method/status, transaction references, HMO/insurance claim information.</li>
                    <li><strong>Technical/audit data:</strong> login/session activity and audit logs for security and accountability.</li>
                </ul>

                <h3>2) How We Use Information</h3>
                <p>We process personal data to:</p>
                <ul>
                    <li>Provide clinic services through the Platform (appointments, consultations, lab tracking, billing, reporting, patient portal access).</li>
                    <li>Maintain security, prevent unauthorized access, and keep an audit trail of key system actions.</li>
                    <li>Communicate clinic-related updates (e.g., appointment confirmations/reminders) when enabled.</li>
                    <li>Comply with legal obligations and respond to lawful requests.</li>
                </ul>

                <h3>3) Sharing and Disclosure</h3>
                <p>We may share data only as needed for legitimate clinic operations, such as:</p>
                <ul>
                    <li>With authorized clinic personnel based on role permissions (Doctor/Secretary/Admin).</li>
                    <li>With service providers that support operations (e.g., hosting, email/SMS gateways).</li>
                    <li>With insurers/HMOs only as required to process claims.</li>
                    <li>When required by law, lawful orders, or regulatory processes.</li>
                </ul>
                <p>We do not sell personal medical information.</p>

                <h3>4) Data Retention</h3>
                <p>We retain personal data only as long as necessary for care delivery, operational needs, and legal/regulatory compliance.</p>

                <h3>5) Security Measures</h3>
                <p>We implement reasonable organizational, physical, and technical safeguards to protect personal and sensitive personal information.</p>

                <h3>6) Your Rights (Philippines)</h3>
                <p>Under RA 10173, data subjects have rights such as the right to be informed, to access, to correct, and to object, among others. Requests may be submitted through the contact details below.</p>

                <h3>7) Breach Management</h3>
                <p>If a personal data breach occurs that requires notification, we follow Philippine guidance for notification to the National Privacy Commission within <strong>72 hours</strong>.</p>

                <h3>8) Cookies and Similar Technologies</h3>
                <p>We use cookies for session management and security. You can control cookies through your browser settings, but some features may not work properly.</p>

                <h3>9) Contact (Privacy)</h3>
                <p>For privacy concerns: <strong>privacy@commonwealthmed.com.ph</strong></p>
            </div>
        </div>
    </div>
</body>

</html>