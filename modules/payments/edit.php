<?php
require_once '../../includes/config.php';
auth_check(['admin','accountant']);
$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare("SELECT * FROM payments WHERE id=?"); $stmt->execute([$id]); $p=$stmt->fetch();
if (!$p) { flash('Not found','error'); header('Location: index.php'); exit; }
$students=$pdo->query("SELECT * FROM students ORDER BY first_name")->fetchAll();
$fee_types=$pdo->query("SELECT * FROM fee_types WHERE is_active=1")->fetchAll();
$years=$pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d=$_POST;
    $status='Pending';
    if ((float)$d['amount_paid']>=(float)$d['amount_due']) $status='Paid';
    elseif ((float)$d['amount_paid']>0) $status='Partial';
    if ($d['status']==='Waived') $status='Waived';
    $paid_date = $d['amount_paid']>0 ? ($p['paid_date']??date('Y-m-d')) : null;
    $pdo->prepare("UPDATE payments SET student_id=?,fee_type_id=?,academic_year_id=?,amount_due=?,amount_paid=?,due_date=?,paid_date=?,method=?,reference_no=?,status=?,notes=? WHERE id=?")
        ->execute([$d['student_id'],$d['fee_type_id'],$d['academic_year_id']??null,$d['amount_due'],$d['amount_paid']??0,$d['due_date']??null,$paid_date,$d['method']??'Cash',$d['reference_no']??null,$status,$d['notes']??null,$id]);
    flash('Payment updated.'); header('Location: index.php'); exit;
}
$page_title='Edit Payment'; $active_page='payments';
require_once '../../includes/header.php';
?>
<div class="page-header"><div><h1>Edit Payment</h1></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card"><div class="card-body"><form method="POST">
  <div class="form-grid">
    <div class="form-group"><label>Student</label><select name="student_id"><?php foreach($students as $s): ?><option value="<?= $s['id'] ?>" <?= $p['student_id']==$s['id']?'selected':'' ?>><?= e($s['first_name'].' '.$s['last_name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Fee Type</label><select name="fee_type_id"><?php foreach($fee_types as $ft): ?><option value="<?= $ft['id'] ?>" <?= $p['fee_type_id']==$ft['id']?'selected':'' ?>><?= e($ft['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Academic Year</label><select name="academic_year_id"><option value="">None</option><?php foreach($years as $y): ?><option value="<?= $y['id'] ?>" <?= $p['academic_year_id']==$y['id']?'selected':'' ?>><?= e($y['label']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Amount Due</label><input type="number" step="0.01" name="amount_due" value="<?= $p['amount_due'] ?>"></div>
    <div class="form-group"><label>Amount Paid</label><input type="number" step="0.01" name="amount_paid" value="<?= $p['amount_paid'] ?>"></div>
    <div class="form-group"><label>Due Date</label><input type="date" name="due_date" value="<?= e($p['due_date']??'') ?>"></div>
    <div class="form-group"><label>Method</label><select name="method"><?php foreach(['Cash','Bank Transfer','Card','Online','Cheque'] as $m): ?><option value="<?= $m ?>" <?= $p['method']===$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Reference No.</label><input name="reference_no" value="<?= e($p['reference_no']??'') ?>"></div>
    <div class="form-group"><label>Override Status</label><select name="status"><?php foreach(['Pending','Partial','Paid','Overdue','Waived'] as $s): ?><option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="form-group full"><label>Notes</label><textarea name="notes"><?= e($p['notes']??'') ?></textarea></div>
  </div>
  <div style="margin-top:24px;display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </div>
</form></div></div>
<?php require_once '../../includes/footer.php'; ?>