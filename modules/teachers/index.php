<?php
require_once '../../includes/config.php';
// Admin and teachers can view teachers list
auth_check(['admin','teacher']);
$page_title = 'Teachers';
$active_page = 'teachers';
$search = trim($_GET['search'] ?? '');
$sql = "SELECT t.*, COUNT(DISTINCT cl.id) AS class_count FROM teachers t LEFT JOIN classes cl ON t.id=cl.teacher_id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.teacher_code LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
$sql .= " GROUP BY t.id ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$teachers = $stmt->fetchAll();
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Teachers</h1><p><?= count($teachers) ?> teacher(s)</p></div>
  <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Teacher</a>
</div>
<div class="card"><div class="card-body">
  <form method="GET" class="search-bar">
    <input name="search" placeholder="Search name or code..." value="<?= e($search) ?>">
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Teacher</th><th>Code</th><th>Specialization</th><th>Phone</th><th>Classes</th><th>Hired</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($teachers as $t): ?>
    <tr>
      <td><div style="display:flex;align-items:center;gap:10px;">
        <div class="avatar" style="background:var(--secondary)"><?= strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1)) ?></div>
        <strong><?= e($t['first_name'].' '.$t['last_name']) ?></strong>
      </div></td>
      <td><?= e($t['teacher_code']??'—') ?></td>
      <td><?= e($t['specialization']??'—') ?></td>
      <td><?= e($t['phone']??'—') ?></td>
      <td><span class="badge badge-primary"><?= $t['class_count'] ?></span></td>
      <td><?= $t['hire_date']?date('M j, Y',strtotime($t['hire_date'])):'—' ?></td>
      <td><span class="badge badge-<?= $t['status']==='Active'?'success':'danger' ?>"><?= e($t['status']) ?></span></td>
      <td>
        <a href="edit.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="fas fa-edit"></i></a>
        <a href="delete.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$teachers): ?><tr><td colspan="8" style="text-align:center;color:#aaa;padding:30px">No teachers found</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div></div>
<?php require_once '../../includes/footer.php'; ?>