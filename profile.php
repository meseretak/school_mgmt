<?php
require_once 'includes/config.php';
auth_check();
$page_title = 'My Profile'; $active_page = '';
$user = $_SESSION['user'];
$errors = []; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_name') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) { $errors[] = 'Name cannot be empty.'; }
        else {
            $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $user['id']]);
            $_SESSION['user']['name'] = $name;
            flash('Profile updated successfully.');
            header('Location: profile.php'); exit;
        }
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user['id']]);
            $_SESSION['user']['password'] = $hash;
            flash('Password changed successfully.');
            header('Location: profile.php'); exit;
        }
    }
}

// Activity: recent logins / last actions (simple — just show account info)
$student = null; $teacher = null;
if ($user['role'] === 'student') {
    $student = $pdo->prepare("SELECT s.*, c.name AS country FROM students s LEFT JOIN countries c ON s.country_id=c.id WHERE s.user_id=?");
    $student->execute([$user['id']]); $student = $student->fetch();
}
if ($user['role'] === 'teacher') {
    $teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
    $teacher->execute([$user['id']]); $teacher = $teacher->fetch();
}

require_once 'includes/header.php';
?>
<div class="page-header">
  <div><h1>My Profile</h1><p>Manage your account settings</p></div>
</div>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid-2">
  <!-- Profile Info -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-user" style="color:var(--primary)"></i> Account Info</h2></div>
    <div class="card-body">
      <div style="text-align:center;margin-bottom:24px">
        <div class="avatar" style="width:80px;height:80px;font-size:2rem;margin:0 auto 12px">
          <?= strtoupper(substr($user['name'],0,2)) ?>
        </div>
        <div style="font-weight:700;font-size:1.1rem"><?= e($user['name']) ?></div>
        <div><span class="badge badge-info"><?= ucfirst($user['role']) ?></span></div>
        <div style="color:#888;font-size:.85rem;margin-top:4px"><?= e($user['email']) ?></div>
      </div>

      <?php if ($student): ?>
      <div style="background:#f8f9ff;border-radius:10px;padding:16px;font-size:.88rem">
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee"><span style="color:#888">Student Code</span><strong><?= e($student['student_code']) ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee"><span style="color:#888">Status</span><span class="badge badge-success"><?= e($student['status']) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee"><span style="color:#888">Nationality</span><strong><?= e($student['nationality'] ?? '—') ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#888">Country</span><strong><?= e($student['country'] ?? '—') ?></strong></div>
      </div>
      <?php elseif ($teacher): ?>
      <div style="background:#f8f9ff;border-radius:10px;padding:16px;font-size:.88rem">
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee"><span style="color:#888">Teacher Code</span><strong><?= e($teacher['teacher_code']) ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee"><span style="color:#888">Specialization</span><strong><?= e($teacher['specialization'] ?? '—') ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0"><span style="color:#888">Status</span><span class="badge badge-success"><?= e($teacher['status']) ?></span></div>
      </div>
      <?php endif; ?>

      <form method="POST" style="margin-top:20px">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_name">
        <div class="form-group">
          <label>Display Name</label>
          <input name="name" value="<?= e($user['name']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Update Name</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-lock" style="color:var(--warning)"></i> Change Password</h2></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label>Current Password</label>
          <div class="input-wrap">
            <i class="fas fa-lock"></i>
            <input type="password" name="current_password" required placeholder="Enter current password">
          </div>
        </div>
        <div class="form-group">
          <label>New Password <small style="color:#aaa">(min 8 characters)</small></label>
          <div class="input-wrap">
            <i class="fas fa-key"></i>
            <input type="password" name="new_password" required placeholder="Enter new password" minlength="8" id="newpw">
          </div>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="input-wrap">
            <i class="fas fa-key"></i>
            <input type="password" name="confirm_password" required placeholder="Repeat new password" id="confirmpw">
          </div>
        </div>
        <div id="pw-match-msg" style="font-size:.82rem;margin-bottom:12px"></div>
        <button type="submit" class="btn btn-warning" style="width:100%"><i class="fas fa-lock"></i> Change Password</button>
      </form>

      <div style="margin-top:24px;padding:16px;background:#fff8f0;border-radius:10px;font-size:.85rem;color:#888">
        <i class="fas fa-info-circle" style="color:var(--warning)"></i>
        Password tips: use at least 8 characters, mix letters and numbers.
      </div>
    </div>
  </div>
</div>

<script>
const np = document.getElementById('newpw'), cp = document.getElementById('confirmpw'), msg = document.getElementById('pw-match-msg');
function checkMatch() {
  if (!cp.value) { msg.textContent=''; return; }
  if (np.value === cp.value) { msg.style.color='green'; msg.textContent='✓ Passwords match'; }
  else { msg.style.color='red'; msg.textContent='✗ Passwords do not match'; }
}
np.addEventListener('input', checkMatch);
cp.addEventListener('input', checkMatch);
</script>
<?php require_once 'includes/footer.php'; ?>
