<?php
require_once '../../includes/config.php';
auth_check(['super_admin']);
$page_title = 'Manage Branches';
$active_page = 'super_branches';

// ── Handle POST actions ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_branch') {
        $bid = (int)($_POST['branch_id'] ?? 0);
        $d = $_POST;
        $fields = [
            $d['name'], $d['code'], $d['address'] ?? null, $d['phone'] ?? null,
            $d['email'] ?? null, $d['principal'] ?? null,
            $d['description'] ?? null, $d['established_date'] ?: null,
            (int)($d['capacity'] ?? 0), (int)($d['is_active'] ?? 1)
        ];
        if ($bid) {
            $pdo->prepare("UPDATE branches SET name=?,code=?,address=?,phone=?,email=?,principal=?,description=?,established_date=?,capacity=?,is_active=? WHERE id=?")
                ->execute(array_merge($fields, [$bid]));
            flash('Branch updated successfully.');
        } else {
            $pdo->prepare("INSERT INTO branches (name,code,address,phone,email,principal,description,established_date,capacity,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute($fields);
            flash('Branch created successfully.');
        }
        log_activity($pdo, $bid ? 'branch_update' : 'branch_create', ($bid ? 'Updated' : 'Created').' branch: '.$d['name']);
        header('Location: branches.php'); exit;
    }

    if ($action === 'toggle_branch') {
        $bid = (int)$_POST['branch_id'];
        $pdo->prepare("UPDATE branches SET is_active = NOT is_active WHERE id=?")->execute([$bid]);
        flash('Branch status updated.');
        header('Location: branches.php'); exit;
    }

    if ($action === 'set_main') {
        $bid = (int)$_POST['branch_id'];
        $pdo->query("UPDATE branches SET is_main=0");
        $pdo->prepare("UPDATE branches SET is_main=1 WHERE id=?")->execute([$bid]);
        flash('Main branch updated.');
        header('Location: branches.php'); exit;
    }
}

$branches = $pdo->query("
    SELECT b.*,
        (SELECT COUNT(*) FROM students s WHERE s.branch_id=b.id AND s.status='Active') AS student_count,
        (SELECT COUNT(*) FROM teachers t WHERE t.branch_id=b.id AND t.status='Active') AS teacher_count,
        (SELECT COUNT(*) FROM users u WHERE u.branch_id=b.id AND u.role='admin') AS admin_count
    FROM branches b ORDER BY b.is_main DESC, b.name
")->fetchAll();

$edit_branch = null;
if (isset($_GET['edit'])) {
    $eb = $pdo->prepare("SELECT * FROM branches WHERE id=?");
    $eb->execute([(int)$_GET['edit']]);
    $edit_branch = $eb->fetch();
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-building" style="color:#f4a261;margin-right:8px"></i>Manage Branches</h1>
    <p>Create and manage all school branches</p>
  </div>
  <a href="branches.php?new=1" class="btn btn-primary"><i class="fas fa-plus"></i> New Branch</a>
</div>

<div class="grid-2" style="align-items:start">

  <!-- Branch List -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> All Branches (<?= count($branches) ?>)</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Branch</th><th>Students</th><th>Teachers</th><th>Admins</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($branches as $b): ?>
      <tr>
        <td>
          <div style="font-weight:700"><?= e($b['name']) ?></div>
          <code style="font-size:.75rem;color:#888"><?= e($b['code']) ?></code>
          <?php if ($b['is_main']): ?><span class="badge badge-primary" style="font-size:.65rem;margin-left:4px">Main</span><?php endif; ?>
          <?php if ($b['principal']): ?><div style="font-size:.75rem;color:#aaa"><i class="fas fa-user"></i> <?= e($b['principal']) ?></div><?php endif; ?>
        </td>
        <td><span class="badge badge-info"><?= $b['student_count'] ?></span></td>
        <td><span class="badge badge-secondary"><?= $b['teacher_count'] ?></span></td>
        <td><span class="badge badge-warning"><?= $b['admin_count'] ?></span></td>
        <td>
          <span class="badge badge-<?= $b['is_active']?'success':'danger' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span>
        </td>
        <td style="white-space:nowrap">
          <a href="branches.php?edit=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
          <?php if (!$b['is_main']): ?>
          <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="set_main">
            <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-secondary" title="Set as main branch"><i class="fas fa-star"></i></button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('Toggle branch status?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle_branch">
            <input type="hidden" name="branch_id" value="<?= $b['id'] ?>">
            <button class="btn btn-sm btn-<?= $b['is_active']?'danger':'success' ?>">
              <i class="fas fa-<?= $b['is_active']?'ban':'check' ?>"></i>
            </button>
          </form>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/modules/super_admin/reports.php?branch_id=<?= $b['id'] ?>" class="btn btn-sm btn-secondary" title="View report">
            <i class="fas fa-chart-bar"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$branches): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No branches yet</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>

  <!-- Add / Edit Form -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-<?= $edit_branch?'edit':'plus' ?>" style="color:var(--success)"></i>
        <?= $edit_branch ? 'Edit: '.e($edit_branch['name']) : 'Add New Branch' ?>
      </h2>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_branch">
        <input type="hidden" name="branch_id" value="<?= $edit_branch['id'] ?? 0 ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Branch Name *</label>
            <input name="name" required value="<?= e($edit_branch['name'] ?? '') ?>" placeholder="e.g. North Campus">
          </div>
          <div class="form-group">
            <label>Branch Code *</label>
            <input name="code" required value="<?= e($edit_branch['code'] ?? '') ?>" placeholder="e.g. NORTH" style="text-transform:uppercase">
          </div>
          <div class="form-group">
            <label>Principal / Head</label>
            <input name="principal" value="<?= e($edit_branch['principal'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input name="phone" value="<?= e($edit_branch['phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= e($edit_branch['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Established Date</label>
            <input type="date" name="established_date" value="<?= e($edit_branch['established_date'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Student Capacity</label>
            <input type="number" name="capacity" value="<?= (int)($edit_branch['capacity'] ?? 0) ?>" min="0">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="is_active">
              <option value="1" <?= ($edit_branch['is_active'] ?? 1) ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= isset($edit_branch) && !$edit_branch['is_active'] ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="form-group full">
            <label>Address</label>
            <textarea name="address"><?= e($edit_branch['address'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Description</label>
            <textarea name="description" rows="2"><?= e($edit_branch['description'] ?? '') ?></textarea>
          </div>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Branch</button>
          <?php if ($edit_branch): ?><a href="branches.php" class="btn btn-secondary">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>
<?php require_once '../../includes/footer.php'; ?>