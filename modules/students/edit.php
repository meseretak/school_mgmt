<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher']);
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?"); $stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { flash('Student not found.','error'); header('Location: index.php'); exit; }
$countries = $pdo->query("SELECT * FROM countries ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty($d['first_name'])) $errors[] = 'First name required.';
    if (empty($d['email']))      $errors[] = 'Email required.';
    // Check email not taken by another user
    if (!empty($d['email'])) {
        $dup = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
        $dup->execute([$d['email'], $student['user_id']]);
        if ($dup->fetch()) $errors[] = 'Email is already in use by another account.';
    }
    if (!$errors) {
        $pdo->prepare("UPDATE students SET first_name=?,last_name=?,dob=?,gender=?,nationality=?,country_id=?,passport_no=?,visa_type=?,visa_expiry=?,phone=?,address=?,emergency_contact=?,emergency_phone=?,enrollment_date=?,status=? WHERE id=?")
            ->execute([$d['first_name'],$d['last_name'],$d['dob']??null,$d['gender']??null,$d['nationality']??null,$d['country_id']??null,$d['passport_no']??null,$d['visa_type']??null,$d['visa_expiry']??null,$d['phone']??null,$d['address']??null,$d['emergency_contact']??null,$d['emergency_phone']??null,$d['enrollment_date']??null,$d['status']??'Active',$id]);
        $pdo->prepare("UPDATE users SET email=?, name=? WHERE id=?")
            ->execute([$d['email'], $d['first_name'].' '.$d['last_name'], $student['user_id']]);
        flash('Student updated successfully.');
        header('Location: view.php?id='.$id); exit;
    }
    $student = array_merge($student, $d);
}
$page_title = 'Edit Student';
$active_page = 'students';
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Edit Student</h1><p><?= e($student['first_name'].' '.$student['last_name']) ?></p></div>
  <a href="view.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
  <form method="POST">
    <div class="form-grid">
      <div class="form-section">Personal Information</div>
      <div class="form-group"><label>First Name *</label><input name="first_name" required value="<?= e($student['first_name']) ?>"></div>
      <div class="form-group"><label>Last Name *</label><input name="last_name" required value="<?= e($student['last_name']) ?>"></div>
      <div class="form-group"><label>Email Address *</label><input type="email" name="email" required value="<?= e($student['email']??'') ?>"></div>
      <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" value="<?= e($student['dob']??'') ?>"></div>
      <div class="form-group"><label>Gender</label><select name="gender"><option value="">Select</option><?php foreach(['Male','Female','Other'] as $g): ?><option value="<?= $g ?>" <?= ($student['gender']??'')===$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($student['phone']??'') ?>"></div>
      <div class="form-group"><label>Status</label><select name="status"><?php foreach(['Active','Inactive','Graduated','Suspended'] as $s): ?><option value="<?= $s ?>" <?= ($student['status']??'')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
      <div class="form-group full"><label>Address</label><textarea name="address"><?= e($student['address']??'') ?></textarea></div>

      <div class="form-section">International Info</div>
      <div class="form-group"><label>Nationality</label><input name="nationality" value="<?= e($student['nationality']??'') ?>"></div>
      <div class="form-group"><label>Country</label><select name="country_id"><option value="">Select</option><?php foreach($countries as $c): ?><option value="<?= $c['id'] ?>" <?= ($student['country_id']??'')==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Passport No.</label><input name="passport_no" value="<?= e($student['passport_no']??'') ?>"></div>
      <div class="form-group"><label>Visa Type</label><input name="visa_type" value="<?= e($student['visa_type']??'') ?>"></div>
      <div class="form-group"><label>Visa Expiry</label><input type="date" name="visa_expiry" value="<?= e($student['visa_expiry']??'') ?>"></div>

      <div class="form-section">Emergency Contact</div>
      <div class="form-group"><label>Contact Name</label><input name="emergency_contact" value="<?= e($student['emergency_contact']??'') ?>"></div>
      <div class="form-group"><label>Contact Phone</label><input name="emergency_phone" value="<?= e($student['emergency_phone']??'') ?>"></div>
      <div class="form-group"><label>Enrollment Date</label><input type="date" name="enrollment_date" value="<?= e($student['enrollment_date']??'') ?>"></div>
    </div>
    <div style="margin-top:24px;display:flex;gap:12px;">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Student</button>
      <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div></div>
<?php require_once '../../includes/footer.php'; ?>