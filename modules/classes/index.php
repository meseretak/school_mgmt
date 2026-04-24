<?php
require_once '../../includes/config.php';
auth_check();
$page_title='Classes'; $active_page='classes';
$classes = $pdo->query("SELECT cl.*, co.name AS course_name, co.code AS course_code, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, ay.label AS year_label, COUNT(DISTINCT e.id) AS enrolled FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id JOIN academic_years ay ON cl.academic_year_id=ay.id LEFT JOIN enrollments e ON cl.id=e.class_id AND e.status='Enrolled' GROUP BY cl.id ORDER BY cl.created_at DESC")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Classes</h1><p><?= count($classes) ?> class(es)</p></div>
  <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Class</a>
</div>
<div class="card"><div class="card-body">
  <div class="table-wrap"><table>
    <thead><tr><th>Course</th><th>Section</th><th>Teacher</th><th>Year</th><th>Room</th><th>Schedule</th><th>Enrolled</th><th>Max</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($classes as $cl): ?>
    <tr>
      <td><strong><?= e($cl['course_code']) ?></strong><br><small style="color:#888"><?= e($cl['course_name']) ?></small></td>
      <td><?= e($cl['section']??'—') ?></td>
      <td><?= e($cl['teacher_name']) ?></td>
      <td><?= e($cl['year_label']) ?></td>
      <td><?= e($cl['room']??'—') ?></td>
      <td><?= e($cl['schedule']??'—') ?></td>
      <td><?= $cl['enrolled'] ?></td>
      <td><?= $cl['max_students'] ?></td>
      <td><span class="badge badge-<?= $cl['status']==='Open'?'success':($cl['status']==='Completed'?'primary':'secondary') ?>"><?= e($cl['status']) ?></span></td>
      <td>
        <a href="enroll.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-success btn-icon" title="Enroll Students"><i class="fas fa-user-plus"></i></a>
        <a href="edit.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="fas fa-edit"></i></a>
        <a href="delete.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$classes): ?><tr><td colspan="10" style="text-align:center;color:#aaa;padding:30px">No classes yet</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div></div>
<?php require_once '../../includes/footer.php'; ?>