<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT * FROM courses WHERE id=?"); $stmt->execute([$id]); $c=$stmt->fetch();
if (!$c) { flash('Not found','error'); header('Location: index.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d=$_POST;
    $pdo->prepare("UPDATE courses SET code=?,name=?,description=?,credits=?,level=?,category=?,is_active=? WHERE id=?")
        ->execute([$d['code'],$d['name'],$d['description']??null,$d['credits']??3,$d['level']??null,$d['category']??null,$d['is_active']??1,$id]);
    flash('Course updated.'); header('Location: index.php'); exit;
}
$page_title='Edit Course'; $active_page='courses';
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Edit Course</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <div class="form-grid">
    <div class="form-group"><label>Code</label><input name="code" required value="<?= e($c['code']) ?>"></div>
    <div class="form-group"><label>Name</label><input name="name" required value="<?= e($c['name']) ?>"></div>
    <div class="form-group"><label>Credits</label><input type="number" name="credits" value="<?= $c['credits'] ?>"></div>
    <div class="form-group"><label>Level</label><select name="level"><?php foreach(['Beginner','Intermediate','Advanced','All'] as $l): ?><option value="<?= $l ?>" <?= ($c['level']??'')===$l?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Category</label><input name="category" value="<?= e($c['category']??'') ?>"></div>
    <div class="form-group"><label>Status</label><select name="is_active"><option value="1" <?= $c['is_active']?'selected':'' ?>>Active</option><option value="0" <?= !$c['is_active']?'selected':'' ?>>Inactive</option></select></div>
    <div class="form-group full"><label>Description</label><textarea name="description"><?= e($c['description']??'') ?></textarea></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<?php require_once '../../includes/footer.php'; ?>