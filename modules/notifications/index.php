<?php
require_once '../../includes/config.php';
auth_check();
$page_title = 'Notifications'; $active_page = 'notifications';
$uid = $_SESSION['user']['id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    flash('All notifications marked as read.');
    header('Location: index.php'); exit;
}
// Mark single as read
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['read'], $uid]);
    header('Location: index.php'); exit;
}
// Delete
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([(int)$_GET['delete'], $uid]);
    flash('Notification deleted.');
    header('Location: index.php'); exit;
}

$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM notifications WHERE user_id=?";
if ($filter === 'unread') $sql .= " AND is_read=0";
$sql .= " ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql); $stmt->execute([$uid]);
$notifs = $stmt->fetchAll();
$unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$unread->execute([$uid]); $unread = $unread->fetchColumn();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Notifications</h1><p><?= $unread ?> unread</p></div>
  <div style="display:flex;gap:8px">
    <?php if ($unread > 0): ?>
    <a href="?mark_all=1" class="btn btn-secondary"><i class="fas fa-check-double"></i> Mark All Read</a>
    <?php endif; ?>
  </div>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px">
  <a href="?filter=all" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-secondary' ?>">All (<?= count($notifs) ?>)</a>
  <a href="?filter=unread" class="btn btn-sm <?= $filter==='unread'?'btn-primary':'btn-secondary' ?>">Unread (<?= $unread ?>)</a>
</div>

<div class="card">
  <?php if ($notifs): ?>
  <?php foreach ($notifs as $n): ?>
  <div style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid #f0f0f0;background:<?= $n['is_read']?'#fff':'#f8f9ff' ?>">
    <div style="width:40px;height:40px;border-radius:50%;background:<?= $n['is_read']?'#e9ecef':'var(--primary)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <i class="fas fa-bell" style="color:<?= $n['is_read']?'#aaa':'#fff' ?>;font-size:.9rem"></i>
    </div>
    <div style="flex:1">
      <div style="font-weight:<?= $n['is_read']?'400':'700' ?>;margin-bottom:2px"><?= e($n['title']) ?></div>
      <div style="font-size:.88rem;color:#666;margin-bottom:4px"><?= e($n['message']) ?></div>
      <div style="font-size:.78rem;color:#aaa"><?= date('M j, Y g:i A', strtotime($n['created_at'])) ?></div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0">
      <?php if (!$n['is_read']): ?>
      <a href="?read=<?= $n['id'] ?>" class="btn btn-sm btn-secondary" title="Mark read"><i class="fas fa-check"></i></a>
      <?php endif; ?>
      <a href="?delete=<?= $n['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this notification?')" title="Delete"><i class="fas fa-trash"></i></a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <div style="text-align:center;padding:60px;color:#aaa">
    <i class="fas fa-bell-slash" style="font-size:2.5rem;margin-bottom:12px;display:block"></i>
    <p>No notifications<?= $filter==='unread'?' to read':'' ?>.</p>
  </div>
  <?php endif; ?>
</div>
<?php require_once '../../includes/footer.php'; ?>