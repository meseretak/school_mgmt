<?php
require_once '../../includes/config.php';
auth_check(['super_admin']);

$branches   = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY is_main DESC, name")->fetchAll();
$branch_id  = (int)($_GET['branch_id'] ?? ($branches[0]['id'] ?? 0));
$branch     = null;
if ($branch_id) {
    $s = $pdo->prepare("SELECT * FROM branches WHERE id=?"); $s->execute([$branch_id]); $branch = $s->fetch();
}
$page_title = ($branch ? e($branch['name']).' — ' : '').'Branch Activity';
$active_page = 'super_branch_activity';

// ── Branch-specific stats ──────────────────────────────────────
$bw = $branch_id ? "AND s.branch_id=$branch_id" : '';
$bwt = $branch_id ? "AND t.branch_id=$branch_id" : '';
$bwc = $branch_id ? "AND cl.branch_id=$branch_id" : '';

$stats = [];
$stats['students']  = $pdo->query("SELECT COUNT(*) FROM students s WHERE s.status='Active' $bw")->fetchColumn();
$stats['teachers']  = $pdo->query("SELECT COUNT(*) FROM teachers t WHERE t.status='Active' $bwt")->fetchColumn();
$stats['classes']   = $pdo->query("SELECT COUNT(*) FROM classes cl WHERE cl.status='Open' $bwc")->fetchColumn();
$stats['revenue']   = $pdo->query("SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Paid' $bw")->fetchColumn();
$stats['overdue']   = $pdo->query("SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Overdue' $bw")->fetchColumn();
$stats['exams']     = $pdo->query("SELECT COUNT(*) FROM exams e JOIN classes cl ON e.class_id=cl.id WHERE e.exam_date>=CURDATE() $bwc")->fetchColumn();

// ── Activity log for this branch (users assigned to branch) ───
$act_sql = "SELECT al.*, u.name AS uname, u.role, b.name AS branch_name
    FROM activity_log al
    JOIN users u ON al.user_id=u.id
    LEFT JOIN branches b ON u.branch_id=b.id
    WHERE 1=1 ".($branch_id ? "AND u.branch_id=$branch_id" : "")."
    ORDER BY al.created_at DESC LIMIT 50";
$activity = $pdo->query($act_sql)->fetchAll();

// ── Recent students ────────────────────────────────────────────
$stu_sql = "SELECT s.*, c.name AS country FROM students s
    LEFT JOIN countries c ON s.country_id=c.id
    WHERE 1=1 $bw ORDER BY s.created_at DESC LIMIT 10";
$recent_students = $pdo->query($stu_sql)->fetchAll();

// ── Recent payments ────────────────────────────────────────────
$pay_sql = "SELECT p.*, CONCAT(s.first_name,' ',s.last_name) AS sname, ft.name AS fee_name
    FROM payments p JOIN students s ON p.student_id=s.id
    JOIN fee_types ft ON p.fee_type_id=ft.id
    WHERE 1=1 $bw ORDER BY p.created_at DESC LIMIT 10";
$recent_payments = $pdo->query($pay_sql)->fetchAll();

// ── Enrollment trend ───────────────────────────────────────────
$enroll_trend = $pdo->query("
    SELECT DATE_FORMAT(en.enrolled_at,'%b %Y') AS mo, COUNT(*) AS cnt
    FROM enrollments en JOIN classes cl ON en.class_id=cl.id
    WHERE en.enrolled_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) $bwc
    GROUP BY DATE_FORMAT(en.enrolled_at,'%Y-%m') ORDER BY DATE_FORMAT(en.enrolled_at,'%Y-%m')
")->fetchAll();

// ── Grade distribution ─────────────────────────────────────────
$grade_dist = $pdo->query("
    SELECT g.grade_letter, COUNT(*) AS cnt FROM grades g
    JOIN enrollments en ON g.enrollment_id=en.id
    JOIN classes cl ON en.class_id=cl.id
    WHERE 1=1 $bwc
    GROUP BY g.grade_letter ORDER BY FIELD(g.grade_letter,'A+','A','A-','B+','B','B-','C+','C','D','F')
")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-history" style="color:#4cc9f0;margin-right:8px"></i>
      <?= $branch ? e($branch['name']) : 'All Branches' ?> — Activity & Report
    </h1>
    <p>Detailed view for this branch</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="reports.php?branch_id=<?= $branch_id ?>" class="btn btn-secondary"><i class="fas fa-chart-bar"></i> Full Report</a>
    <a href="branches.php?edit=<?= $branch_id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Branch</a>
  </div>
</div>

<!-- Branch Switcher -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span style="font-weight:700;font-size:.88rem;color:#666"><i class="fas fa-building"></i> Select Branch:</span>
      <?php foreach ($branches as $b): ?>
      <a href="?branch_id=<?= $b['id'] ?>"
         style="padding:6px 16px;border-radius:20px;font-size:.82rem;font-weight:600;text-decoration:none;
                background:<?= $branch_id==$b['id']?'linear-gradient(135deg,#4361ee,#7209b7)':'#f0f2f8' ?>;
                color:<?= $branch_id==$b['id']?'#fff':'#555' ?>">
        <?= e($b['name']) ?>
        <?php if ($b['is_main']): ?><i class="fas fa-star" style="font-size:.65rem;margin-left:3px"></i><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?= $stats['students'] ?></h3><p>Active Students</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><h3><?= $stats['teachers'] ?></h3><p>Active Teachers</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-door-open"></i></div><div class="stat-info"><h3><?= $stats['classes'] ?></h3><p>Open Classes</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div><div class="stat-info"><h3>$<?= number_format($stats['revenue'],0) ?></h3><p>Revenue</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3><?= $stats['overdue'] ?></h3><p>Overdue Payments</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-file-alt"></i></div><div class="stat-info"><h3><?= $stats['exams'] ?></h3><p>Upcoming Exams</p></div></div>
</div>

<!-- Charts -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-line" style="color:var(--primary)"></i> Enrollment Trend (6 Months)</h2></div>
    <div class="card-body"><canvas id="enrollChart" height="140"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-pie" style="color:var(--warning)"></i> Grade Distribution</h2></div>
    <div class="card-body" style="display:flex;justify-content:center"><canvas id="gradePie" height="180" width="180"></canvas></div>
  </div>
</div>

<!-- Recent Students + Payments -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-user-graduate" style="color:var(--primary)"></i> Recent Students</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Code</th><th>Country</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent_students as $s): ?>
      <tr>
        <td style="font-weight:600"><?= e($s['first_name'].' '.$s['last_name']) ?></td>
        <td><code style="font-size:.78rem"><?= e($s['student_code']??'—') ?></code></td>
        <td><?= e($s['country']??'—') ?></td>
        <td><span class="badge badge-<?= $s['status']==='Active'?'success':'danger' ?>"><?= e($s['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent_students): ?><tr><td colspan="4" style="text-align:center;color:#aaa;padding:16px">No students</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-credit-card" style="color:var(--success)"></i> Recent Payments</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Student</th><th>Fee</th><th>Paid</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent_payments as $p): ?>
      <tr>
        <td style="font-weight:600"><?= e($p['sname']) ?></td>
        <td style="font-size:.82rem"><?= e($p['fee_name']) ?></td>
        <td>$<?= number_format($p['amount_paid'],0) ?></td>
        <td><span class="badge badge-<?php $ps_=($p['status']??''); echo $ps_==='Paid'?'success':($ps_==='Pending'?'warning':($ps_==='Overdue'?'danger':($ps_==='Partial'?'info':'secondary'))); ?>"><?= e($p['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent_payments): ?><tr><td colspan="4" style="text-align:center;color:#aaa;padding:16px">No payments</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- Activity Log -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-history" style="color:#4cc9f0"></i> Branch Activity Log (Last 50)</h2>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach ($activity as $a): ?>
    <tr>
      <td style="font-weight:600"><?= e($a['uname']) ?></td>
      <td><span class="badge badge-<?php $ar_=($a['role']??''); echo $ar_==='super_admin'?'danger':($ar_==='admin'?'primary':($ar_==='teacher'?'secondary':($ar_==='student'?'info':'secondary'))); ?>"><?= ucfirst(str_replace('_',' ',$a['role'])) ?></span></td>
      <td><code style="font-size:.78rem"><?= e($a['action']) ?></code></td>
      <td style="font-size:.82rem;color:#666"><?= e(mb_substr($a['description']??'',0,70)) ?></td>
      <td style="font-size:.78rem;color:#aaa"><?= e($a['ip']??'') ?></td>
      <td style="font-size:.78rem;color:#aaa;white-space:nowrap"><?= date('M j, g:i A', strtotime($a['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$activity): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No activity recorded</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const palette = ['#4361ee','#7209b7','#2dc653','#f4a261','#e63946','#4cc9f0','#e76f51','#52b788'];

new Chart(document.getElementById('enrollChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($enroll_trend,'mo')) ?>,
    datasets: [{ label: 'Enrollments', data: <?= json_encode(array_column($enroll_trend,'cnt')) ?>,
      borderColor: '#4361ee', backgroundColor: 'rgba(67,97,238,.1)', tension: .4, fill: true }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('gradePie'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($grade_dist,'grade_letter')) ?>,
    datasets: [{ data: <?= json_encode(array_column($grade_dist,'cnt')) ?>, backgroundColor: palette }]
  },
  options: { plugins: { legend: { position: 'bottom' } }, cutout: '55%' }
});
</script>
<?php require_once '../../includes/footer.php'; ?>