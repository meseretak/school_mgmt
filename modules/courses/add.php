<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$page_title='Add Course'; $active_page='courses';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d=$_POST;
    $pdo->prepare("INSERT INTO courses (code,name,description,credits,level,category,is_active) VALUES (?,?,?,?,?,?,?)")
        ->execute([$d['code'],$d['name'],$d['description']??null,$d['credits']??3,$d['level']??null,$d['category']??null,$d['is_active']??1]);
    flash('Course added.'); header('Location: index.php'); exit;
}
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Add Course</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <div class="form-grid">
    <div class="form-group"><label>Course Code *</label><input name="code" required placeholder="e.g. CS101" value="<?= e($_POST['code']??'') ?>"></div>
    <div class="form-group"><label>Course Name *</label><input name="name" required value="<?= e($_POST['name']??'') ?>"></div>
    <div class="form-group"><label>Credits</label><input type="number" name="credits" min="1" max="6" value="<?= e($_POST['credits']??3) ?>"></div>
    <div class="form-group"><label>Level</label><select name="level"><option value="">Select</option><?php foreach(['Beginner','Intermediate','Advanced','All'] as $l): ?><option value="<?= $l ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Category</label><input name="category" placeholder="e.g. Technology" value="<?= e($_POST['category']??'') ?>"></div>
    <div class="form-group"><label>Status</label><select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <div class="form-group full"><label>Description</label><textarea name="description"><?= e($_POST['description']??'') ?></textarea></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Course</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<?php require_once '../../includes/footer.php'; ?>