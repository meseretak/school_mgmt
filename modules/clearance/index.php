<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','librarian','teacher']);
$page_title = 'Clearance Management'; $active_page = 'clearance';
$me = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    // Initiate clearance
    if ($action==='initiate' && is_admin()) {
        $sid=(int)$_POST['student_id'];
        $reason=$_POST['reason']??'Transfer';
        $notes=trim($_POST['notes']??'');
        // Check no active clearance
        $existing=$pdo->prepare("SELECT id FROM clearance_requests WHERE student_id=? AND status IN('Pending','In Progress')");
        $existing->execute([$sid]);
        if ($existing->fetch()) { flash('Student already has an active clearance request.','error'); header('Location: index.php'); exit; }
        $pdo->prepare("INSERT INTO clearance_requests (student_id,reason,initiated_by,notes,status) VALUES (?,?,?,?,'In Progress')")->execute([$sid,$reason,$me,$notes]);
        $cid=$pdo->lastInsertId();
        // Create items for each department
        $depts=$pdo->query("SELECT id FROM clearance_departments WHERE is_active=1 ORDER BY sort_order")->fetchAll();
        $ins=$pdo->prepare("INSERT INTO clearance_items (clearance_id,department_id) VALUES (?,?)");
        foreach ($depts as $d) $ins->execute([$cid,$d['id']]);
        log_activity($pdo,'clearance_initiated',"Clearance for student ID $sid");
        flash('Clearance process initiated.'); header('Location: index.php?id='.$cid); exit;
    }

    // Sign off a department
    if ($action==='sign_off') {
        $item_id=(int)$_POST['item_id'];
        $props=trim($_POST['properties_returned']??'');
        $remarks=trim($_POST['remarks']??'');
        $pdo->prepare("UPDATE clearance_items SET status='Cleared',signed_by=?,signed_at=NOW(),remarks=?,properties_returned=? WHERE id=?")->execute([$me,$remarks,$props,$item_id]);
        // Check if all cleared
        $cid=(int)$_POST['clearance_id'];
        $pending=$pdo->prepare("SELECT COUNT(*) FROM clearance_items WHERE clearance_id=? AND status='Pending'");
        $pending->execute([$cid]);
        if ((int)$pending->fetchColumn()===0) {
            $pdo->prepare("UPDATE clearance_requests SET status='Completed',completed_at=NOW() WHERE id=?")->execute([$cid]);
            flash('All departments cleared. Clearance completed!');
        } else {
            flash('Department cleared successfully.');
        }
        header('Location: index.php?id='.$cid); exit;
    }

    // Reject a department item
    if ($action==='reject_item') {
        $item_id=(int)$_POST['item_id'];
        $remarks=trim($_POST['remarks']??'');
        $pdo->prepare("UPDATE clearance_items SET status='Rejected',signed_by=?,signed_at=NOW(),remarks=? WHERE id=?")->execute([$me,$remarks,$item_id]);
        flash('Item rejected.','error'); header('Location: index.php?id='.$_POST['clearance_id']); exit;
    }
}

$view_id=(int)($_GET['id']??0);
$clearance=null; $items=[];
if ($view_id) {
    $q=$pdo->prepare("SELECT cr.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,s.id AS sid FROM clearance_requests cr JOIN students s ON cr.student_id=s.id WHERE cr.id=?");
    $q->execute([$view_id]); $clearance=$q->fetch();
    if ($clearance) {
        $items=$pdo->prepare("SELECT ci.*,cd.name AS dept_name,cd.description,cd.responsible_role,u.name AS signed_by_name FROM clearance_items ci JOIN clearance_departments cd ON ci.department_id=cd.id LEFT JOIN users u ON ci.signed_by=u.id WHERE ci.clearance_id=? ORDER BY cd.sort_order");
        $items->execute([$view_id]); $items=$items->fetchAll();
    }
}

// List all clearances
$all=$pdo->query("SELECT cr.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code FROM clearance_requests cr JOIN students s ON cr.student_id=s.id ORDER BY cr.initiated_at DESC LIMIT 50")->fetchAll();
$students=$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-clipboard-check" style="color:var(--primary)"></i> Student Clearance</h1><p style="color:var(--muted)">Manage student exit clearance process</p></div>
  <?php if (is_admin()): ?>
  <button class="btn btn-primary" onclick="document.getElementById('initiateModal').style.display='flex'"><i class="fas fa-plus"></i> Initiate Clearance</button>
  <?php endif; ?>
</div>

<?php if ($clearance): ?>
<!-- Clearance Detail View -->
<div style="margin-bottom:16px"><a href="index.php" style="color:var(--primary);text-decoration:none"><i class="fas fa-arrow-left"></i> Back to list</a></div>

<div style="background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;border-radius:14px;padding:18px 24px;margin-bottom:20px;display:flex;gap:20px;align-items:center;flex-wrap:wrap">
  <div class="avatar" style="width:52px;height:52px;font-size:1.1rem;background:rgba(255,255,255,.2)"><?= strtoupper(substr($clearance['student_name'],0,2)) ?></div>
  <div style="flex:1">
    <div style="font-size:1.1rem;font-weight:800"><?= e($clearance['student_name']) ?></div>
    <div style="opacity:.8;font-size:.85rem"><?= e($clearance['student_code']) ?> · Reason: <?= e($clearance['reason']) ?></div>
  </div>
  <span class="badge badge-<?php $cs_=($clearance['status']??''); echo $cs_==='Completed'?'success':($cs_==='In Progress'?'warning':($cs_==='Rejected'?'danger':'secondary')); ?>" style="font-size:.85rem"><?= e($clearance['status']) ?></span>
</div>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-tasks"></i> Department Sign-offs</h2></div>
  <div class="card-body">
  <?php foreach ($items as $item): ?>
  <div style="border:1px solid #e0e0e0;border-radius:12px;padding:16px;margin-bottom:12px;background:<?= $item['status']==='Cleared'?'#f0fff4':($item['status']==='Rejected'?'#fff5f5':'#fff') ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-weight:700;font-size:.95rem"><?= e($item['dept_name']) ?></div>
        <div style="font-size:.8rem;color:var(--muted)"><?= e($item['description']) ?></div>
        <?php if ($item['signed_by_name']): ?><div style="font-size:.78rem;color:var(--muted);margin-top:4px">Signed by <?= e($item['signed_by_name']) ?> · <?= date('M j, Y g:i A',strtotime($item['signed_at'])) ?></div><?php endif; ?>
        <?php if ($item['properties_returned']): ?><div style="font-size:.8rem;margin-top:4px"><strong>Items returned:</strong> <?= e($item['properties_returned']) ?></div><?php endif; ?>
        <?php if ($item['remarks']): ?><div style="font-size:.8rem;color:#666;margin-top:4px;font-style:italic"><?= e($item['remarks']) ?></div><?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span class="badge badge-<?php $is_=($item['status']??''); echo $is_==='Cleared'?'success':($is_==='Rejected'?'danger':'warning'); ?>"><?= e($item['status']) ?></span>
        <?php if ($item['status']==='Pending' && $clearance['status']!=='Completed'):
          $can_sign = is_admin() || $role === $item['responsible_role'];
          if ($can_sign): ?>
        <button onclick="openSignOff(<?= $item['id'] ?>,<?= $view_id ?>,'<?= e($item['dept_name']) ?>')" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Clear</button>
        <button onclick="openReject(<?= $item['id'] ?>,<?= $view_id ?>)" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Reject</button>
        <?php endif; endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>

<?php if ($clearance['status']==='Completed'): ?>
<div style="text-align:center;margin-top:20px">
  <a href="<?= BASE_URL ?>/modules/clearance/certificate.php?id=<?= $view_id ?>" target="_blank" class="btn btn-success"><i class="fas fa-certificate"></i> Generate Clearance Certificate</a>
</div>
<?php endif; ?>

<!-- Sign off modal -->
<div id="signOffModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:460px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-check-circle" style="color:var(--success)"></i> Clear Department: <span id="deptName"></span></h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="sign_off">
      <input type="hidden" name="item_id" id="signItemId">
      <input type="hidden" name="clearance_id" value="<?=$view_id?>">
      <div class="form-group" style="margin-bottom:12px"><label>Properties/Items Returned (optional)</label><textarea name="properties_returned" rows="2" placeholder="e.g. Library card, ID card, uniform..."></textarea></div>
      <div class="form-group" style="margin-bottom:16px"><label>Remarks (optional)</label><input name="remarks" placeholder="Any notes..."></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Confirm Clear</button>
        <button type="button" onclick="document.getElementById('signOffModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:400px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-times-circle" style="color:var(--danger)"></i> Reject Item</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="reject_item">
      <input type="hidden" name="item_id" id="rejectItemId">
      <input type="hidden" name="clearance_id" value="<?=$view_id?>">
      <div class="form-group" style="margin-bottom:16px"><label>Reason for rejection *</label><textarea name="remarks" rows="3" required placeholder="Why is this being rejected?"></textarea></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Reject</button>
        <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openSignOff(id,cid,name){document.getElementById('signItemId').value=id;document.getElementById('deptName').textContent=name;document.getElementById('signOffModal').style.display='flex';}
function openReject(id,cid){document.getElementById('rejectItemId').value=id;document.getElementById('rejectModal').style.display='flex';}
</script>

<?php else: ?>
<!-- List View -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list"></i> All Clearance Requests (<?=count($all)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Reason</th><th>Initiated</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($all as $c): ?>
    <tr>
      <td><div style="font-weight:600"><?=e($c['student_name'])?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($c['student_code'])?></div></td>
      <td><span class="badge badge-info"><?=e($c['reason'])?></span></td>
      <td style="font-size:.82rem"><?=date('M j, Y',strtotime($c['initiated_at']))?></td>
      <td><span class="badge badge-<?=match($c['status']){'Completed'=>'success','In Progress'=>'warning','Rejected'=>'danger',default=>'secondary'}?>"><?=e($c['status'])?></span></td>
      <td><a href="?id=<?=$c['id']?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$all):?><tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">No clearance requests yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<!-- Initiate Modal -->
<?php if (is_admin()): ?>
<div id="initiateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:460px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-clipboard-check" style="color:var(--primary)"></i> Initiate Student Clearance</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="initiate">
      <div class="form-group" style="margin-bottom:12px"><label>Student *</label>
        <select name="student_id" required>
          <option value="">Select student...</option>
          <?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Reason *</label>
        <select name="reason"><option>Graduation</option><option>Transfer</option><option>Withdrawal</option><option>Suspension</option><option>Other</option></select>
      </div>
      <div class="form-group" style="margin-bottom:16px"><label>Notes</label><textarea name="notes" rows="2" placeholder="Additional notes..."></textarea></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Start Clearance</button>
        <button type="button" onclick="document.getElementById('initiateModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php'; ?>
