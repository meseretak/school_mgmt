<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher']);
$page_title = 'Disciplinary Records'; $active_page = 'disciplinary';
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='add_record') {
        $sid=(int)$_POST['student_id']; $date=$_POST['incident_date'];
        $type=$_POST['incident_type']; $desc=trim($_POST['description']);
        $action_taken=$_POST['action_taken']; $susp_days=(int)($_POST['suspension_days']??0);
        $notify=(int)($_POST['notify_parent']??0);
        $pdo->prepare("INSERT INTO disciplinary_records (student_id,incident_date,incident_type,description,action_taken,suspension_days,reported_by,parent_notified,parent_notified_at) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$sid,$date,$type,$desc,$action_taken,$susp_days,$me,$notify,$notify?date('Y-m-d H:i:s'):null]);
        $rid=$pdo->lastInsertId();
        // If suspension, update student status temporarily
        if ($action_taken==='Suspension'&&$susp_days>0) {
            // Log in activity
            log_activity($pdo,'disciplinary_suspension',"Student $sid suspended for $susp_days days");
        }
        // Notify parent if requested
        if ($notify) {
            $parent_user=$pdo->prepare("SELECT u.id FROM users u JOIN parents p ON p.user_id=u.id JOIN student_parents sp ON sp.parent_id=p.id WHERE sp.student_id=? AND sp.is_primary=1 LIMIT 1");
            $parent_user->execute([$sid]); $puid=$parent_user->fetchColumn();
            if ($puid) {
                require_once '../../includes/notify.php';
                notify_user($pdo,$puid,'⚠️ Disciplinary Notice','A disciplinary record has been filed for your child. Please contact the school.',0);
            }
        }
        flash('Disciplinary record added.'); header('Location: index.php'); exit;
    }

    if ($action==='update_status') {
        $rid=(int)$_POST['record_id']; $status=$_POST['status']; $notes=trim($_POST['resolution_notes']??'');
        $pdo->prepare("UPDATE disciplinary_records SET status=?,resolution_notes=?,reviewed_by=? WHERE id=?")->execute([$status,$notes,$me,$rid]);
        flash('Record updated.'); header('Location: index.php'); exit;
    }
}

$filter_student=(int)($_GET['student_id']??0);
$filter_type=$_GET['type']??'';
$filter_status=$_GET['status']??'';

$sql="SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,u.name AS reported_by_name FROM disciplinary_records dr JOIN students s ON dr.student_id=s.id JOIN users u ON dr.reported_by=u.id WHERE 1=1";
$params=[];
if ($filter_student) { $sql.=" AND dr.student_id=?"; $params[]=$filter_student; }
if ($filter_type) { $sql.=" AND dr.incident_type=?"; $params[]=$filter_type; }
if ($filter_status) { $sql.=" AND dr.status=?"; $params[]=$filter_status; }
$sql.=" ORDER BY dr.incident_date DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $records=$stmt->fetchAll();

$students=$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
$types=['Misconduct','Cheating','Bullying','Vandalism','Absence','Dress Code','Other'];
$actions=['Warning','Suspension','Expulsion','Counseling','Parent Meeting','Community Service','Other'];

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-gavel" style="color:var(--danger)"></i> Disciplinary Records</h1><p style="color:var(--muted)">Track and manage student disciplinary incidents</p></div>
  <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Add Record</button>
</div>

<!-- Stats -->
<?php
$open=count(array_filter($records,fn($r)=>$r['status']==='Open'));
$resolved=count(array_filter($records,fn($r)=>$r['status']==='Resolved'));
$suspensions=count(array_filter($records,fn($r)=>$r['action_taken']==='Suspension'));
?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><h3><?=count($records)?></h3><p>Total Records</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?=$open?></h3><p>Open Cases</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?=$resolved?></h3><p>Resolved</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-ban"></i></div><div class="stat-info"><h3><?=$suspensions?></h3><p>Suspensions</p></div></div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:16px"><div class="card-body">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <select name="student_id" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Students</option>
      <?php foreach($students as $s):?><option value="<?=$s['id']?>" <?=$filter_student==$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?>
    </select>
    <select name="type" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Types</option>
      <?php foreach($types as $t):?><option value="<?=$t?>" <?=$filter_type===$t?'selected':''?>><?=$t?></option><?php endforeach;?>
    </select>
    <select name="status" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Status</option>
      <?php foreach(['Open','Under Review','Resolved','Appealed'] as $s):?><option value="<?=$s?>" <?=$filter_status===$s?'selected':''?>><?=$s?></option><?php endforeach;?>
    </select>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>

<div class="card">
  <div class="card-header"><h2>Records (<?=count($records)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Date</th><th>Type</th><th>Description</th><th>Action Taken</th><th>Status</th><th>Parent Notified</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($records as $r):?>
    <tr>
      <td><div style="font-weight:600"><?=e($r['student_name'])?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($r['student_code'])?></div></td>
      <td><?=date('M j, Y',strtotime($r['incident_date']))?></td>
      <td><span class="badge badge-<?=match($r['incident_type']){'Cheating'=>'danger','Bullying'=>'danger','Vandalism'=>'warning','Misconduct'=>'warning',default=>'secondary'}?>"><?=e($r['incident_type'])?></span></td>
      <td style="max-width:200px;font-size:.83rem"><?=e(mb_substr($r['description'],0,80)).(strlen($r['description'])>80?'...':'')?></td>
      <td>
        <span class="badge badge-<?=match($r['action_taken']){'Expulsion'=>'danger','Suspension'=>'warning','Warning'=>'info',default=>'secondary'}?>"><?=e($r['action_taken'])?></span>
        <?php if($r['suspension_days']>0):?><div style="font-size:.72rem;color:var(--muted)"><?=$r['suspension_days']?> day(s)</div><?php endif;?>
      </td>
      <td><span class="badge badge-<?=match($r['status']){'Resolved'=>'success','Open'=>'warning','Under Review'=>'info','Appealed'=>'danger',default=>'secondary'}?>"><?=e($r['status'])?></span></td>
      <td style="text-align:center"><?=$r['parent_notified']?'<i class="fas fa-check" style="color:var(--success)"></i>':'<i class="fas fa-times" style="color:var(--muted)"></i>'?></td>
      <td>
        <button onclick="openUpdate(<?=$r['id']?>,'<?=e($r['status'])?>')" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i> Update</button>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$records):?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">No disciplinary records found.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:560px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-gavel" style="color:var(--danger)"></i> Add Disciplinary Record</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_record">
      <div class="form-grid">
        <div class="form-group"><label>Student *</label>
          <select name="student_id" required><option value="">Select student...</option>
            <?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Incident Date *</label><input type="date" name="incident_date" required value="<?=date('Y-m-d')?>"></div>
        <div class="form-group"><label>Incident Type *</label>
          <select name="incident_type" required><?php foreach($types as $t):?><option><?=$t?></option><?php endforeach;?></select>
        </div>
        <div class="form-group"><label>Action Taken *</label>
          <select name="action_taken" required id="actionSelect" onchange="toggleSuspension(this.value)"><?php foreach($actions as $a):?><option><?=$a?></option><?php endforeach;?></select>
        </div>
        <div class="form-group" id="suspDays" style="display:none"><label>Suspension Days</label><input type="number" name="suspension_days" min="1" max="30" value="1"></div>
        <div class="form-group"><label>Notify Parent</label><select name="notify_parent"><option value="1">Yes</option><option value="0">No</option></select></div>
        <div class="form-group full"><label>Description *</label><textarea name="description" rows="4" required placeholder="Describe the incident in detail..."></textarea></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-danger"><i class="fas fa-save"></i> Add Record</button>
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Update Status Modal -->
<div id="updateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:420px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-edit" style="color:var(--primary)"></i> Update Record Status</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="record_id" id="updateRecordId">
      <div class="form-group" style="margin-bottom:12px"><label>Status</label>
        <select name="status" id="updateStatus">
          <?php foreach(['Open','Under Review','Resolved','Appealed'] as $s):?><option><?=$s?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:16px"><label>Resolution Notes</label><textarea name="resolution_notes" rows="3" placeholder="Describe how this was resolved..."></textarea></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
        <button type="button" onclick="document.getElementById('updateModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleSuspension(v){document.getElementById('suspDays').style.display=v==='Suspension'?'block':'none';}
function openUpdate(id,status){document.getElementById('updateRecordId').value=id;document.getElementById('updateStatus').value=status;document.getElementById('updateModal').style.display='flex';}
</script>
<?php require_once '../../includes/footer.php'; ?>
