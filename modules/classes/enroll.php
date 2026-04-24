<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$id = (int)($_GET['id'] ?? 0);

$class = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code AS course_code, CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id WHERE cl.id=?");
$class->execute([$id]); $class = $class->fetch();
if (!$class) { flash('Class not found', 'error'); header('Location: index.php'); exit; }

if (is_teacher()) {
    $teacher = get_teacher_record($pdo);
    if (!$teacher || !teacher_owns_class($pdo, $id, $teacher['id'])) deny();
}

// Enroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enroll') {
    csrf_check();
    $tuition_fee = $pdo->query("SELECT * FROM fee_types WHERE name='Tuition Fee' AND is_active=1 LIMIT 1")->fetch();
    $ay = $pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn();
    $enrolled_count = 0;
    foreach ($_POST['student_ids'] ?? [] as $sid) {
        $sid = (int)$sid;
        try {
            $pdo->prepare("INSERT INTO enrollments (student_id,class_id) VALUES (?,?)")->execute([$sid, $id]);
            $enrolled_count++;
            if ($tuition_fee) {
                $has = $pdo->prepare("SELECT id FROM payments WHERE student_id=? AND fee_type_id=? AND academic_year_id=?");
                $has->execute([$sid, $tuition_fee['id'], $ay ?? 0]);
                if (!$has->fetch()) {
                    $pdo->prepare("INSERT INTO payments (student_id,fee_type_id,academic_year_id,amount_due,due_date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$sid,$tuition_fee['id'],$ay??null,$tuition_fee['amount'],date('Y-m-d',strtotime('+30 days')),'Pending','Auto-generated on enrollment: '.$class['course_name'],$_SESSION['user']['id']]);
                }
            }
        } catch (Exception $e) {}
    }
    flash($enrolled_count . ' student(s) enrolled.');
    header("Location: enroll.php?id=$id"); exit;
}

// Unenroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unenroll') {
    csrf_check();
    $sid = (int)$_POST['student_id'];
    $pdo->prepare("DELETE FROM enrollments WHERE student_id=? AND class_id=?")->execute([$sid, $id]);
    flash('Student removed from class.');
    header("Location: enroll.php?id=$id"); exit;
}

// Currently enrolled
$enrolled = $pdo->prepare("SELECT en.id AS enrollment_id, s.*, en.enrolled_at, en.status AS enroll_status FROM enrollments en JOIN students s ON en.student_id=s.id WHERE en.class_id=? ORDER BY s.first_name");
$enrolled->execute([$id]); $enrolled = $enrolled->fetchAll();
$enrolled_ids = array_column($enrolled, 'id');

// Available students (not yet enrolled)
$available = $pdo->prepare("SELECT * FROM students WHERE status='Active' AND id NOT IN (SELECT student_id FROM enrollments WHERE class_id=?) ORDER BY first_name, last_name");
$available->execute([$id]); $available = $available->fetchAll();

$page_title = 'Manage Enrollment'; $active_page = 'classes';
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-user-plus" style="color:var(--primary)"></i> Manage Enrollment</h1>
    <p style="color:#888"><?= e($class['course_code'].' — '.$class['course_name']) ?> &nbsp;|&nbsp; Teacher: <strong><?= e($class['teacher_name']) ?></strong></p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Classes</a>
</div>

<!-- Currently Enrolled -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header" style="justify-content:space-between">
    <h2><i class="fas fa-users" style="color:var(--success)"></i> Enrolled Students (<?= count($enrolled) ?>)</h2>
    <input type="text" id="enrolledSearch" placeholder="Search enrolled..." style="max-width:220px" oninput="filterTable('enrolledTable', this.value)">
  </div>
  <div class="table-wrap">
    <table id="enrolledTable">
      <thead><tr><th>Name</th><th>Student ID</th><th>Nationality</th><th>Enrolled At</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($enrolled as $s): ?>
      <tr data-name="<?= strtolower(e($s['first_name'].' '.$s['last_name'])) ?>" data-code="<?= strtolower(e($s['student_code']??'')) ?>">
        <td style="font-weight:500"><?= e($s['first_name'].' '.$s['last_name']) ?></td>
        <td style="font-family:monospace;font-size:.85rem"><?= e($s['student_code'] ?? '—') ?></td>
        <td style="font-size:.85rem;color:#888"><?= e($s['nationality'] ?? '—') ?></td>
        <td style="font-size:.82rem"><?= date('M j, Y', strtotime($s['enrolled_at'])) ?></td>
        <td><span class="badge badge-success"><?= e($s['enroll_status']) ?></span></td>
        <td>
          <?php if (is_admin()): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove this student from the class?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="unenroll">
            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
            <button class="btn btn-sm btn-danger"><i class="fas fa-user-minus"></i> Remove</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$enrolled): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No students enrolled yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add More Students -->
<?php if ($available): ?>
<div class="card">
  <div class="card-header" style="justify-content:space-between">
    <h2><i class="fas fa-user-plus" style="color:var(--primary)"></i> Add Students (<?= count($available) ?> available)</h2>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="availSearch" placeholder="Search..." style="max-width:220px" oninput="filterTable('availTable', this.value)">
      <label style="font-size:.85rem;cursor:pointer;display:flex;align-items:center;gap:6px">
        <input type="checkbox" id="checkAll" onchange="toggleAll(this)"> Select All
      </label>
    </div>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="enroll">
    <div class="table-wrap">
      <table id="availTable">
        <thead><tr><th style="width:40px"></th><th>Name</th><th>Student ID</th><th>Nationality</th></tr></thead>
        <tbody>
        <?php foreach ($available as $s): ?>
        <tr data-name="<?= strtolower(e($s['first_name'].' '.$s['last_name'])) ?>" data-code="<?= strtolower(e($s['student_code']??'')) ?>">
          <td><input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-cb" onchange="updateCount()"></td>
          <td style="font-weight:500"><?= e($s['first_name'].' '.$s['last_name']) ?></td>
          <td style="font-family:monospace;font-size:.85rem"><?= e($s['student_code'] ?? '—') ?></td>
          <td style="font-size:.85rem;color:#888"><?= e($s['nationality'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:16px;display:flex;gap:12px;align-items:center">
      <button type="submit" class="btn btn-success"><i class="fas fa-user-plus"></i> Enroll Selected (<span id="selCount">0</span>)</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;color:#aaa;padding:30px">All active students are already enrolled in this class.</div></div>
<?php endif; ?>

<script>
function filterTable(tableId, q) {
    q = q.toLowerCase();
    document.querySelectorAll('#'+tableId+' tbody tr').forEach(r => {
        r.style.display = (r.dataset.name||'').includes(q) || (r.dataset.code||'').includes(q) ? '' : 'none';
    });
}
function toggleAll(cb) {
    document.querySelectorAll('.student-cb').forEach(c => { if (c.closest('tr').style.display !== 'none') c.checked = cb.checked; });
    updateCount();
}
function updateCount() {
    document.getElementById('selCount').textContent = document.querySelectorAll('.student-cb:checked').length;
}
</script>
<?php require_once '../../includes/footer.php'; ?>