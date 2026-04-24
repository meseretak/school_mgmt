<?php
require_once '../../includes/config.php';
auth_check(['teacher']);
$page_title = 'My Teaching Portal'; $active_page = 'teacher_dashboard';

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$_SESSION['user']['id']]); $teacher = $teacher->fetch();
if (!$teacher) {
    require_once '../../includes/header.php';
    echo '<div class="card"><div class="card-body" style="text-align:center;padding:60px"><h2>Teacher Profile Not Found</h2><p>Please contact your administrator.</p><a href="'.BASE_URL.'/logout.php" class="btn btn-secondary">Logout</a></div></div>';
    require_once '../../includes/footer.php'; exit;
}

$tid = $teacher['id'];

// My classes with schedule
$my_classes = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code AS course_code, ay.label AS year_label, COUNT(DISTINCT en.id) AS enrolled, COUNT(DISTINCT ex.id) AS exam_count FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id LEFT JOIN enrollments en ON cl.id=en.class_id AND en.status='Enrolled' LEFT JOIN exams ex ON ex.class_id=cl.id WHERE cl.teacher_id=? GROUP BY cl.id ORDER BY ay.is_current DESC, co.name");
$my_classes->execute([$tid]); $my_classes = $my_classes->fetchAll();

// Timetable slots for this teacher's classes
$timetable = $pdo->prepare("SELECT ts.*, co.name AS course_name, co.code, cl.section FROM timetable_slots ts JOIN classes cl ON ts.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),ts.start_time");
$timetable->execute([$tid]); $timetable = $timetable->fetchAll();
// Group timetable by class_id
$timetable_by_class = [];
foreach ($timetable as $slot) { $timetable_by_class[$slot['class_id']][] = $slot; }
// Group by day for weekly view
$schedule_by_day = [];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
foreach ($days as $d) $schedule_by_day[$d] = [];
foreach ($timetable as $slot) $schedule_by_day[$slot['day_of_week']][] = $slot;

// Upcoming exams
$upcoming = $pdo->prepare("SELECT e.*, co.name AS course_name, co.code, cl.section FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? AND e.exam_date >= CURDATE() ORDER BY e.exam_date LIMIT 8");
$upcoming->execute([$tid]); $upcoming = $upcoming->fetchAll();

// Needs grading
$ungraded = $pdo->prepare("SELECT e.id, e.title, co.code, cl.id AS class_id, COUNT(DISTINCT en.id) AS students, COUNT(DISTINCT g.id) AS graded FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id LEFT JOIN enrollments en ON en.class_id=cl.id AND en.status='Enrolled' LEFT JOIN grades g ON g.exam_id=e.id AND g.enrollment_id=en.id WHERE cl.teacher_id=? GROUP BY e.id HAVING graded < students ORDER BY e.exam_date DESC LIMIT 5");
$ungraded->execute([$tid]); $ungraded = $ungraded->fetchAll();

// Grade distribution
$grade_dist = $pdo->prepare("SELECT g.grade_letter, COUNT(*) AS cnt FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id WHERE cl.teacher_id=? GROUP BY g.grade_letter ORDER BY cnt DESC");
$grade_dist->execute([$tid]); $grade_dist = $grade_dist->fetchAll();

// Attendance rates per class
$att_rates = $pdo->prepare("SELECT co.code, cl.section, COUNT(a.id) AS total, SUM(a.status='Present') AS present FROM classes cl JOIN courses co ON cl.course_id=co.id LEFT JOIN enrollments en ON en.class_id=cl.id LEFT JOIN attendance a ON a.enrollment_id=en.id WHERE cl.teacher_id=? GROUP BY cl.id ORDER BY co.name");
$att_rates->execute([$tid]); $att_rates = $att_rates->fetchAll();

// Total distinct students
$total_students = (int)$pdo->prepare("SELECT COUNT(DISTINCT en.student_id) FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE cl.teacher_id=? AND en.status='Enrolled'")->execute([$tid]) ? $pdo->prepare("SELECT COUNT(DISTINCT en.student_id) FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE cl.teacher_id=? AND en.status='Enrolled'")->execute([$tid]) : 0;
$ts_q = $pdo->prepare("SELECT COUNT(DISTINCT en.student_id) FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE cl.teacher_id=? AND en.status='Enrolled'");
$ts_q->execute([$tid]); $total_students = (int)$ts_q->fetchColumn();

// Pass rate
$pass_pct = get_pass_pct();
$pr_q = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN (g.marks_obtained/ex.total_marks*100) >= ? THEN 1 ELSE 0 END) AS passed FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id WHERE cl.teacher_id=?");
$pr_q->execute([$pass_pct, $tid]); $pr_data = $pr_q->fetch();
$pass_rate = ($pr_data['total'] > 0) ? round($pr_data['passed'] / $pr_data['total'] * 100) : 0;

// Today's attendance marked per class
$ta_q = $pdo->prepare("SELECT cl.id AS class_id, COUNT(a.id) AS marked FROM classes cl LEFT JOIN enrollments en ON en.class_id=cl.id LEFT JOIN attendance a ON a.enrollment_id=en.id AND a.date=CURDATE() WHERE cl.teacher_id=? GROUP BY cl.id");
$ta_q->execute([$tid]); $today_att = array_column($ta_q->fetchAll(), 'marked', 'class_id');

// Monthly submission trend (last 6 months)
$sub_trend = $pdo->prepare("SELECT DATE_FORMAT(sub.submitted_at,'%Y-%m') AS mo, COUNT(*) AS cnt FROM assignment_submissions sub JOIN assignments a ON sub.assignment_id=a.id WHERE a.teacher_id=? AND sub.submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY mo ORDER BY mo");
$sub_trend->execute([$tid]); $sub_trend = $sub_trend->fetchAll();

// Recent assignments
$recent_assignments = $pdo->prepare("SELECT a.*, co.name AS course_name, co.code, cl.section, COUNT(DISTINCT sub.id) AS sub_count, COUNT(DISTINCT en.id) AS enrolled FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id LEFT JOIN enrollments en ON en.class_id=cl.id AND en.status='Enrolled' WHERE a.teacher_id=? GROUP BY a.id ORDER BY a.created_at DESC LIMIT 6");
$recent_assignments->execute([$tid]); $recent_assignments = $recent_assignments->fetchAll();

require_once '../../includes/header.php';
?>
<!-- Page Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:800;color:#1e293b;margin:0">
      <i class="fas fa-chalkboard-teacher" style="color:var(--primary);margin-right:8px"></i>Teaching Portal
    </h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:4px">
      Welcome back, <strong><?= e($teacher['first_name'].' '.$teacher['last_name']) ?></strong>
      <?php if ($teacher['specialization']): ?> · <span style="color:#94a3b8"><?= e($teacher['specialization']) ?></span><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <div style="background:#fff;padding:8px 16px;border-radius:10px;border:1px solid #e2e8f0;font-size:.85rem;color:#64748b">
      <i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:6px"></i><?= date('l, F j, Y') ?>
    </div>
    <a href="<?= BASE_URL ?>/profile.php" class="btn btn-primary" style="font-size:.85rem"><i class="fas fa-user"></i> Profile</a>
  </div>
</div>

<!-- KPI Stat Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:28px">
<?php
$kpis = [
  [count($my_classes),   'fas fa-door-open',      'My Classes',      '#4361ee', '#eef1fd'],
  [$total_students,      'fas fa-user-graduate',   'Total Students',  '#7209b7', '#f3e8ff'],
  [count($upcoming),     'fas fa-file-alt',        'Upcoming Exams',  '#f59e0b', '#fffbeb'],
  [count($ungraded),     'fas fa-star-half-alt',   'Needs Grading',   '#ef4444', '#fef2f2'],
  [$pass_rate.'%',       'fas fa-check-circle',    'Pass Rate',       '#10b981', '#ecfdf5'],
  [count($recent_assignments), 'fas fa-tasks',     'Assignments',     '#6366f1', '#eef2ff'],
];
foreach ($kpis as [$val, $icon, $lbl, $color, $bg]): ?>
  <div style="background:#fff;border-radius:14px;padding:20px 18px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);border-top:4px solid <?= $color ?>;display:flex;flex-direction:column;gap:8px">
    <div style="width:40px;height:40px;border-radius:10px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center">
      <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:1.1rem"></i>
    </div>
    <div style="font-size:1.8rem;font-weight:800;color:#1e293b;line-height:1"><?= $val ?></div>
    <div style="font-size:.75rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em"><?= $lbl ?></div>
  </div>
<?php endforeach; ?>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:28px">

  <!-- Bar: Attendance per class -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:6px"></i>Attendance by Class</div>
    <div style="height:200px;position:relative"><canvas id="attChart"></canvas></div>
  </div>

  <!-- Doughnut: Grade distribution -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-chart-pie" style="color:#7209b7;margin-right:6px"></i>Grade Distribution</div>
    <div style="height:200px;position:relative"><canvas id="gradeChart"></canvas></div>
  </div>

  <!-- Line: Monthly submissions -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-chart-line" style="color:#10b981;margin-right:6px"></i>Monthly Submissions</div>
    <div style="height:200px;position:relative"><canvas id="subChart"></canvas></div>
  </div>

</div>

<!-- My Classes Grid -->
<div style="margin-bottom:28px">
  <div style="font-weight:700;font-size:1rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-door-open" style="color:var(--primary);margin-right:6px"></i>My Classes</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
  <?php
  $class_colors = ['#4361ee','#7209b7','#10b981','#f59e0b','#06b6d4','#ef4444','#6366f1','#f97316'];
  foreach ($my_classes as $i => $cl):
    $cc = $class_colors[$i % count($class_colors)];
    $att_today = $today_att[$cl['id']] ?? 0;
  ?>
  <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06)">
    <div style="background:<?= $cc ?>;padding:16px 18px;color:#fff">
      <div style="font-size:.75rem;opacity:.85;margin-bottom:3px"><?= e($cl['course_code']) ?> &middot; <?= e($cl['year_label']) ?></div>
      <div style="font-weight:800;font-size:.95rem;line-height:1.3"><?= e($cl['course_name']) ?></div>
      <?php if ($cl['section']): ?><div style="font-size:.75rem;opacity:.8;margin-top:3px">Section <?= e($cl['section']) ?></div><?php endif; ?>
    </div>
    <div style="padding:14px 18px;display:flex;gap:16px;font-size:.82rem;color:#64748b">
      <div style="text-align:center">
        <div style="font-size:1.2rem;font-weight:800;color:#1e293b"><?= $cl['enrolled'] ?></div>
        <div>Students</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:1.2rem;font-weight:800;color:#1e293b"><?= $cl['exam_count'] ?></div>
        <div>Exams</div>
      </div>
      <div style="text-align:center">
        <div style="font-size:1.2rem;font-weight:800;color:<?= $att_today > 0 ? '#10b981' : '#94a3b8' ?>"><?= $att_today ?></div>
        <div>Today Att.</div>
      </div>
    </div>
    <div style="padding:0 18px 14px;display:flex;gap:8px">
      <a href="<?= BASE_URL ?>/modules/grades/index.php?class_id=<?= $cl['id'] ?>" class="btn btn-sm btn-primary" style="font-size:.75rem;flex:1;text-align:center"><i class="fas fa-star"></i> Grades</a>
      <a href="<?= BASE_URL ?>/modules/attendance/mark.php?class_id=<?= $cl['id'] ?>" class="btn btn-sm btn-secondary" style="font-size:.75rem;flex:1;text-align:center"><i class="fas fa-calendar-check"></i> Attend.</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$my_classes): ?>
  <div style="background:#fff;border-radius:14px;padding:40px;text-align:center;color:#94a3b8;grid-column:1/-1;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <i class="fas fa-door-open" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:.3"></i>No classes assigned yet.
  </div>
  <?php endif; ?>
  </div>
</div>

<!-- Bottom Tables Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-bottom:28px">

  <!-- Needs Grading Table -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
      <div style="font-weight:700;font-size:.9rem;color:#1e293b"><i class="fas fa-star-half-alt" style="color:#ef4444;margin-right:6px"></i>Needs Grading</div>
      <?php if ($ungraded): ?><span style="background:#fef2f2;color:#ef4444;border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700"><?= count($ungraded) ?> pending</span><?php endif; ?>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.83rem">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px 16px;text-align:left;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Exam</th>
            <th style="padding:10px 16px;text-align:left;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Course</th>
            <th style="padding:10px 16px;text-align:center;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Progress</th>
            <th style="padding:10px 16px;text-align:center;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($ungraded as $ug):
          $pct_done = $ug['students'] > 0 ? round($ug['graded'] / $ug['students'] * 100) : 0;
        ?>
          <tr style="border-top:1px solid #f1f5f9">
            <td style="padding:10px 16px;font-weight:600;color:#1e293b"><?= e($ug['title']) ?></td>
            <td style="padding:10px 16px;color:#64748b"><span style="font-family:monospace;color:var(--primary)"><?= e($ug['code']) ?></span></td>
            <td style="padding:10px 16px;text-align:center">
              <div style="display:flex;align-items:center;gap:6px;justify-content:center">
                <div style="flex:1;height:6px;background:#f1f5f9;border-radius:3px;min-width:60px">
                  <div style="height:6px;border-radius:3px;background:<?= $pct_done >= 100 ? '#10b981' : '#f59e0b' ?>;width:<?= $pct_done ?>%"></div>
                </div>
                <span style="font-size:.75rem;color:#64748b;white-space:nowrap"><?= $ug['graded'] ?>/<?= $ug['students'] ?></span>
              </div>
            </td>
            <td style="padding:10px 16px;text-align:center">
              <a href="<?= BASE_URL ?>/modules/grades/index.php?class_id=<?= $ug['class_id'] ?>&exam_id=<?= $ug['id'] ?>" style="background:var(--primary);color:#fff;border-radius:6px;padding:4px 12px;text-decoration:none;font-size:.75rem;font-weight:600"><i class="fas fa-pen"></i> Grade</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ungraded): ?>
          <tr><td colspan="4" style="padding:30px;text-align:center;color:#94a3b8"><i class="fas fa-check-circle" style="color:#10b981;margin-right:6px"></i>All caught up!</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Upcoming Exams Table -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
      <div style="font-weight:700;font-size:.9rem;color:#1e293b"><i class="fas fa-file-alt" style="color:#f59e0b;margin-right:6px"></i>Upcoming Exams</div>
      <?php if ($upcoming): ?><span style="background:#fffbeb;color:#f59e0b;border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700"><?= count($upcoming) ?> scheduled</span><?php endif; ?>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.83rem">
        <thead>
          <tr style="background:#f8fafc">
            <th style="padding:10px 16px;text-align:left;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Exam</th>
            <th style="padding:10px 16px;text-align:left;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Course</th>
            <th style="padding:10px 16px;text-align:left;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Date</th>
            <th style="padding:10px 16px;text-align:center;font-weight:600;color:#64748b;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em">Type</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($upcoming as $ex):
          $days = (int)ceil((strtotime($ex['exam_date']) - time()) / 86400);
        ?>
          <tr style="border-top:1px solid #f1f5f9">
            <td style="padding:10px 16px">
              <div style="font-weight:600;color:#1e293b"><?= e($ex['title']) ?></div>
              <?php if ($days <= 3): ?><span style="background:#fef2f2;color:#ef4444;border-radius:4px;padding:1px 6px;font-size:.7rem;font-weight:700">In <?= $days ?> day<?= $days != 1 ? 's' : '' ?>!</span><?php endif; ?>
            </td>
            <td style="padding:10px 16px;color:#64748b">
              <span style="font-family:monospace;color:var(--primary)"><?= e($ex['code']) ?></span>
              <?php if ($ex['section']): ?><span style="color:#94a3b8"> §<?= e($ex['section']) ?></span><?php endif; ?>
            </td>
            <td style="padding:10px 16px;font-weight:600;color:#1e293b;white-space:nowrap"><?= date('M j, Y', strtotime($ex['exam_date'])) ?></td>
            <td style="padding:10px 16px;text-align:center">
              <span style="background:#eef1fd;color:var(--primary);border-radius:6px;padding:2px 8px;font-size:.75rem;font-weight:600"><?= e($ex['type']) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$upcoming): ?>
          <tr><td colspan="4" style="padding:30px;text-align:center;color:#94a3b8"><i class="fas fa-calendar-check" style="color:#10b981;margin-right:6px"></i>No upcoming exams.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.font.size = 11;
Chart.defaults.color = '#64748b';

// ── Attendance Bar Chart ──────────────────────────────────────
(function(){
  var labels = <?= json_encode(array_map(fn($r) => $r['code'].($r['section'] ? ' §'.$r['section'] : ''), $att_rates)) ?>;
  var rates  = <?= json_encode(array_map(fn($r) => $r['total'] > 0 ? round($r['present'] / $r['total'] * 100, 1) : 0, $att_rates)) ?>;
  if (!labels.length) { document.getElementById('attChart').parentElement.innerHTML='<p style="text-align:center;color:#94a3b8;padding:60px 0;font-size:.85rem">No attendance data yet.</p>'; return; }
  new Chart(document.getElementById('attChart'), {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{ label: 'Attendance %', data: rates,
        backgroundColor: rates.map(v => v >= 75 ? 'rgba(16,185,129,.75)' : 'rgba(239,68,68,.75)'),
        borderRadius: 6, borderSkipped: false }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { min: 0, max: 100, ticks: { callback: v => v+'%' }, grid: { color: '#f1f5f9' } },
        x: { grid: { display: false } }
      }
    }
  });
})();

// ── Grade Doughnut Chart ──────────────────────────────────────
(function(){
  var labels = <?= json_encode(array_column($grade_dist, 'grade_letter')) ?>;
  var counts = <?= json_encode(array_column($grade_dist, 'cnt')) ?>;
  if (!labels.length) { document.getElementById('gradeChart').parentElement.innerHTML='<p style="text-align:center;color:#94a3b8;padding:60px 0;font-size:.85rem">No grade data yet.</p>'; return; }
  var palette = ['#4361ee','#10b981','#f59e0b','#06b6d4','#ef4444','#7209b7','#6366f1','#f97316'];
  new Chart(document.getElementById('gradeChart'), {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{ data: counts, backgroundColor: palette.slice(0, labels.length), borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '62%',
      plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 10 } } }
    }
  });
})();

// ── Monthly Submissions Line Chart ────────────────────────────
(function(){
  var raw = <?= json_encode($sub_trend) ?>;
  // Fill last 6 months
  var months = [], counts = [];
  for (var i = 5; i >= 0; i--) {
    var d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - i);
    var key = d.getFullYear()+'-'+(String(d.getMonth()+1).padStart(2,'0'));
    var lbl = d.toLocaleString('default',{month:'short',year:'2-digit'});
    months.push(lbl);
    var found = raw.find(r => r.mo === key);
    counts.push(found ? parseInt(found.cnt) : 0);
  }
  new Chart(document.getElementById('subChart'), {
    type: 'line',
    data: {
      labels: months,
      datasets: [{ label: 'Submissions', data: counts,
        borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.12)',
        borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#10b981',
        fill: true, tension: 0.4 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
        x: { grid: { display: false } }
      }
    }
  });
})();
</script>

<!-- Weekly Schedule -->
<div style="margin-bottom:28px">
  <div style="font-weight:700;font-size:1rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-calendar-week" style="color:#6366f1;margin-right:6px"></i>My Weekly Schedule</div>
  <?php if($timetable): ?>
  <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);overflow:hidden">
    <div style="display:grid;grid-template-columns:80px repeat(6,1fr);border-bottom:2px solid #f1f5f9">
      <div style="padding:10px;background:#f8fafc;font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase"></div>
      <?php foreach($days as $d): $isToday=date('l')===$d; ?>
      <div style="padding:10px;text-align:center;font-size:.78rem;font-weight:700;color:<?=$isToday?'var(--primary)':'#64748b'?>;background:<?=$isToday?'#eef1fd':'#f8fafc'?>"><?=$d?></div>
      <?php endforeach; ?>
    </div>
    <?php
    // Get unique time slots
    $all_times = array_unique(array_column($timetable,'start_time'));
    sort($all_times);
    foreach($all_times as $time): ?>
    <div style="display:grid;grid-template-columns:80px repeat(6,1fr);border-bottom:1px solid #f8fafc">
      <div style="padding:10px 8px;font-size:.72rem;color:#94a3b8;font-weight:600;display:flex;align-items:center;justify-content:center;background:#fafafa"><?=date('g:i A',strtotime($time))?></div>
      <?php foreach($days as $d):
        $slot=null;
        foreach($schedule_by_day[$d] as $s){ if($s['start_time']===$time){$slot=$s;break;} }
        $colors2=['#4361ee','#7209b7','#10b981','#f59e0b','#06b6d4','#ef4444'];
        $ci=0; foreach($my_classes as $idx=>$mc){ if($slot&&$mc['id']==$slot['class_id']){$ci=$idx%count($colors2);break;} }
        $isToday=date('l')===$d;
      ?>
      <div style="padding:6px;background:<?=$isToday?'#fafbff':'#fff'?>">
        <?php if($slot): ?>
        <div style="background:<?=$colors2[$ci]?>18;border-left:3px solid <?=$colors2[$ci]?>;border-radius:6px;padding:6px 8px;font-size:.72rem">
          <div style="font-weight:700;color:<?=$colors2[$ci]?>"><?=e($slot['code'])?></div>
          <div style="color:#64748b;font-size:.68rem"><?=date('g:i',strtotime($slot['start_time']))?>–<?=date('g:i A',strtotime($slot['end_time']))?></div>
          <?php if($slot['room']):?><div style="color:#94a3b8;font-size:.65rem"><i class="fas fa-map-marker-alt"></i> <?=e($slot['room'])?></div><?php endif;?>
        </div>
        <?php else: ?>
        <div style="height:100%;min-height:40px"></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div style="background:#fff;border-radius:14px;padding:30px;text-align:center;color:#94a3b8;box-shadow:0 1px 3px rgba(0,0,0,.06)">
    <i class="fas fa-calendar-week" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>
    No timetable slots assigned yet. <a href="<?=BASE_URL?>/modules/timetable/index.php" style="color:var(--primary)">Set up timetable →</a>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
