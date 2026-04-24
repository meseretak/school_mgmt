<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title = 'Transfer Certificates'; $active_page = 'transfer';
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='issue') {
        $sid=(int)$_POST['student_id'];
        $dest=trim($_POST['destination_school']??'');
        $date=$_POST['transfer_date']??date('Y-m-d');
        $reason=trim($_POST['reason']??'');
        $cid=!empty($_POST['clearance_id'])?(int)$_POST['clearance_id']:null;

        // Build academic summary snapshot
        $grades=$pdo->prepare("SELECT co.name AS course, co.code, ay.label AS year, AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(g.id) AS exams FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id WHERE en.student_id=? GROUP BY co.id,ay.id ORDER BY ay.label,co.name");
        $grades->execute([$sid]); $summary=json_encode($grades->fetchAll());

        $cert_no='TC-'.date('Y').'-'.str_pad($sid,4,'0',STR_PAD_LEFT).'-'.strtoupper(substr(uniqid(),0,4));
        $pdo->prepare("INSERT INTO transfer_certificates (student_id,clearance_id,destination_school,transfer_date,reason,academic_summary,issued_by,certificate_no,status) VALUES (?,?,?,?,?,?,?,?,'Issued')")
            ->execute([$sid,$cid,$dest,$date,$reason,$summary,$me,$cert_no]);
        $tid=$pdo->lastInsertId();
        // Update student status
        $pdo->prepare("UPDATE students SET status='Transferred' WHERE id=?")->execute([$sid]);
        log_activity($pdo,'transfer_issued',"Transfer cert $cert_no for student $sid");
        flash("Transfer certificate $cert_no issued.");
        header('Location: index.php?view='.$tid); exit;
    }

    if ($action==='revoke') {
        $tid=(int)$_POST['cert_id'];
        $pdo->prepare("UPDATE transfer_certificates SET status='Revoked' WHERE id=?")->execute([$tid]);
        flash('Certificate revoked.','error'); header('Location: index.php'); exit;
    }
}

$view_id=(int)($_GET['view']??0);
$cert=null;
if ($view_id) {
    $q=$pdo->prepare("SELECT tc.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,s.nationality,s.dob,s.enrollment_date,s.phone,u.email,u.name AS issued_by_name FROM transfer_certificates tc JOIN students s ON tc.student_id=s.id JOIN users u ON tc.issued_by=u.id WHERE tc.id=?");
    $q->execute([$view_id]); $cert=$q->fetch();
}

$certs=$pdo->query("SELECT tc.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code FROM transfer_certificates tc JOIN students s ON tc.student_id=s.id ORDER BY tc.issued_at DESC")->fetchAll();
$students=$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status IN('Active','Inactive') ORDER BY first_name")->fetchAll();
$clearances=$pdo->query("SELECT cr.id,CONCAT(s.first_name,' ',s.last_name) AS student_name FROM clearance_requests cr JOIN students s ON cr.student_id=s.id WHERE cr.status='Completed' ORDER BY cr.completed_at DESC")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-file-export" style="color:var(--primary)"></i> Transfer Certificates</h1></div>
  <button class="btn btn-primary" onclick="document.getElementById('issueModal').style.display='flex'"><i class="fas fa-plus"></i> Issue Certificate</button>
</div>

<?php if ($cert): ?>
<!-- Certificate Preview -->
<div style="margin-bottom:16px"><a href="index.php" style="color:var(--primary);text-decoration:none"><i class="fas fa-arrow-left"></i> Back</a></div>
<div class="card" style="max-width:800px;margin:0 auto">
  <div class="card-body">
    <div style="text-align:center;border-bottom:3px double #4361ee;padding-bottom:20px;margin-bottom:20px">
      <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#4361ee,#7209b7);display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="white"><path d="M12 3L1 9L12 15L21 10.09V17H23V9L12 3Z"/><path d="M5 13.18V17.18L12 21L19 17.18V13.18L12 17L5 13.18Z" opacity=".8"/></svg>
      </div>
      <div style="font-size:20px;font-weight:800;color:#4361ee"><?= APP_NAME ?></div>
      <div style="font-size:15px;font-weight:700;margin-top:4px">TRANSFER CERTIFICATE</div>
      <div style="font-size:11px;color:#888;margin-top:4px">Certificate No: <?= e($cert['certificate_no']) ?> · Issued: <?= date('F j, Y',strtotime($cert['issued_at'])) ?></div>
    </div>
    <p style="margin-bottom:16px;font-size:13px">This is to certify that the following student was enrolled at <strong><?= APP_NAME ?></strong> and is hereby granted a Transfer Certificate:</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;background:#f8f9ff;padding:16px;border-radius:8px;margin-bottom:20px;font-size:13px">
      <div><span style="color:#888;min-width:130px;display:inline-block">Student Name:</span><strong><?= e($cert['student_name']) ?></strong></div>
      <div><span style="color:#888;min-width:130px;display:inline-block">Student Code:</span><strong><?= e($cert['student_code']) ?></strong></div>
      <?php if ($cert['dob']): ?><div><span style="color:#888;min-width:130px;display:inline-block">Date of Birth:</span><?= date('M j, Y',strtotime($cert['dob'])) ?></div><?php endif; ?>
      <?php if ($cert['nationality']): ?><div><span style="color:#888;min-width:130px;display:inline-block">Nationality:</span><?= e($cert['nationality']) ?></div><?php endif; ?>
      <?php if ($cert['enrollment_date']): ?><div><span style="color:#888;min-width:130px;display:inline-block">Enrolled:</span><?= date('M j, Y',strtotime($cert['enrollment_date'])) ?></div><?php endif; ?>
      <div><span style="color:#888;min-width:130px;display:inline-block">Transfer Date:</span><?= date('M j, Y',strtotime($cert['transfer_date'])) ?></div>
      <?php if ($cert['destination_school']): ?><div><span style="color:#888;min-width:130px;display:inline-block">Destination:</span><strong><?= e($cert['destination_school']) ?></strong></div><?php endif; ?>
      <?php if ($cert['reason']): ?><div style="grid-column:1/-1"><span style="color:#888;min-width:130px;display:inline-block">Reason:</span><?= e($cert['reason']) ?></div><?php endif; ?>
    </div>

    <?php
    $summary=json_decode($cert['academic_summary']??'[]',true);
    if ($summary): ?>
    <div style="font-weight:700;margin-bottom:8px;font-size:13px">Academic Record Summary:</div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:20px;font-size:12px">
      <thead><tr style="background:#f0f2f8"><th style="padding:7px 10px;text-align:left">Course</th><th style="padding:7px 10px;text-align:left">Year</th><th style="padding:7px 10px">Exams</th><th style="padding:7px 10px">Avg %</th><th style="padding:7px 10px">Grade</th></tr></thead>
      <tbody>
      <?php foreach($summary as $g): $pct=round($g['avg_pct'],1); ?>
      <tr style="border-bottom:1px solid #f0f0f0">
        <td style="padding:6px 10px;font-weight:600"><?= e($g['course']) ?></td>
        <td style="padding:6px 10px"><?= e($g['year']) ?></td>
        <td style="padding:6px 10px;text-align:center"><?= $g['exams'] ?></td>
        <td style="padding:6px 10px;text-align:center"><?= $pct ?>%</td>
        <td style="padding:6px 10px;text-align:center;font-weight:700;color:<?= $pct>=get_pass_pct()?'#2dc653':'#e63946' ?>"><?= grade_letter($pct) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:30px">
      <div>
        <div style="border-top:1px solid #333;width:200px;padding-top:6px;font-size:12px;color:#555">Authorized Signature</div>
        <div style="font-size:11px;color:#aaa;margin-top:4px"><?= e($cert['issued_by_name']) ?> · <?= APP_NAME ?></div>
      </div>
      <div style="width:90px;height:90px;border:3px double #4361ee;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:.3;transform:rotate(-12deg)">
        <div style="font-size:7px;font-weight:800;color:#4361ee;text-align:center;letter-spacing:.04em;text-transform:uppercase;line-height:1.5">OFFICIAL<br>TRANSFER<br><?= date('Y') ?></div>
      </div>
    </div>
  </div>
  <div class="card-body" style="border-top:1px solid #f0f0f0;display:flex;gap:10px">
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
    <?php if ($cert['status']==='Issued'): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Revoke this certificate?')">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="revoke">
      <input type="hidden" name="cert_id" value="<?=$view_id?>">
      <button class="btn btn-danger"><i class="fas fa-ban"></i> Revoke</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list"></i> All Transfer Certificates (<?=count($certs)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Certificate No</th><th>Student</th><th>Destination</th><th>Transfer Date</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($certs as $c):?>
    <tr>
      <td style="font-family:monospace;font-weight:600"><?=e($c['certificate_no'])?></td>
      <td><div style="font-weight:600"><?=e($c['student_name'])?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($c['student_code'])?></div></td>
      <td><?=e($c['destination_school']??'—')?></td>
      <td><?=date('M j, Y',strtotime($c['transfer_date']))?></td>
      <td><span class="badge badge-<?=$c['status']==='Issued'?'success':($c['status']==='Revoked'?'danger':'secondary')?>"><?=e($c['status'])?></span></td>
      <td><a href="?view=<?=$c['id']?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$certs):?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No transfer certificates yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<!-- Issue Modal -->
<div id="issueModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:500px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:16px"><i class="fas fa-file-export" style="color:var(--primary)"></i> Issue Transfer Certificate</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="issue">
      <div class="form-group" style="margin-bottom:12px"><label>Student *</label>
        <select name="student_id" required><option value="">Select student...</option>
          <?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Destination School</label><input name="destination_school" placeholder="Name of receiving institution"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Transfer Date</label><input type="date" name="transfer_date" value="<?=date('Y-m-d')?>"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Reason</label><textarea name="reason" rows="2" placeholder="Reason for transfer..."></textarea></div>
      <div class="form-group" style="margin-bottom:16px"><label>Linked Clearance (optional)</label>
        <select name="clearance_id"><option value="">None</option>
          <?php foreach($clearances as $cl):?><option value="<?=$cl['id']?>"><?=e($cl['student_name'])?></option><?php endforeach;?>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-file-export"></i> Issue Certificate</button>
        <button type="button" onclick="document.getElementById('issueModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
