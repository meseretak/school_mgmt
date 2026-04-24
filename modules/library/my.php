<?php
require_once '../../includes/config.php';
auth_check(['student','teacher']);
$page_title = 'Library'; $active_page = 'library';
$uid = $_SESSION['user']['id']; $role = $_SESSION['user']['role'];
$teacher = is_teacher() ? get_teacher_record($pdo) : null;
$student = is_student() ? get_student_record($pdo) : null;
$btype = $student ? 'student' : 'teacher';
$bid_self = $student ? $student['id'] : ($teacher ? $teacher['id'] : 0);

// Handle POST
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action = $_POST['action']??'';

    // Submit borrow request
    if ($action==='request_borrow') {
        $book_id = (int)$_POST['book_id'];
        $needed_by = $_POST['needed_by']??null;
        $note = trim($_POST['note']??'');
        // Check no pending request
        $chk = $pdo->prepare("SELECT id FROM library_requests WHERE book_id=? AND borrower_type=? AND ".($student?"student_id=?":"teacher_id=?")." AND status='Pending'");
        $chk->execute([$book_id,$btype,$bid_self]);
        if ($chk->fetch()) { flash('You already have a pending request for this book.','error'); header('Location: index.php'); exit; }
        $pdo->prepare("INSERT INTO library_requests (book_id,borrower_type,student_id,teacher_id,needed_by,note) VALUES (?,?,?,?,?,?)")
            ->execute([$book_id,$btype,$student?$bid_self:null,$teacher?$bid_self:null,$needed_by?:null,$note?:null]);
        flash('Borrow request submitted. Awaiting librarian approval.');
        header('Location: index.php?tab=my'); exit;
    }

    // Cancel request
    if ($action==='cancel_request') {
        $rid = (int)$_POST['request_id'];
        $pdo->prepare("UPDATE library_requests SET status='Cancelled' WHERE id=? AND status='Pending'")->execute([$rid]);
        flash('Request cancelled.'); header('Location: index.php?tab=my'); exit;
    }

    // Return request (student/teacher initiates return)
    if ($action==='request_return') {
        $borrow_id = (int)$_POST['borrow_id'];
        $pdo->prepare("UPDATE library_borrows SET notes=CONCAT(IFNULL(notes,''),' [Return requested by borrower]') WHERE id=?")->execute([$borrow_id]);
        flash('Return request sent to librarian.'); header('Location: index.php?tab=my'); exit;
    }
}

$tab = $_GET['tab']??'browse';
$search = trim($_GET['q']??'');
$cat = $_GET['cat']??'';
$avail = $_GET['avail']??'';

// Books
$bsql = "SELECT * FROM library_books WHERE is_active=1";
$bp = [];
if ($search) { $bsql.=" AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR subject LIKE ?)"; $bp=array_merge($bp,["%$search%","%$search%","%$search%","%$search%"]); }
if ($cat) { $bsql.=" AND category=?"; $bp[]=$cat; }
if ($avail==='1') $bsql.=" AND available_copies>0";
if ($avail==='0') $bsql.=" AND available_copies=0";
$bsql.=" ORDER BY title";
$bstmt=$pdo->prepare($bsql);$bstmt->execute($bp);$books=$bstmt->fetchAll();
$categories=$pdo->query("SELECT DISTINCT category FROM library_books WHERE category IS NOT NULL AND is_active=1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// My requests
$my_requests = $pdo->prepare("SELECT lr.*,bk.title,bk.author,bk.isbn,bk.cover_url FROM library_requests lr JOIN library_books bk ON lr.book_id=bk.id WHERE lr.borrower_type=? AND ".($student?"lr.student_id=?":"lr.teacher_id=?")." ORDER BY lr.requested_at DESC");
$my_requests->execute([$btype,$bid_self]); $my_requests=$my_requests->fetchAll();

// My active borrows
$my_borrows = $pdo->prepare("SELECT lb.*,bk.title,bk.author,bk.cover_url FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id WHERE lb.borrower_type=? AND ".($student?"lb.student_id=?":"lb.teacher_id=?")." AND lb.status IN('Borrowed','Overdue') ORDER BY lb.due_date ASC");
$my_borrows->execute([$btype,$bid_self]); $my_borrows=$my_borrows->fetchAll();

// Pending request book IDs (to show "Requested" badge)
$pending_ids = $pdo->prepare("SELECT book_id FROM library_requests WHERE borrower_type=? AND ".($student?"student_id=?":"teacher_id=?")." AND status='Pending'");
$pending_ids->execute([$btype,$bid_self]); $pending_ids=array_column($pending_ids->fetchAll(),'book_id');

// Borrowed book IDs
$borrowed_ids = $pdo->prepare("SELECT book_id FROM library_borrows WHERE borrower_type=? AND ".($student?"student_id=?":"teacher_id=?")." AND status IN('Borrowed','Overdue')");
$borrowed_ids->execute([$btype,$bid_self]); $borrowed_ids=array_column($borrowed_ids->fetchAll(),'book_id');

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-book-open" style="color:var(--primary)"></i> Library</h1>
    <p style="color:var(--muted)">Browse books, request to borrow, track your loans</p></div>
</div>

<!-- Quick stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:22px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?=count($books)?></h3><p>Books Available</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?=count(array_filter($my_requests,fn($r)=>$r['status']==='Pending'))?></h3><p>Pending Requests</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-hand-holding-heart"></i></div><div class="stat-info"><h3><?=count($my_borrows)?></h3><p>Books I Have</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3><?=count(array_filter($my_borrows,fn($b)=>$b['status']==='Overdue'))?></h3><p>Overdue</p></div></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content">
  <a href="?tab=browse" style="padding:8px 18px;border-radius:8px;font-size:.84rem;font-weight:600;text-decoration:none;<?=$tab==='browse'?'background:#fff;color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.1)':'color:var(--muted)'?>"><i class="fas fa-th"></i> Browse Books</a>
  <a href="?tab=my" style="padding:8px 18px;border-radius:8px;font-size:.84rem;font-weight:600;text-decoration:none;<?=$tab==='my'?'background:#fff;color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.1)':'color:var(--muted)'?>"><i class="fas fa-user-clock"></i> My Loans & Requests <?php $pend=count(array_filter($my_requests,fn($r)=>$r['status']==='Pending')); if($pend): ?><span class="nav-badge"><?=$pend?></span><?php endif; ?></a>
</div>
<?php if($tab==='browse'): ?>
<!-- Search/Filter -->
<div class="card" style="margin-bottom:16px"><div class="card-body" style="padding:12px 18px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="tab" value="browse">
    <input name="q" placeholder="Search title, author, ISBN..." value="<?=e($search)?>" style="max-width:280px">
    <select name="cat" onchange="this.form.submit()"><option value="">All Categories</option><?php foreach($categories as $c): ?><option value="<?=e($c)?>" <?=$cat===$c?'selected':''?>><?=e($c)?></option><?php endforeach; ?></select>
    <select name="avail" onchange="this.form.submit()"><option value="">All</option><option value="1" <?=$avail==='1'?'selected':''?>>Available Now</option><option value="0" <?=$avail==='0'?'selected':''?>>Checked Out</option></select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <a href="?tab=browse" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>

<!-- Book Grid with covers -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:18px">
<?php foreach($books as $bk):
  $is_borrowed = in_array($bk['id'],$borrowed_ids);
  $is_requested = in_array($bk['id'],$pending_ids);
  $cover = $bk['cover_url'] ?: ($bk['isbn'] ? 'https://covers.openlibrary.org/b/isbn/'.$bk['isbn'].'-M.jpg' : '');
?>
<div class="card" style="display:flex;flex-direction:column;overflow:hidden;transition:transform .2s,box-shadow .2s" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 32px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
  <!-- Cover -->
  <div style="height:180px;background:linear-gradient(135deg,#667eea22,#764ba222);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden">
    <?php if($cover): ?>
    <img src="<?=e($cover)?>" alt="<?=e($bk['title'])?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;flex-direction:column;color:#94a3b8">
      <i class="fas fa-book" style="font-size:3rem;opacity:.3"></i>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;color:#94a3b8;width:100%">
      <i class="fas fa-book" style="font-size:3rem;opacity:.3"></i>
      <div style="font-size:.7rem;margin-top:6px;opacity:.5">No Cover</div>
    </div>
    <?php endif; ?>
    <!-- Availability badge -->
    <div style="position:absolute;top:8px;right:8px">
      <span class="badge badge-<?=$bk['available_copies']>0?'success':'danger'?>" style="font-size:.7rem"><?=$bk['available_copies']>0?$bk['available_copies'].' Available':'Checked Out'?></span>
    </div>
    <?php if($bk['price']>0): ?>
    <div style="position:absolute;top:8px;left:8px">
      <span class="badge badge-warning" style="font-size:.7rem"><?=e($bk['currency'])?> <?=number_format($bk['price'],2)?></span>
    </div>
    <?php endif; ?>
  </div>
  <!-- Info -->
  <div style="padding:14px;flex:1;display:flex;flex-direction:column">
    <div style="font-weight:700;font-size:.9rem;color:var(--dark);margin-bottom:4px;line-height:1.3"><?=e(mb_substr($bk['title'],0,50).(strlen($bk['title'])>50?'...':''))?></div>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:6px"><?=e($bk['author'])?></div>
    <?php if($bk['category']): ?><span class="badge badge-info" style="font-size:.68rem;margin-bottom:8px;width:fit-content"><?=e($bk['category'])?></span><?php endif; ?>
    <div style="font-size:.72rem;color:#cbd5e1;margin-bottom:10px;margin-top:auto">
      <?php if($bk['publish_year']): ?><span><?=$bk['publish_year']?></span><?php endif; ?>
      <?php if($bk['language']&&$bk['language']!=='English'): ?> � <span><?=e($bk['language'])?></span><?php endif; ?>
      <?php if($bk['location']): ?> � <i class="fas fa-map-marker-alt"></i> <?=e($bk['location'])?><?php endif; ?>
    </div>
    <!-- Action button -->
    <?php if($is_borrowed): ?>
    <span class="btn btn-secondary btn-sm" style="text-align:center;cursor:default"><i class="fas fa-check"></i> Currently Borrowed</span>
    <?php elseif($is_requested): ?>
    <span class="btn btn-warning btn-sm" style="text-align:center;cursor:default"><i class="fas fa-clock"></i> Request Pending</span>
    <?php elseif($bk['available_copies']>0): ?>
    <button onclick="openRequestModal(<?=$bk['id']?>,'<?=e(addslashes($bk['title']))?>')" class="btn btn-primary btn-sm" style="width:100%"><i class="fas fa-hand-holding-heart"></i> Request to Borrow</button>
    <?php else: ?>
    <button onclick="openRequestModal(<?=$bk['id']?>,'<?=e(addslashes($bk['title']))?>')" class="btn btn-warning btn-sm" style="width:100%"><i class="fas fa-bookmark"></i> Request (Waitlist)</button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if(!$books): ?>
<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted)"><i class="fas fa-book" style="font-size:3rem;opacity:.2;display:block;margin-bottom:12px"></i>No books found.</div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if($tab==='my'): ?>
<!-- Active Borrows -->
<?php if($my_borrows): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h2><i class="fas fa-hand-holding-heart" style="color:var(--success)"></i> Books I Currently Have (<?=count($my_borrows)?>)</h2></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;padding:16px">
  <?php foreach($my_borrows as $br):
    $overdue = $br['status']==='Overdue' || strtotime($br['due_date'])<time();
    $days_left = (int)((strtotime($br['due_date'])-time())/86400);
    $cover = $br['cover_url'] ?: ($br['isbn']??null ? 'https://covers.openlibrary.org/b/isbn/'.$br['isbn'].'-S.jpg' : '');
  ?>
  <div style="display:flex;gap:12px;background:<?=$overdue?'#fff8f8':'#f8fafc'?>;border-radius:12px;padding:14px;border:1px solid <?=$overdue?'#fecaca':'var(--border)'?>">
    <div style="width:50px;height:70px;background:#e2e8f0;border-radius:6px;overflow:hidden;flex-shrink:0">
      <?php if($cover): ?><img src="<?=e($cover)?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'"><?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center"><i class="fas fa-book" style="color:#94a3b8;font-size:1.2rem"></i></div><?php endif; ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:700;font-size:.88rem;margin-bottom:3px"><?=e($br['title'])?></div>
      <div style="font-size:.75rem;color:var(--muted);margin-bottom:6px"><?=e($br['author'])?></div>
      <div style="font-size:.75rem;color:<?=$overdue?'var(--danger)':'var(--muted)'?>;font-weight:<?=$overdue?'700':'400'?>">
        <?php if($overdue): ?><i class="fas fa-exclamation-triangle"></i> Overdue! Due <?=date('M j',strtotime($br['due_date']))?>
        <?php elseif($days_left<=3): ?><i class="fas fa-clock" style="color:var(--warning)"></i> Due in <?=$days_left?> day(s)
        <?php else: ?><i class="fas fa-calendar"></i> Due <?=date('M j, Y',strtotime($br['due_date']))?><?php endif; ?>
      </div>
      <form method="POST" style="margin-top:8px" onsubmit="return confirm('Request return to librarian?')">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="request_return">
        <input type="hidden" name="borrow_id" value="<?=$br['id']?>">
        <button class="btn btn-sm btn-secondary" style="font-size:.75rem"><i class="fas fa-undo"></i> Request Return</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- My Requests -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list-alt" style="color:var(--primary)"></i> My Borrow Requests (<?=count($my_requests)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Requested</th><th>Needed By</th><th>Status</th><th>Note</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach($my_requests as $rq): ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <?php $rc=$rq['cover_url']?:($rq['isbn']?'https://covers.openlibrary.org/b/isbn/'.$rq['isbn'].'-S.jpg':''); ?>
          <?php if($rc): ?><img src="<?=e($rc)?>" style="width:32px;height:44px;object-fit:cover;border-radius:4px" onerror="this.style.display='none'"><?php endif; ?>
          <div><div style="font-weight:600"><?=e($rq['title'])?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($rq['author'])?></div></div>
        </div>
      </td>
      <td style="font-size:.8rem"><?=date('M j, Y',strtotime($rq['requested_at']))?></td>
      <td style="font-size:.8rem"><?=$rq['needed_by']?date('M j, Y',strtotime($rq['needed_by'])):'�'?></td>
      <td><span class="badge badge-<?=match($rq['status']){'Pending'=>'warning','Approved'=>'success','Rejected'=>'danger','Cancelled'=>'secondary',default=>'secondary'}?>"><?=$rq['status']?></span></td>
      <td style="font-size:.78rem;color:var(--muted)"><?=e(mb_substr($rq['reject_reason']??$rq['note']??'',0,40))?></td>
      <td>
        <?php if($rq['status']==='Pending'): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel request?')">
          <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
          <input type="hidden" name="action" value="cancel_request">
          <input type="hidden" name="request_id" value="<?=$rq['id']?>">
          <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Cancel</button>
        </form>
        <?php else: ?><span style="color:var(--muted);font-size:.8rem">�</span><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(!$my_requests): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-inbox" style="font-size:2rem;opacity:.2;display:block;margin-bottom:8px"></i>No requests yet. Browse books to request one.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- Request Modal -->
<div id="requestModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:440px;max-width:98vw">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
    <h3 style="font-weight:700"><i class="fas fa-hand-holding-heart" style="color:var(--primary)"></i> Request to Borrow</h3>
    <button onclick="document.getElementById('requestModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">&times;</button>
  </div>
  <div id="req_book_title" style="background:var(--bg);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-weight:600;color:var(--dark)"></div>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
    <input type="hidden" name="action" value="request_borrow">
    <input type="hidden" name="book_id" id="req_book_id">
    <div class="form-group" style="margin-bottom:14px">
      <label>Needed By (optional)</label>
      <input type="date" name="needed_by" min="<?=date('Y-m-d',strtotime('+1 day'))?>">
    </div>
    <div class="form-group" style="margin-bottom:18px">
      <label>Note to Librarian (optional)</label>
      <textarea name="note" rows="3" placeholder="Any special note..."></textarea>
    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
      <button type="button" onclick="document.getElementById('requestModal').style.display='none'" class="btn btn-secondary">Cancel</button>
    </div>
  </form>
</div></div>

<script>
function openRequestModal(id,title){
  document.getElementById('req_book_id').value=id;
  document.getElementById('req_book_title').textContent='?? '+title;
  document.getElementById('requestModal').style.display='flex';
}
document.getElementById('requestModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
</script>
<?php require_once '../../includes/footer.php'; ?>