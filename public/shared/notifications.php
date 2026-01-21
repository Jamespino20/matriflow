<?php

/**
 * Notifications Page - Generic for all roles
 * Displays in-app notifications with a modern, premium UI.
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u) redirect('/');

$role = $u['role'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        redirect("/public/$role/notifications.php?error=csrf");
    }

    $action = $_POST['action'] ?? '';
    $notifId = (int)($_POST['notification_id'] ?? 0);
    $userId = (int)$u['user_id'];

    switch ($action) {
        case 'mark_read':
            NotificationService::markAsRead($notifId, $userId);
            break;
        case 'mark_all_read':
            NotificationService::markAllAsRead($userId);
            break;
        case 'delete':
            NotificationService::delete($notifId, $userId);
            break;
        case 'clear_all':
            NotificationService::deleteAll($userId);
            break;
    }

    redirect("/public/$role/notifications.php");
}

// Fetch notifications
$notifications = [];
try {
    $notifications = NotificationService::getAll((int)$u['user_id'], 100);
} catch (Throwable $e) {
    error_log("Notifications table error: " . $e->getMessage());
}

ob_start();
?>
<style>
    .notif-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .notif-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border);
    }

    .notif-actions {
        display: flex;
        gap: 12px;
    }

    .notif-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .notif-card {
        display: flex;
        gap: 20px;
        padding: 20px;
        border-radius: 16px;
        background: var(--surface);
        border: 1px solid var(--border);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        text-decoration: none;
        color: inherit;
    }

    .notif-card:hover {
        transform: translateX(4px);
        border-color: var(--primary-light);
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
    }

    .notif-card.unread {
        background: linear-gradient(135deg, rgba(var(--primary-rgb, 20, 69, 123), 0.03) 0%, var(--surface) 100%);
        border-left: 4px solid var(--primary);
    }

    .notif-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notif-content {
        flex: 1;
        min-width: 0;
    }

    .notif-type {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 4px;
        color: var(--text-secondary);
    }

    .notif-msg {
        font-size: 15px;
        line-height: 1.6;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .notif-time {
        font-size: 12px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .notif-btn-group {
        display: flex;
        gap: 8px;
        align-items: flex-start;
    }

    .empty-state {
        text-align: center;
        padding: 80px 20px;
        background: var(--surface);
        border-radius: 20px;
        border: 2px dashed var(--border);
    }

    .empty-icon {
        font-size: 80px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        opacity: 0.2;
        margin-bottom: 20px;
    }

    /* Unread pulsing dot */
    .unread-dot {
        width: 8px;
        height: 8px;
        background: var(--primary);
        border-radius: 50%;
        position: absolute;
        top: 20px;
        right: 20px;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb, 20, 69, 123), 0.1);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(var(--primary-rgb, 20, 69, 123), 0.4);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 10px rgba(var(--primary-rgb, 20, 69, 123), 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(var(--primary-rgb, 20, 69, 123), 0);
        }
    }
</style>

<div class="notif-container">
    <div class="notif-header">
        <div>
            <h1 style="margin: 0; font-size: 28px;">Notifications</h1>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Updates regarding your health and records.</p>
        </div>
        <div class="notif-actions">
            <?php if (!empty($notifications)): ?>
                <form method="POST" style="display: contents;">
                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-secondary" title="Mark everything as seen">
                        <span class="material-symbols-outlined">done_all</span> Mark All Read
                    </button>
                </form>
                <form method="POST" style="display: contents;" onsubmit="return confirm('Delete all notifications? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-outline" style="color: var(--error); border-color: rgba(var(--error-rgb, 244, 67, 54), 0.2);">
                        <span class="material-symbols-outlined">delete_sweep</span> Clear All
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <span class="material-symbols-outlined empty-icon">notifications_active</span>
            <h2 style="margin: 0; color: var(--text-primary);">All Caught Up!</h2>
            <p style="color: var(--text-secondary); margin: 10px 0 0;">You have no new or archived notifications at the moment.</p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $n): ?>
                <?php
                $type = $n['type'] ?? 'general';
                $config = match ($type) {
                    'appointment_reminder' => ['icon' => 'calendar_month', 'bg' => '#e0e7ff', 'color' => '#4338ca', 'label' => 'Appointment'],
                    'lab_result' => ['icon' => 'biotech', 'bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'Laboratory'],
                    'payment_due' => ['icon' => 'account_balance_wallet', 'bg' => '#fef9c3', 'color' => '#a16207', 'label' => 'Billing'],
                    'admin_broadcast' => ['icon' => 'campaign', 'bg' => '#ffedd5', 'color' => '#c2410c', 'label' => 'Announcement'],
                    default => ['icon' => 'info', 'bg' => '#f1f5f9', 'color' => '#475569', 'label' => 'General']
                };
                ?>
                <div class="notif-card <?= $n['is_read'] ? '' : 'unread' ?>">
                    <?php if (!$n['is_read']): ?>
                        <div class="unread-dot"></div>
                    <?php endif; ?>

                    <div class="notif-icon-wrapper" style="background: <?= $config['bg'] ?>; color: <?= $config['color'] ?>;">
                        <span class="material-symbols-outlined"><?= $config['icon'] ?></span>
                    </div>

                    <div class="notif-content">
                        <div class="notif-type"><?= $config['label'] ?></div>
                        <div class="notif-msg"><?= e($n['message']) ?></div>
                        <div class="notif-time">
                            <span class="material-symbols-outlined" style="font-size: 14px;">schedule</span>
                            <?= date('M j, Y • g:i A', strtotime($n['created_at'])) ?>
                        </div>
                    </div>

                    <div class="notif-btn-group">
                        <?php if (!$n['is_read']): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                                <button type="submit" class="btn btn-icon btn-sm" title="Mark as read" style="color: var(--primary);">
                                    <span class="material-symbols-outlined">done</span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= CSRF::token() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                            <button type="submit" class="btn btn-icon btn-sm" title="Delete notification" style="color: var(--text-secondary); opacity: 0.6;">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Subtle entry animations for cards
    document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('.notif-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            setTimeout(() => {
                card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
</script>

<?php
$content = ob_get_clean();
RoleLayout::render($u, $role, 'notifications', [
    'title' => 'Notifications',
    'content' => $content,
]);
