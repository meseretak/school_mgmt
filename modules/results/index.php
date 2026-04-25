<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher','student']);
$page_title = 'Year-End Results'; $active_page = 'results';

// Students redirect to their own dashboard results tab
if (is_student()) {
    header('Location: '.BASE_URL.'/modules/students/dashboard.php#results'); exit;
}

$years = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$year_id = (int)($_GET['year_id'] ?? ($pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn() ?: 0));
$branch_id = (int)($_GET['branch_id'] ?? 0);
$branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
$pass_pct = get_pass_pct();

// Generate results for all students in a year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'generate') {
    csrf_check();
    $yid = (int)$_POST['year_id'];
    $bid = (int)($_POST['branch_id'] ?? 0) ?: null;

    // Get all students enrolled in classes of this year
    $students_q = $pdo->prepare("
        SELECT DISTINCT s.id AS student_id
        FROM students s
        JOIN enrollments en ON en.student_id=s.id
        JOIN classes cl ON en.class_id=cl.id
        WHERE cl.academic_year_id=?
        " . ($bid ? "AND (s.branch_id=? OR cl.branch_id=?)" : ""));
    $params = [$yid]; if ($bid) { $params[] = $bid; $params[] = $bid; }
    $students_q->execute($params);
    $student_ids = array_column($students_q->fetchAll(), 'student_id');

    $generated = 0;
    foreach ($student_ids as $sid) {
        // Get all classes for this student in this year
        $classes_q = $pdo->prepare("SELECT cl.id, co.name AS course_name, co.credits FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? AND cl.academic_year_id=? AND en.status='Enrolled'");
        $classes_q->execute([$sid, $yid]); $student_classes = $classes_q->fetchAll();

        $total_subjects = count($student_classes);
        $passed = 0; $failed = 0; $all_pcts = [];

        foreach ($student_classes as $sc) {
            // Average grade for this student in this class
            $avg = $pdo->prepare("SELECT AVG(g.marks_obtained/ex.total_marks*100) FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id WHERE en.student_id=? AND en.class_id=?");
            $avg->execute([$sid, $sc['id']]); $avg = (float)$avg->fetchColumn();
            if ($avg > 0) {
                $all_pcts[] = $avg;
                if ($avg >= $pass_pct) $passed++; else $failed++;
            }
        }

        $overall_pct = count($all_pcts) ? round(array_sum($all_pcts)/count($all_pcts), 2) : 0;
        // GPA
        $scale_items = $pdo->query("SELECT * FROM grade_scale_items gsi JOIN grade_scales gs ON gsi.scale_id=gs.id WHERE gs.is_default=1 ORDER BY gsi.min_pct DESC")->fetchAll();
        $gpa = 0;
        foreach ($scale_items as $si) { if ($overall_pct >= $si['min_pct']) { $gpa = $si['gpa_points']; break; } }

        $result = 'Incomplete';
        if ($total_subjects > 0 && count($all_pcts) === $total_subjects) {
            if ($overall_pct >= 90) $result = 'Distinction';
            elseif ($overall_pct >= 75) $result = 'Merit';
            elseif ($overall_pct >= $pass_pct && $failed === 0) $result = 'Pass';
            else $result = 'Fail';
        }

        $pdo->prepare("INSERT INTO year_results (student_id,academic_year_id,branch_id,total_subjects,passed_subjects,failed_subjects,overall_pct,gpa,result,generated_by) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE total_subjects=?,passed_subjects=?,failed_subjects=?,overall_pct=?,gpa=?,result=?,generated_by=?")
            ->execute([$sid,$yid,$bid,$total_subjects,$passed,$failed,$overall_pct,$gpa,$result,$_SESSION['user']['id'],$total_subjects,$passed,$failed,$overall_pct,$gpa,$result,$_SESSION['user']['id']]);
        $generated++;
    }
    flash("Results generated for $generated student(s).");
    header("Location: index.php?year_id=$yid&branch_id=".($bid??0)); exit;
}

// Load results
$results = [];
if ($year_id) {
    $sql = "SELECT yr.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, s.id AS student_id, b.name AS branch_name FROM year_results yr JOIN students s ON yr.student_id=s.id LEFT JOIN branches b ON yr.branch_id=b.id WHERE yr.academic_year_id=?";
    $params = [$year_id];
    if ($branch_id) { $sql .= " AND yr.branch_id=?"; $params[] = $branch_id; }
    $sql .= " ORDER BY yr.overall_pct DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $results = $stmt->fetchAll();
}

$pass_count = count(array_filter($results, fn($r) => in_array($r['result'],['Pass','Merit','Distinction'])));
$fail_count = count(array_filter($results, fn($r) => $r['result'] === 'Fail'));

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-graduation-cap" style="color:var(--primary)"></i> Year-End Results</h1><p>Generate and view student pass/fail results</p></div>
  <?php if ($results): ?>
  <a href="<?= BASE_URL ?>/modules/results/print.php?year_id=<?= $year_id ?>&branch_id=<?= $branch_id ?>" target="_blank" class="btn btn-secondary"><i class="fas fa-print"></i> Print Results</a>
  <?php endif; ?>
</div>

<!-- Filters + Generate -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <select name="year_id" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
      <option value="<?= $y['id'] ?>" <?= $year_id==$y['id']?'selected':'' ?>><?= e($y['label']) ?> <?= $y['is_current']?'(Current)':'' ?></option>
      <?php endforeach; ?>
    </select>
    <select name="branch_id" onchange="this.form.submit()">
      <option value="">All Branches</option>
      <?php foreach ($branches as $b): ?>
      <option value="<?= $b['id'] ?>" <?= $branch_id==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <form method="POST" style="margin-top:12px;display:flex;gap:10px;align-items:center">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="generate">
    <input type="hidden" name="year_id" value="<?= $year_id ?>">
    <input type="hidden" name="branch_id" value="<?= $branch_id ?>">
    <button type="submit" class="btn btn-primary" onclick="return confirm('Generate/recalculate results for all students in this year?')">
      <i class="fas fa-sync"></i> Generate Results
    </button>
    <small style="color:#888">This will calculate pass/fail based on the current grade scale (pass: <?= $pass_pct ?>%)</small>
  </form>
</div></div>

<?php if ($results): ?>
<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= count($results) ?></h3><p>Total Students</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $pass_count ?></h3><p>Passed</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div class="stat-info"><h3><?= $fail_count ?></h3><p>Failed</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-percentage"></i></div><div class="stat-info"><h3><?= count($results)?round($pass_count/count($results)*100).'%':'—' ?></h3><p>Pass Rate</p></div></div>
</div>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> Results List</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Student</th><th>Branch</th><th>Subjects</th><th>Passed</th><th>Failed</th><th>Overall %</th><th>GPA</th><th>Grade</th><th>Result</th><th>Certificate</th></tr></thead>
    <tbody>
    <?php foreach ($results as $i => $r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td>
        <div style="font-weight:600"><?= e($r['student_name']) ?></div>
        <small style="color:#888"><?= e($r['student_code']) ?></small>
      </td>
      <td><?= e($r['branch_name']??'—') ?></td>
      <td><?= $r['total_subjects'] ?></td>
      <td style="color:var(--success);font-weight:600"><?= $r['passed_subjects'] ?></td>
      <td style="color:<?= $r['failed_subjects']>0?'var(--danger)':'#aaa' ?>;font-weight:600"><?= $r['failed_subjects'] ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <?= $r['overall_pct'] ?>%
          <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?= min(100,$r['overall_pct']) ?>%;background:<?= $r['overall_pct']>=$pass_pct?'var(--success)':'var(--danger)' ?>"></div></div>
        </div>
      </td>
      <td><?= $r['gpa'] ?></td>
      <td style="font-weight:700;color:var(--success)"><?= grade_letter($r['overall_pct']) ?></td>
      <td>
        <span class="badge badge-<?php $rr_=($r['result']??''); echo ($rr_==='Pass'||$rr_==='Merit'||$rr_==='Distinction')?'success':($rr_==='Fail'?'danger':'warning'); ?>">
          <?= $r['result'] === 'Distinction' ? '🏆 ' : ($r['result'] === 'Merit' ? '⭐ ' : '') ?><?= e($r['result']) ?>
        </span>
      </td>
      <td>
        <a href="<?= BASE_URL ?>/modules/results/certificate.php?student_id=<?= $r['student_id'] ?>&year_id=<?= $year_id ?>" target="_blank" class="btn btn-sm btn-<?= in_array($r['result'],['Pass','Merit','Distinction'])?'success':'secondary' ?>">
          <i class="fas fa-certificate"></i> <?= in_array($r['result'],['Pass','Merit','Distinction'])?'Certificate':'View' ?>
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php elseif ($year_id): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:50px;color:#aaa">
  <i class="fas fa-graduation-cap" style="font-size:2.5rem;display:block;margin-bottom:12px"></i>
  No results yet. Click "Generate Results" to calculate.
</div></div>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>