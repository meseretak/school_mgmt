<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$page_title = 'Attendance'; $active_page = 'attendance';

$class_id = (int)($_GET['class_id'] ?? 0);
$date     = $_GET['date'] ?? date('Y-m-d');

// Filter classes by teacher if role is teacher
$role = $_SESSION['user']['role'];
if ($role === 'teacher') {
    $t = $pdo->prepare("SELECT id FROM teachers WHERE user_id=?");
    $t->execute([$_SESSION['user']['id']]); $t = $t->fetch();
    $tid = $t['id'] ?? 0;
    $classes = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY co.name");
    $classes->execute([$tid]); $classes = $classes->fetchAll();
} else {
    $classes = $pdo->query("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id ORDER BY co.name")->fetchAll();
}

// Load students + enrollment_id in ONE query (no N+1)
$students = [];
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT en.id AS enrollment_id, s.id AS student_id,
               s.first_name, s.last_name, s.student_code,
               COALESCE(a.status,'Present') AS att_status
        FROM enrollments en
        JOIN students s ON en.student_id=s.id
        LEFT JOIN attendance a ON a.enrollment_id=en.id AND a.date=?
        WHERE en.class_id=? AND en.status='Enrolled'
        ORDER BY s.first_name, s.last_name");
    $stmt->execute([$date, $class_id]); $students = $stmt->fetchAll();
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $class_id) {
    csrf_check();
    $att_date = $_POST['date'];
    $statuses = $_POST['status'] ?? [];
    foreach ($statuses as $enrollment_id => $status) {
        $pdo->prepare("INSERT INTO attendance (enrollment_id, date, status)
            VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=?")
            ->execute([$enrollment_id, $att_date, $status, $status]);
    }
    flash('Attendance saved for '.date('M j, Y', strtotime($att_date)));
    header("Location: index.php?class_id=$class_id&date=$att_date"); exit;
}

// Attendance summary for selected class
$summary = [];
if ($class_id) {
    $summary = $pdo->prepare("SELECT a.status, COUNT(*) AS cnt
        FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id
        WHERE en.class_id=? GROUP BY a.status");
    $summary->execute([$class_id]);
    $summary = array_column($summary->fetchAll(), 'cnt', 'status');
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Attendance</h1><p>Mark and view class attendance</p></div>
</div>

<!-- Class & Date selector -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <select name="class_id" onchange="this.form.submit()" required>
      <option value="">— Select Class —</option>
      <?php foreach ($classes as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= $class_id==$cl['id']?'selected':'' ?>>
        <?= e($cl['code'].' — '.$cl['course_name'].' ('.$cl['section'].')') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()" max="<?= date('Y-m-d') ?>">
  </form>
</div></div>

<?php if ($class_id && $summary): ?>
<!-- Summary badges -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <?php $colors=['Present'=>'green','Absent'=>'red','Late'=>'orange','Excused'=>'blue']; ?>
  <?php foreach(['Present','Absent','Late','Excused'] as $s): ?>
  <div class="stat-card">
    <div class="stat-icon <?= $colors[$s] ?>"><i class="fas fa-<?= $s==='Present'?'check':'times' ?>-circle"></i></div>
    <div class="stat-info"><h3><?= $summary[$s] ?? 0 ?></h3><p><?= $s ?></p></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($class_id && $students): ?>
<div class="card"><div class="card-body">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <strong><?= count($students) ?> student(s) — <?= date('l, M j, Y', strtotime($date)) ?></strong>
      <div style="display:flex;gap:8px">
        <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('Present')">All Present</button>
        <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('Absent')">All Absent</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
      </div>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>Student</th><th>Code</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th></tr></thead>
      <tbody>
      <?php foreach ($students as $i => $s): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="avatar" style="width:32px;height:32px;font-size:.75rem"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
            <?= e($s['first_name'].' '.$s['last_name']) ?>
          </div>
        </td>
        <td><?= e($s['student_code']) ?></td>
        <?php foreach(['Present','Absent','Late','Excused'] as $opt): ?>
        <td style="text-align:center">
          <input type="radio" name="status[<?= $s['enrollment_id'] ?>]" value="<?= $opt ?>"
            <?= $s['att_status']===$opt?'checked':'' ?> class="att-radio att-<?= strtolower($opt) ?>">
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="margin-top:16px;text-align:right">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Attendance</button>
    </div>
  </form>
</div></div>
<?php elseif ($class_id): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#aaa">
  <i class="fas fa-users" style="font-size:2rem;margin-bottom:10px"></i>
  <p>No enrolled students in this class.</p>
</div></div>
<?php elseif (!$class_id): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#aaa">
  <i class="fas fa-calendar-check" style="font-size:2rem;margin-bottom:10px"></i>
  <p>Select a class above to mark attendance.</p>
</div></div>
<?php endif; ?>

<script>
function markAll(status) {
  document.querySelectorAll('.att-'+status.toLowerCase()).forEach(r => r.checked = true);
}
</script>
<?php require_once '../../includes/footer.php'; ?>