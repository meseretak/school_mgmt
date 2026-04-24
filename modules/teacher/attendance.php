<?php
require_once '../../includes/config.php';
auth_check(['teacher','admin']);
$page_title = 'Mark Attendance'; $active_page = 'teacher_dashboard';

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$_SESSION['user']['id']]); $teacher = $teacher->fetch();

$class_id = (int)($_GET['class_id'] ?? 0);
$date     = $_GET['date'] ?? date('Y-m-d');
$view     = $_GET['view'] ?? 'mark'; // mark | history

// Teacher's classes only
$my_classes = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY co.name");
$my_classes->execute([$teacher['id']]); $my_classes = $my_classes->fetchAll();

// Verify ownership
if ($class_id && $_SESSION['user']['role'] === 'teacher') {
    $owns = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
    $owns->execute([$class_id, $teacher['id']]);
    if (!$owns->fetch()) { flash('Access denied.','error'); header('Location: dashboard.php'); exit; }
}

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $att_date = $_POST['date'];
    $statuses = $_POST['status'] ?? [];
    $notes    = $_POST['notes'] ?? [];
    foreach ($statuses as $enrollment_id => $status) {
        $pdo->prepare("INSERT INTO attendance (enrollment_id, date, status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status=?")
            ->execute([$enrollment_id, $att_date, $status, $status]);
    }
    flash('Attendance saved for '.date('D, M j, Y', strtotime($att_date)));
    header("Location: attendance.php?class_id=$class_id&date=$att_date"); exit;
}

// Load students with their enrollment_id and today's status — single query, no N+1
$students = [];
if ($class_id) {
    $stmt = $pdo->prepare("
        SELECT en.id AS enrollment_id, s.id AS student_id,
               s.first_name, s.last_name, s.student_code, s.nationality,
               COALESCE(a.status, 'Present') AS att_status
        FROM enrollments en
        JOIN students s ON en.student_id=s.id
        LEFT JOIN attendance a ON a.enrollment_id=en.id AND a.date=?
        WHERE en.class_id=? AND en.status='Enrolled'
        ORDER BY s.first_name, s.last_name");
    $stmt->execute([$date, $class_id]); $students = $stmt->fetchAll();
}

// Attendance history (last 10 dates for this class)
$history_dates = [];
if ($class_id && $view === 'history') {
    $history_dates = $pdo->prepare("SELECT DISTINCT a.date FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id WHERE en.class_id=? ORDER BY a.date DESC LIMIT 10");
    $history_dates->execute([$class_id]); $history_dates = array_column($history_dates->fetchAll(), 'date');
}

// Summary for selected class+date
$summary = [];
if ($class_id && $students) {
    foreach ($students as $s) {
        $summary[$s['att_status']] = ($summary[$s['att_status']] ?? 0) + 1;
    }
}

$class_info = null;
if ($class_id) {
    $ci = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.id=?");
    $ci->execute([$class_id]); $class_info = $ci->fetch();
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Attendance</h1>
    <p><?= $class_info ? e($class_info['code'].' — '.$class_info['course_name'].' (Section '.$class_info['section'].')') : 'Select a class' ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($class_id): ?>
    <a href="?class_id=<?= $class_id ?>&view=mark" class="btn btn-sm <?= $view==='mark'?'btn-primary':'btn-secondary' ?>"><i class="fas fa-edit"></i> Mark</a>
    <a href="?class_id=<?= $class_id ?>&view=history" class="btn btn-sm <?= $view==='history'?'btn-primary':'btn-secondary' ?>"><i class="fas fa-history"></i> History</a>
    <?php endif; ?>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<!-- Class & Date selector -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <select name="class_id" onchange="this.form.submit()">
      <option value="">— Select Your Class —</option>
      <?php foreach ($my_classes as $cl): ?>
      <option value="<?= $cl['id'] ?>" <?= $class_id==$cl['id']?'selected':'' ?>>
        <?= e($cl['code'].' — '.$cl['course_name'].' (Section '.$cl['section'].')') ?>
      </option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
    <input type="hidden" name="view" value="<?= e($view) ?>">
  </form>
</div></div>

<?php if ($class_id && $view === 'mark'): ?>

<!-- Summary -->
<?php if ($summary): ?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <?php foreach(['Present'=>['green','check-circle'],'Absent'=>['red','times-circle'],'Late'=>['orange','clock'],'Excused'=>['blue','user-check']] as $s=>[$col,$ico]): ?>
  <div class="stat-card">
    <div class="stat-icon <?= $col ?>"><i class="fas fa-<?= $ico ?>"></i></div>
    <div class="stat-info"><h3><?= $summary[$s]??0 ?></h3><p><?= $s ?></p></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($students): ?>
<div class="card">
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
        <div>
          <strong><?= count($students) ?> student(s)</strong>
          <span style="color:#888;font-size:.85rem;margin-left:8px"><?= date('l, M j, Y', strtotime($date)) ?></span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('present')"><i class="fas fa-check"></i> All Present</button>
          <button type="button" class="btn btn-sm btn-secondary" onclick="markAll('absent')"><i class="fas fa-times"></i> All Absent</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Attendance</button>
        </div>
      </div>

      <div class="table-wrap"><table>
        <thead>
          <tr>
            <th>#</th><th>Student</th><th>Code</th><th>Nationality</th>
            <th style="color:var(--success)">Present</th>
            <th style="color:var(--danger)">Absent</th>
            <th style="color:var(--warning)">Late</th>
            <th style="color:var(--info)">Excused</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $i => $s): ?>
        <tr id="row-<?= $i ?>">
          <td><?= $i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar" style="width:32px;height:32px;font-size:.75rem"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
              <span style="font-weight:600"><?= e($s['first_name'].' '.$s['last_name']) ?></span>
            </div>
          </td>
          <td><?= e($s['student_code']) ?></td>
          <td style="font-size:.82rem"><?= e($s['nationality']??'—') ?></td>
          <?php foreach(['Present','Absent','Late','Excused'] as $opt): ?>
          <td style="text-align:center">
            <input type="radio" name="status[<?= $s['enrollment_id'] ?>]" value="<?= $opt ?>"
              <?= $s['att_status']===$opt?'checked':'' ?>
              class="att-radio att-<?= strtolower($opt) ?>"
              onchange="highlightRow(<?= $i ?>, '<?= strtolower($opt) ?>')">
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
  </div>
</div>

<?php elseif ($class_id): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#aaa">
  <i class="fas fa-users" style="font-size:2rem;margin-bottom:10px;display:block"></i>
  No enrolled students in this class.
</div></div>
<?php endif; ?>

<?php elseif ($class_id && $view === 'history'): ?>
<!-- Attendance History -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-history" style="color:var(--primary)"></i> Attendance History (Last 10 Sessions)</h2></div>
  <?php if ($history_dates): ?>
  <div class="table-wrap" style="overflow-x:auto"><table>
    <thead>
      <tr>
        <th>Student</th>
        <?php foreach ($history_dates as $hd): ?>
        <th style="font-size:.78rem;white-space:nowrap"><?= date('M j', strtotime($hd)) ?></th>
        <?php endforeach; ?>
        <th>Rate</th>
      </tr>
    </thead>
    <tbody>
    <?php
    // Load all attendance for these dates in one query
    $placeholders = implode(',', array_fill(0, count($history_dates), '?'));
    $hist_data = $pdo->prepare("SELECT en.id AS eid, a.date, a.status FROM enrollments en LEFT JOIN attendance a ON a.enrollment_id=en.id AND a.date IN ($placeholders) WHERE en.class_id=? ORDER BY en.id");
    $hist_data->execute(array_merge($history_dates, [$class_id]));
    $hist_rows = $hist_data->fetchAll();
    // Group by enrollment
    $by_enroll = [];
    foreach ($hist_rows as $hr) { $by_enroll[$hr['eid']][$hr['date']] = $hr['status']; }

    foreach ($students as $s):
      $eid = $s['enrollment_id'];
      $present = 0; $total = count($history_dates);
    ?>
    <tr>
      <td style="font-weight:600;white-space:nowrap"><?= e($s['first_name'].' '.$s['last_name']) ?></td>
      <?php foreach ($history_dates as $hd):
        $st = $by_enroll[$eid][$hd] ?? null;
        $colors = ['Present'=>'#2dc653','Absent'=>'#e63946','Late'=>'#f4a261','Excused'=>'#4cc9f0'];
        $icons  = ['Present'=>'✓','Absent'=>'✗','Late'=>'L','Excused'=>'E'];
        if ($st === 'Present') $present++;
      ?>
      <td style="text-align:center">
        <?php if ($st): ?>
        <span title="<?= $st ?>" style="color:<?= $colors[$st]??'#aaa' ?>;font-weight:700"><?= $icons[$st]??'?' ?></span>
        <?php else: ?><span style="color:#ddd">—</span><?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td>
        <?php $rate = $total > 0 ? round($present/$total*100) : 0; ?>
        <span style="color:<?= $rate<75?'var(--danger)':'var(--success)' ?>;font-weight:700"><?= $rate ?>%</span>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <div class="card-body" style="text-align:center;color:#aaa;padding:30px">No attendance records yet.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$class_id): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:50px;color:#aaa">
  <i class="fas fa-calendar-check" style="font-size:2.5rem;margin-bottom:12px;display:block"></i>
  Select a class above to mark or view attendance.
</div></div>
<?php endif; ?>

<script>
function markAll(status) {
  document.querySelectorAll('.att-'+status).forEach(r => { r.checked = true; });
  document.querySelectorAll('tbody tr').forEach((row, i) => highlightRow(i, status));
}
function highlightRow(i, status) {
  const colors = {present:'rgba(45,198,83,.06)',absent:'rgba(230,57,70,.06)',late:'rgba(244,162,97,.06)',excused:'rgba(76,201,240,.06)'};
  const row = document.getElementById('row-'+i);
  if (row) row.style.background = colors[status] || '';
}
// Highlight on load
document.querySelectorAll('tbody tr').forEach((row, i) => {
  const checked = row.querySelector('input[type=radio]:checked');
  if (checked) highlightRow(i, checked.value.toLowerCase());
});
</script>
<?php require_once '../../includes/footer.php'; ?>