<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$id = (int)($_GET['id'] ?? 0);
$cl = $pdo->prepare("SELECT * FROM classes WHERE id=?"); $cl->execute([$id]); $cl = $cl->fetch();
if (!$cl) { flash('Not found', 'error'); header('Location: index.php'); exit; }

$courses  = $pdo->query("SELECT * FROM courses WHERE is_active=1 ORDER BY name")->fetchAll();
$teachers = $pdo->query("SELECT t.*, CONCAT(t.first_name,' ',t.last_name) AS full_name FROM teachers WHERE status='Active' ORDER BY first_name")->fetchAll();
$years    = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = $_POST;
    $old_teacher = $cl['teacher_id'];
    $pdo->prepare("UPDATE classes SET course_id=?,teacher_id=?,academic_year_id=?,section=?,room=?,schedule=?,max_students=?,status=? WHERE id=?")
        ->execute([$d['course_id'],$d['teacher_id'],$d['academic_year_id'],$d['section']??null,$d['room']??null,$d['schedule']??null,$d['max_students']??30,$d['status']??'Open',$id]);
    // Notify if teacher changed
    if ((int)$d['teacher_id'] !== (int)$old_teacher) {
        require_once '../../includes/notify.php';
        notify_class_assigned($pdo, $id, (int)$d['teacher_id']);
    }
    log_activity($pdo, 'class_updated', "Class ID $id updated.");
    flash('Class updated.');
    header('Location: index.php'); exit;
}

// Enrolled students count
$enrolled_count = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE class_id=? AND status='Enrolled'");
$enrolled_count->execute([$id]); $enrolled_count = (int)$enrolled_count->fetchColumn();

$page_title = 'Edit Class'; $active_page = 'classes';
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Class</h1></div>
  <div style="display:flex;gap:8px">
    <a href="enroll.php?id=<?= $id ?>" class="btn btn-success"><i class="fas fa-user-plus"></i> Manage Students (<?= $enrolled_count ?>)</a>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2>Class Details</h2></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-group">
        <label>Course</label>
        <select name="course_id" required>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cl['course_id']==$c['id']?'selected':'' ?>><?= e($c['code'].' — '.$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Academic Year</label>
        <select name="academic_year_id" required>
          <?php foreach ($years as $y): ?>
          <option value="<?= $y['id'] ?>" <?= $cl['academic_year_id']==$y['id']?'selected':'' ?>><?= e($y['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Section</label><input name="section" value="<?= e($cl['section']??'') ?>"></div>
      <div class="form-group"><label>Room</label><input name="room" value="<?= e($cl['room']??'') ?>"></div>
      <div class="form-group"><label>Schedule</label><input name="schedule" value="<?= e($cl['schedule']??'') ?>"></div>
      <div class="form-group"><label>Max Students</label><input type="number" name="max_students" value="<?= $cl['max_students'] ?>"></div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach (['Open','Closed','Completed'] as $s): ?>
          <option value="<?= $s ?>" <?= $cl['status']===$s?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>

<!-- Teacher reassignment -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-chalkboard-teacher" style="color:var(--purple,#7c3aed)"></i> Assigned Teacher</h2></div>
  <div class="card-body">
    <div style="margin-bottom:12px">
      <input type="text" placeholder="Search teacher..." style="max-width:320px" oninput="filterTeacherCards(this.value)">
    </div>
    <div id="teacherCards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
      <?php foreach ($teachers as $t): ?>
      <label class="teacher-card" data-name="<?= strtolower(e($t['full_name'])) ?>" data-spec="<?= strtolower(e($t['specialization']??'')) ?>"
        style="border:2px solid <?= $cl['teacher_id']==$t['id']?'var(--primary)':'#e0e0e0' ?>;background:<?= $cl['teacher_id']==$t['id']?'#f0f4ff':'#fff' ?>;border-radius:12px;padding:14px;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:12px">
        <input type="radio" name="teacher_id" value="<?= $t['id'] ?>" <?= $cl['teacher_id']==$t['id']?'checked':'' ?> required style="accent-color:var(--primary)" onchange="highlightTeacher(this)">
        <div>
          <div style="font-weight:600"><?= e($t['full_name']) ?></div>
          <div style="font-size:.78rem;color:#888"><?= e($t['specialization'] ?? 'No specialization') ?></div>
          <?php if ($cl['teacher_id']==$t['id']): ?>
          <span style="font-size:.72rem;background:#4361ee22;color:#4361ee;padding:2px 7px;border-radius:8px;margin-top:4px;display:inline-block">Current</span>
          <?php endif; ?>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div style="display:flex;gap:12px;margin-bottom:32px">
  <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
  <a href="index.php" class="btn btn-secondary">Cancel</a>
</div>
</form>

<style>.teacher-card:has(input:checked){border-color:var(--primary)!important;background:#f0f4ff!important}</style>
<script>
function highlightTeacher(radio) {
    document.querySelectorAll('.teacher-card').forEach(c => { c.style.borderColor='#e0e0e0'; c.style.background='#fff'; });
    radio.closest('.teacher-card').style.borderColor = 'var(--primary)';
    radio.closest('.teacher-card').style.background = '#f0f4ff';
}
function filterTeacherCards(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.teacher-card').forEach(c => {
        c.style.display = c.dataset.name.includes(q) || c.dataset.spec.includes(q) ? '' : 'none';
    });
}
</script>
<?php require_once '../../includes/footer.php'; ?>