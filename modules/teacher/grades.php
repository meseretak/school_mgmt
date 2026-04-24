<?php
require_once '../../includes/config.php';
auth_check(['teacher','admin']);
$page_title = 'Enter Grades'; $active_page = 'teacher_dashboard';

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$_SESSION['user']['id']]); $teacher = $teacher->fetch();

$class_id = (int)($_GET['class_id'] ?? 0);
$exam_id  = (int)($_GET['exam_id'] ?? 0);

// Verify ownership
if ($class_id && $_SESSION['user']['role'] === 'teacher') {
    $owns = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
    $owns->execute([$class_id, $teacher['id']]);
    if (!$owns->fetch()) { flash('Access denied.','error'); header('Location: dashboard.php'); exit; }
}

$class_info = null;
if ($class_id) {
    $ci = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.id=?");
    $ci->execute([$class_id]); $class_info = $ci->fetch();
}

// Exams for this class
$exams = [];
if ($class_id) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE class_id=? ORDER BY exam_date DESC");
    $stmt->execute([$class_id]); $exams = $stmt->fetchAll();
}

$exam = null;
if ($exam_id) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id=?");
    $stmt->execute([$exam_id]); $exam = $stmt->fetch();
    if (!$class_id && $exam) $class_id = $exam['class_id'];
}

// Save grades
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $eid = (int)$_POST['exam_id'];
    $ex_data = $pdo->prepare("SELECT total_marks, pass_marks, exam_date FROM exams WHERE id=?");
    $ex_data->execute([$eid]); $ex_data = $ex_data->fetch();

    // Validation: exam must have occurred
    if ($ex_data['exam_date'] && strtotime($ex_data['exam_date']) > strtotime('today')) {
        flash('Cannot enter grades — this exam has not taken place yet (scheduled for '.date('M j, Y', strtotime($ex_data['exam_date'])).').', 'error');
        header("Location: grades.php?class_id=$class_id&exam_id=$eid"); exit;
    }

    if (empty($_POST['grades'])) {
        flash('No grades submitted.', 'error');
        header("Location: grades.php?class_id=$class_id&exam_id=$eid"); exit;
    }

    $saved = 0;
    foreach ($_POST['grades'] as $enroll_id => $marks) {
        if ($marks === '' || $marks === null) continue; // skip blank
        $marks = (float)$marks;
        // Validate marks range
        if ($marks < 0 || $marks > $ex_data['total_marks']) {
            flash("Invalid marks for a student — must be between 0 and {$ex_data['total_marks']}.", 'error');
            header("Location: grades.php?class_id=$class_id&exam_id=$eid"); exit;
        }
        $pct    = $ex_data['total_marks'] > 0 ? $marks / $ex_data['total_marks'] * 100 : 0;
        $letter = grade_letter($pct);
        $remarks = trim($_POST['remarks'][$enroll_id] ?? '');
        $pdo->prepare("INSERT INTO grades (enrollment_id,exam_id,marks_obtained,grade_letter,remarks,graded_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE marks_obtained=?,grade_letter=?,remarks=?,graded_by=?")
            ->execute([$enroll_id,$eid,$marks,$letter,$remarks,$_SESSION['user']['id'],
                       $marks,$letter,$remarks,$_SESSION['user']['id']]);
        require_once '../../includes/notify.php';
        notify_grade_entered($pdo, (int)$enroll_id, $eid);
        $saved++;
    }
    log_activity($pdo, 'grades_saved', "Grades saved for exam ID $eid ($saved students)");
    flash("Grades saved for $saved student(s).");
    header("Location: grades.php?class_id=$class_id&exam_id=$eid"); exit;
}

// Students with existing grades for selected exam
$students = [];
if ($exam_id && $class_id) {
    $stmt = $pdo->prepare("
        SELECT en.id AS enrollment_id, s.first_name, s.last_name, s.student_code,
               g.marks_obtained, g.grade_letter, g.remarks
        FROM enrollments en
        JOIN students s ON en.student_id=s.id
        LEFT JOIN grades g ON g.enrollment_id=en.id AND g.exam_id=?
        WHERE en.class_id=? AND en.status='Enrolled'
        ORDER BY s.first_name, s.last_name");
    $stmt->execute([$exam_id, $class_id]); $students = $stmt->fetchAll();
}

// Grade summary for this exam
$grade_summary = [];
if ($exam_id) {
    $gs = $pdo->prepare("SELECT grade_letter, COUNT(*) AS cnt, AVG(marks_obtained) AS avg_marks, MAX(marks_obtained) AS max_marks, MIN(marks_obtained) AS min_marks FROM grades WHERE exam_id=? GROUP BY grade_letter");
    $gs->execute([$exam_id]); $grade_summary = $gs->fetchAll();
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Grades</h1>
    <p><?= $class_info ? e($class_info['code'].' — '.$class_info['course_name']) : '' ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="exams.php?class_id=<?= $class_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Exams</a>
  </div>
</div>

<!-- Exam selector -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <input type="hidden" name="class_id" value="<?= $class_id ?>">
    <select name="exam_id" onchange="this.form.submit()">
      <option value="">— Select Exam to Grade —</option>
      <?php foreach ($exams as $ex): ?>
      <option value="<?= $ex['id'] ?>" <?= $exam_id==$ex['id']?'selected':'' ?>>
        <?= e($ex['title'].' ('.$ex['type'].', '.($ex['exam_date']?date('M j, Y',strtotime($ex['exam_date'])):'No date').')') ?>
        <?php if ($ex['exam_date'] && strtotime($ex['exam_date']) > strtotime('today')): ?> [UPCOMING - not gradeable]<?php endif; ?>
      </option>
      <?php endforeach; ?>
    </select>
  </form>
</div></div>

<?php if ($exam && $students): ?>

<?php if ($exam['exam_date'] && strtotime($exam['exam_date']) > strtotime('today')): ?>
<div class="alert alert-error" style="margin-bottom:16px">
  <i class="fas fa-exclamation-triangle"></i>
  <strong>This exam is scheduled for <?= date('M j, Y', strtotime($exam['exam_date'])) ?> and has not taken place yet.</strong>
  Grades cannot be entered until after the exam date.
</div>
<?php else: ?>

<!-- Exam info bar -->
<div style="background:#f8f9ff;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;gap:24px;flex-wrap:wrap;font-size:.88rem">
  <div><span style="color:#888">Exam:</span> <strong><?= e($exam['title']) ?></strong></div>
  <div><span style="color:#888">Type:</span> <span class="badge badge-info"><?= e($exam['type']) ?></span></div>
  <div><span style="color:#888">Total Marks:</span> <strong><?= $exam['total_marks'] ?></strong></div>
  <div><span style="color:#888">Pass Marks:</span> <strong><?= $exam['pass_marks'] ?></strong></div>
  <?php if ($exam['exam_date']): ?><div><span style="color:#888">Date:</span> <strong><?= date('M j, Y', strtotime($exam['exam_date'])) ?></strong></div><?php endif; ?>
</div>

<!-- Grade summary if already graded -->
<?php if ($grade_summary): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2><i class="fas fa-chart-bar" style="color:var(--success)"></i> Grade Summary</h2></div>
  <div class="card-body" style="display:flex;gap:16px;flex-wrap:wrap">
    <?php
    $all_marks = $pdo->prepare("SELECT marks_obtained FROM grades WHERE exam_id=?");
    $all_marks->execute([$exam_id]); $all_marks = array_column($all_marks->fetchAll(),'marks_obtained');
    $avg = count($all_marks) ? round(array_sum($all_marks)/count($all_marks),1) : 0;
    $pass = count(array_filter($all_marks, fn($m) => $m >= $exam['pass_marks']));
    ?>
    <div style="background:#f0f8f0;border-radius:10px;padding:14px 20px;text-align:center;min-width:100px">
      <div style="font-size:1.4rem;font-weight:800;color:var(--success)"><?= $avg ?></div>
      <div style="font-size:.78rem;color:#888">Class Average</div>
    </div>
    <div style="background:#f0f4ff;border-radius:10px;padding:14px 20px;text-align:center;min-width:100px">
      <div style="font-size:1.4rem;font-weight:800;color:var(--primary)"><?= count($all_marks) > 0 ? round($pass/count($all_marks)*100) : 0 ?>%</div>
      <div style="font-size:.78rem;color:#888">Pass Rate</div>
    </div>
    <div style="background:#fff8f0;border-radius:10px;padding:14px 20px;text-align:center;min-width:100px">
      <div style="font-size:1.4rem;font-weight:800;color:var(--warning)"><?= $all_marks ? max($all_marks) : '—' ?></div>
      <div style="font-size:.78rem;color:#888">Highest</div>
    </div>
    <div style="background:#fff0f0;border-radius:10px;padding:14px 20px;text-align:center;min-width:100px">
      <div style="font-size:1.4rem;font-weight:800;color:var(--danger)"><?= $all_marks ? min($all_marks) : '—' ?></div>
      <div style="font-size:.78rem;color:#888">Lowest</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Grade entry form -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-edit" style="color:var(--primary)"></i> Enter / Update Grades</h2>
    <span style="font-size:.85rem;color:#888"><?= count($students) ?> students</span>
  </div>
  <div class="card-body">
    <form method="POST" id="gradeForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
      <div class="table-wrap"><table>
        <thead>
          <tr>
            <th>#</th><th>Student</th><th>Code</th>
            <th>Marks <small style="font-weight:400;color:#aaa">(out of <?= $exam['total_marks'] ?>)</small></th>
            <th>%</th><th>Grade</th><th>Remarks</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $i => $s):
          $pct = ($s['marks_obtained'] !== null && $exam['total_marks'] > 0) ? round($s['marks_obtained']/$exam['total_marks']*100,1) : null;
        ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar" style="width:30px;height:30px;font-size:.72rem"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
              <span style="font-weight:600"><?= e($s['first_name'].' '.$s['last_name']) ?></span>
            </div>
          </td>
          <td style="font-size:.82rem;color:#888"><?= e($s['student_code']??'—') ?></td>
          <td>
            <input type="number" step="0.5" min="0" max="<?= $exam['total_marks'] ?>"
              name="grades[<?= $s['enrollment_id'] ?>]"
              value="<?= $s['marks_obtained']??'' ?>"
              style="width:90px"
              oninput="calcGrade(this, <?= $exam['total_marks'] ?>, <?= $i ?>)"
              placeholder="0–<?= $exam['total_marks'] ?>">
          </td>
          <td id="pct-<?= $i ?>" style="font-weight:600;color:#888"><?= $pct !== null ? $pct.'%' : '—' ?></td>
          <td id="grade-<?= $i ?>" style="font-weight:700;color:<?= $s['grade_letter']&&$s['grade_letter']!=='F'?'var(--success)':'var(--danger)' ?>">
            <?= e($s['grade_letter']??'—') ?>
          </td>
          <td><input name="remarks[<?= $s['enrollment_id'] ?>]" value="<?= e($s['remarks']??'') ?>" placeholder="Optional" style="width:160px"></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div style="margin-top:20px;display:flex;gap:12px">
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save All Grades</button>
        <a href="exams.php?class_id=<?= $class_id ?>" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php endif; // end upcoming exam check ?>
<?php elseif ($class_id && !$exam_id): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#aaa">
  <i class="fas fa-star" style="font-size:2rem;margin-bottom:10px;display:block"></i>
  Select an exam above to enter grades.
</div></div>
<?php endif; ?>

<script>
const gradeScale = [
  [90,'A+'],[85,'A'],[80,'A-'],[75,'B+'],[70,'B'],[65,'B-'],[60,'C+'],[55,'C'],[50,'D'],[0,'F']
];
const passColors = {'A+':'var(--success)','A':'var(--success)','A-':'var(--success)','B+':'var(--primary)','B':'var(--primary)','B-':'var(--primary)','C+':'var(--warning)','C':'var(--warning)','D':'var(--warning)','F':'var(--danger)'};

function calcGrade(input, total, i) {
  const marks = parseFloat(input.value);
  const pctEl = document.getElementById('pct-'+i);
  const gradeEl = document.getElementById('grade-'+i);
  if (isNaN(marks) || input.value === '') { pctEl.textContent='—'; gradeEl.textContent='—'; return; }
  const pct = total > 0 ? Math.round(marks/total*1000)/10 : 0;
  pctEl.textContent = pct+'%';
  let letter = 'F';
  for (const [threshold, g] of gradeScale) { if (pct >= threshold) { letter = g; break; } }
  gradeEl.textContent = letter;
  gradeEl.style.color = passColors[letter] || '#333';
}
</script>
<?php require_once '../../includes/footer.php'; ?>