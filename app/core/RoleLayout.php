<?php

declare(strict_types=1);

/**
 * RoleLayout: Shared layout for role-based pages
 * 
 * Usage:
 *   RoleLayout::render($user, 'patient', 'profile', [
 *       'title' => 'My Profile',
 *       'content' => '<h2>Profile Content</h2>'
 *   ]);
 */
class RoleLayout
{
    private static array $sidebarItems = [
        'patient' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/patient/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/patient/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/patient/appointments.php'],
            ['icon' => 'child_care', 'label' => 'Pregnancy Records', 'url' => '/public/patient/pregnancy-records.php'],
            ['icon' => 'medical_services', 'label' => 'Lab Tests', 'url' => '/public/patient/lab-tests.php'],
            ['icon' => 'description', 'label' => 'Files', 'url' => '/public/patient/files.php'],
            ['icon' => 'receipt_long', 'label' => 'Payments', 'url' => '/public/patient/payments.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php', 'class' => 'logout-link'],
        ],
        'secretary' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/secretary/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/secretary/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/secretary/appointments.php'],
            ['icon' => 'group', 'label' => 'Patients', 'url' => '/public/secretary/patients.php'],
            ['icon' => 'medical_services', 'label' => 'Lab Tests', 'url' => '/public/secretary/lab-tests.php'],
            ['icon' => 'schedule', 'label' => 'Schedules', 'url' => '/public/secretary/schedules.php'],
            ['icon' => 'receipt_long', 'label' => 'Payments', 'url' => '/public/secretary/payments.php'],
            ['icon' => 'health_and_safety', 'label' => 'HMO Claims', 'url' => '/public/secretary/hmo-claims.php'],
            ['icon' => 'folder', 'label' => 'Files', 'url' => '/public/secretary/files.php'],
            ['icon' => 'assessment', 'label' => 'Reports', 'url' => '/public/secretary/report-generation.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php', 'class' => 'logout-link'],
        ],
        'doctor' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/doctor/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/doctor/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'group', 'label' => 'Patients', 'url' => '/public/doctor/patients.php'],
            ['icon' => 'medical_services', 'label' => 'Lab Tests', 'url' => '/public/doctor/lab-tests.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/doctor/appointments.php'],
            ['icon' => 'schedule', 'label' => 'Schedules', 'url' => '/public/doctor/schedules.php'],
            ['icon' => 'receipt_long', 'label' => 'Payments', 'url' => '/public/doctor/payments.php'],
            ['icon' => 'folder', 'label' => 'Files', 'url' => '/public/doctor/files.php'],
            ['icon' => 'assessment', 'label' => 'Reports', 'url' => '/public/doctor/report-generation.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php', 'class' => 'logout-link'],
        ],
        'admin' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/admin/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/admin/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/admin/appointments.php'],
            ['icon' => 'schedule', 'label' => 'Schedules', 'url' => '/public/admin/schedules.php'],
            ['icon' => 'admin_panel_settings', 'label' => 'User Management', 'url' => '/public/admin/user-management.php'],
            ['icon' => 'list_alt', 'label' => 'Audit Logs', 'url' => '/public/admin/audit-logs.php'],
            ['icon' => 'assessment', 'label' => 'Reports', 'url' => '/public/admin/report-generation.php'],
            ['icon' => 'receipt_long', 'label' => 'Payments', 'url' => '/public/admin/payments.php'],
            ['icon' => 'health_and_safety', 'label' => 'HMO Claims', 'url' => '/public/admin/hmo-claims.php'],
            ['icon' => 'folder', 'label' => 'Files', 'url' => '/public/admin/files.php'],
            ['icon' => 'settings', 'label' => 'System', 'url' => '/public/admin/system.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php', 'class' => 'logout-link'],
        ],
    ];

    public static function render(array $user, string $role, string $currentPage, array $options = []): void
    {
        $title = $options['title'] ?? 'MatriFlow';
        $content = $options['content'] ?? '';
        $messages = $options['messages'] ?? [];

        // Ensure role is lowercase for array lookup
        $roleKey = strtolower($role);
        $sidebarItems = self::$sidebarItems[$roleKey] ?? [];

        $firstName = htmlspecialchars((string)($user['first_name'] ?? 'User'));
        $lastName = htmlspecialchars((string)($user['last_name'] ?? ''));
        $avatarPath = FileService::getAvatarUrl((int)$user['user_id']);
        $version = '4.1.0'; // Static version for better caching

        // Load System Name
        $systemName = 'MatriFlow';
        try {
            $stmt = Database::getInstance()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_name'");
            $stmt->execute();
            if ($row = $stmt->fetch()) {
                $systemName = $row['setting_value'] ?: 'MatriFlow';
            }
        } catch (Throwable $e) {
        }

        // Override title suffix
        $pageTitle = $title;
        $fullTitle = "$pageTitle - $systemName";

        // Prevent browser caching of authenticated pages
        header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
        header("Pragma: no-cache"); // HTTP 1.0.
        header("Expires: 0"); // Proxies.
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title><?= htmlspecialchars($fullTitle) ?></title>

            <!-- Core Assets -->
            <link rel="stylesheet" href="<?= base_url("/public/assets/css/app.css?v={$version}") ?>" />
            <link rel="stylesheet" href="<?= base_url("/public/assets/css/role-layout.css?v={$version}") ?>" />
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" />
            <link rel="icon" href="<?= base_url("/public/assets/images/favicon.ico") ?>" />

            <!-- Immediate secure configuration application -->
            <script>
                (function() {
                    const html = document.documentElement;
                    html.dataset.baseUrl = '<?= base_url('/public') ?>';
                    html.dataset.role = '<?= $roleKey ?>';
                    html.dataset.csrfToken = '<?= CSRF::token() ?>';

                    const theme = sessionStorage.getItem('matriflow_theme') || 'light';
                    if (theme === 'dark') {
                        html.classList.add('dark-theme');
                    }
                })();
            </script>
        </head>

        <body class="<?= (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark-theme' : '' ?>">
            <div class="role-layout">
                <!-- Header -->
                <header class="role-header">
                    <div class="header-brand">
                        <img src="<?= base_url("/public/assets/images/matriflow_banner.png") ?>" alt="<?= htmlspecialchars($systemName) ?>" class="banner-logo" onerror="this.onerror=null; this.src='https://placehold.co/180x40?text=<?= urlencode($systemName) ?>'">
                    </div>
                    <div class="header-title"><?= htmlspecialchars((string)$title) ?></div>
                    <div class="header-spacer"></div>
                    <?php
                    // Get notification count
                    $notificationCount = 0;
                    try {
                        $notificationCount = NotificationService::getUnreadCount((int)$user['user_id']);
                    } catch (Throwable $e) {
                        // Table might not exist yet, ignore
                    }
                    ?>
                    <div class="header-notifications" style="position: relative; margin-right: 16px;">
                        <a href="<?= base_url("/public/{$role}/notifications.php") ?>" class="btn btn-icon" title="Notifications" style="position: relative;">
                            <span class="material-symbols-outlined">notifications</span>
                            <?php if ($notificationCount > 0): ?>
                                <span style="position: absolute; top: -4px; right: -4px; background: var(--error); color: white; border-radius: 50%; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0 4px;">
                                    <?= $notificationCount > 99 ? '99+' : $notificationCount ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <div class="header-user">
                        <span class="header-role" style="color: var(--text-secondary); font-size: 11px;"><?= ucfirst($role) ?></span>
                        <div class="header-avatar-container" onclick="window.location.href='<?= base_url("/public/{$role}/profile.php") ?>'" style="cursor:pointer;">
                            <img src="<?= $avatarPath ?>?t=<?= $version ?>" alt="Avatar" class="header-avatar"
                                onerror="this.onerror=null; this.src='<?= base_url("/public/assets/images/default-avatar.png") ?>'">
                        </div>
                    </div>
                </header>

                <div class="role-container">
                    <!-- Sidebar -->
                    <aside class="role-sidebar">
                        <nav class="sidebar-nav">
                            <?php foreach ($sidebarItems as $item):
                                $url = base_url($item['url']);
                            ?>
                                <?php
                                $itemPage = basename($item['url'], '.php');
                                $isActive = ($currentPage === $itemPage);

                                // [UI FIX] Mutually exclusive parent highlighting for sub-pages
                                if (!$isActive && $currentPage === 'records') {
                                    // Map sub-page to its PRIMARY parent to avoid double-highlighting
                                    $primaryParent = match ($roleKey) {
                                        'patient' => 'pregnancy-records',
                                        'doctor', 'secretary' => 'patients',
                                        default => 'dashboard'
                                    };
                                    if ($itemPage === $primaryParent) $isActive = true;
                                }
                                ?>
                                <a href="<?= $url ?>"
                                    class="nav-item <?= $isActive ? 'active' : '' ?> <?= $item['class'] ?? '' ?>">
                                    <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
                                    <span class="nav-label"><?= $item['label'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>

                        <!-- Sidebar Footer: Emergency & Clinic Info -->
                        <div class="sidebar-footer">
                            <div class="emergency-label">Emergency Hotline</div>
                            <div class="emergency-info">
                                <a href="tel:911" class="btn btn-danger btn-sidebar-hotline">
                                    <span class="material-symbols-outlined">call</span>
                                    <span>Call 911</span>
                                </a>
                                <a href="tel:0289300000" class="btn btn-danger btn-sidebar-hotline">
                                    <span class="material-symbols-outlined">local_hospital</span>
                                    <span>Call CHMC</span>
                                </a>
                            </div>
                        </div>
                    </aside>

                    <!-- Main Content -->
                    <main class="role-main">
                        <!-- Alert/Message Section -->
                        <?php if (!empty($messages)): ?>
                            <div class="alerts-section">
                                <?php foreach ($messages as $msg): ?>
                                    <div class="alert alert-<?= $msg['type'] ?? 'info' ?>">
                                        <span class="material-symbols-outlined"><?= $msg['icon'] ?? 'info' ?></span>
                                        <div class="alert-content">
                                            <strong><?= $msg['title'] ?? '' ?></strong>
                                            <p><?= $msg['text'] ?? '' ?></p>
                                        </div>
                                        <?php if ($msg['action_url'] ?? null): ?>
                                            <a href="<?= $msg['action_url'] ?>"
                                                class="alert-action"><?= $msg['action_label'] ?? 'View' ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Toast Container for dynamic messages -->
                        <div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>

                        <!-- Page Content -->
                        <div class="page-content">
                            <?= $content ?>
                        </div>
                    </main>
                </div>
            </div>

            <!-- Page Loader Overlay -->
            <div id="page-loader" class="page-loader">
                <img src="<?= base_url("/public/assets/images/loading-spinner.gif") ?>" alt="Loading..." style="width: 150px; height: 150px;">
            </div>



            <script src="<?= base_url("/public/assets/js/app.js") ?>"></script>
            <script src="<?= base_url("/public/assets/js/auth.js") ?>"></script>
            <script src="<?= base_url("/public/assets/js/dashboard.js") ?>"></script>
            <script>
                // Page Transition Loader Logic
                document.addEventListener('DOMContentLoaded', () => {
                    const loader = document.getElementById('page-loader');
                    // Show loader on internal link clicks
                    document.querySelectorAll('a').forEach(link => {
                        link.addEventListener('click', (e) => {
                            const href = link.getAttribute('href');
                            // Ignore hash links, javascript:, external links, and tel: links
                            if (!href ||
                                href.startsWith('#') ||
                                href.startsWith('javascript:') ||
                                href.startsWith('tel:') ||
                                link.target === '_blank') return;

                            // Ignore logout (optional, but logout is fast)
                            if (href.includes('logout.php')) return;

                            // Show loader
                            e.preventDefault();
                            loader.classList.add('show');
                            // Delay navigation slightly to allow GIF animation to complete
                            setTimeout(() => {
                                window.location.href = href;
                            }, 300);
                        });
                    });

                    // Also show on form submit
                    document.querySelectorAll('form').forEach(form => {
                        form.addEventListener('submit', (e) => {
                            if (e.defaultPrevented) return;
                            if (!form.target || form.target === '_self') {
                                loader.classList.add('show');
                            }
                        });
                    });

                    // Hide loader if page is shown via cache (bfcache)
                    window.addEventListener('pageshow', (event) => {
                        if (event.persisted) {
                            loader.classList.remove('show');
                        }
                    });
                });
            </script>

            <!-- Global Avatar Upload Modal -->
            <div id="modal-avatar-upload" class="modal-overlay modal-clean-center">
                <div class="modal-card" style="background:var(--surface); width:95%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
                    <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px;" onclick="document.getElementById('modal-avatar-upload').classList.remove('show')">&times;</button>
                    <h2 style="margin-bottom:16px; font-size:20px; color:var(--text-primary);">Update Profile Picture</h2>

                    <div class="avatar-crop-container" style="text-align:center;">
                        <canvas id="avatar-canvas" width="300" height="300" style="max-width:100%; height:auto; background:#f0f2f4; border-radius:8px; cursor:move; border:1px solid var(--border);"></canvas>
                        <div style="margin-top:20px;">
                            <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-secondary);">Zoom</label>
                            <input type="range" id="avatar-zoom" min="0.5" max="3" step="0.01" value="1" style="width:100%;">
                        </div>
                        <div style="margin-top:24px; display:flex; gap:12px;">
                            <input type="file" id="avatar-file-input" accept="image/*" style="display:none;">
                            <button type="button" class="btn btn-secondary" style="flex:1;" onclick="document.getElementById('avatar-file-input').click()">Choose Image</button>
                            <button type="button" id="btn-save-avatar" class="btn btn-primary" style="flex:1;">Save Image</button>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Inactivity Modal -->
            <div id="modal-inactivity" class="modal-overlay modal-clean-center">
                <div class="modal-card modal-inactivity" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:40px; box-shadow: var(--shadow-lg);">
                    <span class="material-symbols-outlined">timer_off</span>
                    <h2 style="margin-bottom:12px; font-size:22px; color:var(--text-primary);">Session Timeout</h2>
                    <p style="color:var(--text-secondary); margin-bottom:24px; line-height:1.6;">Your session is about to expire due to inactivity. For your security, you will be redirected to the home page.</p>
                    <button type="button" class="btn btn-primary" style="width:100%;" onclick="window.location.href='<?= base_url("/public/logout.php") ?>'">Logout Now</button>
                </div>
            </div>

            <!-- Logout Confirmation Modal -->
            <div id="modal-logout-confirm" class="modal-overlay modal-clean-center">
                <div class="modal-card" style="background:var(--surface); width:90%; max-width:400px; border-radius:16px; padding:32px; text-align:center;">
                    <div style="width:64px; height:64px; background:rgba(239, 68, 68, 0.1); color:var(--error); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                        <span class="material-symbols-outlined" style="font-size:32px;">logout</span>
                    </div>
                    <h2 style="margin-bottom:12px; font-size:22px;">Confirm Logout</h2>
                    <p style="color:var(--text-secondary); margin-bottom:24px;">Are you sure you want to end your session? You will need to log in again to access your records.</p>
                    <div style="display:flex; gap:12px;">
                        <button type="button" class="btn btn-secondary" style="flex:1;" onclick="document.getElementById('modal-logout-confirm').classList.remove('show')">Cancel</button>
                        <a href="<?= base_url('/public/logout.php?confirm=1') ?>" class="btn btn-danger" style="flex:1; background:var(--error); color:white; text-decoration:none;">Logout</a>
                    </div>
                </div>
            </div>

            <script>
                document.querySelectorAll('.logout-link').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        document.getElementById('modal-logout-confirm').classList.add('show');
                    });
                });
            </script>
        </body>

        </html>
<?php
    }
}
