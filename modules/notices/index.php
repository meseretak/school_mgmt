<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student','parent','librarian']);
$page_title = 'Notice Board'; $active_page = 'notices';
$uid = $_SESSION['user']['id']; $role = $_SESSION['user']['role'];

// Auto-delete expired notices
$pdo->query("UPDATE notices SET is_active=0 WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");

// Admin actions
if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $pdo->prepare("INSERT INTO notices (title,body,audience,posted_by,post_date,expiry_date,is_active) VALUES (?,?,?,?,?,?,1)")
            ->execute([trim($_POST['title']),trim($_POST['body']),$_POST['audience']??'all',$uid,$_POST['post_date']??date('Y-m-d'),$_POST['expiry_date']??null]);
        $notice_id = $pdo->lastInsertId();
        require_once '../../includes/notify.php';
        notify_notice_posted($pdo, $notice_id);
        log_activity($pdo, 'notice_created', 'Created notice: '.$_POST['title']);
        flash('Notice posted successfully.');
        header('Location: index.php'); exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM notices WHERE id=?")->execute([(int)$_POST['notice_id']]);
        flash('Notice deleted.');
        header('Location: index.php'); exit;
    }

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE notices SET is_active=NOT is_active WHERE id=?")->execute([(int)$_POST['notice_id']]);
        header('Location: index.php'); exit;
    }
}

// Filters
$filter_audience = $_GET['audience'] ?? '';
$filter_status   = $_GET['status'] ?? 'active';

$sql = "SELECT n.*, u.name AS posted_by_name FROM notices n JOIN users u ON n.posted_by=u.id WHERE 1=1";
$params = [];

// Non-admins only see notices for their audience
if (!is_admin()) {
    $sql .= " AND n.is_active=1 AND (n.audience='all' OR n.audience=?)";
    $params[] = $role === 'teacher' ? 'teachers' : 'students';
} else {
    if ($filter_audience) { $sql .= " AND n.audience=?"; $params[] = $filter_audience; }
    if ($filter_status === 'active')   { $sql .= " AND n.is_active=1"; }
    if ($filter_status === 'inactive') { $sql .= " AND n.is_active=0"; }
    if ($filter_status === 'expired')  { $sql .= " AND n.expiry_date < CURDATE()"; }
}
$sql .= " ORDER BY n.post_date DESC, n.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $notices = $stmt->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-bullhorn" style="color:var(--warning)"></i> Notice Board</h1><p><?= count($notices) ?> notice(s)</p></div>
</div>

<?php if (is_admin()): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-plus" style="color:var(--success)"></i> Post New Notice</h2></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group full"><label>Title *</label><input name="title" required placeholder="Notice title" value="<?= e($_POST['title']??'') ?>"></div>
        <div class="form-group"><label>Audience</label>
          <select name="audience">
            <option value="all">Everyone</option>
            <option value="teachers">Teachers Only</option>
            <option value="students">Students Only</option>
            <option value="admin">Admin Only</option>
          </select>
        </div>
        <div class="form-group"><label>Post Date</label><input type="date" name="post_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Expiry Date <small style="color:#aaa">(auto-hides after this date)</small></label><input type="date" name="expiry_date" min="<?= date('Y-m-d') ?>"></div>
        <div class="form-group full"><label>Message *</label><textarea name="body" required rows="4" placeholder="Write the notice content..."><?= e($_POST['body']??'') ?></textarea></div>
      </div>
      <div style="margin-top:14px"><button type="submit" class="btn btn-primary"><i class="fas fa-bullhorn"></i> Post Notice</button></div>
    </form>
  </div>
</div>

<!-- Admin filters -->
<div class="card" style="margin-bottom:20px"><div class="card-body">
  <form method="GET" class="search-bar">
    <select name="audience" onchange="this.form.submit()">
      <option value="">All Audiences</option>
      <?php foreach(['all'=>'Everyone','teachers'=>'Teachers','students'=>'Students','admin'=>'Admin'] as $v=>$l): ?>
      <option value="<?= $v ?>" <?= $filter_audience===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" onchange="this.form.submit()">
      <option value="active" <?= $filter_status==='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $filter_status==='inactive'?'selected':'' ?>>Inactive</option>
      <option value="expired" <?= $filter_status==='expired'?'selected':'' ?>>Expired</option>
      <option value="" <?= $filter_status===''?'selected':'' ?>>All</option>
    </select>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>
<?php endif; ?>

<!-- Notices list -->
<div style="display:flex;flex-direction:column;gap:16px">
<?php if ($notices): ?>
<?php foreach ($notices as $n):
  $expired = $n['expiry_date'] && strtotime($n['expiry_date']) < time();
  $aud_colors = ['all'=>'#4361ee','teachers'=>'#7209b7','students'=>'#2dc653','admin'=>'#e63946'];
  $aud_icons  = ['all'=>'fas fa-globe','teachers'=>'fas fa-chalkboard-teacher','students'=>'fas fa-user-graduate','admin'=>'fas fa-user-shield'];
  $color = $aud_colors[$n['audience']] ?? '#4361ee';
  $icon  = $aud_icons[$n['audience']] ?? 'fas fa-bullhorn';
?>
<div class="card" style="border-left:4px solid <?= $color ?>;opacity:<?= (!$n['is_active']||$expired)?'.6':'1' ?>">
  <div class="card-body" style="padding:18px 22px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
          <span style="background:<?= $color ?>22;color:<?= $color ?>;border-radius:20px;padding:3px 10px;font-size:.75rem;font-weight:700">
            <i class="<?= $icon ?>"></i> <?= ucfirst($n['audience']) ?>
          </span>
          <?php if (!$n['is_active']): ?><span class="badge badge-secondary">Inactive</span><?php endif; ?>
          <?php if ($expired): ?><span class="badge badge-danger">Expired</span><?php endif; ?>
          <span style="font-size:.78rem;color:#aaa"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($n['post_date'])) ?></span>
          <?php if ($n['expiry_date']): ?>
          <span style="font-size:.78rem;color:<?= $expired?'var(--danger)':'#aaa' ?>"><i class="fas fa-clock"></i> Expires: <?= date('M j, Y', strtotime($n['expiry_date'])) ?></span>
          <?php endif; ?>
        </div>
        <h3 style="font-size:1.05rem;font-weight:700;color:#1a1a2e;margin-bottom:8px"><?= e($n['title']) ?></h3>
        <p style="color:#555;line-height:1.7;font-size:.9rem"><?= nl2br(e($n['body'])) ?></p>
        <div style="margin-top:10px;font-size:.78rem;color:#aaa">
          <i class="fas fa-user"></i> Posted by <?= e($n['posted_by_name']) ?>
        </div>
      </div>
      <?php if (is_admin()): ?>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
          <button class="btn btn-sm btn-secondary" title="<?= $n['is_active']?'Deactivate':'Activate' ?>">
            <i class="fas fa-<?= $n['is_active']?'eye-slash':'eye' ?>"></i>
          </button>
        </form>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this notice?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="notice_id" value="<?= $n['id'] ?>">
          <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:60px;color:#aaa">
  <i class="fas fa-bullhorn" style="font-size:3rem;display:block;margin-bottom:16px;opacity:.2"></i>
  <p>No notices at the moment.</p>
</div></div>
<?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>