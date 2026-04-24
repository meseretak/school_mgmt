<?php
require_once '../../includes/config.php';
auth_check(['admin']);
$page_title = 'Settings'; $active_page = 'settings';
$tab = $_GET['tab'] ?? 'grades';

// ── GRADE SCALE ACTIONS ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_scale') {
        $sid = (int)$_POST['scale_id'];
        $pass_pct = (float)$_POST['pass_percentage'];
        $pdo->prepare("UPDATE grade_scales SET pass_percentage=? WHERE id=?")->execute([$pass_pct, $sid]);
        // Update items
        foreach ($_POST['items'] as $iid => $item) {
            $pdo->prepare("UPDATE grade_scale_items SET grade_letter=?,min_pct=?,max_pct=?,gpa_points=?,description=? WHERE id=? AND scale_id=?")
                ->execute([$item['grade_letter'],$item['min_pct'],$item['max_pct'],$item['gpa_points'],$item['description'],$iid,$sid]);
        }
        flash('Grade scale updated.');
        header('Location: index.php?tab=grades'); exit;
    }

    if ($action === 'add_scale') {
        $pdo->prepare("INSERT INTO grade_scales (name, pass_percentage) VALUES (?,?)")->execute([$_POST['name'], $_POST['pass_percentage']??50]);
        $sid = $pdo->lastInsertId();
        // Copy default items
        $items = $pdo->query("SELECT * FROM grade_scale_items WHERE scale_id=1")->fetchAll();
        foreach ($items as $it) {
            $pdo->prepare("INSERT INTO grade_scale_items (scale_id,grade_letter,min_pct,max_pct,gpa_points,description) VALUES (?,?,?,?,?,?)")
                ->execute([$sid,$it['grade_letter'],$it['min_pct'],$it['max_pct'],$it['gpa_points'],$it['description']]);
        }
        flash('New grade scale created.');
        header('Location: index.php?tab=grades&edit='.$sid); exit;
    }

    if ($action === 'set_default') {
        $pdo->query("UPDATE grade_scales SET is_default=0");
        $pdo->prepare("UPDATE grade_scales SET is_default=1 WHERE id=?")->execute([(int)$_POST['scale_id']]);
        flash('Default grade scale updated.');
        header('Location: index.php?tab=grades'); exit;
    }

    if ($action === 'add_item') {
        $pdo->prepare("INSERT INTO grade_scale_items (scale_id,grade_letter,min_pct,max_pct,gpa_points,description) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['scale_id'],$_POST['grade_letter'],$_POST['min_pct'],$_POST['max_pct'],$_POST['gpa_points']??0,$_POST['description']??'']);
        flash('Grade item added.');
        header('Location: index.php?tab=grades&edit='.$_POST['scale_id']); exit;
    }

    if ($action === 'delete_item') {
        $pdo->prepare("DELETE FROM grade_scale_items WHERE id=?")->execute([(int)$_POST['item_id']]);
        flash('Grade item removed.');
        header('Location: index.php?tab=grades&edit='.$_POST['scale_id']); exit;
    }

    // ── BRANCH ACTIONS ──
    if ($action === 'save_branch') {
        $bid = (int)($_POST['branch_id'] ?? 0);
        $d = $_POST;
        if ($bid) {
            $pdo->prepare("UPDATE branches SET name=?,code=?,address=?,phone=?,email=?,principal=?,is_active=? WHERE id=?")
                ->execute([$d['name'],$d['code'],$d['address']??null,$d['phone']??null,$d['email']??null,$d['principal']??null,$d['is_active']??1,$bid]);
            flash('Branch updated.');
        } else {
            $pdo->prepare("INSERT INTO branches (name,code,address,phone,email,principal,is_active) VALUES (?,?,?,?,?,?,?)")
                ->execute([$d['name'],$d['code'],$d['address']??null,$d['phone']??null,$d['email']??null,$d['principal']??null,$d['is_active']??1]);
            flash('Branch created.');
        }
        header('Location: index.php?tab=branches'); exit;
    }
}

$scales = $pdo->query("SELECT * FROM grade_scales ORDER BY is_default DESC, name")->fetchAll();
$edit_scale_id = (int)($_GET['edit'] ?? ($scales[0]['id'] ?? 1));
$edit_scale = $pdo->prepare("SELECT * FROM grade_scales WHERE id=?"); $edit_scale->execute([$edit_scale_id]); $edit_scale = $edit_scale->fetch();
$scale_items = $pdo->prepare("SELECT * FROM grade_scale_items WHERE scale_id=? ORDER BY min_pct DESC"); $scale_items->execute([$edit_scale_id]); $scale_items = $scale_items->fetchAll();
$branches = $pdo->query("SELECT * FROM branches ORDER BY is_main DESC, name")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-cog" style="color:var(--primary)"></i> Settings</h1><p>Configure grade scales, branches and system options</p></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #eee;padding-bottom:0">
  <?php foreach(['grades'=>['fas fa-star','Grade Scales'],'branches'=>['fas fa-building','Branches']] as $t=>[$ico,$lbl]): ?>
  <a href="?tab=<?= $t ?>" style="padding:10px 20px;text-decoration:none;font-weight:600;font-size:.9rem;border-radius:8px 8px 0 0;color:<?= $tab===$t?'var(--primary)':'#888' ?>;background:<?= $tab===$t?'#fff':'transparent' ?>;border:<?= $tab===$t?'2px solid #eee':'2px solid transparent' ?>;border-bottom:<?= $tab===$t?'2px solid #fff':'2px solid transparent' ?>;margin-bottom:-2px">
    <i class="<?= $ico ?>"></i> <?= $lbl ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'grades'): ?>
<div class="grid-2" style="align-items:start">
  <!-- Scale list -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> Grade Scales</h2></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Pass %</th><th>Default</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($scales as $sc): ?>
        <tr style="background:<?= $sc['id']==$edit_scale_id?'#f8f9ff':'' ?>">
          <td style="font-weight:600"><?= e($sc['name']) ?></td>
          <td><?= $sc['pass_percentage'] ?>%</td>
          <td>
            <?php if ($sc['is_default']): ?>
            <span class="badge badge-success">Default</span>
            <?php else: ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="set_default">
              <input type="hidden" name="scale_id" value="<?= $sc['id'] ?>">
              <button class="btn btn-sm btn-secondary">Set Default</button>
            </form>
            <?php endif; ?>
          </td>
          <td><a href="?tab=grades&edit=<?= $sc['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Edit</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <!-- Add new scale -->
    <div class="card">
      <div class="card-header"><h2><i class="fas fa-plus" style="color:var(--success)"></i> New Grade Scale</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="add_scale">
          <div class="form-group" style="margin-bottom:12px"><label>Scale Name</label><input name="name" required placeholder="e.g. Ethiopian National Scale"></div>
          <div class="form-group" style="margin-bottom:12px"><label>Pass Percentage</label><input type="number" step="0.01" name="pass_percentage" value="50" min="0" max="100"></div>
          <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Create Scale</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit scale items -->
  <?php if ($edit_scale): ?>
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-sliders-h" style="color:var(--warning)"></i> Edit: <?= e($edit_scale['name']) ?></h2>
      <?php if ($edit_scale['is_default']): ?><span class="badge badge-success">Default</span><?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_scale">
        <input type="hidden" name="scale_id" value="<?= $edit_scale['id'] ?>">
        <div class="form-group" style="margin-bottom:16px">
          <label>Pass Percentage (%)</label>
          <input type="number" step="0.01" name="pass_percentage" value="<?= $edit_scale['pass_percentage'] ?>" min="0" max="100" style="max-width:120px">
        </div>
        <div class="table-wrap"><table>
          <thead><tr><th>Grade</th><th>Min %</th><th>Max %</th><th>GPA</th><th>Description</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($scale_items as $item): ?>
          <tr>
            <td><input name="items[<?= $item['id'] ?>][grade_letter]" value="<?= e($item['grade_letter']) ?>" style="width:55px;text-align:center;font-weight:700"></td>
            <td><input type="number" step="0.01" name="items[<?= $item['id'] ?>][min_pct]" value="<?= $item['min_pct'] ?>" style="width:65px"></td>
            <td><input type="number" step="0.01" name="items[<?= $item['id'] ?>][max_pct]" value="<?= $item['max_pct'] ?>" style="width:65px"></td>
            <td><input type="number" step="0.01" name="items[<?= $item['id'] ?>][gpa_points]" value="<?= $item['gpa_points'] ?>" style="width:60px"></td>
            <td><input name="items[<?= $item['id'] ?>][description]" value="<?= e($item['description']??'') ?>" style="width:120px"></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <input type="hidden" name="scale_id" value="<?= $edit_scale['id'] ?>">
                <button class="btn btn-sm btn-danger" onclick="return confirm('Remove this grade?')"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <div style="margin-top:14px"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button></div>
      </form>

      <!-- Add item -->
      <hr style="margin:20px 0;border:none;border-top:1px solid #eee">
      <form method="POST" class="search-bar" style="flex-wrap:wrap;gap:8px">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add_item">
        <input type="hidden" name="scale_id" value="<?= $edit_scale['id'] ?>">
        <input name="grade_letter" placeholder="Grade" style="width:70px" required>
        <input type="number" step="0.01" name="min_pct" placeholder="Min%" style="width:80px" required>
        <input type="number" step="0.01" name="max_pct" placeholder="Max%" style="width:80px" required>
        <input type="number" step="0.01" name="gpa_points" placeholder="GPA" style="width:70px" value="0">
        <input name="description" placeholder="Description" style="width:130px">
        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add Row</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'branches'): ?>
<div class="grid-2" style="align-items:start">
  <!-- Branch list -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-building" style="color:var(--primary)"></i> Branches (<?= count($branches) ?>)</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Branch</th><th>Code</th><th>Principal</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($branches as $b): ?>
      <tr>
        <td>
          <div style="font-weight:700"><?= e($b['name']) ?></div>
          <?php if ($b['is_main']): ?><span class="badge badge-primary">Main</span><?php endif; ?>
        </td>
        <td><code><?= e($b['code']) ?></code></td>
        <td><?= e($b['principal']??'—') ?></td>
        <td><span class="badge badge-<?= $b['is_active']?'success':'secondary' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span></td>
        <td><a href="?tab=branches&edit=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <!-- Add/Edit branch -->
  <?php
  $edit_branch = null;
  if (isset($_GET['edit'])) { $eb = $pdo->prepare("SELECT * FROM branches WHERE id=?"); $eb->execute([(int)$_GET['edit']]); $edit_branch = $eb->fetch(); }
  ?>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-<?= $edit_branch?'edit':'plus' ?>" style="color:var(--success)"></i> <?= $edit_branch?'Edit Branch':'Add Branch' ?></h2></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="save_branch">
        <input type="hidden" name="branch_id" value="<?= $edit_branch['id']??0 ?>">
        <div class="form-grid">
          <div class="form-group"><label>Branch Name *</label><input name="name" required value="<?= e($edit_branch['name']??'') ?>"></div>
          <div class="form-group"><label>Code *</label><input name="code" required value="<?= e($edit_branch['code']??'') ?>" placeholder="e.g. NORTH"></div>
          <div class="form-group"><label>Principal</label><input name="principal" value="<?= e($edit_branch['principal']??'') ?>"></div>
          <div class="form-group"><label>Phone</label><input name="phone" value="<?= e($edit_branch['phone']??'') ?>"></div>
          <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($edit_branch['email']??'') ?>"></div>
          <div class="form-group"><label>Status</label>
            <select name="is_active">
              <option value="1" <?= ($edit_branch['is_active']??1)?'selected':'' ?>>Active</option>
              <option value="0" <?= isset($edit_branch) && !$edit_branch['is_active']?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="form-group full"><label>Address</label><textarea name="address"><?= e($edit_branch['address']??'') ?></textarea></div>
        </div>
        <div style="margin-top:16px;display:flex;gap:10px">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Branch</button>
          <?php if ($edit_branch): ?><a href="?tab=branches" class="btn btn-secondary">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>