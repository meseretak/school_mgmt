<?php
require_once 'includes/config.php';
auth_check();
$role = $_SESSION['user']['role'];

// Redirect to role-specific portals
if ($role === 'super_admin') { header('Location: '.BASE_URL.'/modules/super_admin/dashboard.php'); exit; }
if ($role === 'teacher')    { header('Location: '.BASE_URL.'/modules/teacher/dashboard.php'); exit; }
if ($role === 'student')    { header('Location: '.BASE_URL.'/modules/students/dashboard.php'); exit; }
if ($role === 'librarian')  { header('Location: '.BASE_URL.'/modules/library/librarian.php'); exit; }
if ($role === 'parent')     { header('Location: '.BASE_URL.'/modules/parent/dashboard.php'); exit; }

$page_title = 'Dashboard';
$active_page = 'dashboard';

// â”€â”€ Branch scoping for admin â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$admin_branch_id = (int)($_SESSION['user']['branch_id'] ?? 0);
$bw  = $admin_branch_id ? "AND s.branch_id=$admin_branch_id" : '';
$bwt = $admin_branch_id ? "AND t.branch_id=$admin_branch_id" : '';
$bwc = $admin_branch_id ? "AND cl.branch_id=$admin_branch_id" : '';
$bwp = $admin_branch_id ? "AND s.branch_id=$admin_branch_id" : '';

// Get branch name for display
$admin_branch_name = null;
if ($admin_branch_id) {
    $bn = $pdo->prepare("SELECT name FROM branches WHERE id=?");
    $bn->execute([$admin_branch_id]);
    $admin_branch_name = $bn->fetchColumn();
}

// All 8 stats in a single query
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM students s WHERE s.status='Active' $bw) AS students,
    (SELECT COUNT(*) FROM teachers t WHERE t.status='Active' $bwt) AS teachers,
    (SELECT COUNT(*) FROM courses WHERE is_active=1) AS courses,
    (SELECT COUNT(*) FROM classes cl WHERE cl.status='Open' $bwc) AS classes,
    (SELECT COUNT(*) FROM exams e JOIN classes cl ON e.class_id=cl.id WHERE e.exam_date >= CURDATE() $bwc) AS exams,
    (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Paid' $bwp) AS paid,
    (SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status IN('Pending','Overdue') $bwp) AS pending,
    (SELECT COUNT(*) FROM students s WHERE s.nationality IS NOT NULL AND s.nationality != 'Ethiopian' $bw) AS intl
")->fetch();

$enroll_months = $pdo->query("SELECT DATE_FORMAT(en.enrolled_at,'%b %Y') AS mo, COUNT(*) AS cnt FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE en.enrolled_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) $bwc GROUP BY DATE_FORMAT(en.enrolled_at,'%Y-%m') ORDER BY DATE_FORMAT(en.enrolled_at,'%Y-%m')")->fetchAll();
$pay_months    = $pdo->query("SELECT DATE_FORMAT(p.created_at,'%b %Y') AS mo, SUM(p.amount_due) AS due, SUM(p.amount_paid) AS paid FROM payments p JOIN students s ON p.student_id=s.id WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) $bwp GROUP BY DATE_FORMAT(p.created_at,'%Y-%m') ORDER BY DATE_FORMAT(p.created_at,'%Y-%m')")->fetchAll();
$stu_status    = $pdo->query("SELECT s.status, COUNT(*) AS cnt FROM students s WHERE 1=1 $bw GROUP BY s.status")->fetchAll();
$grade_dist    = $pdo->query("SELECT g.grade_letter, COUNT(*) AS cnt FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id WHERE 1=1 $bwc GROUP BY g.grade_letter ORDER BY cnt DESC")->fetchAll();
$recent_students = $pdo->query("SELECT s.*, c.name AS country FROM students s LEFT JOIN countries c ON s.country_id=c.id WHERE 1=1 $bw ORDER BY s.created_at DESC LIMIT 5")->fetchAll();
$upcoming_exams  = $pdo->query("SELECT e.*, co.name AS course_name, co.code FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE e.exam_date >= CURDATE() $bwc ORDER BY e.exam_date LIMIT 5")->fetchAll();
$recent_payments = $pdo->query("SELECT p.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, ft.name AS fee_name FROM payments p JOIN students s ON p.student_id=s.id JOIN fee_types ft ON p.fee_type_id=ft.id WHERE 1=1 $bwp ORDER BY p.created_at DESC LIMIT 5")->fetchAll();
$overdue         = $pdo->query("SELECT COUNT(*) FROM payments p JOIN students s ON p.student_id=s.id WHERE p.status='Overdue' $bwp")->fetchColumn();
$top_students    = $pdo->query("SELECT CONCAT(s.first_name,' ',s.last_name) AS name, s.student_code, AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN students s ON en.student_id=s.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id WHERE 1=1 $bwc GROUP BY s.id ORDER BY avg_pct DESC LIMIT 5")->fetchAll();

// â”€â”€ Library quick stats â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// All library stats in one query
$lib = $pdo->query("SELECT
    (SELECT COUNT(*) FROM library_books WHERE is_active=1) AS books,
    (SELECT COUNT(*) FROM library_borrows WHERE status IN('Borrowed','Overdue')) AS borrowed,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Returned') AS returned,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Overdue') AS overdue,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Return Requested') AS return_req,
    (SELECT COALESCE(SUM(fine_amount),0) FROM library_borrows WHERE fine_amount>0) AS fines
")->fetch();

require_once 'includes/header.php';

?>
<!-- ── Page Header ─────────────────────────────────────────── -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b">Dashboard Overview</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:2px">
      Welcome back, <strong><?= e($_SESSION['user']['name']) ?></strong>.
      <?php if ($admin_branch_name): ?>
        Showing data for <strong style="color:var(--primary)"><?= e($admin_branch_name) ?></strong> branch.
      <?php else: ?>
        Showing data across all branches.
      <?php endif; ?>
    </p>
  </div>
  <div style="font-size:.82rem;color:#94a3b8;background:#f8fafc;padding:8px 14px;border-radius:10px;border:1px solid #e2e8f0">
    <i class="fas fa-calendar-alt" style="color:var(--primary)"></i> <?= date('l, F j, Y') ?>
  </div>
</div>

<?php if ($overdue > 0): ?>
<div style="background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
  <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;flex-shrink:0"></i>
  <span><strong><?= $overdue ?> overdue payment(s)</strong> require immediate attention.</span>
  <a href="<?= BASE_URL ?>/modules/payments/index.php?status=Overdue" style="margin-left:auto;background:rgba(255,255,255,.2);color:#fff;padding:5px 14px;border-radius:8px;text-decoration:none;font-size:.82rem;font-weight:600;white-space:nowrap">View now →</a>
</div>
<?php endif; ?>

<!-- ── Main KPI Stats ───────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:24px">
  <?php
  $kpis = [
    ['students', 'fas fa-user-graduate', 'Active Students', '#4361ee', BASE_URL.'/modules/students/index.php'],
    ['teachers', 'fas fa-chalkboard-teacher', 'Teachers', '#7209b7', BASE_URL.'/modules/teachers/index.php'],
    ['courses',  'fas fa-book-open', 'Courses', '#0891b2', BASE_URL.'/modules/courses/index.php'],
    ['classes',  'fas fa-door-open', 'Open Classes', '#f59e0b', BASE_URL.'/modules/classes/index.php'],
    ['exams',    'fas fa-file-alt', 'Upcoming Exams', '#6366f1', BASE_URL.'/modules/exams/index.php'],
    ['pending',  'fas fa-exclamation-circle', 'Pending Payments', '#e63946', BASE_URL.'/modules/payments/index.php?status=Pending'],
    ['intl',     'fas fa-globe', 'Intl. Students', '#10b981', ''],
  ];
  foreach ($kpis as [$key, $icon, $label, $color, $link]):
    $val = $key === 'paid' ? '$'.number_format($stats[$key],0) : $stats[$key];
  ?>
  <div onclick="<?= $link ? "location='$link'" : '' ?>" style="background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);cursor:<?= $link?'pointer':'default' ?>;transition:transform .15s,box-shadow .15s;border-top:3px solid <?= $color ?>" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 10px rgba(0,0,0,.06)'">
    <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>18;display:flex;align-items:center;justify-content:center;margin-bottom:12px">
      <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:1rem"></i>
    </div>
    <div style="font-size:1.6rem;font-weight:800;color:#1e293b;line-height:1"><?= $val ?></div>
    <div style="font-size:.78rem;color:#64748b;margin-top:4px;font-weight:500"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
  <!-- Revenue card -->
  <div onclick="location='<?= BASE_URL ?>/modules/payments/index.php'" style="background:linear-gradient(135deg,#4361ee,#7209b7);border-radius:14px;padding:18px 16px;box-shadow:0 4px 16px rgba(67,97,238,.3);cursor:pointer;transition:transform .15s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
    <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;margin-bottom:12px">
      <i class="fas fa-dollar-sign" style="color:#fff;font-size:1rem"></i>
    </div>
    <div style="font-size:1.4rem;font-weight:800;color:#fff;line-height:1">$<?= number_format($stats['paid'],0) ?></div>
    <div style="font-size:.78rem;color:rgba(255,255,255,.8);margin-top:4px;font-weight:500">Revenue Collected</div>
  </div>
</div>

<!-- ── Charts Row ───────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin-bottom:24px">
  <!-- Enrollment trend -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div style="font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px"></i>Enrollments (6 Months)</div>
    </div>
    <div style="position:relative;height:160px">
      <?php if ($enroll_months): ?>
      <canvas id="enrollChart" style="width:100%;height:160px"></canvas>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#cbd5e1">
        <i class="fas fa-chart-line" style="font-size:2rem;margin-bottom:6px"></i><span style="font-size:.82rem">No data yet</span>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <!-- Student status pie -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.92rem;color:#1e293b;margin-bottom:16px"><i class="fas fa-chart-pie" style="color:#f59e0b;margin-right:6px"></i>Student Status</div>
    <div style="position:relative;height:160px;display:flex;align-items:center;justify-content:center">
      <?php if ($stu_status): ?>
      <canvas id="stuPie" style="max-width:160px;max-height:160px"></canvas>
      <?php else: ?>
      <div style="text-align:center;color:#cbd5e1"><i class="fas fa-chart-pie" style="font-size:2rem"></i><p style="font-size:.78rem;margin-top:6px">No data</p></div>
      <?php endif; ?>
    </div>
  </div>
  <!-- Grade distribution -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.92rem;color:#1e293b;margin-bottom:16px"><i class="fas fa-chart-pie" style="color:#7209b7;margin-right:6px"></i>Grade Distribution</div>
    <div style="position:relative;height:160px;display:flex;align-items:center;justify-content:center">
      <?php if ($grade_dist): ?>
      <canvas id="gradePie" style="max-width:160px;max-height:160px"></canvas>
      <?php else: ?>
      <div style="text-align:center;color:#cbd5e1"><i class="fas fa-chart-pie" style="font-size:2rem"></i><p style="font-size:.78rem;margin-top:6px">No data</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Payment Chart ────────────────────────────────────────── -->
<?php if ($pay_months): ?>
<div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06);margin-bottom:24px">
  <div style="font-weight:700;font-size:.92rem;color:#1e293b;margin-bottom:16px"><i class="fas fa-chart-bar" style="color:#10b981;margin-right:6px"></i>Payments: Due vs Collected (6 Months)</div>
  <div style="position:relative;height:160px"><canvas id="payChart" style="width:100%;height:160px"></canvas></div>
</div>
<?php endif; ?>

<!-- ── Library Overview ─────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
  <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8"><i class="fas fa-book-open" style="color:var(--primary)"></i> Library Overview</div>
  <a href="<?= BASE_URL ?>/modules/library/index.php" style="font-size:.78rem;color:var(--primary);text-decoration:none;font-weight:600">Manage Library →</a>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:24px">
  <?php foreach([
    [$lib['books'],'fas fa-book','Book Titles','#4361ee'],
    [$lib['borrowed'],'fas fa-hand-holding-heart','Borrowed','#f59e0b'],
    [$lib['returned'],'fas fa-undo','Returned','#10b981'],
    [$lib['overdue'],'fas fa-exclamation-circle','Overdue','#e63946'],
    [$lib['return_req'],'fas fa-clock','Awaiting Return','#6366f1'],
    ['$'.number_format($lib['fines'],2),'fas fa-dollar-sign','Fines','#dc2626'],
  ] as [$val,$icon,$lbl,$color]): ?>
  <div style="background:#fff;border-radius:12px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.05);display:flex;align-items:center;gap:12px">
    <div style="width:36px;height:36px;border-radius:9px;background:<?=$color?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="<?=$icon?>" style="color:<?=$color?>;font-size:.9rem"></i>
    </div>
    <div><div style="font-size:1.2rem;font-weight:800;color:#1e293b"><?=$val?></div><div style="font-size:.72rem;color:#64748b"><?=$lbl?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Tables Row ───────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
  <!-- Recent Students -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9">
      <div style="font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-user-graduate" style="color:var(--primary);margin-right:6px"></i>Recent Students</div>
      <a href="<?= BASE_URL ?>/modules/students/index.php" style="font-size:.75rem;color:var(--primary);text-decoration:none;font-weight:600">View All</a>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="background:#f8fafc"><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Student</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Code</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent_students as $s): ?>
      <tr style="border-bottom:1px solid #f8fafc">
        <td style="padding:10px 16px">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
            <div><div style="font-weight:600;font-size:.85rem;color:#1e293b"><?= e($s['first_name'].' '.$s['last_name']) ?></div><div style="font-size:.72rem;color:#94a3b8"><?= e($s['nationality']??'') ?></div></div>
          </div>
        </td>
        <td style="padding:10px 16px;font-size:.78rem;font-family:monospace;color:#64748b"><?= e($s['student_code']??'—') ?></td>
        <td style="padding:10px 16px"><span style="padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;background:<?= $s['status']==='Active'?'#dcfce7':'#fee2e2' ?>;color:<?= $s['status']==='Active'?'#16a34a':'#dc2626' ?>"><?= e($s['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent_students): ?><tr><td colspan="3" style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem">No students yet</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Top Students -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9">
      <div style="font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-trophy" style="color:#f59e0b;margin-right:6px"></i>Top Students</div>
      <a href="<?= BASE_URL ?>/modules/reports/index.php" style="font-size:.75rem;color:var(--primary);text-decoration:none;font-weight:600">Full Report</a>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="background:#f8fafc"><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">#</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Student</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Avg %</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Grade</th></tr></thead>
      <tbody>
      <?php foreach ($top_students as $i => $ts): $pct=round($ts['avg_pct'],1); ?>
      <tr style="border-bottom:1px solid #f8fafc">
        <td style="padding:10px 16px;font-weight:800;color:<?= $i<3?'#f59e0b':'#94a3b8' ?>"><?= $i+1 ?></td>
        <td style="padding:10px 16px;font-weight:600;font-size:.85rem;color:#1e293b"><?= e($ts['name']) ?></td>
        <td style="padding:10px 16px">
          <div style="display:flex;align-items:center;gap:8px">
            <div style="flex:1;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden"><div style="height:100%;width:<?= min(100,$pct) ?>%;background:linear-gradient(90deg,#4361ee,#7209b7);border-radius:3px"></div></div>
            <span style="font-size:.78rem;font-weight:700;color:#1e293b;min-width:36px"><?= $pct ?>%</span>
          </div>
        </td>
        <td style="padding:10px 16px;font-weight:800;color:<?= $pct>=50?'#16a34a':'#dc2626' ?>"><?= grade_letter($pct) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$top_students): ?><tr><td colspan="4" style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem">No grades yet</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- ── Bottom Row ───────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
  <!-- Upcoming Exams -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9">
      <div style="font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-file-alt" style="color:#f59e0b;margin-right:6px"></i>Upcoming Exams</div>
      <a href="<?= BASE_URL ?>/modules/exams/index.php" style="font-size:.75rem;color:var(--primary);text-decoration:none;font-weight:600">View All</a>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="background:#f8fafc"><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Exam</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Course</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Date</th></tr></thead>
      <tbody>
      <?php foreach ($upcoming_exams as $ex): ?>
      <tr style="border-bottom:1px solid #f8fafc">
        <td style="padding:10px 16px;font-weight:600;font-size:.85rem;color:#1e293b"><?= e($ex['title']) ?></td>
        <td style="padding:10px 16px"><span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:6px;font-size:.72rem;font-weight:700"><?= e($ex['code']) ?></span></td>
        <td style="padding:10px 16px;font-size:.82rem;color:#64748b"><?= date('M j, Y', strtotime($ex['exam_date'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$upcoming_exams): ?><tr><td colspan="3" style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem">No upcoming exams</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- Recent Payments -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #f1f5f9">
      <div style="font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-credit-card" style="color:#10b981;margin-right:6px"></i>Recent Payments</div>
      <a href="<?= BASE_URL ?>/modules/payments/index.php" style="font-size:.75rem;color:var(--primary);text-decoration:none;font-weight:600">View All</a>
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="background:#f8fafc"><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Student</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Fee</th><th style="padding:10px 16px;text-align:left;font-size:.75rem;color:#64748b;font-weight:600">Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent_payments as $p): ?>
      <tr style="border-bottom:1px solid #f8fafc">
        <td style="padding:10px 16px;font-weight:600;font-size:.85rem;color:#1e293b"><?= e($p['student_name']) ?></td>
        <td style="padding:10px 16px;font-size:.82rem;color:#64748b"><?= e($p['fee_name']) ?></td>
        <td style="padding:10px 16px">
          <?php $sc=match($p['status']){'Paid'=>['#dcfce7','#16a34a'],'Pending'=>['#fef9c3','#ca8a04'],'Overdue'=>['#fee2e2','#dc2626'],'Partial'=>['#dbeafe','#2563eb'],default=>['#f1f5f9','#64748b']}; ?>
          <span style="padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;background:<?=$sc[0]?>;color:<?=$sc[1]?>"><?= e($p['status']) ?></span>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$recent_payments): ?><tr><td colspan="3" style="padding:24px;text-align:center;color:#94a3b8;font-size:.85rem">No payments yet</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const primary='#4361ee',success='#10b981',warning='#f59e0b',danger='#e63946',purple='#7209b7',teal='#0891b2';
const palette=[primary,success,warning,danger,purple,teal,'#e76f51','#52b788','#e9c46a'];

<?php if ($enroll_months): ?>
new Chart(document.getElementById('enrollChart'),{type:'line',data:{labels:<?=json_encode(array_column($enroll_months,'mo'))?>,datasets:[{label:'Enrollments',data:<?=json_encode(array_column($enroll_months,'cnt'))?>,borderColor:primary,backgroundColor:'rgba(67,97,238,.1)',tension:.4,fill:true,pointBackgroundColor:primary,pointRadius:4,borderWidth:2}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,font:{size:10}},grid:{color:'#f1f5f9'}},x:{ticks:{font:{size:10}},grid:{display:false}}}}});
<?php endif; ?>
<?php if ($pay_months): ?>
new Chart(document.getElementById('payChart'),{type:'bar',data:{labels:<?=json_encode(array_column($pay_months,'mo'))?>,datasets:[{label:'Due',data:<?=json_encode(array_column($pay_months,'due'))?>,backgroundColor:'rgba(244,162,97,.8)',borderRadius:4},{label:'Collected',data:<?=json_encode(array_column($pay_months,'paid'))?>,backgroundColor:'rgba(16,185,129,.8)',borderRadius:4}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:11}}}},scales:{y:{beginAtZero:true,ticks:{font:{size:10}},grid:{color:'#f1f5f9'}},x:{ticks:{font:{size:10}},grid:{display:false}}}}});
<?php endif; ?>
<?php if ($stu_status): ?>
new Chart(document.getElementById('stuPie'),{type:'doughnut',data:{labels:<?=json_encode(array_column($stu_status,'status'))?>,datasets:[{data:<?=json_encode(array_column($stu_status,'cnt'))?>,backgroundColor:[success,danger,primary,warning,purple],borderWidth:0}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}},cutout:'65%'}});
<?php endif; ?>
<?php if ($grade_dist): ?>
new Chart(document.getElementById('gradePie'),{type:'doughnut',data:{labels:<?=json_encode(array_column($grade_dist,'grade_letter'))?>,datasets:[{data:<?=json_encode(array_column($grade_dist,'cnt'))?>,backgroundColor:palette,borderWidth:0}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}},cutout:'65%'}});
<?php endif; ?>
</script>
<?php require_once 'includes/footer.php'; ?>
