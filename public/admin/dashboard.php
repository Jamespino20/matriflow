<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

ob_start();
?>
<?php
$stats = AdminController::getDashboardStats();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px;">
    <div class="left-col">
        <div class="card" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border: none;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin: 0; font-size: 24px;">System Overview</h1>
                    <p style="margin: 8px 0 0; opacity: 0.8; font-size: 14px;">Master control panel for MatriFlow instances.</p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 12px; opacity: 0.7; margin-bottom: 4px;">SYSTEM STATUS</div>
                    <div style="display: flex; align-items: center; gap: 8px; justify-content: flex-end;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; box-shadow: 0 0 10px #10b981;"></span>
                        <span style="font-weight: 700; font-size: 14px; color: #10b981;"><?= $stats['systemStatus'] ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 24px;">
            <div class="card">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Total Users</div>
                <div style="font-size: 28px; font-weight: 800;"><?= $stats['totalUsers'] ?></div>
            </div>
            <div class="card">
                <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Total Revenue</div>
                <div style="font-size: 28px; font-weight: 800; color: #10b981;">₱<?= number_format($stats['totalRevenue'], 2) ?></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 16px;">
            <div class="card" style="display:flex; align-items:center; gap:12px; padding:16px;">
                <div style="background:var(--warning-light); color:var(--warning); padding:8px; border-radius:8px;"><span class="material-symbols-outlined">pending_actions</span></div>
                <div>
                    <div style="font-size: 22px; font-weight: 700;"><?= $stats['pendingCount'] ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Pending</div>
                </div>
            </div>
            <div class="card" style="display:flex; align-items:center; gap:12px; padding:16px;">
                <div style="background:var(--primary-light); color:var(--primary); padding:8px; border-radius:8px;"><span class="material-symbols-outlined">today</span></div>
                <div>
                    <div style="font-size: 22px; font-weight: 700;"><?= $stats['scheduledToday'] ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Today</div>
                </div>
            </div>
            <div class="card" style="display:flex; align-items:center; gap:12px; padding:16px;">
                <div style="background:var(--success-light); color:var(--success); padding:8px; border-radius:8px;"><span class="material-symbols-outlined">check_circle</span></div>
                <div>
                    <div style="font-size: 22px; font-weight: 700;"><?= $stats['completedThisMonth'] ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Completed (Month)</div>
                </div>
            </div>
            <div class="card" style="display:flex; align-items:center; gap:12px; padding:16px;">
                <div style="background:var(--error-light); color:var(--error); padding:8px; border-radius:8px;"><span class="material-symbols-outlined">cancel</span></div>
                <div>
                    <div style="font-size: 22px; font-weight: 700;"><?= $stats['cancelledThisMonth'] ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Cancelled (Month)</div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
            <div class="card" style="display:flex; align-items:center; gap:12px; padding:16px;">
                <div style="background:var(--success-light); color:var(--success); padding:8px; border-radius:8px;"><span class="material-symbols-outlined">groups</span></div>
                <div>
                    <div style="font-size: 22px; font-weight: 700;"><?= $stats['activePatients'] ?></div>
                    <div style="font-size: 12px; color: var(--text-secondary);">Active Patients</div>
                </div>
            </div>
            <div class="card" style="padding:16px;">
                <h4 style="margin: 0 0 12px 0; font-size: 13px; text-transform: uppercase; color: var(--text-secondary);">System Health</h4>
                <div style="display: flex; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: <?= $stats['dbHealth'] ? '#10b981' : '#ef4444' ?>;"></span>
                        <span style="font-size: 13px;">Database</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: <?= $stats['storageHealth'] ? '#10b981' : '#ef4444' ?>;"></span>
                        <span style="font-size: 13px;">Storage</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin:0;">Pending Appointment Requests</h3>
                <a href="<?= base_url('/public/admin/appointments.php') ?>" style="font-size: 12px; font-weight: 700;">View All →</a>
            </div>
            <div style="margin-top: 10px;">
                <?php if (empty($stats['pendingList'])): ?>
                    <p style="padding: 20px; text-align: center; color: var(--text-secondary);">No pending requests at the moment.</p>
                <?php else: ?>
                    <?php foreach ($stats['pendingList'] as $apt): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                            <div>
                                <div style="font-weight: 700; font-size: 14px;"><?= htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']) ?></div>
                                <div style="font-size: 12px; color: var(--text-secondary);"><?= date('M j, Y @ H:i', strtotime($apt['appointment_date'])) ?></div>
                            </div>
                            <span class="badge" style="background: var(--warning-light); color: var(--warning); border: none;">PENDING</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-top: 24px;">
            <h3>Recent System Activity (Audit Logs)</h3>
            <div style="margin-top: 15px;">
                <?php if (empty($stats['recentLogs'])): ?>
                    <p style="padding: 20px; text-align: center; color: var(--text-secondary);">No recent activity recorded.</p>
                <?php else: ?>
                    <?php foreach ($stats['recentLogs'] as $log): ?>
                        <div style="display: flex; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--border);">
                            <div style="font-size: 11px; color: var(--text-secondary); width: 80px; flex-shrink: 0;"><?= date('H:i:s', strtotime($log['logged_at'] ?? 'now')) ?></div>
                            <div style="font-size: 13px;">
                                <strong><?= e((string)($log['operation'] ?? 'ACTION')) ?></strong> on <code><?= e((string)($log['table_name'] ?? 'system')) ?></code> (ID: <?= $log['record_id'] ?? '?' ?>)
                                <div style="font-size: 11px; color: var(--text-secondary);">By User ID: <?= $log['user_id'] ?> | IP: <?= e((string)($log['ip_address'] ?? 'N/A')) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="card">
            <h3 style="margin-bottom: 16px;">Quick Controls</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/user-management.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">manage_accounts</span>
                    User Directory
                </button>
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/audit-logs.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">list_alt</span>
                    System Logs
                </button>
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/report-generation.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">assessment</span>
                    Global Reports
                </button>
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/payments.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">payments</span>
                    Financial Records
                </button>
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/appointments.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">event</span>
                    Appointments
                </button>
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/schedules.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">schedule</span>
                    Doctor Schedules
                </button>
                <button class="btn btn-secondary" style="width:100%; justify-content: flex-start;" onclick="window.location.href='<?= base_url('/public/admin/queues.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">queue</span>
                    Queue Monitor
                </button>
                <button class="btn btn-primary" style="width:100%; justify-content: flex-start; margin-top: 10px;" onclick="window.location.href='<?= base_url('/public/admin/system.php') ?>'">
                    <span class="material-symbols-outlined" style="margin-right: 12px;">settings</span>
                    System Settings
                </button>
            </div>
        </div>

        <div class="card" style="margin-top: 24px; padding: 25px; background: var(--surface-hover);">
            <div style="font-style: italic; font-size: 13px; line-height: 1.6; color: var(--text-primary);">
                "<?= $stats['quote'] ?>"
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'dashboard', [
    'title' => 'Dashboard',
    'content' => $content,
]);
