<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$id = (int)($_GET['id']??0);
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id=?"); $stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) { flash('Not found','error'); header('Location: index.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d = $_POST;
    $pdo->prepare("UPDATE teachers SET first_name=?,last_name=?,specialization=?,phone=?,hire_date=?,status=? WHERE id=?")
        ->execute([$d['first_name'],$d['last_name'],$d['specialization']??null,$d['phone']??null,$d['hire_date']??null,$d['status']??'Active',$id]);
    flash('Teacher updated.'); header('Location: index.php'); exit;
}
$page_title='Edit Teacher'; $active_page='teachers';
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Edit Teacher</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <div class="form-grid">
    <div class="form-group"><label>First Name</label><input name="first_name" required value="<?= e($t['first_name']) ?>"></div>
    <div class="form-group"><label>Last Name</label><input name="last_name" required value="<?= e($t['last_name']) ?>"></div>
    <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($t['phone']??'') ?>"></div>
    <div class="form-group"><label>Specialization</label><input name="specialization" value="<?= e($t['specialization']??'') ?>"></div>
    <div class="form-group"><label>Hire Date</label><input type="date" name="hire_date" value="<?= e($t['hire_date']??'') ?>"></div>
    <div class="form-group"><label>Status</label><select name="status"><option value="Active" <?= $t['status']==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $t['status']==='Inactive'?'selected':'' ?>>Inactive</option></select></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<?php require_once '../../includes/footer.php'; ?>