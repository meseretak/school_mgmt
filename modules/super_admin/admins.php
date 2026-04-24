<?php
require_once '../../includes/config.php';
auth_check(['super_admin']);
$page_title = 'Branch Admins';
$active_page = 'super_admins';

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $pass     = $_POST['password'] ?? '';
        $branch   = (int)($_POST['branch_id'] ?? 0);

        if (!$name || !$email || !$pass) { flash('Name, email and password are required.', 'error'); }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Invalid email address.', 'error'); }
        elseif (strlen($pass) < 6) { flash('Password must be at least 6 characters.', 'error'); }
        else {
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?"); $exists->execute([$email]);
            if ($exists->fetch()) { flash('Email already in use.', 'error'); }
            else {
                $pdo->prepare("INSERT INTO users (name,email,password,role,branch_id,is_active) VALUES (?,?,?,?,?,1)")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin', $branch ?: null]);
                log_activity($pdo, 'admin_create', "Created branch admin: $name ($email)");
                flash("Admin account created for $name.");
            }
        }
        header('Location: admins.php'); exit;
    }

    if ($action === 'toggle_admin') {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=? AND role='admin'")->execute([$uid]);
        flash('Admin status updated.');
        header('Location: admins.php'); exit;
    }

    if ($action === 'assign_branch') {
        $uid = (int)$_POST['user_id'];
        $bid = (int)$_POST['branch_id'] ?: null;
        $pdo->prepare("UPDATE users SET branch_id=? WHERE id=? AND role='admin'")->execute([$bid, $uid]);
        flash('Branch assignment updated.');
        header('Location: admins.php'); exit;
    }

    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 6) { flash('Password must be at least 6 characters.', 'error'); }
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=? AND role='admin'")->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
            log_activity($pdo, 'admin_password_reset', "Reset password for admin user id=$uid");
            flash('Password reset successfully.');
        }
        header('Location: admins.php'); exit;
    }
}

$admins = $pdo->query("
    SELECT u.*, b.name AS branch_name, b.code AS branch_code
    FROM users u
    LEFT JOIN branches b ON u.branch_id=b.id
    WHERE u.role='admin'
    ORDER BY u.is_active DESC, u.name
")->fetchAll();

$branches = $pdo->query("SELECT * FROM branches WHERE is_active=1 ORDER BY is_main DESC, name")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-user-shield" style="color:#4361ee;margin-right:8px"></i>Branch Admins</h1>
    <p>Manage admin accounts and their branch assignments</p>
  </div>
</div>

<div class="grid-2" style="align-items:start">

  <!-- Admin List -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-users" style="color:var(--primary)"></i> Admins (<?= count($admins) ?>)</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Admin</th><th>Branch</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
      <tr>
        <td>
          <div style="font-weight:700"><?= e($a['name']) ?></div>
          <div style="font-size:.78rem;color:#888"><?= e($a['email']) ?></div>
        </td>
        <td>
          <?php if ($a['branch_name']): ?>
          <span class="badge badge-info"><?= e($a['branch_name']) ?></span>
          <?php else: ?>
          <span style="color:#aaa;font-size:.82rem">No branch</span>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-<?= $a['is_active']?'success':'danger' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span></td>
        <td style="white-space:nowrap">
          <!-- Assign branch -->
          <form method="POST" style="display:inline;margin-right:4px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="assign_branch">
            <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
            <select name="branch_id" onchange="this.form.submit()" style="font-size:.78rem;padding:3px 6px;border-radius:6px;border:1.5px solid #e0e0e0">
              <option value="0" <?= !$a['branch_id']?'selected':'' ?>>No Branch</option>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $a['branch_id']==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <!-- Toggle active -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle_admin">
            <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
            <button class="btn btn-sm btn-<?= $a['is_active']?'danger':'success' ?>" title="<?= $a['is_active']?'Deactivate':'Activate' ?>">
              <i class="fas fa-<?= $a['is_active']?'ban':'check' ?>"></i>
            </button>
          </form>
          <!-- Reset password (inline toggle) -->
          <button class="btn btn-sm btn-secondary" onclick="toggleReset(<?= $a['id'] ?>)" title="Reset password">
            <i class="fas fa-key"></i>
          </button>
          <!-- Reset password form (hidden) -->
          <form method="POST" id="reset_<?= $a['id'] ?>" style="display:none;margin-top:6px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= $a['id'] ?>">
            <div style="display:flex;gap:6px;margin-top:4px">
              <input type="password" name="new_password" placeholder="New password" minlength="6" required style="font-size:.8rem;padding:4px 8px;border-radius:6px;border:1.5px solid #e0e0e0;width:140px">
              <button type="submit" class="btn btn-sm btn-primary">Set</button>
            </div>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$admins): ?><tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px">No admins yet</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>

  <!-- Create Admin Form -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-plus" style="color:var(--success)"></i> Create Branch Admin</h2></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="create_admin">
        <div class="form-group"><label>Full Name *</label><input name="name" required placeholder="Admin name"></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" required placeholder="admin@school.com"></div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" required minlength="6" placeholder="Min 6 characters">
        </div>
        <div class="form-group">
          <label>Assign to Branch</label>
          <select name="branch_id">
            <option value="0">No specific branch (global admin)</option>
            <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?> (<?= e($b['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-user-plus"></i> Create Admin</button>
      </form>
    </div>
  </div>

</div>

<script>
function toggleReset(id) {
  const f = document.getElementById('reset_' + id);
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php require_once '../../includes/footer.php'; ?>