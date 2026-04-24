<?php
require_once '../../includes/config.php';
auth_check(['librarian','admin','super_admin']);
$page_title = 'Library Dashboard'; $active_page = 'librarian_dash';
$me = $_SESSION['user']['id'];

// Auto-mark overdue
try { $pdo->query("UPDATE library_borrows SET status='Overdue' WHERE status='Borrowed' AND due_date < CURDATE()"); } catch(Exception $e){}

// All stats in minimal queries
$stats_defaults = ['total_titles'=>0,'total_copies'=>0,'available'=>0,'borrowed'=>0,'overdue'=>0,'total_returned'=>0,'returned_today'=>0,'lost'=>0,'fines_due'=>0,'fines_paid'=>0,'pending_requests'=>0,'return_requests'=>0,'total_students'=>0,'total_teachers'=>0,'students_with_books'=>0,'teachers_with_books'=>0];
try { $stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM library_books WHERE is_active=1) AS total_titles,
    (SELECT COALESCE(SUM(total_copies),0) FROM library_books WHERE is_active=1) AS total_copies,
    (SELECT COALESCE(SUM(available_copies),0) FROM library_books WHERE is_active=1) AS available,
    (SELECT COUNT(*) FROM library_borrows WHERE status IN('Borrowed','Overdue')) AS borrowed,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Overdue') AS overdue,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Returned') AS total_returned,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Returned' AND DATE(returned_at)=CURDATE()) AS returned_today,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Lost') AS lost,
    (SELECT COALESCE(SUM(fine_amount+damage_fee),0) FROM library_borrows WHERE fine_amount+damage_fee>0 AND fine_paid=0) AS fines_due,
    (SELECT COALESCE(SUM(fine_amount+damage_fee),0) FROM library_borrows WHERE fine_paid=1) AS fines_paid,
    (SELECT COUNT(*) FROM library_requests WHERE status='Pending') AS pending_requests,
    (SELECT COUNT(*) FROM library_borrows WHERE status='Return Requested') AS return_requests,
    (SELECT COUNT(*) FROM students WHERE status='Active') AS total_students,
    (SELECT COUNT(*) FROM teachers WHERE status='Active') AS total_teachers,
    (SELECT COUNT(DISTINCT student_id) FROM library_borrows WHERE borrower_type='student' AND status IN('Borrowed','Overdue')) AS students_with_books,
    (SELECT COUNT(DISTINCT teacher_id) FROM library_borrows WHERE borrower_type='teacher' AND status IN('Borrowed','Overdue')) AS teachers_with_books
")->fetch() ?: $stats_defaults; } catch(Exception $e) { $stats = $stats_defaults; }

try { $top_books = $pdo->query("SELECT bk.title, bk.author, COUNT(lb.id) AS cnt
    FROM library_borrows lb JOIN library_books bk ON lb.book_id=bk.id
    GROUP BY lb.book_id ORDER BY cnt DESC LIMIT 8")->fetchAll(); } catch(Exception $e) { $top_books = []; }

try { $recent_activity = $pdo->query("SELECT lb.*, bk.title,
    COALESCE(CONCAT(s.first_name,' ',s.last_name), CONCAT(t.first_name,' ',t.last_name)) AS borrower_name,
    lb.borrower_type
    FROM library_borrows lb
    JOIN library_books bk ON lb.book_id=bk.id
    LEFT JOIN students s ON lb.student_id=s.id
    LEFT JOIN teachers t ON lb.teacher_id=t.id
    ORDER BY lb.borrowed_at DESC LIMIT 8")->fetchAll(); } catch(Exception $e) { $recent_activity = []; }

require_once '../../includes/header.php';

// Borrow trend last 6 months
try { $borrow_trend = $pdo->query("SELECT DATE_FORMAT(borrowed_at,'%b %Y') AS mo, COUNT(*) AS cnt FROM library_borrows WHERE borrowed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(borrowed_at,'%Y-%m') ORDER BY DATE_FORMAT(borrowed_at,'%Y-%m')")->fetchAll(); } catch(Exception $e) { $borrow_trend = []; }
// Category distribution
try { $cat_dist = $pdo->query("SELECT COALESCE(category,'Uncategorized') AS cat, COUNT(*) AS cnt FROM library_books WHERE is_active=1 GROUP BY category ORDER BY cnt DESC LIMIT 8")->fetchAll(); } catch(Exception $e) { $cat_dist = []; }
?>
<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px"></i>Library Dashboard</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:2px">Overview and reports — <?= date('l, F j, Y') ?></p>
  </div>
  <a href="librarian_desk.php" class="btn btn-primary"><i class="fas fa-tasks"></i> Librarian Desk</a>
</div>

<!-- KPI Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px">
  <?php foreach([
    [$stats['total_titles'],'fas fa-book','Titles','#4361ee'],
    [$stats['total_copies'],'fas fa-copy','Total Copies','#0891b2'],
    [$stats['available'],'fas fa-check-circle','Available','#10b981'],
    [$stats['borrowed'],'fas fa-hand-holding-heart','Borrowed','#f59e0b'],
    [$stats['overdue'],'fas fa-exclamation-circle','Overdue','#e63946'],
    [$stats['total_returned'],'fas fa-undo','Returned','#7209b7'],
    [$stats['returned_today'],'fas fa-calendar-check','Returned Today','#06b6d4'],
    [$stats['lost'],'fas fa-times-circle','Lost','#dc2626'],
    ['$'.number_format($stats['fines_due'],2),'fas fa-dollar-sign','Fines Due','#f97316'],
    [$stats['pending_requests'],'fas fa-inbox','Pending Requests','#8b5cf6'],
    [$stats['total_students'],'fas fa-user-graduate','Students','#4361ee'],
    [$stats['students_with_books'],'fas fa-book-reader','Students w/ Books','#10b981'],
  ] as [$val,$icon,$lbl,$color]): ?>
  <div style="background:#fff;border-radius:12px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,.06);border-top:3px solid <?=$color?>">
    <div style="width:36px;height:36px;border-radius:9px;background:<?=$color?>18;display:flex;align-items:center;justify-content:center;margin-bottom:10px">
      <i class="<?=$icon?>" style="color:<?=$color?>;font-size:.9rem"></i>
    </div>
    <div style="font-size:1.4rem;font-weight:800;color:#1e293b;line-height:1"><?=$val?></div>
    <div style="font-size:.72rem;color:#64748b;margin-top:4px"><?=$lbl?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
  <!-- Borrow trend -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.92rem;color:#1e293b;margin-bottom:16px"><i class="fas fa-chart-line" style="color:var(--primary);margin-right:6px"></i>Borrow Trend (6 Months)</div>
    <?php if($borrow_trend): ?>
    <canvas id="borrowChart" style="width:100%;height:180px"></canvas>
    <?php else: ?>
    <div style="height:180px;display:flex;align-items:center;justify-content:center;color:#cbd5e1;flex-direction:column"><i class="fas fa-chart-line" style="font-size:2rem;margin-bottom:8px"></i>No borrow data yet</div>
    <?php endif; ?>
  </div>
  <!-- Category distribution -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.92rem;color:#1e293b;margin-bottom:16px"><i class="fas fa-chart-pie" style="color:#7209b7;margin-right:6px"></i>Books by Category</div>
    <?php if($cat_dist): ?>
    <canvas id="catChart" style="width:100%;height:180px"></canvas>
    <?php else: ?>
    <div style="height:180px;display:flex;align-items:center;justify-content:center;color:#cbd5e1;flex-direction:column"><i class="fas fa-chart-pie" style="font-size:2rem;margin-bottom:8px"></i>No books added yet</div>
    <?php endif; ?>
  </div>
</div>

<!-- Bottom Row: Top Books + Recent Activity -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
  <!-- Top borrowed books -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-fire" style="color:#f59e0b;margin-right:6px"></i>Most Borrowed Books</div>
    <?php if($top_books): ?>
    <div style="padding:8px 0">
    <?php foreach($top_books as $i=>$b): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid #f8fafc">
      <div style="width:28px;height:28px;border-radius:8px;background:<?=['#4361ee','#7209b7','#10b981','#f59e0b','#e63946','#0891b2','#8b5cf6','#06b6d4'][$i%8]?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;flex-shrink:0"><?=$i+1?></div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:.85rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($b['title'])?></div>
        <div style="font-size:.75rem;color:#94a3b8"><?=e($b['author'])?></div>
      </div>
      <span style="background:#f1f5f9;color:#475569;border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700;flex-shrink:0"><?=$b['cnt']?>x</span>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="padding:40px;text-align:center;color:#cbd5e1"><i class="fas fa-book" style="font-size:2rem;display:block;margin-bottom:8px"></i>No borrow records yet</div>
    <?php endif; ?>
  </div>

  <!-- Recent activity -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.92rem;color:#1e293b"><i class="fas fa-history" style="color:#10b981;margin-right:6px"></i>Recent Activity</div>
    <?php if($recent_activity): ?>
    <div style="padding:8px 0">
    <?php foreach($recent_activity as $a): ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid #f8fafc">
      <div style="width:36px;height:36px;border-radius:10px;background:<?=$a['status']==='Overdue'?'#fee2e2':($a['status']==='Returned'?'#dcfce7':'#fef9c3')?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fas fa-<?=$a['status']==='Returned'?'undo':($a['status']==='Overdue'?'exclamation-triangle':'hand-holding-heart')?>" style="color:<?=$a['status']==='Overdue'?'#e63946':($a['status']==='Returned'?'#10b981':'#f59e0b')?>;font-size:.85rem"></i>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:600;font-size:.83rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($a['title'])?></div>
        <div style="font-size:.75rem;color:#94a3b8"><?=e($a['borrower_name']??'—')?> &middot; <?=ucfirst($a['borrower_type'])?></div>
      </div>
      <span style="font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:8px;background:<?=match($a['status']){'Borrowed'=>'#fef9c3','Returned'=>'#dcfce7','Overdue'=>'#fee2e2','Lost'=>'#fce7f3',default=>'#f1f5f9'}?>;color:<?=match($a['status']){'Borrowed'=>'#92400e','Returned'=>'#166534','Overdue'=>'#991b1b','Lost'=>'#9d174d',default=>'#475569'}?>;flex-shrink:0"><?=$a['status']?></span>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="padding:40px;text-align:center;color:#cbd5e1"><i class="fas fa-history" style="font-size:2rem;display:block;margin-bottom:8px"></i>No activity yet</div>
    <?php endif; ?>
  </div>
</div>

<?php if($borrow_trend||$cat_dist): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const palette=['#4361ee','#7209b7','#10b981','#f59e0b','#e63946','#0891b2','#8b5cf6','#06b6d4'];
<?php if($borrow_trend): ?>
new Chart(document.getElementById('borrowChart'),{type:'bar',data:{labels:<?=json_encode(array_column($borrow_trend,'mo'))?>,datasets:[{label:'Borrows',data:<?=json_encode(array_column($borrow_trend,'cnt'))?>,backgroundColor:'rgba(67,97,238,.8)',borderRadius:6}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1,font:{size:10}},grid:{color:'#f1f5f9'}},x:{ticks:{font:{size:10}},grid:{display:false}}}}});
<?php endif; ?>
<?php if($cat_dist): ?>
new Chart(document.getElementById('catChart'),{type:'doughnut',data:{labels:<?=json_encode(array_column($cat_dist,'cat'))?>,datasets:[{data:<?=json_encode(array_column($cat_dist,'cnt'))?>,backgroundColor:palette,borderWidth:0}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{font:{size:10},boxWidth:10}}},cutout:'60%'}});
<?php endif; ?>
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>