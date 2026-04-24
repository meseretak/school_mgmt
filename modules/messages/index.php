<?php
require_once '../../includes/config.php';
auth_check();
$page_title = 'Messages'; $active_page = 'messages';
$uid = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$tab = $_GET['tab'] ?? 'inbox';

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $rtype   = $_POST['recipient_type'] ?? 'user';
    $class_id = (int)($_POST['class_id'] ?? 0) ?: null;
    $branch_id = (int)($_POST['branch_id'] ?? 0) ?: null;

    if ($body) {
        $pdo->prepare("INSERT INTO messages (sender_id,subject,body,recipient_type,class_id,branch_id) VALUES (?,?,?,?,?,?)")
            ->execute([$uid, $subject, $body, $rtype, $class_id, $branch_id]);
        $mid = $pdo->lastInsertId();

        // Determine recipients
        $recipient_ids = [];
        if ($rtype === 'user' && !empty($_POST['to_user_ids'])) {
            $recipient_ids = array_map('intval', (array)$_POST['to_user_ids']);
        } elseif ($rtype === 'group_students') {
            $q = $class_id
                ? $pdo->prepare("SELECT u.id FROM users u JOIN students s ON s.user_id=u.id JOIN enrollments en ON en.student_id=s.id WHERE en.class_id=? AND en.status='Enrolled'")
                : $pdo->query("SELECT u.id FROM users u WHERE u.role='student' AND u.is_active=1");
            if ($class_id) $q->execute([$class_id]); 
            $recipient_ids = array_column($q->fetchAll(), 'id');
        } elseif ($rtype === 'group_teachers') {
            $q = $pdo->query("SELECT id FROM users WHERE role='teacher' AND is_active=1");
            $recipient_ids = array_column($q->fetchAll(), 'id');
        } elseif ($rtype === 'group_class' && $class_id) {
            $q = $pdo->prepare("SELECT DISTINCT u.id FROM users u LEFT JOIN students s ON s.user_id=u.id LEFT JOIN enrollments en ON en.student_id=s.id LEFT JOIN teachers t ON t.user_id=u.id LEFT JOIN classes cl ON cl.teacher_id=t.id WHERE (en.class_id=? AND en.status='Enrolled') OR cl.id=?");
            $q->execute([$class_id, $class_id]);
            $recipient_ids = array_column($q->fetchAll(), 'id');
        } elseif ($rtype === 'broadcast') {
            $q = $pdo->query("SELECT id FROM users WHERE is_active=1");
            $recipient_ids = array_column($q->fetchAll(), 'id');
        }

        // Insert recipients (exclude self)
        $ins = $pdo->prepare("INSERT IGNORE INTO message_recipients (message_id,user_id) VALUES (?,?)");
        foreach (array_unique($recipient_ids) as $rid) {
            if ($rid != $uid) $ins->execute([$mid, $rid]);
        }
        // Notify recipients
        require_once '../../includes/notify.php';
        notify_message_received($pdo, $mid);
        flash('Message sent to '.count($recipient_ids).' recipient(s).');
        header('Location: index.php?tab=sent'); exit;
    }
}

// Mark read
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE message_recipients SET is_read=1, read_at=NOW() WHERE message_id=? AND user_id=?")->execute([(int)$_GET['read'], $uid]);
    header('Location: index.php?view='.(int)$_GET['read']); exit;
}

// View message
$view_msg = null;
if (isset($_GET['view'])) {
    $vm = $pdo->prepare("SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.id=?");
    $vm->execute([(int)$_GET['view']]); $view_msg = $vm->fetch();
    // Mark as read
    $pdo->prepare("UPDATE message_recipients SET is_read=1, read_at=NOW() WHERE message_id=? AND user_id=?")->execute([(int)$_GET['view'], $uid]);
}

// Inbox
$inbox = $pdo->prepare("SELECT m.*, u.name AS sender_name, mr.is_read FROM message_recipients mr JOIN messages m ON mr.message_id=m.id JOIN users u ON m.sender_id=u.id WHERE mr.user_id=? ORDER BY m.created_at DESC LIMIT 50");
$inbox->execute([$uid]); $inbox = $inbox->fetchAll();
$unread_count = count(array_filter($inbox, fn($m) => !$m['is_read']));

// Sent
$sent = $pdo->prepare("SELECT m.*, u.name AS sender_name, COUNT(mr.id) AS recipients FROM messages m JOIN users u ON m.sender_id=u.id LEFT JOIN message_recipients mr ON mr.message_id=m.id WHERE m.sender_id=? GROUP BY m.id ORDER BY m.created_at DESC LIMIT 50");
$sent->execute([$uid]); $sent = $sent->fetchAll();

// For compose: classes and users
$classes = $pdo->query("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id ORDER BY co.name")->fetchAll();
$all_users = $pdo->query("SELECT id, name, role FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
$branches = $pdo->query("SELECT * FROM branches WHERE is_active=1")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-envelope" style="color:var(--primary)"></i> Messages</h1><p><?= $unread_count ?> unread</p></div>
</div>

<div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start">
  <!-- Sidebar -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-body" style="padding:12px">
        <a href="?tab=compose" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:12px"><i class="fas fa-pen"></i> Compose</a>
        <?php foreach(['inbox'=>['fas fa-inbox','Inbox',$unread_count],'sent'=>['fas fa-paper-plane','Sent',0]] as $t=>[$ico,$lbl,$cnt]): ?>
        <a href="?tab=<?= $t ?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:<?= $tab===$t?'var(--primary)':'#555' ?>;background:<?= $tab===$t?'#f0f4ff':'' ?>;font-weight:<?= $tab===$t?'700':'400' ?>">
          <i class="<?= $ico ?>"></i> <?= $lbl ?>
          <?php if ($cnt > 0): ?><span class="badge badge-danger" style="margin-left:auto"><?= $cnt ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Main area -->
  <div>
    <?php if ($tab === 'compose'): ?>
    <div class="card">
      <div class="card-header"><h2><i class="fas fa-pen" style="color:var(--primary)"></i> New Message</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <div class="form-group" style="margin-bottom:14px">
            <label>Send To</label>
            <select name="recipient_type" id="rtype" onchange="toggleRecipient(this.value)">
              <option value="user">Specific User(s)</option>
              <option value="group_students">All Students (or class)</option>
              <option value="group_teachers">All Teachers</option>
              <option value="group_class">Entire Class (students + teacher)</option>
              <?php if ($role === 'admin'): ?><option value="broadcast">Everyone (Broadcast)</option><?php endif; ?>
            </select>
          </div>
          <div id="user_select" class="form-group" style="margin-bottom:14px">
            <label>Select User(s)</label>
            <select name="to_user_ids[]" multiple style="height:120px">
              <?php foreach ($all_users as $u): ?>
              <?php if ($u['id'] != $uid): ?>
              <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= $u['role'] ?>)</option>
              <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <small style="color:#888">Hold Ctrl/Cmd to select multiple</small>
          </div>
          <div id="class_select" class="form-group" style="margin-bottom:14px;display:none">
            <label>Select Class (optional)</label>
            <select name="class_id">
              <option value="">All classes</option>
              <?php foreach ($classes as $cl): ?>
              <option value="<?= $cl['id'] ?>"><?= e($cl['code'].' — '.$cl['course_name'].' ('.$cl['section'].')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:14px"><label>Subject</label><input name="subject" placeholder="Message subject"></div>
          <div class="form-group" style="margin-bottom:14px"><label>Message *</label><textarea name="body" required rows="6" placeholder="Write your message here..."></textarea></div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Message</button>
        </form>
      </div>
    </div>

    <?php elseif ($view_msg): ?>
    <div class="card">
      <div class="card-header">
        <h2><?= e($view_msg['subject'] ?: '(No subject)') ?></h2>
        <a href="?tab=<?= $tab ?>" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
      </div>
      <div class="card-body">
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #eee">
          <div class="avatar"><?= strtoupper(substr($view_msg['sender_name'],0,2)) ?></div>
          <div>
            <div style="font-weight:700"><?= e($view_msg['sender_name']) ?></div>
            <div style="font-size:.8rem;color:#888"><?= date('M j, Y g:i A', strtotime($view_msg['created_at'])) ?></div>
          </div>
          <span class="badge badge-info" style="margin-left:auto"><?= e($view_msg['recipient_type']) ?></span>
        </div>
        <div style="line-height:1.7;white-space:pre-wrap"><?= e($view_msg['body']) ?></div>
      </div>
    </div>

    <?php else: ?>
    <div class="card">
      <div class="card-header"><h2><i class="fas fa-<?= $tab==='inbox'?'inbox':'paper-plane' ?>" style="color:var(--primary)"></i> <?= ucfirst($tab) ?></h2></div>
      <?php $msgs = $tab === 'inbox' ? $inbox : $sent; ?>
      <?php if ($msgs): ?>
      <?php foreach ($msgs as $m): ?>
      <a href="?view=<?= $m['id'] ?>&tab=<?= $tab ?>" style="display:flex;gap:14px;padding:14px 20px;border-bottom:1px solid #f5f5f5;text-decoration:none;color:inherit;background:<?= ($tab==='inbox'&&!$m['is_read'])?'#f8f9ff':'#fff' ?>;transition:.15s" onmouseover="this.style.background='#f5f7ff'" onmouseout="this.style.background='<?= ($tab==='inbox'&&!$m['is_read'])?'#f8f9ff':'#fff' ?>'">
        <div class="avatar" style="width:38px;height:38px;font-size:.8rem;flex-shrink:0"><?= strtoupper(substr($m['sender_name']??'?',0,2)) ?></div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-weight:<?= ($tab==='inbox'&&!$m['is_read'])?'700':'500' ?>"><?= e($m['sender_name']??'Unknown') ?></span>
            <span style="font-size:.75rem;color:#aaa"><?= date('M j', strtotime($m['created_at'])) ?></span>
          </div>
          <div style="font-size:.88rem;font-weight:<?= ($tab==='inbox'&&!$m['is_read'])?'600':'400' ?>"><?= e($m['subject']?:'(No subject)') ?></div>
          <div style="font-size:.8rem;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e(substr($m['body'],0,80)) ?></div>
        </div>
        <?php if ($tab==='inbox' && !$m['is_read']): ?><div style="width:8px;height:8px;border-radius:50%;background:var(--primary);flex-shrink:0;margin-top:6px"></div><?php endif; ?>
      </a>
      <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align:center;padding:50px;color:#aaa"><i class="fas fa-envelope-open" style="font-size:2rem;display:block;margin-bottom:10px"></i>No messages</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleRecipient(val) {
  document.getElementById('user_select').style.display = val==='user' ? '' : 'none';
  document.getElementById('class_select').style.display = ['group_students','group_class'].includes(val) ? '' : 'none';
}
</script>
<?php require_once '../../includes/footer.php'; ?>