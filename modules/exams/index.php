<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$page_title='Exams'; $active_page='exams';

// Teachers only see exams for their own classes
if (is_teacher()) {
    $teacher = get_teacher_record($pdo);
    if (!$teacher) { flash('Teacher profile not found.','error'); header('Location: '.BASE_URL.'/dashboard.php'); exit; }
    $exams = $pdo->prepare("SELECT e.*, co.name AS course_name, co.code AS course_code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id WHERE cl.teacher_id=? ORDER BY e.exam_date DESC");
    $exams->execute([$teacher['id']]); $exams = $exams->fetchAll();
} else {
    $exams = $pdo->query("SELECT e.*, co.name AS course_name, co.code AS course_code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id ORDER BY e.exam_date DESC")->fetchAll();
}
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Exams</h1><p><?= count($exams) ?> exam(s)</p></div>
  <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Exam</a>
</div>
<div class="card"><div class="card-body">
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Course</th><th>Teacher</th><th>Date</th><th>Time</th><th>Duration</th><th>Total Marks</th><th>Pass Marks</th><th>Type</th><th>Room</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($exams as $ex): ?>
    <tr>
      <td><strong><?= e($ex['title']) ?></strong></td>
      <td><?= e($ex['course_code']) ?> — <?= e($ex['course_name']) ?></td>
      <td><?= e($ex['teacher']) ?></td>
      <td><?= $ex['exam_date']?date('M j, Y',strtotime($ex['exam_date'])):'—' ?></td>
      <td><?= $ex['start_time']?date('g:i A',strtotime($ex['start_time'])):'—' ?></td>
      <td><?= $ex['duration']?$ex['duration'].' min':'—' ?></td>
      <td><?= $ex['total_marks'] ?></td>
      <td><?= $ex['pass_marks'] ?></td>
      <td><span class="badge badge-info"><?= e($ex['type']) ?></span></td>
      <td><?= e($ex['room']??'—') ?></td>
      <td>
        <a href="../grades/add.php?exam_id=<?= $ex['id'] ?>" class="btn btn-sm btn-success btn-icon" title="Enter Grades"><i class="fas fa-star"></i></a>
        <a href="edit.php?id=<?= $ex['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="fas fa-edit"></i></a>
        <a href="delete.php?id=<?= $ex['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$exams): ?><tr><td colspan="11" style="text-align:center;color:#aaa;padding:30px">No exams yet</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div></div>
<?php require_once '../../includes/footer.php'; ?>