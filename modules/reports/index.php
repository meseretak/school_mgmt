<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title='Reports & Analytics'; $active_page='reports';

// ── Branch scoping: admin sees only their branch ───────────────
$admin_branch_id = (int)($_SESSION['user']['branch_id'] ?? 0);
$branch_id = $admin_branch_id ?: (int)($_GET['branch_id'] ?? 0);
$bw  = $branch_id ? " AND s.branch_id=$branch_id" : '';
$bwt = $branch_id ? " AND t.branch_id=$branch_id" : '';
$bwc = $branch_id ? " AND cl.branch_id=$branch_id" : '';

$admin_branch_name = null;
if ($admin_branch_id) {
    $bn = $pdo->prepare("SELECT name FROM branches WHERE id=?");
    $bn->execute([$admin_branch_id]); $admin_branch_name = $bn->fetchColumn();
}

$branches = is_super_admin()
    ? $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY name")->fetchAll()
    : [];

// CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    if ($type === 'students') {
        $sql = "SELECT s.student_code AS 'Student ID', CONCAT(s.first_name,' ',s.last_name) AS 'Full Name',
                u.email, s.phone, s.gender, s.nationality, c.name AS 'Country', s.enrollment_date, s.status
                FROM students s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN countries c ON s.country_id=c.id
                WHERE 1=1 $bw ORDER BY s.student_code";
        $filename = 'students_'.($admin_branch_name??'all').'_'.date('Ymd').'.csv';
    } else {
        $sql = "SELECT t.teacher_code AS 'Teacher ID', CONCAT(t.first_name,' ',t.last_name) AS 'Full Name',
                u.email, t.phone, t.specialization, t.hire_date, t.status
                FROM teachers t LEFT JOIN users u ON t.user_id=u.id
                WHERE 1=1 $bwt ORDER BY t.teacher_code";
        $filename = 'teachers_'.($admin_branch_name??'all').'_'.date('Ymd').'.csv';
    }
    $stmt = $pdo->prepare($sql); $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    if ($rows) fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out); exit;
}

$r['total_students']  = $pdo->query("SELECT COUNT(*) FROM students s WHERE 1=1$bw")->fetchColumn();
$r['active_students'] = $pdo->query("SELECT COUNT(*) FROM students s WHERE s.status='Active'$bw")->fetchColumn();
$r['intl_students']   = $pdo->query("SELECT COUNT(*) FROM students s WHERE s.country_id IS NOT NULL$bw")->fetchColumn();
$r['total_teachers']  = $pdo->query("SELECT COUNT(*) FROM teachers t WHERE 1=1$bwt")->fetchColumn();
$r['total_courses']   = $pdo->query("SELECT COUNT(*) FROM courses WHERE is_active=1")->fetchColumn();
$r['total_classes']   = $pdo->query("SELECT COUNT(*) FROM classes cl WHERE 1=1$bwc")->fetchColumn();
$r['total_exams']     = $pdo->query("SELECT COUNT(*) FROM exams e JOIN classes cl ON e.class_id=cl.id WHERE 1=1$bwc")->fetchColumn();
$r['total_grades']    = $pdo->query("SELECT COUNT(*) FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id WHERE 1=1$bwc")->fetchColumn();
$r['pass_count']      = $pdo->query("SELECT COUNT(*) FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id WHERE g.grade_letter NOT IN('F')$bwc")->fetchColumn();
$r['total_due']       = $pdo->query("SELECT COALESCE(SUM(p.amount_due),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE 1=1$bw")->fetchColumn();
$r['total_paid']      = $pdo->query("SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE 1=1$bw")->fetchColumn();
$r['paid_count']      = $pdo->query("SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Paid'$bw")->fetchColumn();
$r['overdue_count']   = $pdo->query("SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Overdue'$bw")->fetchColumn();

$by_country   = $pdo->query("SELECT c.name AS country, COUNT(s.id) AS cnt FROM students s JOIN countries c ON s.country_id=c.id WHERE 1=1$bw GROUP BY c.id ORDER BY cnt DESC LIMIT 10")->fetchAll();
$grade_dist   = $pdo->query("SELECT g.grade_letter, COUNT(*) AS cnt FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id WHERE 1=1$bwc GROUP BY g.grade_letter ORDER BY FIELD(g.grade_letter,'A+','A','A-','B+','B','B-','C+','C','D','F')")->fetchAll();
$pay_by_type  = $pdo->query("SELECT ft.name, SUM(p.amount_due) AS due, SUM(p.amount_paid) AS paid FROM payments p JOIN students s ON p.student_id=s.id JOIN fee_types ft ON p.fee_type_id=ft.id WHERE 1=1$bw GROUP BY ft.id ORDER BY due DESC")->fetchAll();
$pay_status   = $pdo->query("SELECT p.status, COUNT(*) AS cnt FROM payments p JOIN students s ON p.student_id=s.id WHERE 1=1$bw GROUP BY p.status")->fetchAll();
$enroll_trend = $pdo->query("SELECT DATE_FORMAT(en.enrolled_at,'%b %Y') AS mo, COUNT(*) AS cnt FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE en.enrolled_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)$bwc GROUP BY DATE_FORMAT(en.enrolled_at,'%Y-%m') ORDER BY DATE_FORMAT(en.enrolled_at,'%Y-%m')")->fetchAll();
$exam_types   = $pdo->query("SELECT e.type, COUNT(*) AS cnt FROM exams e JOIN classes cl ON e.class_id=cl.id WHERE 1=1$bwc GROUP BY e.type")->fetchAll();
$att_summary  = $pdo->query("SELECT a.status, COUNT(*) AS cnt FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id WHERE 1=1$bwc GROUP BY a.status")->fetchAll();
$top_students = $pdo->query("SELECT CONCAT(s.first_name,' ',s.last_name) AS name, s.student_code, AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(g.id) AS exams_taken FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN students s ON en.student_id=s.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id WHERE 1=1$bwc GROUP BY s.id ORDER BY avg_pct DESC LIMIT 10")->fetchAll();
$course_enroll = $pdo->query("SELECT co.name, COUNT(en.id) AS cnt FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE 1=1$bwc GROUP BY co.id ORDER BY cnt DESC LIMIT 8")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Reports & Analytics</h1>
    <p>
      <?php if ($admin_branch_name): ?>
        Showing data for <strong style="color:var(--primary)"><?= e($admin_branch_name) ?></strong> branch only
      <?php else: ?>
        System-wide overview with charts
      <?php endif; ?>
    </p>
  </div>
  <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print Report</button>
</div>

<!-- Export Panel -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-download" style="color:var(--success)"></i> Export Lists</h2></div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
    <?php if ($admin_branch_name): ?>
    <div style="background:#f0f4ff;border-radius:8px;padding:8px 14px;font-size:.85rem;color:#4361ee;font-weight:600">
      <i class="fas fa-building"></i> Exporting: <?= e($admin_branch_name) ?> branch only
    </div>
    <?php endif; ?>
    <a href="?export=students" class="btn btn-primary"><i class="fas fa-user-graduate"></i> Download Students CSV</a>
    <a href="?export=teachers" class="btn btn-secondary"><i class="fas fa-chalkboard-teacher"></i> Download Teachers CSV</a>
  </div>
</div>

<!-- Key Metrics -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= $r['total_students'] ?></h3><p>Total Students</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-globe"></i></div><div class="stat-info"><h3><?= $r['intl_students'] ?></h3><p>International</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $r['total_grades']>0?round($r['pass_count']/$r['total_grades']*100).'%':'—' ?></h3><p>Pass Rate</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-dollar-sign"></i></div><div class="stat-info"><h3>$<?= number_format($r['total_paid'],0) ?></h3><p>Revenue</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation"></i></div><div class="stat-info"><h3>$<?= number_format($r['total_due']-$r['total_paid'],0) ?></h3><p>Outstanding</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-info"><h3><?= $r['total_teachers'] ?></h3><p>Teachers</p></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?= $r['total_courses'] ?></h3><p>Courses</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-file-alt"></i></div><div class="stat-info"><h3><?= $r['total_exams'] ?></h3><p>Total Exams</p></div></div>
</div>

<!-- Row 1: Enrollment trend + Country bar -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-line" style="color:var(--primary)"></i> Enrollment Trend (12 Months)</h2></div>
    <div class="card-body"><canvas id="enrollTrend" height="130"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--info)"></i> Students by Country</h2></div>
    <div class="card-body"><canvas id="countryBar" height="130"></canvas></div>
  </div>
</div>

<!-- Row 2: Grade pie + Payment status pie -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-pie" style="color:var(--warning)"></i> Grade Distribution</h2></div>
    <div class="card-body" style="display:flex;justify-content:center"><canvas id="gradePie" height="200" width="200"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-pie" style="color:var(--success)"></i> Payment Status</h2></div>
    <div class="card-body" style="display:flex;justify-content:center"><canvas id="payPie" height="200" width="200"></canvas></div>
  </div>
</div>

<!-- Row 3: Course popularity + Exam types -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--secondary)"></i> Most Popular Courses</h2></div>
    <div class="card-body"><canvas id="courseBar" height="130"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-doughnut" style="color:var(--danger)"></i> Exam Types & Attendance</h2></div>
    <div class="card-body" style="display:flex;gap:20px;justify-content:center;align-items:center">
      <div style="text-align:center"><p style="font-size:.8rem;color:#888;margin-bottom:8px">Exam Types</p><canvas id="examPie" height="150" width="150"></canvas></div>
      <div style="text-align:center"><p style="font-size:.8rem;color:#888;margin-bottom:8px">Attendance</p><canvas id="attPie" height="150" width="150"></canvas></div>
    </div>
  </div>
</div>

<!-- Row 4: Payment by fee type + Revenue bar -->
<div class="grid-2" style="margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--success)"></i> Revenue by Fee Type</h2></div>
    <div class="card-body"><canvas id="feeBar" height="130"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-trophy" style="color:#f4a261"></i> Top Performing Students</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Student</th><th>Avg %</th><th>Exams</th><th>Grade</th></tr></thead>
      <tbody>
      <?php foreach ($top_students as $i => $ts): ?>
      <tr>
        <td><?= $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) ?></td>
        <td><?= e($ts['name']) ?><br><small style="color:#888"><?= e($ts['student_code']??'') ?></small></td>
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
      <?php if (!$top_students): ?><tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px">No data</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Payment by fee type table -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-table" style="color:var(--primary)"></i> Payment Collection by Fee Type</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Fee Type</th><th>Total Due</th><th>Collected</th><th>Outstanding</th><th>Collection %</th></tr></thead>
    <tbody>
    <?php foreach ($pay_by_type as $pt):
      $pct = $pt['due']>0 ? round($pt['paid']/$pt['due']*100) : 0;
    ?>
    <tr>
      <td><?= e($pt['name']) ?></td>
      <td>$<?= number_format($pt['due'],2) ?></td>
      <td style="color:var(--success)">$<?= number_format($pt['paid'],2) ?></td>
      <td style="color:var(--danger)">$<?= number_format($pt['due']-$pt['paid'],2) ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="progress" style="width:100px"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=80?'var(--success)':($pct>=50?'var(--warning)':'var(--danger)') ?>"></div></div>
          <span><?= $pct ?>%</span>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$pay_by_type): ?><tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px">No data</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const C = {primary:'#4361ee',success:'#2dc653',warning:'#f4a261',danger:'#e63946',purple:'#7209b7',teal:'#4cc9f0',orange:'#e76f51'};
const palette = [C.primary,C.success,C.warning,C.danger,C.purple,C.teal,C.orange,'#52b788','#e9c46a','#264653'];

new Chart(document.getElementById('enrollTrend'),{type:'line',data:{
  labels:<?= json_encode(array_column($enroll_trend,'mo')) ?>,
  datasets:[{label:'Enrollments',data:<?= json_encode(array_column($enroll_trend,'cnt')) ?>,
    borderColor:C.primary,backgroundColor:'rgba(67,97,238,.1)',tension:.4,fill:true,pointBackgroundColor:C.primary}]
},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('countryBar'),{type:'bar',data:{
  labels:<?= json_encode(array_column($by_country,'country')) ?>,
  datasets:[{label:'Students',data:<?= json_encode(array_column($by_country,'cnt')) ?>,
    backgroundColor:palette}]
},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}},indexAxis:'y'}});

new Chart(document.getElementById('gradePie'),{type:'doughnut',data:{
  labels:<?= json_encode(array_column($grade_dist,'grade_letter')) ?>,
  datasets:[{data:<?= json_encode(array_column($grade_dist,'cnt')) ?>,backgroundColor:palette}]
},options:{plugins:{legend:{position:'bottom'}},cutout:'55%'}});

new Chart(document.getElementById('payPie'),{type:'doughnut',data:{
  labels:<?= json_encode(array_column($pay_status,'status')) ?>,
  datasets:[{data:<?= json_encode(array_column($pay_status,'cnt')) ?>,
    backgroundColor:[C.success,C.warning,C.danger,C.teal,C.purple]}]
},options:{plugins:{legend:{position:'bottom'}},cutout:'55%'}});

new Chart(document.getElementById('courseBar'),{type:'bar',data:{
  labels:<?= json_encode(array_column($course_enroll,'name')) ?>,
  datasets:[{label:'Enrollments',data:<?= json_encode(array_column($course_enroll,'cnt')) ?>,
    backgroundColor:C.primary}]
},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});

new Chart(document.getElementById('examPie'),{type:'pie',data:{
  labels:<?= json_encode(array_column($exam_types,'type')) ?>,
  datasets:[{data:<?= json_encode(array_column($exam_types,'cnt')) ?>,backgroundColor:palette}]
},options:{plugins:{legend:{position:'bottom'}}}});

new Chart(document.getElementById('attPie'),{type:'pie',data:{
  labels:<?= json_encode(array_column($att_summary,'status')) ?>,
  datasets:[{data:<?= json_encode(array_column($att_summary,'cnt')) ?>,
    backgroundColor:[C.success,C.danger,C.warning,C.teal]}]
},options:{plugins:{legend:{position:'bottom'}}}});

new Chart(document.getElementById('feeBar'),{type:'bar',data:{
  labels:<?= json_encode(array_column($pay_by_type,'name')) ?>,
  datasets:[
    {label:'Due',data:<?= json_encode(array_column($pay_by_type,'due')) ?>,backgroundColor:'rgba(244,162,97,.8)'},
    {label:'Collected',data:<?= json_encode(array_column($pay_by_type,'paid')) ?>,backgroundColor:'rgba(45,198,83,.8)'}
  ]
},options:{plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});
</script>
<?php require_once '../../includes/footer.php'; ?>