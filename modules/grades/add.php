<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$page_title='Enter Grades'; $active_page='grades';
$exam_id = (int)($_GET['exam_id']??0);

// Teachers only see their own exams
if (is_teacher()) {
    $teacher = get_teacher_record($pdo);
    if (!$teacher) deny();
    $exams = $pdo->prepare("SELECT e.id, e.title, e.total_marks, e.pass_marks, co.name AS course_name FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY e.exam_date DESC");
    $exams->execute([$teacher['id']]); $exams = $exams->fetchAll();
} else {
    $exams = $pdo->query("SELECT e.id, e.title, e.total_marks, e.pass_marks, co.name AS course_name FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id ORDER BY e.exam_date DESC")->fetchAll();
}

$students = [];
$exam = null;
if ($exam_id) {
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id=?"); $stmt->execute([$exam_id]); $exam = $stmt->fetch();
    if ($exam) {
        // Verify teacher owns this class
        if (is_teacher() && !teacher_owns_class($pdo, $exam['class_id'], $teacher['id'])) deny();
        $students = $pdo->prepare("SELECT en.id AS enrollment_id, s.first_name, s.last_name, s.student_code, g.marks_obtained, g.grade_letter, g.remarks FROM enrollments en JOIN students s ON en.student_id=s.id LEFT JOIN grades g ON g.enrollment_id=en.id AND g.exam_id=? WHERE en.class_id=? AND en.status='Enrolled' ORDER BY s.first_name");
        $students->execute([$exam_id, $exam['class_id']]); $students = $students->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['grades'])) {
    $eid = (int)$_POST['exam_id'];
    $stmt_ex = $pdo->prepare("SELECT total_marks, exam_date FROM exams WHERE id=?");
    $stmt_ex->execute([$eid]); $ex = $stmt_ex->fetch();

    // Exam must have taken place
    if ($ex['exam_date'] && strtotime($ex['exam_date']) > strtotime('today')) {
        flash('Cannot enter grades — exam is scheduled for '.date('M j, Y', strtotime($ex['exam_date'])).' and has not taken place yet.', 'error');
        header('Location: add.php?exam_id='.$eid); exit;
    }

    $saved = 0;
    foreach ($_POST['grades'] as $enroll_id => $marks) {
        if ($marks === '' || $marks === null) continue;
        $marks = (float)$marks;
        if ($marks < 0 || $marks > $ex['total_marks']) {
            flash("Marks must be between 0 and {$ex['total_marks']}.", 'error');
            header('Location: add.php?exam_id='.$eid); exit;
        }
        $pct = $ex['total_marks']>0 ? $marks/$ex['total_marks']*100 : 0;
        $letter = grade_letter($pct);
        $remarks = $_POST['remarks'][$enroll_id] ?? null;
        $pdo->prepare("INSERT INTO grades (enrollment_id,exam_id,marks_obtained,grade_letter,remarks,graded_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE marks_obtained=?,grade_letter=?,remarks=?,graded_by=?")
            ->execute([$enroll_id,$eid,$marks,$letter,$remarks,$_SESSION['user']['id'],$marks,$letter,$remarks,$_SESSION['user']['id']]);
        $saved++;
    }
    flash("Grades saved for $saved student(s).");
    header('Location: index.php?class_id='.($exam['class_id']??0)); exit;
}
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Enter Grades</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>

<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <select name="exam_id" onchange="this.form.submit()">
      <option value="">— Select Exam —</option>
      <?php foreach($exams as $ex): ?>
      <option value="<?= $ex['id'] ?>" <?= $exam_id==$ex['id']?'selected':'' ?>><?= e($ex['title'].' ('.$ex['course_name'].')') ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div></div>

<?php if ($exam && $students): ?>
<div class="card"><div class="card-header">
  <h2><?= e($exam['title']) ?></h2>
  <span>Total: <?= $exam['total_marks'] ?> | Pass: <?= $exam['pass_marks'] ?></span>
</div><div class="card-body">
  <form method="POST">
    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
    <div class="table-wrap"><table>
      <thead><tr><th>Student</th><th>Code</th><th>Marks (out of <?= $exam['total_marks'] ?>)</th><th>Grade</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($students as $s): ?>
      <tr>
        <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
        <td><?= e($s['student_code']??'—') ?></td>
        <td><input type="number" step="0.01" min="0" max="<?= $exam['total_marks'] ?>" name="grades[<?= $s['enrollment_id'] ?>]" value="<?= $s['marks_obtained']??'' ?>" style="width:100px" required></td>
        <td><strong><?= e($s['grade_letter']??'—') ?></strong></td>
        <td><input name="remarks[<?= $s['enrollment_id'] ?>]" value="<?= e($s['remarks']??'') ?>" placeholder="Optional remarks" style="width:180px"></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="margin-top:20px;display:flex;gap:12px;">
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save All Grades</button>
    </div>
  </form>
</div></div>
<?php elseif ($exam_id): ?>
<div class="card"><div class="card-body" style="text-align:center;color:#aaa;padding:40px">No enrolled students found for this exam.</div></div>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>