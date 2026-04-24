<?php
require_once '../../includes/config.php';
auth_check(['teacher','admin']);
$page_title = 'Exams'; $active_page = 'teacher_dashboard';

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$_SESSION['user']['id']]); $teacher = $teacher->fetch();

$class_id = (int)($_GET['class_id'] ?? 0);

// Verify ownership
if ($class_id && $_SESSION['user']['role'] === 'teacher') {
    $owns = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
    $owns->execute([$class_id, $teacher['id']]);
    if (!$owns->fetch()) { flash('Access denied.','error'); header('Location: dashboard.php'); exit; }
}

$class_info = null;
if ($class_id) {
    $ci = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.id=?");
    $ci->execute([$class_id]); $class_info = $ci->fetch();
}

// Add exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'add_exam') {
    csrf_check();
    $d = $_POST;
    $pdo->prepare("INSERT INTO exams (class_id,title,exam_date,start_time,duration,total_marks,pass_marks,type,room) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$class_id, $d['title'], $d['exam_date']??null, $d['start_time']??null, $d['duration']??null, $d['total_marks']??100, $d['pass_marks']??50, $d['type']??'Midterm', $d['room']??null]);
    flash('Exam added successfully.');
    header("Location: exams.php?class_id=$class_id"); exit;
}

// Delete exam
if (isset($_GET['delete'])) {
    $eid = (int)$_GET['delete'];
    // Verify exam belongs to teacher's class
    $check = $pdo->prepare("SELECT e.id FROM exams e JOIN classes cl ON e.class_id=cl.id WHERE e.id=? AND cl.teacher_id=?");
    $check->execute([$eid, $teacher['id']]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM grades WHERE exam_id=?")->execute([$eid]);
        $pdo->prepare("DELETE FROM exams WHERE id=?")->execute([$eid]);
        flash('Exam deleted.');
    }
    header("Location: exams.php?class_id=$class_id"); exit;
}

$exams = [];
if ($class_id) {
    $stmt = $pdo->prepare("SELECT e.*, COUNT(DISTINCT g.id) AS graded, COUNT(DISTINCT en.id) AS total_students FROM exams e LEFT JOIN grades g ON g.exam_id=e.id LEFT JOIN enrollments en ON en.class_id=e.class_id AND en.status='Enrolled' WHERE e.class_id=? GROUP BY e.id ORDER BY e.exam_date DESC");
    $stmt->execute([$class_id]); $exams = $stmt->fetchAll();
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1>Exams</h1>
    <p><?= $class_info ? e($class_info['code'].' — '.$class_info['course_name']) : '' ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="grades.php?class_id=<?= $class_id ?>" class="btn btn-secondary"><i class="fas fa-star"></i> Grades</a>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<!-- Add Exam Form -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-plus" style="color:var(--primary)"></i> Add New Exam</h2></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="add_exam">
      <div class="form-grid">
        <div class="form-group full"><label>Exam Title *</label><input name="title" required placeholder="e.g. Chapter 1-3 Quiz"></div>
        <div class="form-group"><label>Type</label>
          <select name="type">
            <?php foreach(['Quiz','Midterm','Final','Assignment','Project','Lab','Presentation'] as $t): ?>
            <option><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Exam Date</label><input type="date" name="exam_date"></div>
        <div class="form-group"><label>Start Time</label><input type="time" name="start_time"></div>
        <div class="form-group"><label>Duration (min)</label><input type="number" name="duration" value="90" min="5"></div>
        <div class="form-group"><label>Total Marks</label><input type="number" step="0.01" name="total_marks" value="100" min="1"></div>
        <div class="form-group"><label>Pass Marks</label><input type="number" step="0.01" name="pass_marks" value="50" min="0"></div>
        <div class="form-group"><label>Room / Location</label><input name="room" placeholder="e.g. Room 201"></div>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Exam</button>
      </div>
    </form>
  </div>
</div>

<!-- Exams List -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--warning)"></i> Exam List (<?= count($exams) ?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Title</th><th>Type</th><th>Date</th><th>Time</th><th>Duration</th><th>Total</th><th>Pass</th><th>Room</th><th>Graded</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($exams as $ex): ?>
    <tr>
      <td style="font-weight:600"><?= e($ex['title']) ?></td>
      <td><span class="badge badge-info"><?= e($ex['type']) ?></span></td>
      <td><?= $ex['exam_date']?date('M j, Y',strtotime($ex['exam_date'])):'—' ?></td>
      <td><?= $ex['start_time']?date('g:i A',strtotime($ex['start_time'])):'—' ?></td>
      <td><?= $ex['duration']?$ex['duration'].' min':'—' ?></td>
      <td><?= $ex['total_marks'] ?></td>
      <td><?= $ex['pass_marks'] ?></td>
      <td><?= e($ex['room']??'—') ?></td>
      <td>
        <span style="color:<?= $ex['graded']>=$ex['total_students']&&$ex['total_students']>0?'var(--success)':'var(--warning)' ?>">
          <?= $ex['graded'] ?>/<?= $ex['total_students'] ?>
        </span>
      </td>
      <td>
        <div style="display:flex;gap:6px">
          <a href="grades.php?class_id=<?= $class_id ?>&exam_id=<?= $ex['id'] ?>" class="btn btn-sm btn-primary" title="Enter Grades"><i class="fas fa-star"></i> Grade</a>
          <a href="?class_id=<?= $class_id ?>&delete=<?= $ex['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this exam and all its grades?')"><i class="fas fa-trash"></i></a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$exams): ?><tr><td colspan="10" style="text-align:center;color:#aaa;padding:30px">No exams yet. Add one above.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require_once '../../includes/footer.php'; ?>