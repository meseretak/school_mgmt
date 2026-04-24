<?php
require_once '../../includes/config.php';
auth_check(['super_admin']);
$page_title = 'Super Admin Dashboard';
$active_page = 'super_dashboard';

// ── Global stats across ALL branches ──────────────────────────
$stats = [];
$stats['branches']  = $pdo->query("SELECT COUNT(*) FROM branches WHERE is_active=1")->fetchColumn();
$stats['admins']    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn();
$stats['students']  = $pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$stats['teachers']  = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$stats['courses']   = $pdo->query("SELECT COUNT(*) FROM courses WHERE is_active=1")->fetchColumn();
$stats['revenue']   = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE status='Paid'")->fetchColumn();
$stats['pending']   = $pdo->query("SELECT COALESCE(SUM(amount_due-amount_paid),0) FROM payments WHERE status IN('Pending','Overdue','Partial')")->fetchColumn();
$stats['overdue']   = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='Overdue'")->fetchColumn();

// ── Per-branch breakdown ───────────────────────────────────────
$branch_stats = $pdo->query("
    SELECT b.id, b.name, b.code, b.is_main,
        (SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id AND s.status='Active') AS students,
        (SELECT COUNT(*) FROM teachers t WHERE t.branch_id=b.id AND t.status='Active') AS teachers,
        (SELECT COUNT(*) FROM classes cl WHERE cl.branch_id=b.id AND cl.status='Open') AS classes,
        (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE s.branch_id=b.id AND p.status='Paid') AS revenue,
        (SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE s.branch_id=b.id AND p.status='Overdue') AS overdue
    FROM branches b WHERE b.is_active=1 ORDER BY b.is_main DESC, b.name
")->fetchAll();

// ── Revenue trend (last 6 months) ─────────────────────────────
$rev_trend = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS mo, SUM(amount_paid) AS paid, SUM(amount_due) AS due
    FROM payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY DATE_FORMAT(created_at,'%Y-%m')
")->fetchAll();

// ── Students per branch (for chart) ───────────────────────────
$stu_by_branch = $pdo->query("
    SELECT b.name, COUNT(s.id) AS cnt
    FROM branches b LEFT JOIN students s ON s.branch_id=b.id AND s.status='Active'
    WHERE b.is_active=1 GROUP BY b.id ORDER BY cnt DESC
")->fetchAll();

// ── Recent activity log ────────────────────────────────────────
$recent_activity = $pdo->query("
    SELECT al.*, u.name, u.role FROM activity_log al
    JOIN users u ON al.user_id=u.id
    ORDER BY al.created_at DESC LIMIT 10
")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-crown" style="color:#f4a261;margin-right:8px"></i>Super Admin Dashboard</h1>
    <p>Global overview across all branches — <?= date('l, F j, Y') ?></p>
  </div>
  <a href="<?= BASE_URL ?>/modules/super_admin/reports.php" class="btn btn-primary">
    <i class="fas fa-chart-bar"></i> Full Reports
  </a>
</div>

<!-- Global Stat Cards -->
<div class="stats-grid">
  <div class="stat-card" onclick="location='<?= BASE_URL ?>/modules/super_admin/branches.php'" style="cursor:pointer">
    <div class="stat-icon orange"><i class="fas fa-building"></i></div>
    <div class="stat-info"><h3><?= $stats['branches'] ?></h3><p>Active Branches</p></div>
  </div>
  <div class="stat-card" onclick="location='<?= BASE_URL ?>/modules/super_admin/admins.php'" style="cursor:pointer">
    <div class="stat-icon red"><i class="fas fa-user-shield"></i></div>
    <div class="stat-info"><h3><?= $stats['admins'] ?></h3><p>Branch Admins</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
    <div class="stat-info"><h3><?= number_format($stats['students']) ?></h3><p>Total Students</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div>
    <div class="stat-info"><h3><?= number_format($stats['teachers']) ?></h3><p>Total Teachers</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-book-open"></i></div>
    <div class="stat-info"><h3><?= number_format($stats['courses']) ?></h3><p>Active Courses</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div>
    <div class="stat-info"><h3>$<?= number_format($stats['revenue'],0) ?></h3><p>Total Revenue</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
    <div class="stat-info"><h3>$<?= number_format($stats['pending'],0) ?></h3><p>Outstanding</p></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
    <div class="stat-info"><h3><?= $stats['overdue'] ?></h3><p>Overdue Payments</p></div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Students per Branch</h2></div>
    <div class="card-body"><canvas id="stuBranchChart" height="140"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-line" style="color:var(--success)"></i> Revenue Trend (6 Months)</h2></div>
    <div class="card-body"><canvas id="revTrendChart" height="140"></canvas></div>
  </div>
</div>

<!-- Branch Performance Table -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h2><i class="fas fa-building" style="color:var(--orange,#f4a261)"></i> Branch Performance</h2>
    <a href="<?= BASE_URL ?>/modules/super_admin/branches.php" class="btn btn-sm btn-primary"><i class="fas fa-cog"></i> Manage</a>
  </div>
  <div class="table-wrap"><table>
    <thead>
      <tr><th>Branch</th><th>Students</th><th>Teachers</th><th>Open Classes</th><th>Revenue</th><th>Overdue</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($branch_stats as $b): ?>
    <tr>
      <td>
        <div style="font-weight:700"><?= e($b['name']) ?></div>
        <code style="font-size:.75rem;color:#888"><?= e($b['code']) ?></code>
        <?php if ($b['is_main']): ?> <span class="badge badge-primary" style="font-size:.65rem">Main</span><?php endif; ?>
      </td>
      <td><span class="badge badge-info"><?= $b['students'] ?></span></td>
      <td><span class="badge badge-secondary"><?= $b['teachers'] ?></span></td>
      <td><?= $b['classes'] ?></td>
      <td style="color:var(--success);font-weight:600">$<?= number_format($b['revenue'],0) ?></td>
      <td>
        <?php if ($b['overdue'] > 0): ?>
        <span class="badge badge-danger"><?= $b['overdue'] ?></span>
        <?php else: ?>
        <span style="color:#aaa">—</span>
        <?php endif; ?>
      </td>
      <td>
        <a href="<?= BASE_URL ?>/modules/super_admin/reports.php?branch_id=<?= $b['id'] ?>" class="btn btn-sm btn-secondary">
          <i class="fas fa-chart-bar"></i> Report
        </a>
        <a href="<?= BASE_URL ?>/modules/super_admin/branch_activity.php?branch_id=<?= $b['id'] ?>" class="btn btn-sm btn-secondary">
          <i class="fas fa-history"></i> Activity
        </a>
        <a href="<?= BASE_URL ?>/modules/super_admin/branches.php?edit=<?= $b['id'] ?>" class="btn btn-sm btn-primary">
          <i class="fas fa-edit"></i>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$branch_stats): ?>
    <tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px">No branches yet. <a href="<?= BASE_URL ?>/modules/super_admin/branches.php">Create one</a></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>
</div>

<!-- Recent Activity -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-history" style="color:var(--teal,#4cc9f0)"></i> Recent System Activity</h2>
    <a href="<?= BASE_URL ?>/modules/activity/index.php" class="btn btn-sm btn-secondary">View All</a>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach ($recent_activity as $a): ?>
    <tr>
      <td style="font-weight:600"><?= e($a['name']) ?></td>
      <td><span class="badge badge-<?= match($a['role']){'super_admin'=>'danger','admin'=>'primary','teacher'=>'secondary','student'=>'info',default=>'secondary'} ?>"><?= ucfirst($a['role']) ?></span></td>
      <td><code style="font-size:.78rem"><?= e($a['action']) ?></code></td>
      <td style="font-size:.82rem;color:#666"><?= e(mb_substr($a['description']??'',0,60)) ?></td>
      <td style="font-size:.78rem;color:#aaa"><?= e($a['ip']??'') ?></td>
      <td style="font-size:.78rem;color:#aaa;white-space:nowrap"><?= date('M j, g:i A', strtotime($a['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$recent_activity): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No activity yet</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const palette = ['#4361ee','#7209b7','#2dc653','#f4a261','#e63946','#4cc9f0','#e76f51','#52b788'];

new Chart(document.getElementById('stuBranchChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($stu_by_branch,'name')) ?>,
    datasets: [{ label: 'Active Students', data: <?= json_encode(array_column($stu_by_branch,'cnt')) ?>, backgroundColor: palette }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('revTrendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($rev_trend,'mo')) ?>,
    datasets: [
      { label: 'Collected', data: <?= json_encode(array_column($rev_trend,'paid')) ?>, borderColor: '#2dc653', backgroundColor: 'rgba(45,198,83,.1)', tension: .4, fill: true },
      { label: 'Due', data: <?= json_encode(array_column($rev_trend,'due')) ?>, borderColor: '#f4a261', backgroundColor: 'rgba(244,162,97,.08)', tension: .4, fill: true }
    ]
  },
  options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});
</script>
<?php require_once '../../includes/footer.php'; ?>