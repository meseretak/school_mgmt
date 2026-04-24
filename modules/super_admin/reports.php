<?php
require_once '../../includes/config.php';
auth_check(['super_admin']);
$page_title = 'Global Reports';
$active_page = 'super_reports';

$branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY is_main DESC, name")->fetchAll();
$branch_id = (int)($_GET['branch_id'] ?? 0);
$bwhere    = $branch_id ? " AND s.branch_id=$branch_id" : '';
$bwhere_t  = $branch_id ? " AND t.branch_id=$branch_id" : '';

// ── CSV Export ─────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $bid  = (int)($_GET['branch_id'] ?? 0);
    if ($type === 'students') {
        $sql = "SELECT s.student_code AS 'ID', CONCAT(s.first_name,' ',s.last_name) AS 'Name',
                u.email, s.phone, s.gender, s.nationality,
                c.name AS 'Country', b.name AS 'Branch', s.enrollment_date, s.status
                FROM students s
                LEFT JOIN users u ON s.user_id=u.id
                LEFT JOIN countries c ON s.country_id=c.id
                LEFT JOIN branches b ON s.branch_id=b.id";
        $params = []; if ($bid) { $sql .= " WHERE s.branch_id=?"; $params[] = $bid; }
        $sql .= " ORDER BY b.name, s.student_code";
        $filename = 'global_students_'.date('Ymd').'.csv';
    } elseif ($type === 'teachers') {
        $sql = "SELECT t.teacher_code AS 'ID', CONCAT(t.first_name,' ',t.last_name) AS 'Name',
                u.email, t.phone, t.specialization, t.qualification,
                b.name AS 'Branch', t.hire_date, t.status
                FROM teachers t
                LEFT JOIN users u ON t.user_id=u.id
                LEFT JOIN branches b ON t.branch_id=b.id";
        $params = []; if ($bid) { $sql .= " WHERE t.branch_id=?"; $params[] = $bid; }
        $sql .= " ORDER BY b.name, t.teacher_code";
        $filename = 'global_teachers_'.date('Ymd').'.csv';
    } else {
        $sql = "SELECT b.name AS 'Branch', b.code,
                (SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id AND s.status='Active') AS 'Active Students',
                (SELECT COUNT(*) FROM teachers t WHERE t.branch_id=b.id AND t.status='Active') AS 'Active Teachers',
                (SELECT COUNT(*) FROM classes cl WHERE cl.branch_id=b.id AND cl.status='Open') AS 'Open Classes',
                (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE s.branch_id=b.id AND p.status='Paid') AS 'Revenue Collected',
                (SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE s.branch_id=b.id AND p.status='Overdue') AS 'Overdue Payments'
                FROM branches b WHERE b.is_active=1 ORDER BY b.is_main DESC, b.name";
        $params = [];
        $filename = 'branch_summary_'.date('Ymd').'.csv';
    }
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    if ($rows) fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out); exit;
}

// ── High-level metrics ─────────────────────────────────────────
$r = [];
$r['total_students']  = $pdo->query("SELECT COUNT(*) FROM students s WHERE 1=1$bwhere")->fetchColumn();
$r['active_students'] = $pdo->query("SELECT COUNT(*) FROM students s WHERE s.status='Active'$bwhere")->fetchColumn();
$r['total_teachers']  = $pdo->query("SELECT COUNT(*) FROM teachers t WHERE 1=1$bwhere_t")->fetchColumn();
$r['total_revenue']   = $pdo->query("SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Paid'$bwhere")->fetchColumn();
$r['total_due']       = $pdo->query("SELECT COALESCE(SUM(p.amount_due),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE 1=1$bwhere")->fetchColumn();
$r['overdue_count']   = $pdo->query("SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Overdue'$bwhere")->fetchColumn();
$r['pass_rate']       = $pdo->query("SELECT ROUND(COUNT(CASE WHEN grade_letter NOT IN('F') THEN 1 END)/NULLIF(COUNT(*),0)*100,1) FROM grades")->fetchColumn();
$r['total_exams']     = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();

// ── Charts data ────────────────────────────────────────────────
$branch_revenue = $pdo->query("
    SELECT COALESCE(b.name,'— Unassigned') AS name,
        COALESCE(SUM(p.amount_paid),0) AS paid,
        COALESCE(SUM(p.amount_due),0) AS due
    FROM students s
    LEFT JOIN branches b ON s.branch_id=b.id
    LEFT JOIN payments p ON p.student_id=s.id
    GROUP BY s.branch_id ORDER BY paid DESC
")->fetchAll();

$branch_students = $pdo->query("
    SELECT COALESCE(b.name,'— Unassigned') AS name,
        SUM(CASE WHEN s.status='Active' THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN s.status='Inactive' THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN s.status='Graduated' THEN 1 ELSE 0 END) AS graduated
    FROM students s
    LEFT JOIN branches b ON s.branch_id=b.id
    GROUP BY s.branch_id ORDER BY active DESC
")->fetchAll();

// ── Per-branch summary table ───────────────────────────────────
$branch_summary = $pdo->query("
    SELECT
        COALESCE(b.name,'— Unassigned') AS bname,
        COALESCE(b.code,'N/A') AS bcode,
        b.id AS bid,
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT CASE WHEN s.status='Active' THEN s.id END) AS active_students,
        COUNT(DISTINCT t.id) AS total_teachers,
        COUNT(DISTINCT cl.id) AS open_classes,
        COALESCE(SUM(CASE WHEN p.status='Paid' THEN p.amount_paid ELSE 0 END),0) AS revenue,
        COUNT(CASE WHEN p.status='Overdue' THEN 1 END) AS overdue
    FROM students s
    LEFT JOIN branches b ON s.branch_id=b.id
    LEFT JOIN teachers t ON t.branch_id=b.id
    LEFT JOIN classes cl ON cl.branch_id=b.id AND cl.status='Open'
    LEFT JOIN payments p ON p.student_id=s.id
    GROUP BY s.branch_id
    ORDER BY active_students DESC
")->fetchAll();

$enroll_trend = $pdo->query("
    SELECT DATE_FORMAT(en.enrolled_at,'%b %Y') AS mo, COUNT(*) AS cnt
    FROM enrollments en
    JOIN classes cl ON en.class_id=cl.id
    WHERE en.enrolled_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ".($branch_id ? "AND cl.branch_id=$branch_id" : "")."
    GROUP BY DATE_FORMAT(en.enrolled_at,'%Y-%m') ORDER BY DATE_FORMAT(en.enrolled_at,'%Y-%m')
")->fetchAll();

$grade_dist = $pdo->query("SELECT grade_letter, COUNT(*) AS cnt FROM grades GROUP BY grade_letter ORDER BY FIELD(grade_letter,'A+','A','A-','B+','B','B-','C+','C','D','F')")->fetchAll();

$pay_status = $pdo->query("SELECT p.status, COUNT(*) AS cnt FROM payments p JOIN students s ON p.student_id=s.id WHERE 1=1$bwhere GROUP BY p.status")->fetchAll();

$top_students = $pdo->query("
    SELECT CONCAT(s.first_name,' ',s.last_name) AS name, s.student_code,
        b.name AS branch, AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(g.id) AS exams_taken
    FROM grades g
    JOIN enrollments en ON g.enrollment_id=en.id
    JOIN students s ON en.student_id=s.id
    JOIN exams ex ON g.exam_id=ex.id
    LEFT JOIN branches b ON s.branch_id=b.id
    GROUP BY s.id ORDER BY avg_pct DESC LIMIT 15
")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px"></i>Global Reports</h1>
    <p>High-level analytics across <?= $branch_id ? '1 branch' : 'all branches' ?></p>
  </div>
  <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print</button>
</div>

<!-- Branch Filter + Export -->
<div class="card" style="margin-bottom:24px">
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="margin:0;min-width:200px">
        <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:4px">Filter by Branch</label>
        <select name="branch_id" onchange="this.form.submit()" style="height:38px">
          <option value="0" <?= !$branch_id?'selected':'' ?>>All Branches</option>
          <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $branch_id==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
    <a href="?export=students&branch_id=<?= $branch_id ?>" class="btn btn-primary"><i class="fas fa-user-graduate"></i> Export Students CSV</a>
    <a href="?export=teachers&branch_id=<?= $branch_id ?>" class="btn btn-secondary"><i class="fas fa-chalkboard-teacher"></i> Export Teachers CSV</a>
    <a href="?export=branches" class="btn btn-secondary"><i class="fas fa-building"></i> Export Branch Summary CSV</a>
  </div>
</div>

<!-- Key Metrics -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= number_format($r['total_students']) ?></h3><p>Total Students</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-user-check"></i></div><div class="stat-info"><h3><?= number_format($r['active_students']) ?></h3><p>Active Students</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><h3><?= number_format($r['total_teachers']) ?></h3><p>Total Teachers</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div><div class="stat-info"><h3>$<?= number_format($r['total_revenue'],0) ?></h3><p>Total Revenue</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div class="stat-info"><h3>$<?= number_format($r['total_due']-$r['total_revenue'],0) ?></h3><p>Outstanding</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation"></i></div><div class="stat-info"><h3><?= $r['overdue_count'] ?></h3><p>Overdue Payments</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $r['pass_rate'] ?? '—' ?>%</h3><p>Pass Rate</p></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-file-alt"></i></div><div class="stat-info"><h3><?= number_format($r['total_exams']) ?></h3><p>Total Exams</p></div></div>
</div>

<!-- Branch Breakdown Table -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h2><i class="fas fa-building" style="color:#f4a261"></i> Data by Branch</h2>
    <a href="?export=branches" class="btn btn-sm btn-secondary"><i class="fas fa-download"></i> Export CSV</a>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Branch</th><th>Total Students</th><th>Active</th><th>Teachers</th><th>Open Classes</th><th>Revenue</th><th>Overdue</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($branch_summary as $bs): ?>
    <tr>
      <td>
        <div style="font-weight:700"><?= e($bs['bname']) ?></div>
        <code style="font-size:.75rem;color:#888"><?= e($bs['bcode']) ?></code>
      </td>
      <td><?= $bs['total_students'] ?></td>
      <td><span class="badge badge-success"><?= $bs['active_students'] ?></span></td>
      <td><?= $bs['total_teachers'] ?></td>
      <td><?= $bs['open_classes'] ?></td>
      <td style="color:var(--success);font-weight:600">$<?= number_format($bs['revenue'],0) ?></td>
      <td><?= $bs['overdue'] > 0 ? '<span class="badge badge-danger">'.$bs['overdue'].'</span>' : '<span style="color:#aaa">—</span>' ?></td>
      <td>
        <?php if ($bs['bid']): ?>
        <a href="?branch_id=<?= $bs['bid'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</a>
        <a href="branch_activity.php?branch_id=<?= $bs['bid'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-history"></i></a>
        <?php else: ?>
        <span style="font-size:.78rem;color:#aaa">Assign branches to students</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$branch_summary): ?><tr><td colspan="8" style="text-align:center;color:#aaa;padding:20px">No data</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<!-- Charts Row 1 -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--success)"></i> Revenue by Branch</h2></div>
    <div class="card-body"><canvas id="branchRevChart" height="140"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-line" style="color:var(--primary)"></i> Enrollment Trend (12 Months)</h2></div>
    <div class="card-body"><canvas id="enrollChart" height="140"></canvas></div>
  </div>
</div>

<!-- Charts Row 2 -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--info)"></i> Students per Branch</h2></div>
    <div class="card-body"><canvas id="branchStuChart" height="140"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-pie" style="color:var(--warning)"></i> Grade Distribution & Payment Status</h2></div>
    <div class="card-body" style="display:flex;gap:20px;justify-content:center;align-items:center">
      <div style="text-align:center"><p style="font-size:.8rem;color:#888;margin-bottom:8px">Grades</p><canvas id="gradePie" height="160" width="160"></canvas></div>
      <div style="text-align:center"><p style="font-size:.8rem;color:#888;margin-bottom:8px">Payments</p><canvas id="payPie" height="160" width="160"></canvas></div>
    </div>
  </div>
</div>

<!-- Top Students Table -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-trophy" style="color:#f4a261"></i> Top Performing Students (All Branches)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Student</th><th>Branch</th><th>Avg %</th><th>Exams</th><th>Grade</th></tr></thead>
    <tbody>
    <?php foreach ($top_students as $i => $ts): ?>
    <tr>
      <td><?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) ?></td>
      <td>
        <div style="font-weight:600"><?= e($ts['name']) ?></div>
        <small style="color:#888"><?= e($ts['student_code']??'') ?></small>
      </td>
      <td><span class="badge badge-info"><?= e($ts['branch']??'—') ?></span></td>
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <?= round($ts['avg_pct'],1) ?>%
          <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?= min(100,round($ts['avg_pct'])) ?>%"></div></div>
        </div>
      </td>
      <td><?= $ts['exams_taken'] ?></td>
      <td><strong style="color:var(--success)"><?= grade_letter($ts['avg_pct']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$top_students): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No grade data yet</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const palette = ['#4361ee','#7209b7','#2dc653','#f4a261','#e63946','#4cc9f0','#e76f51','#52b788','#e9c46a','#264653'];

new Chart(document.getElementById('branchRevChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($branch_revenue,'name')) ?>,
    datasets: [
      { label: 'Collected', data: <?= json_encode(array_column($branch_revenue,'paid')) ?>, backgroundColor: 'rgba(45,198,83,.8)' },
      { label: 'Due', data: <?= json_encode(array_column($branch_revenue,'due')) ?>, backgroundColor: 'rgba(244,162,97,.6)' }
    ]
  },
  options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('enrollChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($enroll_trend,'mo')) ?>,
    datasets: [{ label: 'Enrollments', data: <?= json_encode(array_column($enroll_trend,'cnt')) ?>,
      borderColor: '#4361ee', backgroundColor: 'rgba(67,97,238,.1)', tension: .4, fill: true }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('branchStuChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($branch_students,'name')) ?>,
    datasets: [
      { label: 'Active', data: <?= json_encode(array_column($branch_students,'active')) ?>, backgroundColor: 'rgba(45,198,83,.8)' },
      { label: 'Inactive', data: <?= json_encode(array_column($branch_students,'inactive')) ?>, backgroundColor: 'rgba(230,57,70,.6)' },
      { label: 'Graduated', data: <?= json_encode(array_column($branch_students,'graduated')) ?>, backgroundColor: 'rgba(67,97,238,.6)' }
    ]
  },
  options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true, stacked: false } } }
});

new Chart(document.getElementById('gradePie'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($grade_dist,'grade_letter')) ?>,
    datasets: [{ data: <?= json_encode(array_column($grade_dist,'cnt')) ?>, backgroundColor: palette }]
  },
  options: { plugins: { legend: { position: 'bottom' } }, cutout: '55%' }
});

new Chart(document.getElementById('payPie'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($pay_status,'status')) ?>,
    datasets: [{ data: <?= json_encode(array_column($pay_status,'cnt')) ?>,
      backgroundColor: ['#2dc653','#f4a261','#e63946','#4cc9f0','#7209b7'] }]
  },
  options: { plugins: { legend: { position: 'bottom' } }, cutout: '55%' }
});
</script>
<?php require_once '../../includes/footer.php'; ?>