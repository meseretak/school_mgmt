<?php
require_once '../../includes/config.php';
require_once '../../includes/mailer.php';
require_once '../../includes/notify.php';
auth_check(['admin','accountant']);
$page_title = 'Payment Reminders'; $active_page = 'payments';

// Get all students with unpaid/overdue/partial payments
$unpaid = $pdo->query("
    SELECT s.id AS student_id, s.first_name, s.last_name, s.student_code,
           u.email,
           SUM(p.amount_due) AS total_due,
           SUM(p.amount_paid) AS total_paid,
           SUM(p.amount_due - p.amount_paid) AS balance,
           COUNT(p.id) AS payment_count,
           MAX(p.due_date) AS latest_due,
           GROUP_CONCAT(DISTINCT p.status ORDER BY p.status SEPARATOR ', ') AS statuses
    FROM payments p
    JOIN students s ON p.student_id=s.id
    JOIN users u ON s.user_id=u.id
    WHERE p.status IN ('Pending','Partial','Overdue')
    GROUP BY s.id
    ORDER BY balance DESC
")->fetchAll();

$sent = 0; $errors = [];

// Send individual reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder'])) {
    csrf_check();
    $student_ids = (array)($_POST['student_ids'] ?? []);
    $custom_msg  = trim($_POST['custom_message'] ?? '');

    foreach ($student_ids as $sid) {
        $sid = (int)$sid;
        // Get student details
        $s = $pdo->prepare("SELECT s.*, u.email, u.name FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
        $s->execute([$sid]); $s = $s->fetch();
        if (!$s || !$s['email']) continue;

        // Get their unpaid payments
        $pays = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id WHERE p.student_id=? AND p.status IN ('Pending','Partial','Overdue') ORDER BY p.due_date");
        $pays->execute([$sid]); $pays = $pays->fetchAll();
        if (!$pays) continue;

        $total_balance = array_sum(array_column($pays, 'amount_due')) - array_sum(array_column($pays, 'amount_paid'));

        // Build payment rows
        $rows = '';
        foreach ($pays as $p) {
            $bal = $p['amount_due'] - $p['amount_paid'];
            $status_color = ['Overdue'=>'#e63946','Pending'=>'#f4a261','Partial'=>'#4cc9f0'][$p['status']] ?? '#888';
            $rows .= "<tr>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0'>{$p['fee_name']}</td>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0'>" . ($p['year'] ?? '—') . "</td>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0'>\${$p['amount_due']}</td>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;color:var(--success)'>\${$p['amount_paid']}</td>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0;font-weight:700;color:#e63946'>\$$bal</td>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0'>" . ($p['due_date'] ? date('M j, Y', strtotime($p['due_date'])) : '—') . "</td>
                <td style='padding:10px 12px;border-bottom:1px solid #f0f0f0'><span style='background:{$status_color}22;color:{$status_color};padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700'>{$p['status']}</span></td>
            </tr>";
        }

        $custom_block = $custom_msg ? "<div style='background:#fff8e1;border-left:4px solid #f9c74f;padding:14px 16px;border-radius:0 8px 8px 0;margin-bottom:20px;font-size:14px;color:#555'>".nl2br(htmlspecialchars($custom_msg))."</div>" : '';

        $body = "
        <p style='font-size:16px;color:#333;margin-bottom:20px'>Dear <strong>{$s['first_name']} {$s['last_name']}</strong>,</p>
        <p style='color:#555;margin-bottom:20px;line-height:1.6'>This is a friendly reminder that you have outstanding fee payment(s) with <strong>" . APP_NAME . "</strong>. Please review the details below and make payment at your earliest convenience.</p>
        $custom_block
        <div style='background:#fff0f0;border-radius:10px;padding:16px 20px;margin-bottom:20px;text-align:center'>
            <div style='font-size:13px;color:#888;margin-bottom:4px'>Total Outstanding Balance</div>
            <div style='font-size:32px;font-weight:800;color:#e63946'>\$$total_balance</div>
        </div>
        <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #f0f0f0;border-radius:8px;overflow:hidden;font-size:13px'>
            <thead><tr style='background:#f8f9ff'>
                <th style='padding:10px 12px;text-align:left;color:#555'>Fee Type</th>
                <th style='padding:10px 12px;text-align:left;color:#555'>Year</th>
                <th style='padding:10px 12px;text-align:left;color:#555'>Due</th>
                <th style='padding:10px 12px;text-align:left;color:#555'>Paid</th>
                <th style='padding:10px 12px;text-align:left;color:#555'>Balance</th>
                <th style='padding:10px 12px;text-align:left;color:#555'>Due Date</th>
                <th style='padding:10px 12px;text-align:left;color:#555'>Status</th>
            </tr></thead>
            <tbody>$rows</tbody>
        </table>
        <p style='color:#888;font-size:13px;margin-top:20px;line-height:1.6'>
            Your Student ID: <strong style='color:#4361ee'>{$s['student_code']}</strong><br>
            If you have already made payment, please disregard this notice or contact the finance office.
        </p>";

        $html = email_template(
            'Outstanding Payment Reminder',
            $body,
            BASE_URL . '/modules/student/dashboard.php',
            'View My Payments'
        );

        $result = send_email($s['email'], $s['first_name'].' '.$s['last_name'], APP_NAME.' — Payment Reminder', $html);

        if ($result === true) {
            $sent++;
            log_activity($pdo, 'payment_reminder_sent', "Email reminder sent to {$s['email']} (balance: \$$total_balance)");
            // Also add in-app notification
            notify_user($pdo, $s['user_id'] ?? 0,
                '💳 Payment Reminder',
                "You have an outstanding balance of \$$total_balance. Please check your payments.",
                0
            );
        } else {
            $errors[] = "Failed to send to {$s['email']}: $result";
        }
    }

    if ($sent > 0) flash("✅ Reminder sent to $sent student(s).");
    if ($errors) flash(implode('; ', $errors), 'error');
    header('Location: remind.php'); exit;
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-envelope" style="color:var(--warning)"></i> Payment Reminders</h1>
    <p>Send email reminders to students with outstanding balances</p>
  </div>
  <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Payments</a>
</div>

<?php if (!MAIL_ENABLED): ?>
<div class="alert alert-warning">
  <i class="fas fa-exclamation-triangle"></i>
  <div>
    <strong>Email not configured.</strong> To enable email sending:
    <ol style="margin:8px 0 0 16px;font-size:.88rem">
      <li>Run <code>composer require phpmailer/phpmailer</code> in <code>C:\xampp1\htdocs\school_mgmt</code></li>
      <li>Create a Gmail App Password at <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com</a></li>
      <li>Edit <code>includes/config.php</code> — fill in <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code>, <code>MAIL_FROM</code></li>
      <li>Set <code>MAIL_ENABLED</code> to <code>true</code></li>
    </ol>
    <strong>In-app notifications will still be sent even without email.</strong>
  </div>
</div>
<?php endif; ?>

<!-- Summary stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?= count($unpaid) ?></h3><p>Students with Balance</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-dollar-sign"></i></div><div class="stat-info"><h3>$<?= number_format(array_sum(array_column($unpaid,'balance')),0) ?></h3><p>Total Outstanding</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3><?= count(array_filter($unpaid, fn($r) => str_contains($r['statuses'],'Overdue'))) ?></h3><p>Overdue</p></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-envelope"></i></div><div class="stat-info"><h3><?= MAIL_ENABLED ? 'Active' : 'Disabled' ?></h3><p>Email Status</p></div></div>
</div>

<?php if ($unpaid): ?>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="send_reminder" value="1">

  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h2><i class="fas fa-comment-alt" style="color:var(--primary)"></i> Custom Message (optional)</h2></div>
    <div class="card-body">
      <textarea name="custom_message" rows="3" placeholder="Add a personal note to include in the reminder email (e.g. 'Payment deadline is Dec 31. Late fees apply after this date.')"></textarea>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-list" style="color:var(--danger)"></i> Students with Outstanding Payments</h2>
      <div style="display:flex;gap:8px">
        <button type="button" onclick="selectAll(true)" class="btn btn-sm btn-secondary">Select All</button>
        <button type="button" onclick="selectAll(false)" class="btn btn-sm btn-secondary">Deselect All</button>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Send reminder emails to selected students?')">
          <i class="fas fa-paper-plane"></i> Send Reminders
        </button>
      </div>
    </div>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th><input type="checkbox" id="checkAll" onchange="selectAll(this.checked)"></th>
          <th>Student</th><th>ID</th><th>Email</th>
          <th>Total Due</th><th>Paid</th><th>Balance</th>
          <th>Latest Due Date</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($unpaid as $s):
        $is_overdue = str_contains($s['statuses'], 'Overdue');
      ?>
      <tr style="background:<?= $is_overdue?'#fff8f8':'' ?>">
        <td><input type="checkbox" name="student_ids[]" value="<?= $s['student_id'] ?>" class="row-check" checked></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="avatar" style="width:32px;height:32px;font-size:.72rem"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
            <div>
              <div style="font-weight:600"><?= e($s['first_name'].' '.$s['last_name']) ?></div>
            </div>
          </div>
        </td>
        <td style="font-family:monospace;font-size:.82rem;color:var(--primary)"><?= e($s['student_code']) ?></td>
        <td style="font-size:.82rem"><?= e($s['email']) ?></td>
        <td>$<?= number_format($s['total_due'],2) ?></td>
        <td style="color:var(--success)">$<?= number_format($s['total_paid'],2) ?></td>
        <td style="font-weight:700;color:var(--danger)">$<?= number_format($s['balance'],2) ?></td>
        <td style="color:<?= $is_overdue?'var(--danger)':'inherit' ?>">
          <?= $s['latest_due'] ? date('M j, Y', strtotime($s['latest_due'])) : '—' ?>
          <?php if ($is_overdue): ?><span class="badge badge-danger" style="margin-left:4px">Overdue</span><?php endif; ?>
        </td>
        <td><span style="font-size:.78rem;color:#888"><?= e($s['statuses']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div style="padding:14px 20px;background:#f8f9ff;border-top:1px solid #eee;display:flex;justify-content:flex-end">
      <button type="submit" class="btn btn-primary" onclick="return confirm('Send reminder emails to selected students?')">
        <i class="fas fa-paper-plane"></i> Send Reminders to Selected
      </button>
    </div>
  </div>
</form>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:60px;color:#aaa">
  <i class="fas fa-check-circle" style="font-size:3rem;color:var(--success);display:block;margin-bottom:16px"></i>
  <p style="font-size:1rem">All students are up to date with payments!</p>
</div></div>
<?php endif; ?>

<script>
function selectAll(checked) {
  document.querySelectorAll('.row-check').forEach(cb => cb.checked = checked);
  document.getElementById('checkAll').checked = checked;
}
</script>
<?php require_once '../../includes/footer.php'; ?>