<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
$u = Auth::user();
if (!$u || $u['role'] !== 'secretary')
    redirect('/');

ob_start();
?>
<?php
$stats = SecretaryController::getDashboardStats((int)$u['user_id']);
$appointments = $stats['recentAppointments'] ?? [];
?>
<div style="display: grid; grid-template-columns: 1fr 320px; gap: 24px;">
    <div class="left-col">
        <div class="card" style="background: linear-gradient(135deg, #ed2327 0%, #b91c1c 100%); color: white; border: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px;">
                <div>
                    <h1 style="margin: 0; font-size: 24px;">Mabuhay, <?= e($u['first_name']) ?></h1>
                    <p style="margin: 8px 0 0; opacity: 0.8; font-size: 14px;">Operational overview: <strong><?= $stats['todayAppointments'] ?></strong> appointments today.</p>
                </div>
                <div style="text-align: right; max-width: 300px;">
                    <div style="font-style: italic; font-size: 12px; opacity: 0.9; line-height: 1.4;">"<?= $stats['quote'] ?>"</div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px;">
            <div class="card">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Pending Requests</div>
                <div style="font-size: 28px; font-weight: 800; color: #f59e0b;"><?= $stats['pendingRequests'] ?></div>
            </div>
            <div class="card">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Unpaid Billing</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--error);"><?= $stats['pendingPayments'] ?></div>
            </div>
            <div class="card">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Messages</div>
                <div style="font-size: 28px; font-weight: 800; color: var(--primary);"><?= $stats['unreadCount'] ?></div>
            </div>
        </div>

        <div class="card" style="margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0;">Recent Appointments</h3>
                <a href="<?= base_url('/public/secretary/appointments.php') ?>" style="font-size: 13px; color: var(--primary); font-weight: 600;">Manage All</a>
            </div>

            <?php if (empty($appointments)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-secondary);">
                    <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 12px;">event_available</span>
                    No appointments recorded for today.
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= date('h:i A', strtotime($appt['appointment_date'])) ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?= e($appt['first_name'] . ' ' . $appt['last_name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-secondary);">ID: <?= e($appt['identification_number'] ?? 'N/A') ?> | <?= e($appt['contact_number'] ?? 'No Phone') ?></div>
                                </td>
                                <td><span class="badge badge-<?= $appt['appointment_status'] ?>"><?= strtoupper($appt['appointment_status']) ?></span></td>
                                <td style="text-align: right;">
                                    <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?= base_url('/public/secretary/appointments.php?search=' . urlencode($appt['first_name'])) ?> '">Manage</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="right-col">
        <div class="card">
            <h3 style="margin-bottom: 16px;">Reception Tools</h3>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <button class="btn btn-primary" style="width: 100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/secretary/appointments.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">event_note</span>
                    Appointment Desk
                </button>
                <button class="btn btn-secondary" style="width: 100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/secretary/patients.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">person_search</span>
                    Patient Lookup
                </button>
                <button class="btn btn-secondary" style="width: 100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/secretary/payments.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">receipt_long</span>
                    Billing & Payments
                </button>
                <button class="btn btn-secondary" style="width: 100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/shared/messages.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">mail</span>
                    Inbox
                </button>
            </div>
        </div>


    </div>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'secretary', 'dashboard', [
    'title' => 'Dashboard',
    'content' => $content,
]);
