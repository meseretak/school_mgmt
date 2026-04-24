<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title = 'Activity Log'; $active_page = 'activity';

$role_filter = $_GET['role'] ?? '';
$user_filter = trim($_GET['user'] ?? '');
$date_from   = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to     = $_GET['date_to']   ?? date('Y-m-d');

$sql = "SELECT al.*, u.name AS user_name, u.email FROM activity_log al JOIN users u ON al.user_id=u.id WHERE al.created_at BETWEEN ? AND DATE_ADD(?,INTERVAL 1 DAY)";
$params = [$date_from, $date_to];

// Branch admins only see their branch's activity
$admin_branch = $_SESSION['user']['branch_id'] ?? null;
if ($admin_branch && !is_super_admin()) {
    $sql .= " AND u.branch_id=?"; $params[] = $admin_branch;
}
if ($role_filter) { $sql .= " AND al.role=?"; $params[] = $role_filter; }
if ($user_filter) { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$user_filter%"; $params[] = "%$user_filter%"; }
$sql .= " ORDER BY al.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $logs = $stmt->fetchAll();

// Summary counts
$summary = $pdo->prepare("SELECT role, COUNT(*) AS cnt FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY role");
$summary->execute(); $summary = array_column($summary->fetchAll(), 'cnt', 'role');

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-history" style="color:var(--primary)"></i> Activity Log</h1><p>System-wide user activity</p></div>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <?php foreach(['admin'=>['red','user-shield'],'teacher'=>['purple','chalkboard-teacher'],'student'=>['blue','user-graduate'],'accountant'=>['green','calculator']] as $r=>[$c,$i]): ?>
  <div class="stat-card"><div class="stat-icon <?= $c ?>"><i class="fas fa-<?= $i ?>"></i></div><div class="stat-info"><h3><?= $summary[$r]??0 ?></h3><p><?= ucfirst($r) ?> Actions (7d)</p></div></div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar" style="flex-wrap:wrap">
    <input name="user" placeholder="Search user name/email..." value="<?= e($user_filter) ?>" style="min-width:200px">
    <select name="role">
      <option value="">All Roles</option>
      <?php foreach(['admin','teacher','student','accountant'] as $r): ?>
      <option value="<?= $r ?>" <?= $role_filter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= e($date_from) ?>">
    <input type="date" name="date_to" value="<?= e($date_to) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> <?= count($logs) ?> Activities</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $log):
      $role_colors = ['admin'=>'danger','teacher'=>'primary','student'=>'info','accountant'=>'success'];
      $rc = $role_colors[$log['role']] ?? 'secondary';
    ?>
    <tr>
      <td style="font-size:.8rem;white-space:nowrap;color:#888"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></td>
      <td>
        <div style="font-weight:600"><?= e($log['user_name']) ?></div>
        <div style="font-size:.75rem;color:#aaa"><?= e($log['email']) ?></div>
      </td>
      <td><span class="badge badge-<?= $rc ?>"><?= ucfirst($log['role']) ?></span></td>
      <td style="font-weight:600;font-size:.85rem"><?= e(str_replace('_',' ', $log['action'])) ?></td>
      <td style="font-size:.82rem;color:#666"><?= e($log['description']??'—') ?></td>
      <td style="font-size:.78rem;font-family:monospace;color:#aaa"><?= e($log['ip']??'—') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:30px">No activity in this period.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require_once '../../includes/footer.php'; ?>