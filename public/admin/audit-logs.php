<?php
require_once __DIR__ . '/../../bootstrap.php';
Auth::enforce2FA();
Auth::requireLogin();

$u = Auth::user();
if (!$u || $u['role'] !== 'admin')
    redirect('/');

ob_start();
?>
<?php
$q = trim((string)($_GET['q'] ?? ''));
$op = trim((string)($_GET['op'] ?? ''));
$table = trim((string)($_GET['table'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR a.table_name LIKE :q3 OR a.changes_made LIKE :q4)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
    $params[':q3'] = "%$q%";
    $params[':q4'] = "%$q%";
}
if ($op !== '') {
    $where[] = "a.operation = :op";
    $params[':op'] = $op;
}
if ($table !== '') {
    $where[] = "a.table_name = :table";
    $params[':table'] = $table;
}
if ($dateFrom !== '') {
    $where[] = "a.logged_at >= :date_from";
    $params[':date_from'] = $dateFrom . " 00:00:00";
}
if ($dateTo !== '') {
    $where[] = "a.logged_at <= :date_to";
    $params[':date_to'] = $dateTo . " 23:59:59";
}

$whereClause = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Filter out incomplete registrations from regular view to avoid clutter
if ($whereClause === "") {
    $whereClause = " WHERE (u.account_status IS NULL OR u.account_status != 'pending')";
} else {
    $whereClause .= " AND (u.account_status IS NULL OR u.account_status != 'pending')";
}

// Get Total Count for Pagination
$countSql = "SELECT COUNT(*) FROM audit_log a LEFT JOIN user u ON a.user_id = u.user_id $whereClause";
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$totalEvents = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalEvents / $limit);

// Get Data
$sql = "SELECT a.*, u.first_name, u.last_name, u.role 
        FROM audit_log a 
        LEFT JOIN user u ON a.user_id = u.user_id 
        $whereClause 
        ORDER BY a.logged_at DESC LIMIT $limit OFFSET $offset";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0;">Audit Logs</h2>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Tracking system activity and user security events.</p>
        </div>
        <div class="badge badge-info">Last 100 Events</div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px; padding:16px; background:var(--surface-light); border-radius:8px; border:1px solid var(--border);">
        <div style="display:flex; gap:12px;">
            <div style="flex:1; position:relative;">
                <span class="material-symbols-outlined" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-secondary); font-size:20px;">search</span>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search logs, tables, changes..." style="width:100%; padding:10px 10px 10px 40px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
            </div>
            <select name="op" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface); min-width:140px;">
                <option value="">All Operations</option>
                <option value="INSERT" <?= $op === 'INSERT' ? 'selected' : '' ?>>INSERT</option>
                <option value="UPDATE" <?= $op === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                <option value="DELETE" <?= $op === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                <option value="LOGIN" <?= $op === 'LOGIN' ? 'selected' : '' ?>>LOGIN</option>
            </select>
        </div>
        <div style="display:flex; gap:12px; align-items:center;">
            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">
            <span style="font-size:12px; color:var(--text-secondary);">to</span>
            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" style="padding:10px; border:1px solid var(--border); border-radius:6px; background:var(--surface);">

            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($q || $op || $table || $dateFrom || $dateTo): ?>
                <a href="audit-logs.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>User / Role</th>
                <th>Action</th>
                <th>Resource / ID</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">No audit logs found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr onclick="toggleDetails(<?= $log['audit_log_id'] ?>)" style="cursor: pointer;">
                        <td style="white-space: nowrap;">
                            <div style="font-weight: 600;"><?= date('M j, Y', strtotime($log['logged_at'])) ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);"><?= date('h:i:s A', strtotime($log['logged_at'])) ?></div>
                        </td>
                        <td>
                            <?php if ($log['user_id']): ?>
                                <div style="font-weight: 600;"><?= e($log['first_name'] . ' ' . $log['last_name']) ?></div>
                                <div style="font-size: 11px; color: var(--text-secondary);"><?= ucfirst($log['role']) ?> (ID: <?= $log['user_id'] ?>)</div>
                            <?php else: ?>
                                <span style="color: var(--text-secondary);">System / Anonymous</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $log['operation'] === 'DELETE' ? 'danger' : ($log['operation'] === 'LOGIN' ? 'success' : 'info') ?>" style="font-size: 10px;">
                                <?= $log['operation'] ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-family: monospace; font-size: 12px;"><?= e($log['table_name'] ?? 'N/A') ?></div>
                            <div style="font-size: 11px; color: var(--text-secondary);">Record ID: <?= $log['record_id'] ?? '?' ?></div>
                        </td>
                        <td style="font-size: 12px; color: var(--text-secondary);"><?= e($log['ip_address'] ?? 'N/A') ?></td>
                    </tr>
                    <tr id="details-<?= $log['audit_log_id'] ?>" style="display: none; background: var(--surface-hover);">
                        <td colspan="5" style="padding: 16px;">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px; font-weight: 700; text-transform: uppercase;">Change Details:</div>
                            <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: 'DM Mono', monospace; background: var(--surface); padding: 12px; border-radius: 6px; border: 1px solid var(--border); font-size: 13px;"><?= e($log['changes_made'] ?: 'No additional details recorded.') ?></pre>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:center; gap:8px; margin-top:24px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="btn btn-primary btn-sm" style="min-width:32px;"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn btn-outline btn-sm" style="min-width:32px;"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleDetails(id) {
        const el = document.getElementById('details-' + id);
        if (el.style.display === 'none') {
            el.style.display = 'table-row';
        } else {
            el.style.display = 'none';
        }
    }
</script>
<?php
$content = ob_get_clean();
RoleLayout::render($u, 'admin', 'audit-logs', [
    'title' => 'Audit Logs',
    'content' => $content,
]);
