<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$page_title = 'Students';
$active_page = 'students';

$search    = trim($_GET['search'] ?? '');
$status    = $_GET['status'] ?? '';
$country   = $_GET['country'] ?? '';
$gender    = $_GET['gender'] ?? '';
$year_id   = (int)($_GET['year_id'] ?? 0);

$sql = "SELECT s.*, c.name AS country_name, u.email FROM students s LEFT JOIN countries c ON s.country_id=c.id LEFT JOIN users u ON s.user_id=u.id WHERE 1=1";
$params = [];
if ($search)  { $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR s.phone LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($status)  { $sql .= " AND s.status=?";     $params[] = $status; }
if ($country) { $sql .= " AND s.country_id=?"; $params[] = $country; }
if ($gender)  { $sql .= " AND s.gender=?";     $params[] = $gender; }
if ($year_id) { $sql .= " AND s.id IN (SELECT DISTINCT en.student_id FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE cl.academic_year_id=?)"; $params[] = $year_id; }
$sql .= " ORDER BY s.student_code ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();
$countries = $pdo->query("SELECT * FROM countries ORDER BY name")->fetchAll();
$years     = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-user-graduate" style="color:var(--primary)"></i> Students</h1><p><?= count($students) ?> student(s) found</p></div>
  <div style="display:flex;gap:8px">
    <a href="lookup.php" class="btn btn-secondary"><i class="fas fa-id-card"></i> ID Lookup</a>
    <?php if (is_admin()): ?><a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Student</a><?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <form method="GET" class="search-bar" style="flex-wrap:wrap">
      <input type="text" name="search" placeholder="Search name, ID, phone..." value="<?= e($search) ?>" style="min-width:200px">
      <select name="status">
        <option value="">All Status</option>
        <?php foreach (['Active','Inactive','Graduated','Suspended'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <select name="gender">
        <option value="">All Genders</option>
        <?php foreach (['Male','Female','Other'] as $g): ?>
        <option value="<?= $g ?>" <?= $gender===$g?'selected':'' ?>><?= $g ?></option>
        <?php endforeach; ?>
      </select>
      <select name="country">
        <option value="">All Countries</option>
        <?php foreach ($countries as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $country==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="year_id">
        <option value="">All Years</option>
        <?php foreach ($years as $y): ?>
        <option value="<?= $y['id'] ?>" <?= $year_id==$y['id']?'selected':'' ?>><?= e($y['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
      <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Code</th><th>Email</th><th>Gender</th><th>Nationality</th><th>Visa</th><th>Phone</th><th>Enrolled</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="avatar"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
              <div>
                <div style="font-weight:600"><?= e($s['first_name'].' '.$s['last_name']) ?></div>
                <div style="font-size:.78rem;color:#888"><?= e($s['country_name'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td><?= e($s['student_code'] ?? '—') ?></td>
          <td style="font-size:.82rem"><?= e($s['email'] ?? '—') ?></td>
          <td><?= e($s['gender'] ?? '—') ?></td>
          <td><?= e($s['nationality'] ?? '—') ?></td>
          <td><?= $s['visa_expiry'] ? '<span class="badge badge-info">'.e($s['visa_type']).'</span>' : '—' ?></td>
          <td><?= e($s['phone'] ?? '—') ?></td>
          <td><?= $s['enrollment_date'] ? date('M j, Y', strtotime($s['enrollment_date'])) : '—' ?></td>
          <td><span class="badge badge-<?= $s['status']==='Active'?'success':($s['status']==='Graduated'?'primary':'danger') ?>"><?= e($s['status']) ?></span></td>
          <td>
            <a href="view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="View"><i class="fas fa-eye"></i></a>
            <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary btn-icon" title="Edit"><i class="fas fa-edit"></i></a>
            <a href="delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete" title="Delete"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?><tr><td colspan="10" style="text-align:center;color:#aaa;padding:30px">No students found</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>