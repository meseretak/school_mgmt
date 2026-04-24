<?php
require_once '../../includes/config.php';
auth_check();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Access control — student can only see own transcript
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] != $id) { deny(); }
}
// Parent can only see linked children
if ($_SESSION['user']['role'] === 'parent') {
    $par = $pdo->prepare("SELECT p.id FROM parents p WHERE p.user_id=?");
    $par->execute([$_SESSION['user']['id']]); $par = $par->fetch();
    if ($par) {
        $link = $pdo->prepare("SELECT id FROM student_parents WHERE parent_id=? AND student_id=?");
        $link->execute([$par['id'], $id]);
        if (!$link->fetch()) deny();
    } else { deny(); }
}

$student = $pdo->prepare("SELECT s.*, c.name AS country, u.email FROM students s LEFT JOIN countries c ON s.country_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?");
$student->execute([$id]); $student = $student->fetch();
if (!$student) { die('Student not found.'); }

// All grades grouped by year > semester > course
$grades = $pdo->prepare("
    SELECT g.marks_obtained, g.grade_letter, g.remarks,
        ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, ex.exam_date,
        co.name AS course_name, co.code AS course_code, co.credits,
        cl.section, ay.label AS year_label, ay.id AS year_id, ay.start_date, ay.end_date
    FROM grades g
    JOIN enrollments en ON g.enrollment_id=en.id
    JOIN exams ex ON g.exam_id=ex.id
    JOIN classes cl ON en.class_id=cl.id
    JOIN courses co ON cl.course_id=co.id
    JOIN academic_years ay ON cl.academic_year_id=ay.id
    WHERE en.student_id=?
    ORDER BY ay.start_date, co.name, ex.exam_date");
$grades->execute([$id]); $grades = $grades->fetchAll();

// Group by year > course
$grouped = [];
foreach ($grades as $g) {
    $grouped[$g['year_label']][$g['course_code'].' — '.$g['course_name']][] = $g;
}

// Semester registrations
$semesters = $pdo->prepare("SELECT sr.*, ay.label AS year_label FROM semester_registrations sr JOIN academic_years ay ON sr.academic_year_id=ay.id WHERE sr.student_id=? ORDER BY ay.start_date, sr.semester");
$semesters->execute([$id]); $semesters = $semesters->fetchAll();

// Grade reports (archived per semester)
$reports = $pdo->prepare("SELECT gr.*, ay.label AS year_label FROM grade_reports gr JOIN academic_years ay ON gr.academic_year_id=ay.id WHERE gr.student_id=? AND gr.is_published=1 ORDER BY ay.start_date, gr.semester");
$reports->execute([$id]); $reports = $reports->fetchAll();

// Overall stats
$gpa_data = $pdo->prepare("SELECT AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(g.id) AS total FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id WHERE en.student_id=?");
$gpa_data->execute([$id]); $gpa_data = $gpa_data->fetch();
$overall_pct = round($gpa_data['avg_pct'] ?? 0, 1);
$total_exams = $gpa_data['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transcript — <?= e($student['first_name'].' '.$student['last_name']) ?></title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Segoe UI',sans-serif; font-size:13px; color:#222; background:#fff; padding:30px; }
.header { text-align:center; border-bottom:3px double #4361ee; padding-bottom:18px; margin-bottom:22px; }
.logo-circle { width:72px; height:72px; border-radius:50%; background:linear-gradient(135deg,#4361ee,#7209b7); display:inline-flex; align-items:center; justify-content:center; margin-bottom:10px; }
.school-name { font-size:22px; font-weight:800; color:#4361ee; }
.doc-title { font-size:15px; font-weight:700; color:#333; margin-top:4px; }
.doc-meta { font-size:11px; color:#888; margin-top:4px; }
.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 24px; margin-bottom:20px; background:#f8f9ff; padding:14px; border-radius:8px; font-size:12px; }
.info-grid div { display:flex; gap:8px; }
.info-grid span:first-child { color:#888; min-width:120px; }
.year-block { margin-bottom:24px; page-break-inside:avoid; }
.year-header { background:#4361ee; color:#fff; padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; }
.course-header { font-weight:700; color:#333; margin:10px 0 4px; font-size:12px; padding:4px 8px; background:#f0f2f8; border-radius:4px; display:flex; justify-content:space-between; }
table { width:100%; border-collapse:collapse; margin-bottom:6px; font-size:12px; }
th { background:#f8f9ff; padding:6px 10px; text-align:left; color:#555; font-size:11px; }
td { padding:6px 10px; border-bottom:1px solid #f5f5f5; }
.pass { color:#2dc653; font-weight:700; }
.fail { color:#e63946; font-weight:700; }
.summary-bar { display:flex; gap:20px; background:#f8f9ff; padding:14px 18px; border-radius:10px; margin-bottom:20px; flex-wrap:wrap; }
.summary-item { text-align:center; }
.summary-val { font-size:1.4rem; font-weight:800; color:#4361ee; }
.summary-lbl { font-size:10px; color:#888; text-transform:uppercase; letter-spacing:.05em; }
.semester-badge { background:#fff; color:#4361ee; border-radius:6px; padding:2px 8px; font-size:11px; font-weight:700; }
.report-row { display:flex; gap:16px; background:#f0fff4; border:1px solid #bbf7d0; border-radius:8px; padding:10px 14px; margin-bottom:8px; flex-wrap:wrap; font-size:12px; }
.footer { margin-top:30px; border-top:1px solid #eee; padding-top:14px; display:flex; justify-content:space-between; font-size:11px; color:#aaa; }
.stamp { width:90px; height:90px; border:3px double #4361ee; border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:.25; transform:rotate(-12deg); }
.no-print { margin-bottom:20px; display:flex; gap:10px; align-items:center; }
@media print { .no-print { display:none; } body { padding:15px; } }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()" style="background:#4361ee;color:#fff;border:none;padding:10px 22px;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600">🖨 Print / Save as PDF</button>
  <a href="view.php?id=<?= $id ?>" style="color:#666;text-decoration:none;font-size:13px">← Back to Student</a>
</div>

<!-- Header -->
<div class="header">
  <div class="logo-circle">
    <svg width="38" height="38" viewBox="0 0 24 24" fill="white"><path d="M12 3L1 9L12 15L21 10.09V17H23V9L12 3Z"/><path d="M5 13.18V17.18L12 21L19 17.18V13.18L12 17L5 13.18Z" opacity=".8"/></svg>
  </div>
  <div class="school-name"><?= APP_NAME ?></div>
  <div class="doc-title">Official Academic Transcript</div>
  <div class="doc-meta">Generated: <?= date('F j, Y') ?> · Student ID: <?= e($student['student_code']) ?></div>
</div>

<!-- Student Info -->
<div class="info-grid">
  <div><span>Full Name:</span><strong><?= e($student['first_name'].' '.$student['last_name']) ?></strong></div>
  <div><span>Student Code:</span><strong><?= e($student['student_code']) ?></strong></div>
  <div><span>Date of Birth:</span><?= $student['dob'] ? date('F j, Y', strtotime($student['dob'])) : '—' ?></div>
  <div><span>Gender:</span><?= e($student['gender'] ?? '—') ?></div>
  <div><span>Nationality:</span><?= e($student['nationality'] ?? '—') ?></div>
  <div><span>Country:</span><?= e($student['country'] ?? '—') ?></div>
  <div><span>Email:</span><?= e($student['email'] ?? '—') ?></div>
  <div><span>Phone:</span><?= e($student['phone'] ?? '—') ?></div>
  <div><span>Enrollment Date:</span><?= $student['enrollment_date'] ? date('F j, Y', strtotime($student['enrollment_date'])) : '—' ?></div>
  <div><span>Status:</span><strong><?= e($student['status']) ?></strong></div>
  <?php if ($student['passport_no']): ?><div><span>Passport No:</span><?= e($student['passport_no']) ?></div><?php endif; ?>
  <?php if ($student['visa_type']): ?><div><span>Visa Type:</span><?= e($student['visa_type']) ?></div><?php endif; ?>
</div>

<!-- Overall Summary -->
<div class="summary-bar">
  <div class="summary-item"><div class="summary-val"><?= $overall_pct ?>%</div><div class="summary-lbl">Overall Average</div></div>
  <div class="summary-item"><div class="summary-val"><?= grade_letter($overall_pct) ?></div><div class="summary-lbl">Overall Grade</div></div>
  <div class="summary-item"><div class="summary-val"><?= $total_exams ?></div><div class="summary-lbl">Total Exams</div></div>
  <div class="summary-item"><div class="summary-val"><?= count($grouped) ?></div><div class="summary-lbl">Academic Years</div></div>
  <div class="summary-item"><div class="summary-val"><?= e($student['status']) ?></div><div class="summary-lbl">Student Status</div></div>
</div>

<!-- Published Grade Reports (Semester Summaries) -->
<?php if ($reports): ?>
<div style="margin-bottom:20px">
  <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:#4361ee"><i>📊 Official Semester Grade Reports</i></div>
  <?php foreach ($reports as $r): ?>
  <div class="report-row">
    <div><strong><?= e($r['year_label']) ?></strong> · <?= e($r['semester']) ?></div>
    <div>Subjects: <strong><?= $r['total_subjects'] ?></strong></div>
    <div style="color:#2dc653">Passed: <strong><?= $r['passed_subjects'] ?></strong></div>
    <div style="color:#e63946">Failed: <strong><?= $r['failed_subjects'] ?></strong></div>
    <div>Average: <strong><?= $r['overall_pct'] ?>%</strong></div>
    <div>GPA: <strong><?= $r['gpa'] ?></strong></div>
    <?php if ($r['rank_in_class']): ?><div>Rank: <strong><?= $r['rank_in_class'] ?>/<?= $r['total_in_class'] ?></strong></div><?php endif; ?>
    <div><span style="background:<?= match($r['result']){'Distinction'=>'#2dc653','Merit'=>'#4361ee','Pass'=>'#2dc653','Fail'=>'#e63946',default=>'#888'} ?>;color:#fff;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700"><?= e($r['result']) ?></span></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Detailed Grades by Year -->
<?php foreach ($grouped as $year => $courses): ?>
<div class="year-block">
  <div class="year-header">
    <span>📅 Academic Year: <?= e($year) ?></span>
    <?php
    $year_marks = []; $year_total = 0;
    foreach ($courses as $exams) {
        foreach ($exams as $e2) {
            if ($e2['total_marks'] > 0) $year_marks[] = $e2['marks_obtained']/$e2['total_marks']*100;
        }
    }
    $year_avg = count($year_marks) ? round(array_sum($year_marks)/count($year_marks),1) : 0;
    ?>
    <span class="semester-badge">Year Avg: <?= $year_avg ?>% · <?= grade_letter($year_avg) ?></span>
  </div>

  <?php foreach ($courses as $course_key => $exams): ?>
  <?php
  $course_marks = array_filter(array_map(fn($e2) => $e2['total_marks']>0 ? $e2['marks_obtained']/$e2['total_marks']*100 : null, $exams));
  $course_avg = count($course_marks) ? round(array_sum($course_marks)/count($course_marks),1) : 0;
  $course_pass = $course_avg >= get_pass_pct();
  ?>
  <div class="course-header">
    <span><?= e($course_key) ?></span>
    <span style="color:<?= $course_pass?'#2dc653':'#e63946' ?>">Avg: <?= $course_avg ?>% · <?= grade_letter($course_avg) ?></span>
  </div>
  <table>
    <thead><tr><th>Exam</th><th>Type</th><th>Date</th><th>Marks</th><th>Total</th><th>%</th><th>Grade</th><th>Remarks</th></tr></thead>
    <tbody>
    <?php foreach ($exams as $ex):
      $pct = $ex['total_marks']>0 ? round($ex['marks_obtained']/$ex['total_marks']*100,1) : 0;
      $pass = $pct >= get_pass_pct();
    ?>
    <tr>
      <td style="font-weight:600"><?= e($ex['exam_title']) ?></td>
      <td><?= e($ex['exam_type']) ?></td>
      <td><?= $ex['exam_date'] ? date('M j, Y', strtotime($ex['exam_date'])) : '—' ?></td>
      <td><?= number_format($ex['marks_obtained'],2) ?></td>
      <td><?= $ex['total_marks'] ?></td>
      <td><?= $pct ?>%</td>
      <td class="<?= $pass?'pass':'fail' ?>"><?= e($ex['grade_letter']) ?></td>
      <td style="color:#666"><?= e($ex['remarks'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (!$grouped): ?>
<div style="text-align:center;padding:40px;color:#aaa">
  <div style="font-size:2rem;margin-bottom:10px">📋</div>
  No academic records found for this student.
</div>
<?php endif; ?>

<!-- Footer -->
<div class="footer">
  <div>
    <div style="border-top:1px solid #333;width:200px;padding-top:6px;font-size:11px;color:#555">Authorized Signature</div>
    <div style="font-size:10px;color:#aaa;margin-top:3px"><?= APP_NAME ?> — Registrar's Office</div>
  </div>
  <div style="text-align:center">
    <div class="stamp">
      <div style="font-size:7px;font-weight:800;color:#4361ee;text-align:center;letter-spacing:.04em;text-transform:uppercase;line-height:1.5">OFFICIAL<br>TRANSCRIPT<br><?= date('Y') ?></div>
    </div>
  </div>
  <div style="text-align:right">
    <div><?= APP_NAME ?> — Academic Records</div>
    <div style="margin-top:3px">This is an official document generated by the system.</div>
    <div style="margin-top:3px"><?= date('Y') ?></div>
  </div>
</div>

</body>
</html>
