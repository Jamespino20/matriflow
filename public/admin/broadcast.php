<?php

/**
 * Admin Broadcast Notifications
 * Allows admins to send notifications to all users or by role
 */
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin') redirect('/');

$success = null;
$error = null;

// Handle broadcast
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } else {
        $message = trim($_POST['message'] ?? '');
        $role = $_POST['target_role'] ?? 'all';

        if (strlen($message) < 5) {
            $error = "Message must be at least 5 characters.";
        } else {
            try {
                $count = NotificationService::broadcast($role, $message, (int)$u['user_id']);
                $success = "Notification sent to $count users.";
            } catch (Throwable $e) {
                $error = "Failed to send notifications: " . $e->getMessage();
            }
        }
    }
}

ob_start();
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Broadcast Notifications</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Send important announcements to users system-wide.</p>
        </div>
        <a href="<?= base_url('/public/admin/notifications.php') ?>" class="btn btn-outline">
            <span class="material-symbols-outlined">inbox</span> My Notifications
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" style="max-width: 600px;">
        <input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

        <div class="form-group">
            <label>Target Audience</label>
            <select name="target_role" style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;">
                <option value="all">All Users</option>
                <option value="patient">Patients Only</option>
                <option value="doctor">Doctors Only</option>
                <option value="secretary">Secretaries Only</option>
            </select>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea name="message" rows="4" placeholder="Enter your announcement message..." required
                style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px;"></textarea>
            <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">This message will appear in users' notification inbox.</p>
        </div>

        <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">send</span> Send Broadcast
            </button>
        </div>
    </form>
</div>

<div class="card" style="margin-top: 24px;">
    <h3 style="margin-top: 0;">Recent Broadcasts</h3>
    <?php
    // Show recent broadcasts
    try {
        $stmt = db()->prepare("
            SELECT n.*, COUNT(n2.notification_id) as recipient_count 
            FROM notifications n
            LEFT JOIN notifications n2 ON n.message = n2.message AND n.created_at = n2.created_at
            WHERE n.type = 'admin_broadcast' AND n.related_id = ?
            GROUP BY n.notification_id
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$u['user_id']]);
        $recentBroadcasts = $stmt->fetchAll();
    } catch (Throwable $e) {
        $recentBroadcasts = [];
    }
    ?>

    <?php if (empty($recentBroadcasts)): ?>
        <p style="color: var(--text-secondary);">No broadcasts sent yet.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($recentBroadcasts as $b): ?>
                <div style="padding: 12px; border: 1px solid var(--border); border-radius: 8px;">
                    <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 8px;">
                        <?= date('M j, Y \a\t g:i A', strtotime($b['created_at'])) ?>
                        • <?= $b['recipient_count'] ?> recipients
                    </div>
                    <div><?= htmlspecialchars($b['message']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'broadcast', [
    'title' => 'Broadcast Notifications',
    'content' => $content,
]);
