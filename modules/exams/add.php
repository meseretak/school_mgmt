<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$page_title='Add Exam'; $active_page='exams';

// Teachers only see their own classes
if (is_teacher()) {
    $teacher = get_teacher_record($pdo);
    if (!$teacher) deny();
    $classes = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? AND cl.status='Open' ORDER BY co.name");
    $classes->execute([$teacher['id']]); $classes = $classes->fetchAll();
} else {
    $classes = $pdo->query("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.status='Open' ORDER BY co.name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $d = $_POST;
    $class_id = (int)$d['class_id'];
    // Verify teacher owns this class
    if (is_teacher() && !teacher_owns_class($pdo, $class_id, $teacher['id'])) deny();
    $pdo->prepare("INSERT INTO exams (class_id,title,exam_date,start_time,duration,total_marks,pass_marks,type,room) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$class_id,$d['title'],$d['exam_date']??null,$d['start_time']??null,$d['duration']??null,$d['total_marks']??100,$d['pass_marks']??50,$d['type']??'Midterm',$d['room']??null]);
    $exam_id = $pdo->lastInsertId();

    // ── Auto: Exam Fee for all enrolled students in this class ─
    $exam_fee = $pdo->query("SELECT * FROM fee_types WHERE name='Exam Fee' AND is_active=1 LIMIT 1")->fetch();
    if ($exam_fee) {
        $ay = $pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn();
        $enrolled = $pdo->prepare("SELECT student_id FROM enrollments WHERE class_id=? AND status='Enrolled'");
        $enrolled->execute([$class_id]); $enrolled = $enrolled->fetchAll();
        foreach ($enrolled as $en) {
            $pdo->prepare("INSERT IGNORE INTO payments (student_id,fee_type_id,academic_year_id,amount_due,due_date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$en['student_id'],$exam_fee['id'],$ay??null,$exam_fee['amount'],
                    $d['exam_date']??date('Y-m-d'),'Pending',
                    'Auto-generated for exam: '.$d['title'],
                    $_SESSION['user']['id']]);
        }
        $fee_count = count($enrolled);
    }

    require_once '../../includes/notify.php';
    notify_exam_added($pdo, $exam_id);
    log_activity($pdo, 'exam_added', "Exam added: {$d['title']}");
    flash('Exam added. '.($fee_count??0).' exam fee(s) auto-generated for enrolled students.');
    header('Location: index.php'); exit;
}
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Add Exam</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <div class="form-grid">
    <div class="form-group full"><label>Exam Title *</label><input name="title" required placeholder="e.g. Midterm Exam — Chapter 1-5" value="<?= e($_POST['title']??'') ?>"></div>
    <div class="form-group"><label>Class *</label><select name="class_id" required><option value="">Select Class</option><?php foreach($classes as $cl): ?><option value="<?= $cl['id'] ?>"><?= e($cl['code'].' — '.$cl['course_name'].' ('.$cl['section'].')') ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Exam Type</label><select name="type"><?php foreach(['Quiz','Midterm','Final','Assignment','Project'] as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Exam Date</label><input type="date" name="exam_date" value="<?= e($_POST['exam_date']??'') ?>"></div>
    <div class="form-group"><label>Start Time</label><input type="time" name="start_time" value="<?= e($_POST['start_time']??'') ?>"></div>
    <div class="form-group"><label>Duration (minutes)</label><input type="number" name="duration" value="<?= e($_POST['duration']??90) ?>"></div>
    <div class="form-group"><label>Total Marks</label><input type="number" step="0.01" name="total_marks" value="<?= e($_POST['total_marks']??100) ?>"></div>
    <div class="form-group"><label>Pass Marks</label><input type="number" step="0.01" name="pass_marks" value="<?= e($_POST['pass_marks']??50) ?>"></div>
    <div class="form-group"><label>Room</label><input name="room" value="<?= e($_POST['room']??'') ?>"></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Exam</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<?php require_once '../../includes/footer.php'; ?>