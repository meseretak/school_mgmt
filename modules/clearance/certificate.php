<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$id=(int)($_GET['id']??0);
$cr=$pdo->prepare("SELECT cr.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,s.nationality,s.enrollment_date,s.dob FROM clearance_requests cr JOIN students s ON cr.student_id=s.id WHERE cr.id=? AND cr.status='Completed'");
$cr->execute([$id]); $cr=$cr->fetch();
if (!$cr) die('Clearance not found or not completed.');
$items=$pdo->prepare("SELECT ci.*,cd.name AS dept_name,u.name AS signed_by FROM clearance_items ci JOIN clearance_departments cd ON ci.department_id=cd.id LEFT JOIN users u ON ci.signed_by=u.id WHERE ci.clearance_id=? ORDER BY cd.sort_order");
$items->execute([$id]); $items=$items->fetchAll();
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Clearance Certificate</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;padding:40px;color:#222;background:#fff}
.header{text-align:center;border-bottom:3px double #4361ee;padding-bottom:20px;margin-bottom:24px}
.logo{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#4361ee,#7209b7);display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px}
h1{font-size:22px;color:#4361ee;margin-bottom:4px}
.cert-no{font-size:11px;color:#888;margin-top:4px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;background:#f8f9ff;padding:16px;border-radius:8px;margin-bottom:20px}
.info-grid div{display:flex;gap:8px;font-size:13px}
.info-grid span:first-child{color:#888;min-width:130px}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
th{background:#f0f2f8;padding:8px 12px;text-align:left;font-size:12px;color:#555}
td{padding:8px 12px;border-bottom:1px solid #f0f0f0;font-size:13px}
.cleared{color:#2dc653;font-weight:700}
.footer{margin-top:40px;display:flex;justify-content:space-between;font-size:11px;color:#aaa;border-top:1px solid #eee;padding-top:14px}
.stamp{width:90px;height:90px;border:3px double #4361ee;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:.4;transform:rotate(-12deg);margin-left:auto}
@media print{.no-print{display:none}body{padding:20px}}
</style></head><body>
<div class="no-print" style="margin-bottom:16px">
  <button onclick="window.print()" style="background:#4361ee;color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer">🖨 Print Certificate</button>
  <a href="index.php?id=<?=$id?>" style="margin-left:10px;color:#666;text-decoration:none">← Back</a>
</div>
<div class="header">
  <div class="logo"><svg width="36" height="36" viewBox="0 0 24 24" fill="white"><path d="M12 3L1 9L12 15L21 10.09V17H23V9L12 3Z"/><path d="M5 13.18V17.18L12 21L19 17.18V13.18L12 17L5 13.18Z" opacity=".8"/></svg></div>
  <h1><?= APP_NAME ?></h1>
  <div style="font-size:16px;font-weight:700;color:#333;margin-top:6px">STUDENT CLEARANCE CERTIFICATE</div>
  <div class="cert-no">Certificate No: CLR-<?= str_pad($id,5,'0',STR_PAD_LEFT) ?> · Issued: <?= date('F j, Y') ?></div>
</div>
<p style="margin-bottom:16px;font-size:13px">This is to certify that the following student has been duly cleared from all departments of <strong><?= APP_NAME ?></strong>:</p>
<div class="info-grid">
  <div><span>Student Name:</span><strong><?= e($cr['student_name']) ?></strong></div>
  <div><span>Student Code:</span><strong><?= e($cr['student_code']) ?></strong></div>
  <div><span>Reason:</span><?= e($cr['reason']) ?></div>
  <div><span>Clearance Date:</span><?= date('F j, Y',strtotime($cr['completed_at'])) ?></div>
  <?php if ($cr['enrollment_date']): ?><div><span>Enrollment Date:</span><?= date('M j, Y',strtotime($cr['enrollment_date'])) ?></div><?php endif; ?>
  <?php if ($cr['nationality']): ?><div><span>Nationality:</span><?= e($cr['nationality']) ?></div><?php endif; ?>
</div>
<table>
  <thead><tr><th>#</th><th>Department</th><th>Status</th><th>Cleared By</th><th>Date</th><th>Items Returned</th></tr></thead>
  <tbody>
  <?php foreach ($items as $i=>$item): ?>
  <tr>
    <td><?=$i+1?></td>
    <td style="font-weight:600"><?=e($item['dept_name'])?></td>
    <td class="cleared"><i class="fas fa-check-circle"></i> <?=e($item['status'])?></td>
    <td><?=e($item['signed_by']??'—')?></td>
    <td style="font-size:.8rem"><?=$item['signed_at']?date('M j, Y',strtotime($item['signed_at'])):'—'?></td>
    <td style="font-size:.8rem"><?=e($item['properties_returned']??'—')?></td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:30px">
  <div>
    <div style="border-top:1px solid #333;width:200px;padding-top:6px;font-size:12px;color:#555">Authorized Signature</div>
    <div style="font-size:11px;color:#aaa;margin-top:4px"><?= APP_NAME ?> Administration</div>
  </div>
  <div class="stamp">
    <div style="font-size:8px;font-weight:800;color:#4361ee;text-align:center;letter-spacing:.04em;text-transform:uppercase;line-height:1.5">CLEARED<br>OFFICIAL<br><?=date('Y')?></div>
  </div>
</div>
<div class="footer">
  <span><?= APP_NAME ?> — Official Clearance Document</span>
  <span>Generated: <?= date('F j, Y g:i A') ?></span>
</div>
</body></html>
