<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student','librarian']);
$page_title = 'Library'; $active_page = 'library';
$uid      = $_SESSION['user']['id'];
$role     = $_SESSION['user']['role'];
$is_admin = is_admin();
$teacher  = is_teacher() ? get_teacher_record($pdo) : null;
$student  = is_student() ? get_student_record($pdo) : null;
$FINE_PER_DAY = 0.50;
$BORROW_DAYS  = 14;
try { $pdo->query("UPDATE library_borrows SET status='Overdue' WHERE status='Borrowed' AND due_date < CURDATE()"); } catch(Exception $e){}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'add_book' && $is_admin) {
        $d = $_POST;
        $isbn = trim($d['isbn'] ?? '') ?: null; // trim whitespace
        try {
            $pdo->prepare("INSERT INTO library_books (isbn,title,author,publisher,publish_year,edition,language,category,subject,description,total_copies,available_copies,price,currency,location,branch_id,added_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$isbn,$d['title'],$d['author'],$d['publisher']??null,$d['publish_year']??null,$d['edition']??null,$d['language']??'English',$d['category']??null,$d['subject']??null,$d['description']??null,(int)($d['total_copies']??1),(int)($d['total_copies']??1),(float)($d['price']??0),$d['currency']??'USD',$d['location']??null,$_SESSION['user']['branch_id']??null,$uid]);
            log_activity($pdo,'book_added',"Book: {$d['title']}");
            flash('Book added.'); header('Location: index.php'); exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('A book with this ISBN already exists. Use a different ISBN or leave it blank.','error');
                header('Location: index.php'); exit;
            }
            throw $e;
        }
    }
    if ($action === 'edit_book' && $is_admin) {
        $d = $_POST; $bid = (int)$d['book_id'];
        $isbn = trim($d['isbn'] ?? '') ?: null;
        $old = $pdo->prepare("SELECT available_copies,total_copies FROM library_books WHERE id=?"); $old->execute([$bid]); $old = $old->fetch();
        $nt = (int)$d['total_copies'];
        $na = max(0, $old['available_copies'] + ($nt - $old['total_copies']));
        try {
            $pdo->prepare("UPDATE library_books SET isbn=?,title=?,author=?,publisher=?,publish_year=?,edition=?,language=?,category=?,subject=?,description=?,total_copies=?,available_copies=?,price=?,currency=?,location=?,is_active=? WHERE id=?")
                ->execute([$isbn,$d['title'],$d['author'],$d['publisher']??null,$d['publish_year']??null,$d['edition']??null,$d['language']??'English',$d['category']??null,$d['subject']??null,$d['description']??null,$nt,$na,(float)($d['price']??0),$d['currency']??'USD',$d['location']??null,(int)($d['is_active']??1),$bid]);
            flash('Book updated.'); header('Location: index.php'); exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash('Another book already has this ISBN.','error');
                header('Location: index.php'); exit;
            }
            throw $e;
        }
    }
    if ($action === 'delete_book' && $is_admin) {
        $pdo->prepare("DELETE FROM library_books WHERE id=?")->execute([(int)$_POST['book_id']]);
        flash('Book removed.'); header('Location: index.php'); exit;
    }
    if ($action === 'borrow' && $is_admin) {
        $bid = (int)$_POST['book_id']; $btype = $_POST['borrower_type'];
        $sid = $btype==='student'?(int)$_POST['borrower_id']:null;
        $tid = $btype==='teacher'?(int)$_POST['borrower_id']:null;
        $due = $_POST['due_date'] ?: date('Y-m-d',strtotime("+{$BORROW_DAYS} days"));
        $av = $pdo->prepare("SELECT available_copies FROM library_books WHERE id=? AND is_active=1"); $av->execute([$bid]);
        if ((int)$av->fetchColumn()<1){flash('No copies available.','error');header('Location: index.php?tab=borrow');exit;}
        $pdo->prepare("INSERT INTO library_borrows (book_id,borrower_type,student_id,teacher_id,due_date,issued_by) VALUES (?,?,?,?,?,?)")->execute([$bid,$btype,$sid,$tid,$due,$uid]);
        $pdo->prepare("UPDATE library_books SET available_copies=available_copies-1 WHERE id=?")->execute([$bid]);
        flash('Book issued.'); header('Location: index.php?tab=borrow'); exit;
    }
    if ($action === 'return' && $is_admin) {
        $lid = (int)$_POST['borrow_id'];
        $row = $pdo->prepare("SELECT * FROM library_borrows WHERE id=?"); $row->execute([$lid]); $row = $row->fetch();
        if (!$row||$row['status']==='Returned'){flash('Invalid.','error');header('Location: index.php?tab=borrow');exit;}
        $dl = max(0,(int)((time()-strtotime($row['due_date']))/86400));
        $fine = $dl>0?round($dl*$FINE_PER_DAY,2):0;
        $pdo->prepare("UPDATE library_borrows SET status='Returned',returned_at=NOW(),fine_amount=?,returned_to=? WHERE id=?")->execute([$fine,$uid,$lid]);
        $pdo->prepare("UPDATE library_books SET available_copies=available_copies+1 WHERE id=?")->execute([$row['book_id']]);
        flash('Returned.'.($fine>0?" Fine: \${$fine}":''));header('Location: index.php?tab=borrow');exit;
    }
    if ($action === 'reserve') {
        $bid=(int)$_POST['book_id']; $btype=$student?'student':'teacher';
        $sid=$student?$student['id']:null; $tid=$teacher?$teacher['id']:null;
        $chk=$pdo->prepare("SELECT id FROM library_reservations WHERE book_id=? AND ".($student?"student_id=?":"teacher_id=?")." AND status IN('Pending','Ready')");
        $chk->execute([$bid,$student?$sid:$tid]);
        if($chk->fetch()){flash('Already reserved.','error');header('Location: index.php');exit;}
        $pdo->prepare("INSERT INTO library_reservations (book_id,borrower_type,student_id,teacher_id,expires_at) VALUES (?,?,?,?,?)")->execute([$bid,$btype,$sid,$tid,date('Y-m-d',strtotime('+3 days'))]);
        flash('Reserved.');header('Location: index.php');exit;
    }
    if ($action === 'cancel_reservation') {
        $pdo->prepare("UPDATE library_reservations SET status='Cancelled' WHERE id=?")->execute([(int)$_POST['reservation_id']]);
        flash('Cancelled.');header('Location: index.php?tab=reserve');exit;
    }
    if ($action === 'reservation_ready' && $is_admin) {
        $pdo->prepare("UPDATE library_reservations SET status='Ready' WHERE id=?")->execute([(int)$_POST['reservation_id']]);
        flash('Marked ready.');header('Location: index.php?tab=reserve');exit;
    }
}

$tab=$_GET['tab']??'books'; $search=trim($_GET['q']??''); $cat=$_GET['cat']??''; $avail=$_GET['avail']??''; $bstatus=$_GET['bstatus']??'';
$stats=['titles'=>$pdo->query("SELECT COUNT(*) FROM library_books WHERE is_active=1")->fetchColumn(),'total_copies'=>$pdo->query("SELECT COALESCE(SUM(total_copies),0) FROM library_books WHERE is_active=1")->fetchColumn(),'available'=>$pdo->query("SELECT COALESCE(SUM(available_copies),0) FROM library_books WHERE is_active=1")->fetchColumn(),'borrowed'=>$pdo->query("SELECT COUNT(*) FROM library_borrows WHERE status IN('Borrowed','Overdue')")->fetchColumn(),'overdue'=>$pdo->query("SELECT COUNT(*) FROM library_borrows WHERE status='Overdue'")->fetchColumn(),'reservations'=>$pdo->query("SELECT COUNT(*) FROM library_reservations WHERE status IN('Pending','Ready')")->fetchColumn()];
$bsql="SELECT * FROM library_books WHERE is_active=1"; $bp=[];
if($search){$bsql.=" AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR subject LIKE ?)";$bp=array_merge($bp,["%$search%","%$search%","%$search%","%$search%"]);}
if($cat){$bsql.=" AND category=?";$bp[]=$cat;}
if($avail==='1')$bsql.=" AND available_copies>0";
if($avail==='0')$bsql.=" AND available_copies=0";
$bsql.=" ORDER BY title";
$bstmt=$pdo->prepare($bsql);$bstmt->execute($bp);$books=$bstmt->fetchAll();
$categories=$pdo->query("SELECT DISTINCT category FROM library_books WHERE category IS NOT NULL AND is_active=1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$brsql="SELECT lb.*,bk.title,bk.author,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name,t.teacher_code FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id LEFT JOIN students s ON lb.student_id=s.id LEFT JOIN teachers t ON lb.teacher_id=t.id WHERE 1=1";
$brp=[];
if(!$is_admin){if($student){$brsql.=" AND lb.borrower_type='student' AND lb.student_id=?";$brp[]=$student['id'];}elseif($teacher){$brsql.=" AND lb.borrower_type='teacher' AND lb.teacher_id=?";$brp[]=$teacher['id'];}}
if($bstatus){$brsql.=" AND lb.status=?";$brp[]=$bstatus;}
$brsql.=" ORDER BY lb.borrowed_at DESC";
$brstmt=$pdo->prepare($brsql);$brstmt->execute($brp);$borrows=$brstmt->fetchAll();
$rssql="SELECT lr.*,bk.title,bk.author,bk.available_copies,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM library_reservations lr JOIN library_books bk ON lr.book_id=bk.id LEFT JOIN students s ON lr.student_id=s.id LEFT JOIN teachers t ON lr.teacher_id=t.id WHERE lr.status IN('Pending','Ready')";
$rsp=[];
if(!$is_admin){if($student){$rssql.=" AND lr.borrower_type='student' AND lr.student_id=?";$rsp[]=$student['id'];}elseif($teacher){$rssql.=" AND lr.borrower_type='teacher' AND lr.teacher_id=?";$rsp[]=$teacher['id'];}}
$rssql.=" ORDER BY lr.reserved_at DESC";
$rsstmt=$pdo->prepare($rssql);$rsstmt->execute($rsp);$reservations=$rsstmt->fetchAll();
$all_students=$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
$all_teachers=$pdo->query("SELECT id,teacher_code,CONCAT(first_name,' ',last_name) AS name FROM teachers WHERE status='Active' ORDER BY first_name")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-book-open" style="color:var(--primary)"></i> Library</h1><p style="color:var(--muted)">Books catalog · Borrowing · Returns · Reservations</p></div>
  <?php if($is_admin):?><button class="btn btn-primary" onclick="document.getElementById('addBookModal').style.display='flex'"><i class="fas fa-plus"></i> Add Book</button><?php endif;?>
</div>
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:22px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?=$stats['titles']?></h3><p>Titles</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-copy"></i></div><div class="stat-info"><h3><?=$stats['total_copies']?></h3><p>Total Copies</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?=$stats['available']?></h3><p>Available</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-hand-holding-heart"></i></div><div class="stat-info"><h3><?=$stats['borrowed']?></h3><p>Borrowed</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3><?=$stats['overdue']?></h3><p>Overdue</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-bookmark"></i></div><div class="stat-info"><h3><?=$stats['reservations']?></h3><p>Reservations</p></div></div>
</div>
<div style="display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;padding:4px;border-radius:10px;width:fit-content">
  <?php foreach(['books'=>'<i class="fas fa-book"></i> Books','borrow'=>'<i class="fas fa-exchange-alt"></i> Borrow / Return','reserve'=>'<i class="fas fa-bookmark"></i> Reservations'] as $t=>$lbl):?>
  <a href="?tab=<?=$t?>" style="padding:8px 18px;border-radius:8px;font-size:.84rem;font-weight:600;text-decoration:none;transition:all .2s;<?=$tab===$t?'background:#fff;color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.1)':'color:var(--muted)'?>"><?=$lbl?></a>
  <?php endforeach;?>
</div>

<?php if($tab==='books'):?>
<div class="card" style="margin-bottom:14px"><div class="card-body" style="padding:12px 18px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="tab" value="books">
    <input name="q" placeholder="Search title, author, ISBN..." value="<?=e($search)?>" style="max-width:260px">
    <select name="cat" onchange="this.form.submit()"><option value="">All Categories</option><?php foreach($categories as $c):?><option value="<?=e($c)?>" <?=$cat===$c?'selected':''?>><?=e($c)?></option><?php endforeach;?></select>
    <select name="avail" onchange="this.form.submit()"><option value="">All</option><option value="1" <?=$avail==='1'?'selected':''?>>Available</option><option value="0" <?=$avail==='0'?'selected':''?>>Checked Out</option></select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <a href="?tab=books" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-book" style="color:var(--primary)"></i> Books Catalog (<?=count($books)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Title / Author</th><th>ISBN</th><th>Category</th><th>Language</th><th>Year</th><th>Location</th><th>Price</th><th>Copies</th><th>Available</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($books as $bk):?>
    <tr>
      <td><div style="font-weight:700"><?=e($bk['title'])?></div><div style="font-size:.78rem;color:var(--muted)"><?=e($bk['author'])?></div><?php if($bk['publisher']):?><div style="font-size:.7rem;color:#cbd5e1"><?=e($bk['publisher'])?></div><?php endif;?></td>
      <td style="font-family:monospace;font-size:.8rem"><?=e($bk['isbn']??'—')?></td>
      <td><?=$bk['category']?'<span class="badge badge-info">'.e($bk['category']).'</span>':'—'?></td>
      <td style="font-size:.82rem"><?=e($bk['language']??'—')?></td>
      <td style="font-size:.82rem"><?=$bk['publish_year']??'—'?></td>
      <td style="font-size:.8rem;color:var(--muted)"><?=e($bk['location']??'—')?></td>
      <td style="font-weight:600"><?=$bk['price']>0?e($bk['currency']).' '.number_format($bk['price'],2):'<span style="color:var(--success)">Free</span>'?></td>
      <td style="text-align:center"><?=$bk['total_copies']?></td>
      <td style="text-align:center"><span class="badge badge-<?=$bk['available_copies']>0?'success':'danger'?>"><?=$bk['available_copies']?></span></td>
      <td>
        <?php if($is_admin):?>
          <button onclick="openEditBook(<?=htmlspecialchars(json_encode($bk),ENT_QUOTES)?>)" class="btn btn-sm btn-primary btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
          <?php if($bk['available_copies']>0):?><button onclick="openBorrowModal(<?=$bk['id']?>,'<?=e(addslashes($bk['title']))?>')" class="btn btn-sm btn-success btn-icon" title="Issue"><i class="fas fa-hand-holding-heart"></i></button><?php endif;?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete_book"><input type="hidden" name="book_id" value="<?=$bk['id']?>"><button class="btn btn-sm btn-danger btn-icon"><i class="fas fa-trash"></i></button></form>
        <?php elseif($student||$teacher):?>
          <?php if($bk['available_copies']<1):?>
          <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="reserve"><input type="hidden" name="book_id" value="<?=$bk['id']?>"><button class="btn btn-sm btn-warning"><i class="fas fa-bookmark"></i> Reserve</button></form>
          <?php else:?><span class="badge badge-success" style="font-size:.72rem">Available</span><?php endif;?>
        <?php endif;?>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$books):?><tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-book" style="font-size:2rem;opacity:.2;display:block;margin-bottom:8px"></i>No books found.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<?php if($tab==='borrow'):?>
<div class="card" style="margin-bottom:14px"><div class="card-body" style="padding:12px 18px">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="tab" value="borrow">
    <select name="bstatus" onchange="this.form.submit()"><option value="">All Status</option><?php foreach(['Borrowed','Returned','Overdue','Lost'] as $s):?><option value="<?=$s?>" <?=$bstatus===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select>
    <a href="?tab=borrow" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-exchange-alt" style="color:var(--warning)"></i> Borrow Records (<?=count($borrows)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Borrower</th><th>Type</th><th>Issued</th><th>Due Date</th><th>Returned</th><th>Status</th><th>Fine</th><?php if($is_admin):?><th>Action</th><?php endif;?></tr></thead>
    <tbody>
    <?php foreach($borrows as $br):
      $ov=$br['status']==='Borrowed'&&strtotime($br['due_date'])<time();
      $dl=$ov?(int)((time()-strtotime($br['due_date']))/86400):0;
      $fine=$dl>0?round($dl*$FINE_PER_DAY,2):(float)($br['fine_amount']??0);
    ?>
    <tr style="<?=$ov?'background:#fff8f8':''?>">
      <td><div style="font-weight:600"><?=e($br['title'])?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($br['author'])?></div></td>
      <td><?php if($br['borrower_type']==='student'):?><div style="font-weight:600"><?=e($br['student_name']??'—')?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($br['student_code']??'')?></div><?php else:?><div style="font-weight:600"><?=e($br['teacher_name']??'—')?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($br['teacher_code']??'')?></div><?php endif;?></td>
      <td><span class="badge badge-<?=$br['borrower_type']==='student'?'info':'primary'?>"><?=ucfirst($br['borrower_type'])?></span></td>
      <td style="font-size:.8rem"><?=date('M j, Y',strtotime($br['borrowed_at']))?></td>
      <td style="font-size:.8rem;color:<?=$ov?'var(--danger)':'inherit'?>;font-weight:<?=$ov?'700':'400'?>"><?=date('M j, Y',strtotime($br['due_date']))?><?php if($ov):?><div style="font-size:.7rem;color:var(--danger)"><?=$dl?> day(s) late</div><?php endif;?></td>
      <td style="font-size:.8rem"><?=$br['returned_at']?date('M j, Y',strtotime($br['returned_at'])):'—'?></td>
      <td><span class="badge badge-<?=match($br['status']){'Borrowed'=>'warning','Returned'=>'success','Overdue'=>'danger',default=>'secondary'}?>"><?=$br['status']?></span></td>
      <td style="font-weight:600;color:<?=$fine>0?'var(--danger)':'var(--muted)'?>"><?=$fine>0?'$'.number_format($fine,2):'—'?></td>
      <?php if($is_admin):?><td><?php if(in_array($br['status'],['Borrowed','Overdue'])):?><form method="POST" style="display:inline" onsubmit="return confirm('Mark returned?')"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="return"><input type="hidden" name="borrow_id" value="<?=$br['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-undo"></i> Return</button></form><?php else:?><span style="color:var(--muted)">—</span><?php endif;?></td><?php endif;?>
    </tr>
    <?php endforeach;?>
    <?php if(!$borrows):?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-exchange-alt" style="font-size:2rem;opacity:.2;display:block;margin-bottom:8px"></i>No records.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<?php if($tab==='reserve'):?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-bookmark" style="color:var(--secondary)"></i> Reservations (<?=count($reservations)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Book</th><th>Reserved By</th><th>Type</th><th>Reserved</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($reservations as $rv):?>
    <tr>
      <td><div style="font-weight:600"><?=e($rv['title'])?></div><div style="font-size:.75rem;color:var(--muted)"><?=e($rv['author'])?></div><span class="badge badge-<?=$rv['available_copies']>0?'success':'danger'?>" style="font-size:.65rem"><?=$rv['available_copies']>0?'Now Available':'Not Available'?></span></td>
      <td><?php if($rv['borrower_type']==='student'):?><div style="font-weight:600"><?=e($rv['student_name']??'—')?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($rv['student_code']??'')?></div><?php else:?><div style="font-weight:600"><?=e($rv['teacher_name']??'—')?></div><?php endif;?></td>
      <td><span class="badge badge-<?=$rv['borrower_type']==='student'?'info':'primary'?>"><?=ucfirst($rv['borrower_type'])?></span></td>
      <td style="font-size:.8rem"><?=date('M j, Y',strtotime($rv['reserved_at']))?></td>
      <td style="font-size:.8rem"><?=$rv['expires_at']?date('M j, Y',strtotime($rv['expires_at'])):'—'?></td>
      <td><span class="badge badge-<?=$rv['status']==='Ready'?'success':'warning'?>"><?=$rv['status']?></span></td>
      <td><div style="display:flex;gap:4px">
        <?php if($is_admin&&$rv['status']==='Pending'):?><form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="reservation_ready"><input type="hidden" name="reservation_id" value="<?=$rv['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Ready</button></form><?php endif;?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Cancel?')"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="cancel_reservation"><input type="hidden" name="reservation_id" value="<?=$rv['id']?>"><button class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Cancel</button></form>
      </div></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$reservations):?><tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-bookmark" style="font-size:2rem;opacity:.2;display:block;margin-bottom:8px"></i>No reservations.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<?php if($is_admin):?>
<div id="addBookModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:680px;max-width:98vw;max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h3 style="font-weight:700"><i class="fas fa-plus" style="color:var(--success)"></i> Add New Book</h3><button onclick="document.getElementById('addBookModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button></div>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="add_book">
    <div class="form-grid">
      <div class="form-group full"><label>ISBN <span style="font-size:.75rem;color:var(--muted)">— optional, auto-fills fields</span></label><div style="display:flex;gap:6px"><input name="isbn" id="addBookIsbn" placeholder="e.g. 9780061965784 — leave blank if unknown" style="flex:1"><button type="button" id="isbnLookupBtn" onclick="lookupISBN()" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Lookup</button></div></div>
      <div class="form-group full"><label>Title *</label><input name="title" id="add_title" required placeholder="Book title"></div>
      <div class="form-group full"><label>Author(s) *</label><input name="author" id="add_author" required placeholder="Author name(s)"></div>
      <div class="form-group"><label>Publisher</label><input name="publisher" id="add_publisher"></div>
      <div class="form-group"><label>Year</label><input type="number" name="publish_year" id="add_year" min="1800" max="<?=date('Y')?>" placeholder="<?=date('Y')?>"></div>
      <div class="form-group"><label>Edition</label><input name="edition" placeholder="e.g. 3rd"></div>
      <div class="form-group"><label>Language</label><select name="language"><?php foreach(['English','Amharic','Arabic','French','Spanish','German','Chinese','Other'] as $l):?><option><?=$l?></option><?php endforeach;?></select></div>
      <div class="form-group"><label>Category</label><input name="category" id="add_category" placeholder="e.g. Science, Fiction"></div>
      <div class="form-group"><label>Subject</label><input name="subject" id="add_subject" placeholder="e.g. Mathematics"></div>
      <div class="form-group"><label>Shelf Location</label><input name="location" placeholder="e.g. A-3, Row 2"></div>
      <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" value="1" min="1"></div>
      <div class="form-group"><label>Price</label><div style="display:flex;gap:6px"><select name="currency" style="width:80px"><?php foreach(['USD','ETB','EUR','GBP','AED'] as $c):?><option><?=$c?></option><?php endforeach;?></select><input type="number" step="0.01" name="price" value="0.00" min="0"></div></div>
      <div class="form-group full"><label>Description</label><textarea name="description" id="add_description" rows="3"></textarea></div>
    </div>
    <div style="margin-top:16px;display:flex;gap:10px"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Book</button><button type="button" onclick="document.getElementById('addBookModal').style.display='none'" class="btn btn-secondary">Cancel</button></div>
  </form>
</div></div>

<div id="editBookModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:680px;max-width:98vw;max-height:90vh;overflow-y:auto">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h3 style="font-weight:700"><i class="fas fa-edit" style="color:var(--primary)"></i> Edit Book</h3><button onclick="document.getElementById('editBookModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button></div>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="edit_book"><input type="hidden" name="book_id" id="eb_id">
    <div class="form-grid">
      <div class="form-group full"><label>Title *</label><input name="title" id="eb_title" required></div>
      <div class="form-group full"><label>Author(s) *</label><input name="author" id="eb_author" required></div>
      <div class="form-group"><label>ISBN</label><input name="isbn" id="eb_isbn"></div>
      <div class="form-group"><label>Publisher</label><input name="publisher" id="eb_publisher"></div>
      <div class="form-group"><label>Year</label><input type="number" name="publish_year" id="eb_year"></div>
      <div class="form-group"><label>Edition</label><input name="edition" id="eb_edition"></div>
      <div class="form-group"><label>Language</label><input name="language" id="eb_language"></div>
      <div class="form-group"><label>Category</label><input name="category" id="eb_category"></div>
      <div class="form-group"><label>Subject</label><input name="subject" id="eb_subject"></div>
      <div class="form-group"><label>Location</label><input name="location" id="eb_location"></div>
      <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" id="eb_copies" min="1"></div>
      <div class="form-group"><label>Price</label><input type="number" step="0.01" name="price" id="eb_price" min="0"></div>
      <div class="form-group"><label>Currency</label><input name="currency" id="eb_currency" style="max-width:80px"></div>
      <div class="form-group"><label>Active</label><select name="is_active" id="eb_active"><option value="1">Yes</option><option value="0">No</option></select></div>
      <div class="form-group full"><label>Description</label><textarea name="description" id="eb_desc" rows="3"></textarea></div>
    </div>
    <div style="margin-top:16px;display:flex;gap:10px"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button><button type="button" onclick="document.getElementById('editBookModal').style.display='none'" class="btn btn-secondary">Cancel</button></div>
  </form>
</div></div>

<div id="borrowModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
<div style="background:#fff;border-radius:16px;padding:28px;width:460px;max-width:98vw">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px"><h3 style="font-weight:700"><i class="fas fa-hand-holding-heart" style="color:var(--success)"></i> Issue Book</h3><button onclick="document.getElementById('borrowModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button></div>
  <div id="borrow_title" style="background:var(--bg);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-weight:600"></div>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="borrow"><input type="hidden" name="book_id" id="borrow_book_id">
    <div class="form-group" style="margin-bottom:14px"><label>Borrower Type</label><select name="borrower_type" onchange="toggleBorrower(this.value)"><option value="student">Student</option><option value="teacher">Teacher</option></select></div>
    <div class="form-group" style="margin-bottom:14px" id="stu_wrap"><label>Student</label><select name="borrower_id" id="stu_sel"><?php foreach($all_students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?> (<?=e($s['student_code']??'')?>)</option><?php endforeach;?></select></div>
    <div class="form-group" style="margin-bottom:14px;display:none" id="tch_wrap"><label>Teacher</label><select name="borrower_id" id="tch_sel" disabled><?php foreach($all_teachers as $t):?><option value="<?=$t['id']?>"><?=e($t['name'])?> (<?=e($t['teacher_code']??'')?>)</option><?php endforeach;?></select></div>
    <div class="form-group" style="margin-bottom:18px"><label>Due Date</label><input type="date" name="due_date" value="<?=date('Y-m-d',strtotime("+{$BORROW_DAYS} days"))?>" min="<?=date('Y-m-d',strtotime('+1 day'))?>"></div>
    <div style="display:flex;gap:10px"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Issue</button><button type="button" onclick="document.getElementById('borrowModal').style.display='none'" class="btn btn-secondary">Cancel</button></div>
  </form>
</div></div>
<?php endif;?>

<script>
function openBorrowModal(id,title){document.getElementById('borrow_book_id').value=id;document.getElementById('borrow_title').textContent='📖 '+title;document.getElementById('borrowModal').style.display='flex';}
function toggleBorrower(type){document.getElementById('stu_wrap').style.display=type==='student'?'':'none';document.getElementById('tch_wrap').style.display=type==='teacher'?'':'none';document.getElementById('stu_sel').disabled=type!=='student';document.getElementById('tch_sel').disabled=type!=='teacher';}
function openEditBook(bk){var m={'id':'id','title':'title','author':'author','isbn':'isbn','publisher':'publisher','publish_year':'year','edition':'edition','language':'language','category':'category','subject':'subject','location':'location','total_copies':'copies','price':'price','currency':'currency','is_active':'active','description':'desc'};Object.keys(m).forEach(function(k){var el=document.getElementById('eb_'+m[k]);if(el)el.value=bk[k]||'';});document.getElementById('editBookModal').style.display='flex';}
['addBookModal','editBookModal','borrowModal'].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener('click',function(e){if(e.target===this)this.style.display='none';});});

function lookupISBN() {
    var isbn = document.getElementById('addBookIsbn').value.trim().replace(/[-\s]/g,'');
    var btn  = document.getElementById('isbnLookupBtn');
    if (!isbn) { alert('Enter an ISBN first.'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Looking up...';

    // Try Open Library first (100% free, no key)
    fetch('https://openlibrary.org/api/books?bibkeys=ISBN:' + isbn + '&format=json&jscmd=data')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            var key = 'ISBN:' + isbn;
            if (data[key]) {
                var b = data[key];
                var fill = function(id, val) { var el=document.getElementById(id); if(el && val) el.value=val; };
                fill('add_title',       b.title || '');
                fill('add_author',      b.authors ? b.authors.map(function(a){return a.name;}).join(', ') : '');
                fill('add_publisher',   b.publishers ? b.publishers[0].name : '');
                fill('add_year',        b.publish_date ? b.publish_date.replace(/\D/g,'').substring(0,4) : '');
                fill('add_description', b.notes ? (typeof b.notes==='string'?b.notes:b.notes.value||'') : '');
                if (b.subjects && b.subjects.length) {
                    fill('add_subject',   b.subjects[0].name || '');
                    fill('add_category',  b.subjects[0].name || '');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check" style="color:#2dc653"></i> Found!';
                setTimeout(function(){ btn.innerHTML = '<i class="fas fa-search"></i> Lookup'; }, 2500);
            } else {
                // Fallback: Google Books (also free, no key for basic use)
                return fetch('https://www.googleapis.com/books/v1/volumes?q=isbn:' + isbn)
                    .then(function(r){ return r.json(); })
                    .then(function(gdata) {
                        if (gdata.totalItems > 0) {
                            var info = gdata.items[0].volumeInfo;
                            var fill = function(id, val) { var el=document.getElementById(id); if(el && val) el.value=val; };
                            fill('add_title',       info.title || '');
                            fill('add_author',      (info.authors||[]).join(', '));
                            fill('add_publisher',   info.publisher || '');
                            fill('add_year',        (info.publishedDate||'').substring(0,4));
                            fill('add_description', (info.description||'').substring(0,400));
                            if (info.categories && info.categories.length) {
                                fill('add_category', info.categories[0]);
                                fill('add_subject',  info.categories[0]);
                            }
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-check" style="color:#2dc653"></i> Found!';
                            setTimeout(function(){ btn.innerHTML = '<i class="fas fa-search"></i> Lookup'; }, 2500);
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-times" style="color:#e63946"></i> Not found';
                            setTimeout(function(){ btn.innerHTML = '<i class="fas fa-search"></i> Lookup'; }, 2500);
                        }
                    });
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-times" style="color:#e63946"></i> Error';
            setTimeout(function(){ btn.innerHTML = '<i class="fas fa-search"></i> Lookup'; }, 2500);
        });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
