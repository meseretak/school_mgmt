<?php
require_once '../../includes/config.php';
auth_check();
$page_title = 'Courses';
$active_page = 'courses';
$courses = $pdo->query("SELECT c.*, COUNT(DISTINCT cl.id) AS class_count FROM courses c LEFT JOIN classes cl ON c.id=cl.course_id GROUP BY c.id ORDER BY c.created_at DESC")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Courses</h1><p><?= count($courses) ?> course(s)</p></div>
  <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Course</a>
</div>
<div class="card"><div class="card-body">
  <div class="table-wrap"><table>
    <thead><tr><th>Code</th><th>Course Name</th><th>Credits</th><th>Level</th><th>Category</th><th>Classes</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($courses as $c): ?>
    <tr>
      <td><strong><?= e($c['code']) ?></strong></td>
      <td><?= e($c['name']) ?></td>
      <td><?= $c['credits'] ?></td>
      <td><span class="badge badge-info"><?= e($c['level']??'—') ?></span></td>
      <td><?= e($c['category']??'—') ?></td>
      <td><span class="badge badge-primary"><?= $c['class_count'] ?></span></td>
      <td><span class="badge badge-<?= $c['is_active']?'success':'secondary' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span></td>
      <td>
        <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="fas fa-edit"></i></a>
        <a href="delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div></div>
<?php require_once '../../includes/footer.php'; ?>