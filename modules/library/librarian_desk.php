<?php
require_once '../../includes/config.php';
auth_check(['librarian','admin','super_admin']);
$page_title = 'Librarian Desk'; $active_page = 'librarian_desk';
$me = $_SESSION['user']['id'];

try { $cfg = $pdo->query("SELECT * FROM library_settings WHERE id=1")->fetch(); } catch(Exception $e) { $cfg = []; }
$FPD = (float)($cfg['fine_per_day'] ?? 0.50);
$BD  = (int)($cfg['max_borrow_days'] ?? 14);

try { $pdo->query("UPDATE library_borrows SET status='Overdue' WHERE status='Borrowed' AND due_date < CURDATE()"); } catch(Exception $e){}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='save_settings') {
        $pdo->prepare("UPDATE library_settings SET fine_per_day=?,max_borrow_days=?,max_books_student=?,max_books_teacher=?,max_renewals=?,lost_penalty_multiplier=?,lost_after_days=?,currency=? WHERE id=1")
            ->execute([$_POST['fine_per_day'],$_POST['max_borrow_days'],$_POST['max_books_student'],$_POST['max_books_teacher'],$_POST['max_renewals'],$_POST['lost_penalty_multiplier'],$_POST['lost_after_days'],$_POST['currency']]);
        flash('Settings saved.'); header('Location: librarian_desk.php?tab=settings'); exit;
    }
    if ($action==='approve_request') {
        $rid=(int)$_POST['request_id'];
        $req=$pdo->prepare("SELECT * FROM library_requests WHERE id=?"); $req->execute([$rid]); $req=$req->fetch();
        if(!$req||$req['status']!=='Pending'){flash('Invalid.','error');header('Location: librarian_desk.php');exit;}
        $btype=$req['borrower_type']; $bid_self=$req['student_id']??$req['teacher_id'];
        $limit=$btype==='student'?(int)($cfg['max_books_student']??3):(int)($cfg['max_books_teacher']??5);
        $cur=$pdo->prepare("SELECT COUNT(*) FROM library_borrows WHERE borrower_type=? AND ".($btype==='student'?"student_id=?":"teacher_id=?")." AND status IN('Borrowed','Overdue')");
        $cur->execute([$btype,$bid_self]);
        if((int)$cur->fetchColumn()>=$limit){flash("Borrower already at limit ($limit books).",'error');header('Location: librarian_desk.php');exit;}
        $av=$pdo->prepare("SELECT available_copies FROM library_books WHERE id=?"); $av->execute([$req['book_id']]);
        if((int)$av->fetchColumn()<1){flash('No copies available.','error');header('Location: librarian_desk.php');exit;}
        $due=date('Y-m-d',strtotime("+{$BD} days"));
        $pdo->prepare("INSERT INTO library_borrows (book_id,borrower_type,student_id,teacher_id,due_date,issued_by) VALUES (?,?,?,?,?,?)")->execute([$req['book_id'],$btype,$req['student_id'],$req['teacher_id'],$due,$me]);
        $bid2=$pdo->lastInsertId();
        $pdo->prepare("UPDATE library_books SET available_copies=available_copies-1 WHERE id=?")->execute([$req['book_id']]);
        $pdo->prepare("UPDATE library_requests SET status='Approved',reviewed_by=?,reviewed_at=NOW(),borrow_id=? WHERE id=?")->execute([$me,$bid2,$rid]);
        flash('Approved. Due '.$due.'.'); header('Location: librarian_desk.php'); exit;
    }
    if ($action==='reject_request') {
        $rid=(int)$_POST['request_id']; $reason=trim($_POST['reason']??'');
        $pdo->prepare("UPDATE library_requests SET status='Rejected',reviewed_by=?,reviewed_at=NOW(),reject_reason=? WHERE id=?")->execute([$me,$reason,$rid]);
        flash('Request rejected.'); header('Location: librarian_desk.php'); exit;
    }
    if ($action==='process_return'||$action==='confirm_return') {
        $lid=(int)$_POST['borrow_id']; $condition=$_POST['condition']??'Good';
        $damage_fee=$condition==='Damaged'?(float)($_POST['damage_fee']??0):0;
        $row=$pdo->prepare("SELECT * FROM library_borrows WHERE id=?"); $row->execute([$lid]); $row=$row->fetch();
        if(!$row||$row['status']==='Returned'){flash('Invalid.','error');header('Location: librarian_desk.php');exit;}
        $dl=max(0,(int)((time()-strtotime($row['due_date']))/86400));
        $fine=$dl>0?round($dl*$FPD,2):0; $total=$fine+$damage_fee;
        $ns=$condition==='Lost'?'Lost':'Returned';
        $pdo->prepare("UPDATE library_borrows SET status=?,returned_at=NOW(),fine_amount=?,damage_fee=?,condition_on_return=?,returned_to=? WHERE id=?")->execute([$ns,$total,$damage_fee,$condition,$me,$lid]);
        if($condition!=='Lost') $pdo->prepare("UPDATE library_books SET available_copies=available_copies+1 WHERE id=?")->execute([$row['book_id']]);
        flash('Returned.'.($total>0?" Fine: \${$total}":''));
        header('Location: librarian_desk.php?tab=active'); exit;
    }
    if ($action==='mark_fine_paid') {
        $pdo->prepare("UPDATE library_borrows SET fine_paid=1,fine_paid_at=NOW(),fine_paid_by=? WHERE id=?")->execute([$me,(int)$_POST['borrow_id']]);
        flash('Fine marked as paid.'); header('Location: librarian_desk.php?tab=fines'); exit;
    }
    if ($action==='approve_renewal') {
        $rnid=(int)$_POST['renewal_id'];
        $rn=$pdo->prepare("SELECT * FROM library_renewals WHERE id=?"); $rn->execute([$rnid]); $rn=$rn->fetch();
        $new_due=date('Y-m-d',strtotime($rn['old_due_date']." +{$BD} days"));
        $pdo->prepare("UPDATE library_renewals SET status='Approved',reviewed_by=?,reviewed_at=NOW(),new_due_date=? WHERE id=?")->execute([$me,$new_due,$rnid]);
        $pdo->prepare("UPDATE library_borrows SET due_date=?,status='Borrowed',renewal_count=renewal_count+1 WHERE id=?")->execute([$new_due,$rn['borrow_id']]);
        flash('Renewal approved. New due: '.$new_due.'.'); header('Location: librarian_desk.php?tab=renewals'); exit;
    }
    if ($action==='reject_renewal') {
        $pdo->prepare("UPDATE library_renewals SET status='Rejected',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$me,(int)$_POST['renewal_id']]);
        flash('Renewal rejected.'); header('Location: librarian_desk.php?tab=renewals'); exit;
    }
    if ($action==='send_reminders') {
        require_once '../../includes/notify.php';
        $overdue=$pdo->query("SELECT lb.*,bk.title,s.user_id AS s_uid,t.user_id AS t_uid FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id LEFT JOIN students s ON lb.student_id=s.id LEFT JOIN teachers t ON lb.teacher_id=t.id WHERE lb.status='Overdue'")->fetchAll();
        $sent=0;
        foreach($overdue as $ob){ $uid2=$ob['s_uid']??$ob['t_uid'];
            if($uid2){ $days=(int)((time()-strtotime($ob['due_date']))/86400); $fine=round($days*$FPD,2);
                notify_user($pdo,$uid2,'Overdue: '.$ob['title'],"Overdue {$days} day(s). Fine: \${$fine}.",0); $sent++; }
        }
        flash("Reminders sent to {$sent} borrower(s)."); header('Location: librarian_desk.php?tab=active'); exit;
    }
}

$tab=$_GET['tab']??'requests';
$requests=$pdo->query("SELECT lr.*,bk.title,bk.author,bk.available_copies,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name,t.teacher_code FROM library_requests lr LEFT JOIN library_books bk ON lr.book_id=bk.id LEFT JOIN students s ON lr.student_id=s.id LEFT JOIN teachers t ON lr.teacher_id=t.id WHERE lr.status='Pending' ORDER BY lr.requested_at ASC")->fetchAll();
$active_borrows=$pdo->query("SELECT lb.*,bk.title,bk.author,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name,t.teacher_code FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id LEFT JOIN students s ON lb.student_id=s.id LEFT JOIN teachers t ON lb.teacher_id=t.id WHERE lb.status IN('Borrowed','Overdue','Return Requested') ORDER BY lb.due_date ASC")->fetchAll();
$fines=$pdo->query("SELECT lb.*,bk.title,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id LEFT JOIN students s ON lb.student_id=s.id LEFT JOIN teachers t ON lb.teacher_id=t.id WHERE (lb.fine_amount+lb.damage_fee)>0 ORDER BY lb.fine_paid ASC,lb.borrowed_at DESC")->fetchAll();
$renewals=$pdo->query("SELECT rn.*,lb.due_date AS current_due,bk.title,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM library_renewals rn JOIN library_borrows lb ON rn.borrow_id=lb.id JOIN library_books bk ON lb.book_id=bk.id LEFT JOIN students s ON lb.student_id=s.id LEFT JOIN teachers t ON lb.teacher_id=t.id WHERE rn.status='Pending' ORDER BY rn.requested_at ASC")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-tasks" style="color:var(--primary)"></i> Librarian Desk</h1><p style="color:var(--muted)">Requests · Returns · Renewals · Fines · Settings</p></div>
  <div style="display:flex;gap:8px">
    <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="send_reminders"><button class="btn btn-warning btn-sm"><i class="fas fa-bell"></i> Remind Overdue</button></form>
    <a href="librarian.php" class="btn btn-secondary btn-sm"><i class="fas fa-chart-bar"></i> Dashboard</a>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-book"></i> Catalog</a>
  </div>
</div>

<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #eee;overflow-x:auto">
<?php foreach(['requests'=>['fas fa-inbox','Requests',count($requests)],'active'=>['fas fa-exchange-alt','Active',0],'renewals'=>['fas fa-sync','Renewals',count($renewals)],'fines'=>['fas fa-dollar-sign','Fines',0],'settings'=>['fas fa-cog','Settings',0]] as $t=>[$ico,$lbl,$cnt]): ?>
<a href="?tab=<?=$t?>" style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:.85rem;border-radius:8px 8px 0 0;color:<?=$tab===$t?'var(--primary)':'#888'?>;background:<?=$tab===$t?'#fff':'transparent'?>;border:2px solid <?=$tab===$t?'#eee':'transparent'?>;border-bottom:<?=$tab===$t?'2px solid #fff':'none'?>;margin-bottom:-2px;white-space:nowrap">
  <i class="<?=$ico?>"></i> <?=$lbl?><?php if($cnt>0):?> <span style="background:var(--danger);color:#fff;border-radius:10px;padding:1px 7px;font-size:.7rem;margin-left:4px"><?=$cnt?></span><?php endif;?>
</a>
<?php endforeach;?>
</div>

<?php if($tab==='requests'): ?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-inbox" style="color:var(--primary)"></i> Pending Borrow Requests (<?=count($requests)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Requested By</th><th>Type</th><th>Available</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($requests as $r): ?>
    <tr>
      <td><div style="font-weight:600"><?=e($r['title'])?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($r['author'])?></div></td>
      <td><?php if($r['student_name']??''): ?><div style="font-weight:600"><?=e($r['student_name'])?></div><div style="font-size:.75rem;font-family:monospace"><?=e($r['student_code']??'')?></div><?php else: ?><?=e($r['teacher_name']??'—')?><?php endif;?></td>
      <td><span class="badge badge-<?=$r['borrower_type']==='student'?'info':'primary'?>"><?=ucfirst($r['borrower_type'])?></span></td>
      <td><span class="badge badge-<?=$r['available_copies']>0?'success':'danger'?>"><?=$r['available_copies']?> left</span></td>
      <td style="font-size:.8rem"><?=date('M j, Y',strtotime($r['requested_at']))?></td>
      <td>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="approve_request"><input type="hidden" name="request_id" value="<?=$r['id']?>"><button class="btn btn-sm btn-success" <?=$r['available_copies']<1?'disabled':''?>><i class="fas fa-check"></i> Approve</button></form>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="reject_request"><input type="hidden" name="request_id" value="<?=$r['id']?>"><input type="hidden" name="reason" value=""><button class="btn btn-sm btn-danger" onclick="return confirm('Reject?')"><i class="fas fa-times"></i> Reject</button></form>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$requests): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No pending requests.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php elseif($tab==='active'): ?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-exchange-alt" style="color:var(--warning)"></i> Active Borrows (<?=count($active_borrows)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Borrower</th><th>Type</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($active_borrows as $b):
      $ov=strtotime($b['due_date'])<time()&&$b['status']!=='Return Requested';
      $dl=$ov?(int)((time()-strtotime($b['due_date']))/86400):0;
    ?>
    <tr style="<?=$ov?'background:#fff8f8':''?>">
      <td><div style="font-weight:600"><?=e($b['title'])?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($b['author'])?></div></td>
      <td><?php if($b['student_name']??''): ?><div style="font-weight:600"><?=e($b['student_name'])?></div><div style="font-size:.75rem;font-family:monospace"><?=e($b['student_code']??'')?></div><?php else: ?><?=e($b['teacher_name']??'—')?><?php endif;?></td>
      <td><span class="badge badge-<?=$b['borrower_type']==='student'?'info':'primary'?>"><?=ucfirst($b['borrower_type'])?></span></td>
      <td style="color:<?=$ov?'var(--danger)':'inherit'?>;font-weight:<?=$ov?'700':'400'?>"><?=date('M j, Y',strtotime($b['due_date']))?><?php if($dl>0): ?><div style="font-size:.72rem;color:var(--danger)"><?=$dl?> day(s) late</div><?php endif;?></td>
      <td><span class="badge badge-<?=match($b['status']){'Borrowed'=>'warning','Overdue'=>'danger','Return Requested'=>'info',default=>'secondary'}?>"><?=$b['status']?></span></td>
      <td>
        <form method="POST" style="display:inline" onsubmit="return confirm('Mark returned?')">
          <input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="process_return">
          <input type="hidden" name="borrow_id" value="<?=$b['id']?>"><input type="hidden" name="condition" value="Good">
          <button class="btn btn-sm btn-success"><i class="fas fa-undo"></i> Return</button>
        </form>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$active_borrows): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">No active borrows.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php elseif($tab==='renewals'): ?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-sync" style="color:var(--primary)"></i> Renewal Requests (<?=count($renewals)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Borrower</th><th>Current Due</th><th>Requested</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($renewals as $rn): ?>
    <tr>
      <td style="font-weight:600"><?=e($rn['title'])?></td>
      <td><?=e($rn['student_name']??$rn['teacher_name']??'—')?></td>
      <td><?=date('M j, Y',strtotime($rn['current_due']))?></td>
      <td style="font-size:.8rem"><?=date('M j, Y',strtotime($rn['requested_at']))?></td>
      <td>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="approve_renewal"><input type="hidden" name="renewal_id" value="<?=$rn['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Approve</button></form>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="reject_renewal"><input type="hidden" name="renewal_id" value="<?=$rn['id']?>"><button class="btn btn-sm btn-danger" onclick="return confirm('Reject?')"><i class="fas fa-times"></i> Reject</button></form>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$renewals): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">No pending renewals.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php elseif($tab==='fines'): ?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-dollar-sign" style="color:var(--danger)"></i> Fines</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Borrower</th><th>Fine</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($fines as $f): $total=$f['fine_amount']+$f['damage_fee']; ?>
    <tr>
      <td style="font-weight:600"><?=e($f['title'])?></td>
      <td><?=e($f['student_name']??$f['teacher_name']??'—')?></td>
      <td style="font-weight:700;color:<?=$f['fine_paid']?'var(--success)':'var(--danger)'?>">$<?=number_format($total,2)?></td>
      <td><span class="badge badge-<?=$f['fine_paid']?'success':'danger'?>"><?=$f['fine_paid']?'Paid':'Unpaid'?></span></td>
      <td><?php if(!$f['fine_paid']): ?><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="mark_fine_paid"><input type="hidden" name="borrow_id" value="<?=$f['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Mark Paid</button></form><?php else: ?><span style="color:var(--muted);font-size:.8rem">Paid</span><?php endif;?></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$fines): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:var(--muted)">No fines.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php elseif($tab==='settings'): ?>
<div class="card" style="max-width:600px">
  <div class="card-header"><h2><i class="fas fa-cog"></i> Library Settings</h2></div>
  <div class="card-body">
    <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="save_settings">
      <div class="form-grid">
        <div class="form-group"><label>Fine Per Day ($)</label><input type="number" step="0.01" name="fine_per_day" value="<?=e($cfg['fine_per_day']??'0.50')?>"></div>
        <div class="form-group"><label>Max Borrow Days</label><input type="number" name="max_borrow_days" value="<?=e($cfg['max_borrow_days']??'14')?>"></div>
        <div class="form-group"><label>Max Books (Student)</label><input type="number" name="max_books_student" value="<?=e($cfg['max_books_student']??'3')?>"></div>
        <div class="form-group"><label>Max Books (Teacher)</label><input type="number" name="max_books_teacher" value="<?=e($cfg['max_books_teacher']??'5')?>"></div>
        <div class="form-group"><label>Max Renewals</label><input type="number" name="max_renewals" value="<?=e($cfg['max_renewals']??'2')?>"></div>
        <div class="form-group"><label>Lost Penalty (x price)</label><input type="number" step="0.1" name="lost_penalty_multiplier" value="<?=e($cfg['lost_penalty_multiplier']??'1.5')?>"></div>
        <div class="form-group"><label>Mark Lost After (days)</label><input type="number" name="lost_after_days" value="<?=e($cfg['lost_after_days']??'30')?>"></div>
        <div class="form-group"><label>Currency</label><input name="currency" value="<?=e($cfg['currency']??'USD')?>" style="max-width:80px"></div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
    </form>
  </div>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php'; ?>
