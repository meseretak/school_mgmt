<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$page_title = 'Add Teacher';
$active_page = 'teachers';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty($d['first_name'])||empty($d['last_name'])||empty($d['email'])) $errors[] = 'Name and email are required.';
    if (!$errors) {
        $auto_branch = $_SESSION['user']['branch_id'] ?? null;
        $pass = password_hash('teacher123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name,email,password,role,branch_id) VALUES (?,?,?,'teacher',?)")->execute([$d['first_name'].' '.$d['last_name'],$d['email'],$pass,$auto_branch]);
        $uid = $pdo->lastInsertId();
        $code = generate_teacher_id($pdo, $uid);
        $pdo->prepare("INSERT INTO teachers (user_id,teacher_code,branch_id,first_name,last_name,specialization,phone,hire_date,status) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$uid,$code,$auto_branch,$d['first_name'],$d['last_name'],$d['specialization']??null,$d['phone']??null,$d['hire_date']??null,$d['status']??'Active']);
        flash('Teacher added. Login: '.$d['email'].' / teacher123');
        log_activity($pdo, 'teacher_added', 'Added teacher: '.$d['first_name'].' '.$d['last_name'].' ('.$code.')');
        header('Location: index.php'); exit;
    }
}
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Add Teacher</h1></div>
  <div style="display:flex;gap:8px">
    <button type="button" onclick="fillFakeTeacher()" class="btn btn-secondary"><i class="fas fa-magic"></i> Fill Test Data</button>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= e($e) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
  <form method="POST">
    <div class="form-grid">
      <div class="form-group"><label>First Name *</label><input name="first_name" required value="<?= e($_POST['first_name']??'') ?>"></div>
      <div class="form-group"><label>Last Name *</label><input name="last_name" required value="<?= e($_POST['last_name']??'') ?>"></div>
      <div class="form-group"><label>Email *</label><input type="email" name="email" required value="<?= e($_POST['email']??'') ?>"></div>
      <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($_POST['phone']??'') ?>"></div>
      <div class="form-group"><label>Specialization</label><input name="specialization" value="<?= e($_POST['specialization']??'') ?>"></div>
      <div class="form-group"><label>Hire Date</label><input type="date" name="hire_date" value="<?= e($_POST['hire_date']??date('Y-m-d')) ?>"></div>
      <div class="form-group"><label>Status</label><select name="status"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:12px;">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Teacher</button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once '../../includes/footer.php'; ?>