<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT * FROM exams WHERE id=?"); $stmt->execute([$id]); $ex=$stmt->fetch();
if (!$ex) { flash('Not found','error'); header('Location: index.php'); exit; }
$classes=$pdo->query("SELECT cl.id,co.code,co.name AS course_name,cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id ORDER BY co.name")->fetchAll();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d=$_POST;
    $pdo->prepare("UPDATE exams SET class_id=?,title=?,exam_date=?,start_time=?,duration=?,total_marks=?,pass_marks=?,type=?,room=? WHERE id=?")
        ->execute([$d['class_id'],$d['title'],$d['exam_date']??null,$d['start_time']??null,$d['duration']??null,$d['total_marks']??100,$d['pass_marks']??50,$d['type']??'Midterm',$d['room']??null,$id]);
    flash('Exam updated.'); header('Location: index.php'); exit;
}
$page_title='Edit Exam'; $active_page='exams';
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Edit Exam</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <div class="form-grid">
    <div class="form-group full"><label>Title</label><input name="title" required value="<?= e($ex['title']) ?>"></div>
    <div class="form-group"><label>Class</label><select name="class_id"><?php foreach($classes as $cl): ?><option value="<?= $cl['id'] ?>" <?= $ex['class_id']==$cl['id']?'selected':'' ?>><?= e($cl['code'].' — '.$cl['course_name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Type</label><select name="type"><?php foreach(['Quiz','Midterm','Final','Assignment','Project'] as $t): ?><option value="<?= $t ?>" <?= $ex['type']===$t?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Date</label><input type="date" name="exam_date" value="<?= e($ex['exam_date']??'') ?>"></div>
    <div class="form-group"><label>Start Time</label><input type="time" name="start_time" value="<?= e($ex['start_time']??'') ?>"></div>
    <div class="form-group"><label>Duration (min)</label><input type="number" name="duration" value="<?= $ex['duration'] ?>"></div>
    <div class="form-group"><label>Total Marks</label><input type="number" step="0.01" name="total_marks" value="<?= $ex['total_marks'] ?>"></div>
    <div class="form-group"><label>Pass Marks</label><input type="number" step="0.01" name="pass_marks" value="<?= $ex['pass_marks'] ?>"></div>
    <div class="form-group"><label>Room</label><input name="room" value="<?= e($ex['room']??'') ?>"></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<?php require_once '../../includes/footer.php'; ?>