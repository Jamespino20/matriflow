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
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php'],
        ],
        'secretary' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/secretary/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/secretary/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/secretary/appointments.php'],
            ['icon' => 'group', 'label' => 'Patients', 'url' => '/public/secretary/patients.php'],
            ['icon' => 'medical_services', 'label' => 'Lab Tests', 'url' => '/public/secretary/lab-tests.php'],
            ['icon' => 'schedule', 'label' => 'Schedules', 'url' => '/public/secretary/schedules.php'],
            ['icon' => 'groups', 'label' => 'Queues', 'url' => '/public/secretary/queues.php'],
            ['icon' => 'receipt_long', 'label' => 'Payments', 'url' => '/public/secretary/payments.php'],
            ['icon' => 'description', 'label' => 'Records', 'url' => '/public/secretary/records.php'],
            ['icon' => 'folder', 'label' => 'Files', 'url' => '/public/secretary/files.php'],
            ['icon' => 'assessment', 'label' => 'Reports', 'url' => '/public/secretary/report-generation.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php'],
        ],
        'doctor' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/doctor/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/doctor/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'group', 'label' => 'Patients', 'url' => '/public/doctor/patients.php'],
            ['icon' => 'medical_services', 'label' => 'Lab Tests', 'url' => '/public/doctor/lab-tests.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/doctor/appointments.php'],
            ['icon' => 'schedule', 'label' => 'Schedules', 'url' => '/public/doctor/schedules.php'],
            ['icon' => 'groups', 'label' => 'Queues', 'url' => '/public/doctor/queues.php'],
            ['icon' => 'receipt_long', 'label' => 'Payments', 'url' => '/public/doctor/payments.php'],
            ['icon' => 'description', 'label' => 'Records', 'url' => '/public/doctor/records.php'],
            ['icon' => 'folder', 'label' => 'Files', 'url' => '/public/doctor/files.php'],
            ['icon' => 'assessment', 'label' => 'Reports', 'url' => '/public/doctor/report-generation.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php'],
        ],
        'admin' => [
            ['icon' => 'dashboard', 'label' => 'Dashboard', 'url' => '/public/admin/dashboard.php'],
            ['icon' => 'person', 'label' => 'Profile', 'url' => '/public/admin/profile.php'],
            ['icon' => 'forum', 'label' => 'Messages', 'url' => '/public/shared/messages.php'],
            ['icon' => 'calendar_month', 'label' => 'Appointments', 'url' => '/public/admin/appointments.php'],
            ['icon' => 'schedule', 'label' => 'Schedules', 'url' => '/public/admin/schedules.php'],
            ['icon' => 'groups', 'label' => 'Queues', 'url' => '/public/admin/queues.php'],
            ['icon' => 'admin_panel_settings', 'label' => 'User Management', 'url' => '/public/admin/user-management.php'],
            ['icon' => 'list_alt', 'label' => 'Audit Logs', 'url' => '/public/admin/audit-logs.php'],
            ['icon' => 'assessment', 'label' => 'Reports', 'url' => '/public/admin/report-generation.php'],
            ['icon' => 'settings', 'label' => 'System', 'url' => '/public/admin/system.php'],
            ['icon' => 'folder', 'label' => 'Files', 'url' => '/public/admin/files.php'],
            ['icon' => 'logout', 'label' => 'Logout', 'url' => '/public/logout.php'],
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
        $avatarPath = base_url("/public/assets/images/avatars/{$user['user_id']}.png");
        $version = time(); // Simple cache buster

        // Load System Name
        $systemName = 'MatriFlow';
        try {
            $stmt = db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_name'");
            $stmt->execute();
            if ($row = $stmt->fetch()) {
                $systemName = $row['setting_value'] ?: 'MatriFlow';
            }
        } catch (Throwable $e) {
        }

        // Override title suffix
        $pageTitle = $title;
        $fullTitle = "$pageTitle - $systemName";

?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title><?= htmlspecialchars($fullTitle) ?></title>

            <!-- Core Assets -->
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

                    const theme = localStorage.getItem('matriflow_theme') || 'light';
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
                                <a href="<?= $url ?>"
                                    class="nav-item <?= $currentPage === basename($item['url'], '.php') ? 'active' : '' ?>">
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
                <img src="<?= base_url("/public/assets/images/loading-spinner.gif") ?>" alt="Loading..." style="width: 80px; height: 80px;">
            </div>

            <style>
                .page-loader {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(255, 255, 255, 0.9);
                    z-index: 99999;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    backdrop-filter: blur(4px);
                }

                .page-loader.show {
                    display: flex;
                }
            </style>

            <script src="<?= base_url("/public/assets/js/app.js") ?>"></script>
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
                            // Delay navigation by 2 seconds to allow GIF animation to complete
                            setTimeout(() => {
                                window.location.href = href;
                            }, 2000);
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
            </div>

            <!-- Global Avatar Upload Modal -->
            <div id="modal-avatar-upload" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
                <div class="modal-card" style="background:var(--surface); width:90%; max-width:500px; border-radius:12px; padding:32px; position:relative;">
                    <button type="button" class="modal-close" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-secondary);" onclick="document.getElementById('modal-avatar-upload').style.display='none'">&times;</button>
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

            <style>
                .modal-overlay {
                    display: none;
                }

                .modal-overlay.show {
                    display: flex !important;
                }

                .header-avatar-container {
                    position: relative;
                }

                .header-avatar {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    object-fit: cover;
                    border: 2px solid var(--border);
                }

                /* Inactivity Modal Specifics */
                .modal-inactivity {
                    text-align: center;
                }

                .modal-inactivity .material-symbols-outlined {
                    font-size: 64px;
                    color: var(--warning);
                    margin-bottom: 16px;
                }
            </style>

            <!-- Inactivity Modal -->
            <div id="modal-inactivity" class="modal-overlay modal-clean-center" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:10001; background:rgba(0,0,0,0.8); align-items:center; justify-content:center; backdrop-filter: blur(4px);">
                <div class="modal-card modal-inactivity" style="background:var(--surface); width:90%; max-width:400px; border-radius:12px; padding:40px; box-shadow: var(--shadow-lg);">
                    <span class="material-symbols-outlined">timer_off</span>
                    <h2 style="margin-bottom:12px; font-size:22px; color:var(--text-primary);">Session Timeout</h2>
                    <p style="color:var(--text-secondary); margin-bottom:24px; line-height:1.6;">Your session is about to expire due to inactivity. For your security, you will be redirected to the home page.</p>
                    <button type="button" class="btn btn-primary" style="width:100%;" onclick="window.location.href='<?= base_url("/public/logout.php") ?>'">Logout Now</button>
                </div>
            </div>
        </body>

        </html>
<?php
    }
}
