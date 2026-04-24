<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title = 'API Keys'; $active_page = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $perms = $_POST['permissions'] ?? [];
        if (!$name) { flash('Name required.','error'); header('Location: api_keys.php'); exit; }
        $key    = bin2hex(random_bytes(24)); // 48 char key
        $secret = bin2hex(random_bytes(32)); // 64 char secret
        $pdo->prepare("INSERT INTO api_keys (name,api_key,secret_key,permissions,created_by) VALUES (?,?,?,?,?)")
            ->execute([$name, $key, $secret, json_encode($perms), $_SESSION['user']['id']]);
        flash("API key created. Key: <code>$key</code> — copy it now, it won't be shown again.");
        header('Location: api_keys.php'); exit;
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['key_id'];
        $pdo->prepare("UPDATE api_keys SET is_active=NOT is_active WHERE id=?")->execute([$id]);
        flash('API key updated.'); header('Location: api_keys.php'); exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM api_keys WHERE id=?")->execute([(int)$_POST['key_id']]);
        flash('API key deleted.'); header('Location: api_keys.php'); exit;
    }
}

$keys = $pdo->query("SELECT ak.*, u.name AS created_by_name FROM api_keys ak LEFT JOIN users u ON ak.created_by=u.id ORDER BY ak.created_at DESC")->fetchAll();
$base = BASE_URL . '/api/v1.php';
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-key" style="color:var(--primary)"></i> API Keys</h1>
    <p style="color:var(--muted)">Manage API keys for external integrations (banks, payment gateways, etc.)</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'"><i class="fas fa-plus"></i> Create API Key</button>
</div>

<!-- API Docs quick reference -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2><i class="fas fa-book" style="color:var(--info)"></i> API Reference</h2></div>
  <div class="card-body" style="font-size:.85rem">
    <div style="background:#0f172a;color:#e2e8f0;border-radius:10px;padding:16px;font-family:monospace;font-size:.8rem;line-height:1.8">
      <div style="color:#94a3b8;margin-bottom:8px"># Base URL</div>
      <div style="color:#60a5fa"><?= BASE_URL ?>/api/v1.php</div>
      <div style="color:#94a3b8;margin-top:12px;margin-bottom:8px"># Authentication</div>
      <div>Authorization: Bearer <span style="color:#fbbf24">YOUR_API_KEY</span></div>
      <div style="color:#94a3b8;margin-top:12px;margin-bottom:8px"># Endpoints</div>
      <?php foreach([
        ['GET','ping','Health check'],
        ['GET','students','List students (?search=&status=Active)'],
        ['GET','students&id=1','Get single student'],
        ['GET','teachers','List teachers'],
        ['GET','classes','List classes'],
        ['GET','enrollments','List enrollments (?student_id=&class_id=)'],
        ['GET','payments','List payments (?student_id=&status=)'],
        ['POST','payments','Create payment record'],
        ['POST','payment_confirm','Confirm payment (bank callback)'],
        ['POST','webhook?provider=chapa','Chapa payment webhook'],
        ['POST','webhook?provider=bank','Bank payment webhook'],
        ['GET','fee_types','List fee types'],
        ['GET','academic_years','List academic years'],
      ] as [$m,$ep,$desc]): ?>
      <div><span style="color:<?= $m==='GET'?'#34d399':'#f472b6' ?>;min-width:40px;display:inline-block"><?= $m ?></span> <span style="color:#60a5fa">?endpoint=<?= $ep ?></span> <span style="color:#64748b"> — <?= $desc ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Keys table -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> API Keys (<?= count($keys) ?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>API Key</th><th>Permissions</th><th>Status</th><th>Last Used</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($keys as $k): ?>
    <tr>
      <td style="font-weight:600"><?= e($k['name']) ?></td>
      <td><code style="font-size:.75rem;background:#f1f5f9;padding:3px 8px;border-radius:6px"><?= substr($k['api_key'],0,12) ?>...<?= substr($k['api_key'],-6) ?></code>
        <button onclick="navigator.clipboard.writeText('<?= $k['api_key'] ?>')" class="btn btn-sm btn-secondary" style="margin-left:4px;padding:2px 8px;font-size:.7rem"><i class="fas fa-copy"></i></button>
      </td>
      <td style="font-size:.78rem">
        <?php $perms = json_decode($k['permissions']??'[]',true)??[];
        foreach($perms as $p): ?><span class="badge badge-info" style="margin:1px;font-size:.65rem"><?= e($p) ?></span><?php endforeach; ?>
      </td>
      <td><span class="badge badge-<?= $k['is_active']?'success':'secondary' ?>"><?= $k['is_active']?'Active':'Inactive' ?></span></td>
      <td style="font-size:.8rem"><?= $k['last_used_at']?date('M j, Y g:i A',strtotime($k['last_used_at'])):'Never' ?></td>
      <td style="font-size:.8rem"><?= date('M j, Y',strtotime($k['created_at'])) ?></td>
      <td>
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
          <button class="btn btn-sm btn-secondary"><?= $k['is_active']?'Disable':'Enable' ?></button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this API key?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="key_id" value="<?= $k['id'] ?>">
          <button class="btn btn-sm btn-danger btn-icon"><i class="fas fa-trash"></i></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(!$keys): ?><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">No API keys yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<!-- Create Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:520px;max-width:98vw">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <h3 style="font-weight:700"><i class="fas fa-plus" style="color:var(--success)"></i> Create API Key</h3>
    <button onclick="document.getElementById('createModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">&times;</button>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-group" style="margin-bottom:16px">
      <label>Key Name * <small style="color:var(--muted)">(e.g. "Bank Integration", "Payment Gateway")</small></label>
      <input name="name" required placeholder="e.g. CBE Bank Integration">
    </div>
    <div class="form-group" style="margin-bottom:18px">
      <label>Permissions</label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px">
        <?php foreach([
          ['*','Full Access (all endpoints)'],
          ['students:read','Read Students'],
          ['teachers:read','Read Teachers'],
          ['classes:read','Read Classes'],
          ['enrollments:read','Read Enrollments'],
          ['payments:read','Read Payments'],
          ['payments:write','Create/Update Payments'],
        ] as [$val,$label]): ?>
        <label style="display:flex;align-items:center;gap:8px;font-size:.84rem;cursor:pointer;padding:6px 10px;border:1px solid var(--border);border-radius:8px">
          <input type="checkbox" name="permissions[]" value="<?= $val ?>" style="accent-color:var(--primary)">
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Generate Key</button>
      <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>
<script>
document.getElementById('createModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
</script>
<?php require_once '../../includes/footer.php'; ?>