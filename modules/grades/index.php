<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$page_title='Grades'; $active_page='grades';
$class_id = (int)($_GET['class_id']??0);

// Teachers only see their own classes
if (is_teacher()) {
    $teacher = get_teacher_record($pdo);
    if (!$teacher) deny();
    if ($class_id && !teacher_owns_class($pdo, $class_id, $teacher['id'])) deny();
    $classes = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY co.name");
    $classes->execute([$teacher['id']]); $classes = $classes->fetchAll();
} else {
    $classes = $pdo->query("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id ORDER BY co.name")->fetchAll();
}

$grades = [];
if ($class_id) {
    $grades = $pdo->prepare("SELECT g.*, ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN students s ON en.student_id=s.id JOIN exams ex ON g.exam_id=ex.id WHERE en.class_id=? ORDER BY s.first_name, ex.exam_date");
    $grades->execute([$class_id]); $grades = $grades->fetchAll();
}
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Grades</h1></div>
  <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Enter Grades</a>
</div>
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <select name="class_id" onchange="this.form.submit()">
      <option value="">— Select Class to View Grades —</option>
      <?php foreach($classes as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= $class_id==$cl['id']?'selected':'' ?>><?= e($cl['code'].' — '.$cl['course_name'].' ('.$cl['section'].')') ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div></div>

<?php if ($class_id && $grades): ?>
<div class="card"><div class="card-body">
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Code</th><th>Exam</th><th>Type</th><th>Marks</th><th>Total</th><th>%</th><th>Grade</th><th>Remarks</th></tr></thead>
    <tbody>
    <?php foreach ($grades as $g):
      $pct = $g['total_marks']>0 ? round($g['marks_obtained']/$g['total_marks']*100,1) : 0;
    ?>
    <tr>
      <td><?= e($g['student_name']) ?></td>
      <td><?= e($g['student_code']??'—') ?></td>
      <td><?= e($g['exam_title']) ?></td>
      <td><span class="badge badge-info"><?= e($g['exam_type']) ?></span></td>
      <td><?= $g['marks_obtained'] ?></td>
      <td><?= $g['total_marks'] ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px;">
          <?= $pct ?>%
          <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=50?'var(--success)':'var(--danger)' ?>"></div></div>
        </div>
      </td>
      <td><strong style="color:<?= $pct>=50?'var(--success)':'var(--danger)' ?>"><?= e($g['grade_letter']) ?></strong></td>
      <td><?= e($g['remarks']??'—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div></div>
<?php elseif ($class_id): ?>
<div class="card"><div class="card-body" style="text-align:center;color:#aaa;padding:40px">No grades recorded for this class yet. <a href="add.php?class_id=<?= $class_id ?>">Enter grades</a></div></div>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>