<?php
require_once '../../includes/config.php';
auth_check(['admin','accountant']);
$page_title='Record Payment'; $active_page='payments';
$students  = $pdo->query("SELECT * FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
$fee_types = $pdo->query("SELECT * FROM fee_types WHERE is_active=1 ORDER BY name")->fetchAll();
$years     = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$preselect = (int)($_GET['student_id']??0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d=$_POST;
    $status = 'Pending';
    if ((float)$d['amount_paid'] >= (float)$d['amount_due']) $status = 'Paid';
    elseif ((float)$d['amount_paid'] > 0) $status = 'Partial';
    $paid_date = $d['amount_paid']>0 ? date('Y-m-d') : null;
    $pdo->prepare("INSERT INTO payments (student_id,fee_type_id,academic_year_id,amount_due,amount_paid,due_date,paid_date,method,reference_no,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$d['student_id'],$d['fee_type_id'],$d['academic_year_id']??null,$d['amount_due'],$d['amount_paid']??0,$d['due_date']??null,$paid_date,$d['method']??'Cash',$d['reference_no']??null,$status,$d['notes']??null,$_SESSION['user']['id']]);
    $pay_id = $pdo->lastInsertId();
    require_once '../../includes/notify.php';
    notify_payment_recorded($pdo, $pay_id);
    log_activity($pdo, 'payment_recorded', "Payment recorded for student ID {$d['student_id']}");
    flash('Payment recorded successfully.');
    header('Location: index.php'); exit;
}
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Record Payment</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <div class="form-grid">
    <div class="form-group"><label>Student *</label>
      <select name="student_id" required>
        <option value="">Select Student</option>
        <?php foreach($students as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $preselect==$s['id']?'selected':'' ?>><?= e($s['first_name'].' '.$s['last_name'].' ('.$s['student_code'].')') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Fee Type *</label>
      <select name="fee_type_id" required id="fee_type">
        <option value="">Select Fee Type</option>
        <?php foreach($fee_types as $ft): ?>
        <option value="<?= $ft['id'] ?>" data-amount="<?= $ft['amount'] ?>"><?= e($ft['name'].' ($'.$ft['amount'].')') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Academic Year</label>
      <select name="academic_year_id">
        <option value="">Select Year</option>
        <?php foreach($years as $y): ?><option value="<?= $y['id'] ?>" <?= $y['is_current']?'selected':'' ?>><?= e($y['label']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Amount Due ($) *</label><input type="number" step="0.01" name="amount_due" id="amount_due" required value="<?= e($_POST['amount_due']??'') ?>"></div>
    <div class="form-group"><label>Amount Paid ($)</label><input type="number" step="0.01" name="amount_paid" value="<?= e($_POST['amount_paid']??0) ?>"></div>
    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="<?= e($_POST['due_date']??'') ?>"></div>
    <div class="form-group"><label>Payment Method</label>
      <select name="method">
        <?php foreach(['Cash','Bank Transfer','Card','Online','Cheque'] as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Reference No.</label><input name="reference_no" placeholder="Receipt/Transaction number" value="<?= e($_POST['reference_no']??'') ?>"></div>
    <div class="form-group full"><label>Notes</label><textarea name="notes"><?= e($_POST['notes']??'') ?></textarea></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Record Payment</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<script>
document.getElementById('fee_type').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    document.getElementById('amount_due').value = opt.dataset.amount || '';
});
</script>
<?php require_once '../../includes/footer.php'; ?>