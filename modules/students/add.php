<?php
require_once '../../includes/config.php';
// Only admin can add students — teachers manage enrollment through their portal
auth_check(['admin']);
$page_title = 'Add Student';
$active_page = 'students';
$countries = $pdo->query("SELECT * FROM countries ORDER BY name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    if (empty($d['first_name'])) $errors[] = 'First name is required.';
    if (empty($d['last_name']))  $errors[] = 'Last name is required.';
    if (empty($d['email']))      $errors[] = 'Email is required.';

    if (!$errors) {
        // Auto-assign branch from the logged-in admin's branch
        $auto_branch = $_SESSION['user']['branch_id'] ?? null;

        // Allow fake/test emails — append unique suffix if duplicate
        $email = trim($d['email']);
        $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $email = preg_replace('/(@|$)/', '_'.uniqid().'$1', $email, 1);
        }
        // Create user account — also tag with same branch
        $pass = password_hash('student123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name,email,password,role,branch_id) VALUES (?,?,?,'student',?)")
            ->execute([$d['first_name'].' '.$d['last_name'], $email, $pass, $auto_branch]);
        $uid = $pdo->lastInsertId();

        $code = generate_student_id($pdo, $uid);

        $pdo->prepare("INSERT INTO students (user_id,student_code,branch_id,first_name,last_name,dob,gender,nationality,country_id,passport_no,visa_type,visa_expiry,phone,address,emergency_contact,emergency_phone,enrollment_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$uid,$code,$auto_branch,$d['first_name'],$d['last_name'],
                $d['dob']??null,
                $d['gender']??null,
                $d['nationality']??null,
                !empty($d['country_id']) ? (int)$d['country_id'] : null,
                $d['passport_no']??null,
                $d['visa_type']??null,
                !empty($d['visa_expiry']) ? $d['visa_expiry'] : null,
                $d['phone']??null,
                $d['address']??null,
                $d['emergency_contact']??null,
                $d['emergency_phone']??null,
                !empty($d['enrollment_date']) ? $d['enrollment_date'] : date('Y-m-d'),
                $d['status']??'Active'
            ]);
        $student_id = $pdo->lastInsertId();

        // ── Auto: Registration Fee ─────────────────────────────
        $reg_fee = $pdo->query("SELECT * FROM fee_types WHERE name='Registration Fee' AND is_active=1 LIMIT 1")->fetch();
        if ($reg_fee) {
            $ay = $pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn();
            $pdo->prepare("INSERT INTO payments (student_id,fee_type_id,academic_year_id,amount_due,due_date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$student_id,$reg_fee['id'],$ay??null,$reg_fee['amount'],date('Y-m-d',strtotime('+30 days')),'Pending','Auto-generated on registration',$_SESSION['user']['id']]);
        }

        flash("Student {$d['first_name']} {$d['last_name']} added successfully. Login: {$d['email']} / student123");
        header('Location: index.php'); exit;
    }
}
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Add New Student</h1><p>Register a new student in the system</p></div>
  <div style="display:flex;gap:8px">
    <button type="button" onclick="fillFakeStudent()" class="btn btn-secondary"><i class="fas fa-magic"></i> Fill Test Data</button>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= e($err) ?></div>
<?php endforeach; ?>

<div class="card">
  <div class="card-body">
    <form method="POST">
      <div class="form-grid">
        <div class="form-section">Personal Information</div>
        <div class="form-group"><label>First Name *</label><input name="first_name" required value="<?= e($_POST['first_name']??'') ?>"></div>
        <div class="form-group"><label>Last Name *</label><input name="last_name" required value="<?= e($_POST['last_name']??'') ?>"></div>
        <div class="form-group"><label>Email Address *</label><input type="email" name="email" required value="<?= e($_POST['email']??'') ?>"></div>
        <div class="form-group"><label>Date of Birth</label><input type="date" name="dob" value="<?= e($_POST['dob']??'') ?>"></div>
        <div class="form-group"><label>Gender</label>
          <select name="gender"><option value="">Select</option>
            <?php foreach (['Male','Female','Other'] as $g): ?><option value="<?= $g ?>" <?= ($_POST['gender']??'')===$g?'selected':'' ?>><?= $g ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($_POST['phone']??'') ?>"></div>
        <div class="form-group full"><label>Address</label><textarea name="address"><?= e($_POST['address']??'') ?></textarea></div>

        <div class="form-section">International Student Information</div>
        <div class="form-group"><label>Nationality</label><input name="nationality" value="<?= e($_POST['nationality']??'') ?>"></div>
        <div class="form-group"><label>Country of Origin</label>
          <select name="country_id"><option value="">Select Country</option>
            <?php foreach ($countries as $c): ?><option value="<?= $c['id'] ?>" <?= ($_POST['country_id']??'')==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Passport Number</label><input name="passport_no" value="<?= e($_POST['passport_no']??'') ?>"></div>
        <div class="form-group"><label>Visa Type</label><input name="visa_type" placeholder="e.g. Student Visa F-1" value="<?= e($_POST['visa_type']??'') ?>"></div>
        <div class="form-group"><label>Visa Expiry Date</label><input type="date" name="visa_expiry" value="<?= e($_POST['visa_expiry']??'') ?>"></div>

        <div class="form-section">Emergency Contact</div>
        <div class="form-group"><label>Emergency Contact Name</label><input name="emergency_contact" value="<?= e($_POST['emergency_contact']??'') ?>"></div>
        <div class="form-group"><label>Emergency Phone</label><input name="emergency_phone" value="<?= e($_POST['emergency_phone']??'') ?>"></div>

        <div class="form-section">Enrollment</div>
        <div class="form-group"><label>Enrollment Date</label><input type="date" name="enrollment_date" value="<?= e($_POST['enrollment_date']??date('Y-m-d')) ?>"></div>
        <div class="form-group"><label>Status</label>
          <select name="status">
            <?php foreach (['Active','Inactive','Suspended'] as $s): ?><option value="<?= $s ?>" <?= ($_POST['status']??'Active')===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="margin-top:24px;display:flex;gap:12px;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Student</button>
        <a href="index.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>