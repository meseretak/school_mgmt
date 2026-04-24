<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$page_title = 'Create Class'; $active_page = 'classes';

$courses  = $pdo->query("SELECT * FROM courses WHERE is_active=1 ORDER BY name")->fetchAll();
$teachers = $pdo->query("SELECT t.*, CONCAT(t.first_name,' ',t.last_name) AS full_name, t.specialization FROM teachers t WHERE t.status='Active' ORDER BY t.first_name")->fetchAll();
$years    = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$students = $pdo->query("SELECT * FROM students WHERE status='Active' ORDER BY first_name, last_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = $_POST;

    // 1. Create the class
    $pdo->prepare("INSERT INTO classes (course_id,teacher_id,academic_year_id,section,room,schedule,max_students,status) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$d['course_id'],$d['teacher_id'],$d['academic_year_id'],$d['section']??null,$d['room']??null,$d['schedule']??null,$d['max_students']??30,$d['status']??'Open']);
    $class_id = $pdo->lastInsertId();

    // 2. Notify teacher
    require_once '../../includes/notify.php';
    notify_class_assigned($pdo, $class_id, (int)$d['teacher_id']);

    // 3. Enroll selected students
    $enrolled = 0;
    $tuition_fee = $pdo->query("SELECT * FROM fee_types WHERE name='Tuition Fee' AND is_active=1 LIMIT 1")->fetch();
    $ay = $pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn();

    foreach (($_POST['student_ids'] ?? []) as $sid) {
        $sid = (int)$sid;
        try {
            $pdo->prepare("INSERT INTO enrollments (student_id,class_id) VALUES (?,?)")->execute([$sid, $class_id]);
            $enrolled++;
            if ($tuition_fee) {
                $has = $pdo->prepare("SELECT id FROM payments WHERE student_id=? AND fee_type_id=? AND academic_year_id=?");
                $has->execute([$sid, $tuition_fee['id'], $ay??0]);
                if (!$has->fetch()) {
                    $course_name = $pdo->prepare("SELECT name FROM courses WHERE id=?"); $course_name->execute([$d['course_id']]); $course_name = $course_name->fetchColumn();
                    $pdo->prepare("INSERT INTO payments (student_id,fee_type_id,academic_year_id,amount_due,due_date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$sid,$tuition_fee['id'],$ay??null,$tuition_fee['amount'],date('Y-m-d',strtotime('+30 days')),'Pending','Auto-generated on enrollment: '.$course_name,$_SESSION['user']['id']]);
                }
            }
        } catch (Exception $e) {}
    }

    log_activity($pdo, 'class_created', "Class ID $class_id with $enrolled student(s) enrolled.");
    flash("Class created and $enrolled student(s) enrolled.");
    header('Location: index.php'); exit;
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-plus-circle" style="color:var(--primary)"></i> Create Class</h1>
    <p style="color:#888;font-size:.9rem">Set up class, assign teacher, and enroll students in one step</p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<form method="POST">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

<!-- Step 1: Class Details -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2><span style="background:var(--primary);color:#fff;border-radius:50%;width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;margin-right:8px">1</span> Class Details</h2>
  </div>
  <div class="card-body">
    <div class="form-grid">
      <div class="form-group">
        <label>Course *</label>
        <select name="course_id" id="courseSelect" required onchange="filterTeachers(this.value)">
          <option value="">— Select Course —</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>"><?= e($c['code'].' — '.$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Academic Year *</label>
        <select name="academic_year_id" required>
          <option value="">— Select Year —</option>
          <?php foreach ($years as $y): ?>
          <option value="<?= $y['id'] ?>" <?= $y['is_current'] ? 'selected' : '' ?>><?= e($y['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Section</label>
        <input name="section" placeholder="e.g. A, B, Morning">
      </div>
      <div class="form-group">
        <label>Room</label>
        <input name="room" placeholder="e.g. Room 101">
      </div>
      <div class="form-group">
        <label>Schedule</label>
        <input name="schedule" placeholder="e.g. Mon/Wed 9:00–10:30">
      </div>
      <div class="form-group">
        <label>Max Students</label>
        <input type="number" name="max_students" value="30" min="1">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="Open">Open</option>
          <option value="Closed">Closed</option>
        </select>
      </div>
    </div>
  </div>
</div>

<!-- Step 2: Assign Teacher -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2><span style="background:var(--success);color:#fff;border-radius:50%;width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;margin-right:8px">2</span> Assign Teacher</h2>
  </div>
  <div class="card-body">
    <div style="margin-bottom:12px">
      <input type="text" id="teacherSearch" placeholder="Search teacher by name or specialization..." style="max-width:360px" oninput="filterTeacherCards(this.value)">
    </div>
    <div id="teacherCards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
      <?php foreach ($teachers as $t): ?>
      <label class="teacher-card" data-name="<?= strtolower(e($t['full_name'])) ?>" data-spec="<?= strtolower(e($t['specialization']??'')) ?>"
        style="border:2px solid #e0e0e0;border-radius:12px;padding:14px;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:12px">
        <input type="radio" name="teacher_id" value="<?= $t['id'] ?>" required style="accent-color:var(--primary)" onchange="highlightTeacher(this)">
        <div>
          <div style="font-weight:600"><?= e($t['full_name']) ?></div>
          <div style="font-size:.78rem;color:#888"><?= e($t['specialization'] ?? 'No specialization') ?></div>
        </div>
      </label>
      <?php endforeach; ?>
      <?php if (!$teachers): ?>
      <p style="color:#aaa">No active teachers found. <a href="<?= BASE_URL ?>/modules/teachers/add.php">Add a teacher first.</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Step 3: Enroll Students -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header" style="justify-content:space-between;align-items:center">
    <h2><span style="background:var(--warning);color:#fff;border-radius:50%;width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;margin-right:8px">3</span> Enroll Students <span id="selectedCount" style="font-size:.85rem;font-weight:400;color:#888;margin-left:8px">0 selected</span></h2>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="studentSearch" placeholder="Search students..." style="max-width:240px" oninput="filterStudents(this.value)">
      <label style="font-size:.85rem;cursor:pointer;display:flex;align-items:center;gap:6px">
        <input type="checkbox" id="checkAll" onchange="toggleAll(this)"> Select All
      </label>
    </div>
  </div>
  <div class="table-wrap">
    <table id="studentTable">
      <thead><tr><th style="width:40px"></th><th>Name</th><th>Student ID</th><th>Nationality</th></tr></thead>
      <tbody>
      <?php foreach ($students as $s): ?>
      <tr data-name="<?= strtolower(e($s['first_name'].' '.$s['last_name'])) ?>" data-code="<?= strtolower(e($s['student_code']??'')) ?>">
        <td><input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-cb" onchange="updateCount()"></td>
        <td style="font-weight:500"><?= e($s['first_name'].' '.$s['last_name']) ?></td>
        <td style="font-family:monospace;font-size:.85rem"><?= e($s['student_code'] ?? '—') ?></td>
        <td style="font-size:.85rem;color:#888"><?= e($s['nationality'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?>
      <tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px">No active students found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="padding:12px 16px;font-size:.82rem;color:#aaa">Students can also be enrolled later from the Classes list.</div>
</div>

<div style="display:flex;gap:12px;margin-bottom:32px">
  <button type="submit" class="btn btn-primary" style="padding:12px 32px;font-size:1rem"><i class="fas fa-save"></i> Create Class</button>
  <a href="index.php" class="btn btn-secondary">Cancel</a>
</div>
</form>

<style>
.teacher-card:has(input:checked) { border-color:var(--primary); background:#f0f4ff; }
</style>
<script>
function highlightTeacher(radio) {
    document.querySelectorAll('.teacher-card').forEach(c => c.style.borderColor = '#e0e0e0');
    radio.closest('.teacher-card').style.borderColor = 'var(--primary)';
    radio.closest('.teacher-card').style.background = '#f0f4ff';
}
function filterTeacherCards(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.teacher-card').forEach(c => {
        const match = c.dataset.name.includes(q) || c.dataset.spec.includes(q);
        c.style.display = match ? '' : 'none';
    });
}
function filterStudents(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#studentTable tbody tr').forEach(r => {
        const match = r.dataset.name.includes(q) || r.dataset.code.includes(q);
        r.style.display = match ? '' : 'none';
    });
}
function toggleAll(cb) {
    document.querySelectorAll('.student-cb').forEach(c => { if (c.closest('tr').style.display !== 'none') c.checked = cb.checked; });
    updateCount();
}
function updateCount() {
    const n = document.querySelectorAll('.student-cb:checked').length;
    document.getElementById('selectedCount').textContent = n + ' selected';
}
</script>
<?php require_once '../../includes/footer.php'; ?>