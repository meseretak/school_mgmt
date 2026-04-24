<?php
require_once '../../includes/config.php';
auth_check(['parent']);
$page_title = 'Parent Dashboard'; $active_page = 'parent_dashboard';
$me = $_SESSION['user']['id'];

$parent = $pdo->prepare("SELECT p.*, u.email FROM parents p JOIN users u ON p.user_id=u.id WHERE p.user_id=?");
$parent->execute([$me]); $parent = $parent->fetch();
if (!$parent) { flash('Parent profile not found.','error'); header('Location: '.BASE_URL.'/logout.php'); exit; }

$children = $pdo->prepare("
    SELECT s.*, u.email, sp.is_primary,
        (SELECT COUNT(*) FROM payments WHERE student_id=s.id AND status IN('Pending','Overdue')) AS pending_count,
        (SELECT COALESCE(SUM(amount_due-amount_paid),0) FROM payments WHERE student_id=s.id AND status IN('Pending','Overdue','Partial')) AS balance_due,
        (SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE student_id=s.id AND status='Paid') AS total_paid,
        (SELECT COALESCE(SUM(amount_due),0) FROM payments WHERE student_id=s.id) AS total_fees,
        (SELECT COUNT(*) FROM enrollments WHERE student_id=s.id AND status='Enrolled') AS enrolled_courses,
        (SELECT ROUND(AVG(g.marks_obtained/ex.total_marks*100),1) FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id WHERE en.student_id=s.id) AS avg_grade,
        (SELECT COUNT(*) FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id WHERE en.student_id=s.id) AS att_total,
        (SELECT COUNT(*) FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id WHERE en.student_id=s.id AND a.status='Present') AS att_present,
        (SELECT COUNT(*) FROM library_borrows WHERE borrower_type='student' AND student_id=s.id AND status IN('Borrowed','Overdue')) AS books_borrowed,
        (SELECT COUNT(*) FROM library_borrows WHERE borrower_type='student' AND student_id=s.id AND status='Overdue') AS books_overdue
    FROM student_parents sp JOIN students s ON sp.student_id=s.id JOIN users u ON s.user_id=u.id
    WHERE sp.parent_id=? ORDER BY sp.is_primary DESC, s.first_name");
$children->execute([$parent['id']]); $children = $children->fetchAll();

// Aggregate totals across ALL children
$agg = [
    'total_children'  => count($children),
    'total_balance'   => array_sum(array_column($children,'balance_due')),
    'total_paid'      => array_sum(array_column($children,'total_paid')),
    'total_fees'      => array_sum(array_column($children,'total_fees')),
    'pending_payments'=> array_sum(array_column($children,'pending_count')),
    'books_borrowed'  => array_sum(array_column($children,'books_borrowed')),
    'books_overdue'   => array_sum(array_column($children,'books_overdue')),
];

// Recent notices for parents
try {
    $notices = $pdo->query("SELECT * FROM notices WHERE is_active=1 AND audience IN('all','parent') ORDER BY post_date DESC LIMIT 5")->fetchAll();
} catch(Exception $e) { $notices = []; }

$selected_id = (int)($_GET['student_id'] ?? ($children[0]['id'] ?? 0));
$tab = $_GET['tab'] ?? 'overview';
$student = null;
foreach ($children as $c) { if ($c['id'] == $selected_id) { $student = $c; break; } }

if ($student) {
    $sid = $student['id'];

    // Grades per course
    $grades = $pdo->prepare("SELECT co.name AS course_name, co.code, ay.label AS year,
        AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(g.id) AS exams_taken,
        MIN(g.marks_obtained/ex.total_marks*100) AS min_pct, MAX(g.marks_obtained/ex.total_marks*100) AS max_pct
        FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id
        JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id
        JOIN academic_years ay ON cl.academic_year_id=ay.id
        WHERE en.student_id=? GROUP BY co.id, ay.id ORDER BY ay.start_date DESC, co.name");
    $grades->execute([$sid]); $grades = $grades->fetchAll();

    // Recent exam results
    $recent_grades = $pdo->prepare("SELECT g.marks_obtained, g.grade_letter, g.remarks,
        ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, ex.exam_date,
        co.name AS course_name, co.code
        FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id
        JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id
        WHERE en.student_id=? ORDER BY g.graded_at DESC LIMIT 10");
    $recent_grades->execute([$sid]); $recent_grades = $recent_grades->fetchAll();

    // Attendance summary
    $att = $pdo->prepare("SELECT COUNT(*) AS total, SUM(a.status='Present') AS present,
        SUM(a.status='Absent') AS absent, SUM(a.status='Late') AS late
        FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id WHERE en.student_id=?");
    $att->execute([$sid]); $att = $att->fetch();
    $att_rate = $att['total'] > 0 ? round($att['present']/$att['total']*100) : null;

    // Attendance per course
    $att_detail = $pdo->prepare("SELECT co.name AS course_name, co.code, cl.section,
        COUNT(a.id) AS total, SUM(a.status='Present') AS present,
        SUM(a.status='Absent') AS absent, SUM(a.status='Late') AS late
        FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id
        JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id
        WHERE en.student_id=? GROUP BY cl.id ORDER BY co.name");
    $att_detail->execute([$sid]); $att_detail = $att_detail->fetchAll();

    // All payments
    $payments = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year
        FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id
        LEFT JOIN academic_years ay ON p.academic_year_id=ay.id
        WHERE p.student_id=? ORDER BY p.status='Overdue' DESC, p.status='Pending' DESC, p.due_date ASC");
    $payments->execute([$sid]); $payments = $payments->fetchAll();
    $total_paid = array_sum(array_column($payments,'amount_paid'));
    $total_due  = array_sum(array_column($payments,'amount_due'));
    $paid_list  = array_filter($payments, fn($p) => $p['status']==='Paid');
    $pending_list = array_filter($payments, fn($p) => in_array($p['status'],['Pending','Overdue','Partial']));

    // Teacher feedback
    $feedback = $pdo->prepare("SELECT sf.*, CONCAT(t.first_name,' ',t.last_name) AS teacher_name,
        co.name AS course_name, ay.label AS year_label
        FROM student_feedback sf JOIN teachers t ON sf.teacher_id=t.id
        JOIN classes cl ON sf.class_id=cl.id JOIN courses co ON cl.course_id=co.id
        JOIN academic_years ay ON sf.academic_year_id=ay.id
        WHERE sf.student_id=? AND sf.is_shared_with_parent=1 ORDER BY sf.created_at DESC");
    $feedback->execute([$sid]); $feedback = $feedback->fetchAll();

    // Enrolled courses
    $enrollments = $pdo->prepare("SELECT co.name AS course_name, co.code, cl.section, cl.schedule, cl.room,
        ay.label AS year, CONCAT(t.first_name,' ',t.last_name) AS teacher_name
        FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id
        JOIN academic_years ay ON cl.academic_year_id=ay.id JOIN teachers t ON cl.teacher_id=t.id
        WHERE en.student_id=? AND en.status='Enrolled' ORDER BY ay.is_current DESC, co.name");
    $enrollments->execute([$sid]); $enrollments = $enrollments->fetchAll();
}
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-user-friends" style="color:var(--primary)"></i> Parent Portal</h1>
    <p style="color:var(--muted)">Welcome, <?= e($parent['first_name'].' '.$parent['last_name']) ?> &mdash; <?= date('l, F j, Y') ?></p>
  </div>
</div>

<!-- ── FAMILY SUMMARY CARDS ─────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px">
  <div style="background:linear-gradient(135deg,#4361ee,#7209b7);border-radius:14px;padding:18px 16px;color:#fff;display:flex;align-items:center;gap:14px">
    <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-user-graduate" style="font-size:1.1rem"></i></div>
    <div><div style="font-size:1.8rem;font-weight:800;line-height:1"><?= $agg['total_children'] ?></div><div style="font-size:.75rem;opacity:.8;margin-top:2px">Student<?= $agg['total_children']!=1?'s':'' ?> Assigned</div></div>
  </div>
  <div style="background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;border-top:3px solid #10b981">
    <div style="width:44px;height:44px;border-radius:12px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-check-circle" style="color:#10b981;font-size:1.1rem"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1">$<?= number_format($agg['total_paid'],0) ?></div><div style="font-size:.75rem;color:#64748b;margin-top:2px">Total Paid</div></div>
  </div>
  <div style="background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;border-top:3px solid <?= $agg['total_balance']>0?'#e63946':'#10b981' ?>">
    <div style="width:44px;height:44px;border-radius:12px;background:<?= $agg['total_balance']>0?'#fee2e2':'#dcfce7' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-exclamation-circle" style="color:<?= $agg['total_balance']>0?'#e63946':'#10b981' ?>;font-size:1.1rem"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1">$<?= number_format($agg['total_balance'],0) ?></div><div style="font-size:.75rem;color:#64748b;margin-top:2px">Balance Due</div></div>
  </div>
  <div style="background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;border-top:3px solid <?= $agg['pending_payments']>0?'#f59e0b':'#10b981' ?>">
    <div style="width:44px;height:44px;border-radius:12px;background:<?= $agg['pending_payments']>0?'#fef9c3':'#dcfce7' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-receipt" style="color:<?= $agg['pending_payments']>0?'#f59e0b':'#10b981' ?>;font-size:1.1rem"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1"><?= $agg['pending_payments'] ?></div><div style="font-size:.75rem;color:#64748b;margin-top:2px">Pending Payment<?= $agg['pending_payments']!=1?'s':'' ?></div></div>
  </div>
  <div style="background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;border-top:3px solid #4361ee">
    <div style="width:44px;height:44px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-book-open" style="color:#4361ee;font-size:1.1rem"></i></div>
    <div><div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1"><?= $agg['books_borrowed'] ?></div><div style="font-size:.75rem;color:#64748b;margin-top:2px">Books Borrowed<?= $agg['books_overdue']>0?' <span style="color:#e63946">('.$agg['books_overdue'].' overdue)</span>':'' ?></div></div>
  </div>
</div>

<!-- ── ALERTS ────────────────────────────────────────────────── -->
<?php if($agg['total_balance']>0): ?>
<div style="background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;border-radius:12px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px">
  <i class="fas fa-exclamation-triangle" style="font-size:1.4rem;flex-shrink:0"></i>
  <div><strong><?= $agg['pending_payments'] ?> pending payment<?= $agg['pending_payments']!=1?'s':'' ?></strong> totalling <strong>$<?= number_format($agg['total_balance'],2) ?></strong> outstanding across your children.</div>
</div>
<?php endif; ?>
<?php if($agg['books_overdue']>0): ?>
<div style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border-radius:12px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:14px">
  <i class="fas fa-book" style="font-size:1.4rem;flex-shrink:0"></i>
  <div><strong><?= $agg['books_overdue'] ?> overdue library book<?= $agg['books_overdue']!=1?'s':'' ?></strong> — please return them to avoid fines.</div>
</div>
<?php endif; ?>

<!-- ── ALL CHILDREN AT A GLANCE ──────────────────────────────── -->
<?php if(count($children) > 0): ?>
<div style="margin-bottom:24px">
  <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-bottom:12px"><i class="fas fa-users" style="color:var(--primary)"></i> Your Children</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach($children as $ch):
    $ch_att = $ch['att_total']>0 ? round($ch['att_present']/$ch['att_total']*100) : null;
    $ch_paid_pct = $ch['total_fees']>0 ? round($ch['total_paid']/$ch['total_fees']*100) : 0;
    $colors = ['#4361ee','#7209b7','#10b981','#f59e0b','#e63946','#0891b2'];
    $col = $colors[abs(crc32($ch['first_name']))%count($colors)];
  ?>
  <a href="?student_id=<?=$ch['id']?>&tab=overview" style="text-decoration:none">
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden;transition:transform .15s,box-shadow .15s;border:2px solid <?=$ch['id']==$selected_id?'var(--primary)':'transparent'?>" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.1)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 10px rgba(0,0,0,.06)'">
    <!-- Header strip -->
    <div style="background:<?=$col?>;padding:14px 16px;display:flex;align-items:center;gap:12px">
      <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.25);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:800;flex-shrink:0"><?=strtoupper(substr($ch['first_name'],0,1).substr($ch['last_name'],0,1))?></div>
      <div style="flex:1;min-width:0">
        <div style="font-weight:800;color:#fff;font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($ch['first_name'].' '.$ch['last_name'])?></div>
        <div style="font-size:.75rem;color:rgba(255,255,255,.8)"><?=e($ch['student_code'])?> &middot; <?=$ch['enrolled_courses']?> course<?=$ch['enrolled_courses']!=1?'s':''?></div>
      </div>
      <?php if($ch['pending_count']>0): ?><span style="background:#e63946;color:#fff;border-radius:20px;padding:2px 9px;font-size:.72rem;font-weight:700;flex-shrink:0"><?=$ch['pending_count']?> due</span><?php endif; ?>
    </div>
    <!-- Stats row -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border-bottom:1px solid #f1f5f9">
      <div style="padding:12px;text-align:center;border-right:1px solid #f1f5f9">
        <div style="font-size:1.1rem;font-weight:800;color:#1e293b"><?=$ch['avg_grade']?$ch['avg_grade'].'%':'—'?></div>
        <div style="font-size:.68rem;color:#94a3b8;margin-top:1px">Avg Grade</div>
      </div>
      <div style="padding:12px;text-align:center;border-right:1px solid #f1f5f9">
        <div style="font-size:1.1rem;font-weight:800;color:<?=$ch_att!==null&&$ch_att<75?'#e63946':'#10b981'?>"><?=$ch_att!==null?$ch_att.'%':'—'?></div>
        <div style="font-size:.68rem;color:#94a3b8;margin-top:1px">Attendance</div>
      </div>
      <div style="padding:12px;text-align:center">
        <div style="font-size:1.1rem;font-weight:800;color:<?=$ch['balance_due']>0?'#e63946':'#10b981'?>">$<?=number_format($ch['balance_due'],0)?></div>
        <div style="font-size:.68rem;color:#94a3b8;margin-top:1px">Balance</div>
      </div>
    </div>
    <!-- Payment progress bar -->
    <div style="padding:10px 14px">
      <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#94a3b8;margin-bottom:4px">
        <span>Fees paid</span><span style="font-weight:700;color:#1e293b">$<?=number_format($ch['total_paid'],0)?> / $<?=number_format($ch['total_fees'],0)?></span>
      </div>
      <div style="height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden">
        <div style="height:100%;width:<?=min(100,$ch_paid_pct)?>%;background:<?=$ch['balance_due']>0?'#f59e0b':'#10b981'?>;border-radius:3px;transition:width .3s"></div>
      </div>
    </div>
  </div>
  </a>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── NOTICES ───────────────────────────────────────────────── -->
<?php if($notices): ?>
<div style="margin-bottom:24px">
  <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-bottom:12px"><i class="fas fa-bullhorn" style="color:var(--warning)"></i> Recent Notices</div>
  <div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach($notices as $n): ?>
  <div style="background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 1px 6px rgba(0,0,0,.05);border-left:4px solid var(--warning);display:flex;gap:14px;align-items:flex-start">
    <i class="fas fa-bullhorn" style="color:var(--warning);margin-top:2px;flex-shrink:0"></i>
    <div style="flex:1">
      <div style="font-weight:700;font-size:.9rem;color:#1e293b"><?=e($n['title'])?></div>
      <div style="font-size:.82rem;color:#64748b;margin-top:3px;line-height:1.5"><?=e(mb_substr($n['body'],0,120).(strlen($n['body'])>120?'...':''))?></div>
      <div style="font-size:.72rem;color:#94a3b8;margin-top:4px"><i class="fas fa-calendar-alt"></i> <?=date('M j, Y',strtotime($n['post_date']))?></div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php if (count($children) > 1): ?>
<div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-bottom:10px"><i class="fas fa-user-graduate" style="color:var(--primary)"></i> View Detailed Report For</div>
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ($children as $c): ?>
  <a href="?student_id=<?=$c['id']?>&tab=<?=$tab?>" style="padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:600;font-size:.88rem;background:<?=$c['id']==$selected_id?'var(--primary)':'#f0f2f8'?>;color:<?=$c['id']==$selected_id?'#fff':'#333'?>;display:flex;align-items:center;gap:8px">
    <div class="avatar" style="width:28px;height:28px;font-size:.7rem;background:<?=$c['id']==$selected_id?'rgba(255,255,255,.3)':'var(--primary)'?>"><?=strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1))?></div>
    <?=e($c['first_name'].' '.$c['last_name'])?>
    <?php if($c['pending_count']>0):?><span style="background:#e63946;color:#fff;border-radius:10px;padding:1px 7px;font-size:.7rem"><?=$c['pending_count']?></span><?php endif;?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($student): ?>
<!-- Student info banner -->
<div style="background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;gap:20px;align-items:center;flex-wrap:wrap">
  <div class="avatar" style="width:60px;height:60px;font-size:1.3rem;background:rgba(255,255,255,.2);flex-shrink:0"><?=strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1))?></div>
  <div style="flex:1">
    <div style="font-size:1.15rem;font-weight:800"><?=e($student['first_name'].' '.$student['last_name'])?></div>
    <div style="opacity:.8;font-size:.85rem"><?=e($student['student_code'])?> &middot; <?=$student['enrolled_courses']?> courses &middot; <?=e($student['status'])?></div>
    <?php if($student['dob']):?><div style="opacity:.7;font-size:.8rem;margin-top:2px"><i class="fas fa-birthday-cake"></i> <?=date('M j, Y',strtotime($student['dob']))?></div><?php endif;?>
  </div>
  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <div style="text-align:center"><div style="font-size:1.5rem;font-weight:800"><?=$student['avg_grade']?$student['avg_grade'].'%':'—'?></div><div style="font-size:.72rem;opacity:.7">Avg Grade</div></div>
    <div style="text-align:center"><div style="font-size:1.5rem;font-weight:800;color:<?=$att_rate!==null&&$att_rate<75?'#fbbf24':'#4ade80'?>"><?=$att_rate!==null?$att_rate.'%':'—'?></div><div style="font-size:.72rem;opacity:.7">Attendance</div></div>
    <div style="text-align:center"><div style="font-size:1.5rem;font-weight:800;color:<?=$student['balance_due']>0?'#fbbf24':'#4ade80'?>">$<?=number_format($student['balance_due'],0)?></div><div style="font-size:.72rem;opacity:.7">Balance Due</div></div>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:24px;border-bottom:2px solid #eee;overflow-x:auto">
  <?php foreach(['overview'=>['fas fa-home','Overview'],'grades'=>['fas fa-star','Grades'],'attendance'=>['fas fa-calendar-check','Attendance'],'payments'=>['fas fa-credit-card','Payments'],'feedback'=>['fas fa-comment-alt','Feedback'],'courses'=>['fas fa-book-open','Courses']] as $t=>[$ico,$lbl]):?>
  <a href="?student_id=<?=$selected_id?>&tab=<?=$t?>" style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:.85rem;border-radius:8px 8px 0 0;color:<?=$tab===$t?'var(--primary)':'#888'?>;background:<?=$tab===$t?'#fff':'transparent'?>;border:2px solid <?=$tab===$t?'#eee':'transparent'?>;border-bottom:<?=$tab===$t?'2px solid #fff':'none'?>;margin-bottom:-2px;white-space:nowrap">
    <i class="<?=$ico?>"></i> <?=$lbl?>
    <?php if($t==='payments'&&$student['pending_count']>0):?><span style="background:#e63946;color:#fff;border-radius:10px;padding:1px 6px;font-size:.7rem;margin-left:4px"><?=$student['pending_count']?></span><?php endif;?>
  </a>
  <?php endforeach;?>
</div>

<!-- OVERVIEW TAB -->
<?php if($tab==='overview'):?>
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book-open"></i></div><div class="stat-info"><h3><?=$student['enrolled_courses']?></h3><p>Enrolled Courses</p></div></div>
  <div class="stat-card"><div class="stat-icon <?=$att_rate!==null&&$att_rate<75?'red':'green'?>"><i class="fas fa-calendar-check"></i></div><div class="stat-info"><h3><?=$att_rate!==null?$att_rate.'%':'—'?></h3><p>Attendance Rate</p></div></div>
  <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-star"></i></div><div class="stat-info"><h3><?=$student['avg_grade']?$student['avg_grade'].'%':'—'?></h3><p>Overall Average</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div><div class="stat-info"><h3>$<?=number_format($total_paid,0)?></h3><p>Total Paid</p></div></div>
  <div class="stat-card"><div class="stat-icon <?=$student['balance_due']>0?'red':'green'?>"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3>$<?=number_format($student['balance_due'],0)?></h3><p>Balance Due</p></div></div>
  <div class="stat-card"><div class="stat-icon <?=$student['books_overdue']>0?'red':'blue'?>"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?=$student['books_borrowed']?></h3><p>Books Borrowed<?=$student['books_overdue']>0?' <small style="color:var(--danger)">('.$student['books_overdue'].' overdue)</small>':''?></p></div></div>
</div>

<?php if($student['balance_due']>0):?>
<div style="background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px">
  <i class="fas fa-exclamation-circle" style="font-size:1.8rem;flex-shrink:0"></i>
  <div><div style="font-weight:800;font-size:1rem"><?=$student['pending_count']?> pending payment<?=$student['pending_count']>1?'s':''?> — $<?=number_format($student['balance_due'],2)?> outstanding</div>
  <div style="font-size:.85rem;opacity:.9">Please settle fees to avoid disruption to your child's studies.</div></div>
</div>
<?php endif;?>

<?php if($att_rate!==null&&$att_rate<75):?>
<div class="alert alert-error" style="margin-bottom:20px"><i class="fas fa-exclamation-triangle"></i> <strong>Attendance Warning:</strong> <?=e($student['first_name'])?>'s attendance is <?=$att_rate?>% — below the required 75%. Please contact the school.</div>
<?php endif;?>

<!-- Recent grades -->
<?php if($recent_grades):?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-star" style="color:var(--warning)"></i> Recent Exam Results</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Exam</th><th>Course</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
    <tbody>
    <?php foreach(array_slice($recent_grades,0,5) as $g): $pct=$g['total_marks']>0?round($g['marks_obtained']/$g['total_marks']*100,1):0; $pass=$pct>=get_pass_pct(); ?>
    <tr>
      <td style="font-weight:600"><?=e($g['exam_title'])?></td>
      <td><?=e($g['code'])?></td>
      <td><?=$g['exam_date']?date('M j, Y',strtotime($g['exam_date'])):'—'?></td>
      <td><?=$g['marks_obtained']?>/<?=$g['total_marks']?></td>
      <td><?=$pct?>%</td>
      <td style="font-weight:700;color:<?=$pass?'var(--success)':'var(--danger)'?>"><?=e($g['grade_letter'])?></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<!-- GRADES TAB -->
<?php elseif($tab==='grades'):?>
<div class="grid-2" style="margin-bottom:20px">
  <?php foreach($grades as $g): $pct=round($g['avg_pct'],1); $pass=$pct>=get_pass_pct(); ?>
  <div class="card">
    <div class="card-body">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div><div style="font-weight:700"><?=e($g['course_name'])?></div><div style="font-size:.78rem;color:var(--muted)"><?=e($g['code'])?> &middot; <?=e($g['year'])?> &middot; <?=$g['exams_taken']?> exam(s)</div></div>
        <div style="text-align:right"><div style="font-size:1.5rem;font-weight:800;color:<?=$pass?'var(--success)':'var(--danger)'?>"><?=grade_letter($pct)?></div><div style="font-size:.78rem;color:#888"><?=$pct?>%</div></div>
      </div>
      <div class="progress"><div class="progress-bar" style="width:<?=$pct?>%;background:<?=$pass?'var(--success)':'var(--danger)'?>"></div></div>
      <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#aaa;margin-top:4px"><span>Min: <?=round($g['min_pct'],1)?>%</span><span>Max: <?=round($g['max_pct'],1)?>%</span></div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> All Exam Results</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Exam</th><th>Course</th><th>Type</th><th>Date</th><th>Marks</th><th>%</th><th>Grade</th><th>Remarks</th></tr></thead>
    <tbody>
    <?php foreach($recent_grades as $g): $pct=$g['total_marks']>0?round($g['marks_obtained']/$g['total_marks']*100,1):0; $pass=$pct>=get_pass_pct(); ?>
    <tr>
      <td style="font-weight:600"><?=e($g['exam_title'])?></td>
      <td><?=e($g['code'])?></td>
      <td><span class="badge badge-info"><?=e($g['exam_type'])?></span></td>
      <td><?=$g['exam_date']?date('M j, Y',strtotime($g['exam_date'])):'—'?></td>
      <td><?=$g['marks_obtained']?>/<?=$g['total_marks']?></td>
      <td><?=$pct?>%</td>
      <td style="font-weight:700;color:<?=$pass?'var(--success)':'var(--danger)'?>"><?=e($g['grade_letter'])?></td>
      <td style="font-size:.82rem;color:#666"><?=e($g['remarks']??'—')?></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$recent_grades):?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">No grades yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- ATTENDANCE TAB -->
<?php elseif($tab==='attendance'):?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check"></i></div><div class="stat-info"><h3><?=$att['present']??0?></h3><p>Present</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times"></i></div><div class="stat-info"><h3><?=$att['absent']??0?></h3><p>Absent</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-clock"></i></div><div class="stat-info"><h3><?=$att['late']??0?></h3><p>Late</p></div></div>
  <div class="stat-card"><div class="stat-icon <?=$att_rate!==null&&$att_rate<75?'red':'blue'?>"><i class="fas fa-percent"></i></div><div class="stat-info"><h3><?=$att_rate!==null?$att_rate.'%':'—'?></h3><p>Rate</p></div></div>
</div>
<?php if($att_rate!==null&&$att_rate<75):?>
<div class="alert alert-error" style="margin-bottom:16px"><i class="fas fa-exclamation-triangle"></i> Attendance is below 75%. Please ensure your child attends classes regularly.</div>
<?php endif;?>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> Attendance by Course</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Course</th><th>Section</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>Rate</th></tr></thead>
    <tbody>
    <?php foreach($att_detail as $ad): $rate=$ad['total']>0?round($ad['present']/$ad['total']*100):0; ?>
    <tr>
      <td style="font-weight:600"><?=e($ad['course_name'])?></td>
      <td><?=e($ad['code'])?> §<?=e($ad['section']??'—')?></td>
      <td><?=$ad['total']?></td>
      <td style="color:var(--success);font-weight:600"><?=$ad['present']?></td>
      <td style="color:var(--danger);font-weight:600"><?=$ad['absent']?></td>
      <td style="color:var(--warning);font-weight:600"><?=$ad['late']?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="progress" style="width:70px"><div class="progress-bar" style="width:<?=$rate?>%;background:<?=$rate>=75?'var(--success)':'var(--danger)'?>"></div></div>
          <span style="font-weight:700;color:<?=$rate>=75?'var(--success)':'var(--danger)'?>"><?=$rate?>%</span>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$att_detail):?><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">No attendance records yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- PAYMENTS TAB -->
<?php elseif($tab==='payments'):?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3>$<?=number_format($total_paid,2)?></h3><p>Total Paid</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3>$<?=number_format($student['balance_due'],2)?></h3><p>Balance Due</p></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-receipt"></i></div><div class="stat-info"><h3><?=count($payments)?></h3><p>Total Records</p></div></div>
</div>

<?php if($pending_list):?>
<div class="card" style="margin-bottom:20px;border:2px solid #fecaca">
  <div class="card-header" style="background:#fff5f5"><h2 style="color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> Pending / Overdue Payments</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Fee</th><th>Year</th><th>Amount Due</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($pending_list as $p): $bal=$p['amount_due']-$p['amount_paid']; ?>
    <tr style="background:#fff8f8">
      <td style="font-weight:600"><?=e($p['fee_name'])?></td>
      <td><?=e($p['year']??'—')?></td>
      <td>$<?=number_format($p['amount_due'],2)?></td>
      <td style="color:var(--success)">$<?=number_format($p['amount_paid'],2)?></td>
      <td style="font-weight:700;color:var(--danger)">$<?=number_format($bal,2)?></td>
      <td style="color:<?=$p['due_date']&&strtotime($p['due_date'])<time()?'var(--danger)':'inherit'?>"><?=$p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—'?></td>
      <td><span class="badge badge-<?=match($p['status']){'Overdue'=>'danger','Pending'=>'warning','Partial'=>'info',default=>'secondary'}?>"><?=e($p['status'])?></span></td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-history" style="color:var(--success)"></i> Payment History</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Fee</th><th>Year</th><th>Amount Due</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Method</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($payments as $p): $bal=$p['amount_due']-$p['amount_paid']; ?>
    <tr>
      <td style="font-weight:600"><?=e($p['fee_name'])?></td>
      <td><?=e($p['year']??'—')?></td>
      <td>$<?=number_format($p['amount_due'],2)?></td>
      <td style="color:var(--success)">$<?=number_format($p['amount_paid'],2)?></td>
      <td style="font-weight:700;color:<?=$bal>0?'var(--danger)':'var(--success)'?>">$<?=number_format($bal,2)?></td>
      <td><?=$p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—'?></td>
      <td style="font-size:.82rem"><?=e($p['method']??'—')?></td>
      <td><span class="badge badge-<?=match($p['status']){'Paid'=>'success','Pending'=>'warning','Overdue'=>'danger','Partial'=>'info',default=>'secondary'}?>"><?=e($p['status'])?></span></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$payments):?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">No payment records.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- FEEDBACK TAB -->
<?php elseif($tab==='feedback'):?>
<?php if($feedback): foreach($feedback as $f): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;flex-wrap:wrap;gap:8px">
      <div>
        <div style="font-weight:700;font-size:.95rem"><?=e($f['teacher_name'])?></div>
        <div style="font-size:.8rem;color:var(--muted)"><?=e($f['course_name'])?> &middot; <?=e($f['year_label'])?> &middot; <?=e($f['semester'])?></div>
      </div>
      <span class="badge badge-<?=match($f['recommendation']){'Excellent'=>'success','Good'=>'primary','Satisfactory'=>'info','Needs Improvement'=>'warning',default=>'danger'}?>"><?=e($f['recommendation'])?></span>
    </div>
    <div style="display:flex;gap:20px;margin-bottom:12px;flex-wrap:wrap">
      <?php foreach(['behavior_rating'=>'Behavior','participation_rating'=>'Participation','effort_rating'=>'Effort'] as $k=>$lbl): ?>
      <div style="text-align:center">
        <div style="font-size:.72rem;color:var(--muted);margin-bottom:3px"><?=$lbl?></div>
        <div><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star" style="color:<?=$i<=$f[$k]?'#f59e0b':'#e0e0e0'?>;font-size:.85rem"></i><?php endfor; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if($f['comments']):?><p style="font-size:.88rem;color:#555;margin-bottom:8px;line-height:1.6"><?=nl2br(e($f['comments']))?></p><?php endif;?>
    <?php if($f['strengths']):?><p style="font-size:.83rem;margin-bottom:4px"><strong style="color:var(--success)">Strengths:</strong> <?=e($f['strengths'])?></p><?php endif;?>
    <?php if($f['areas_for_improvement']):?><p style="font-size:.83rem"><strong style="color:var(--warning)">Areas to improve:</strong> <?=e($f['areas_for_improvement'])?></p><?php endif;?>
  </div>
</div>
<?php endforeach; else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-comment-alt" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>No teacher feedback shared yet.</div></div>
<?php endif;?>

<!-- COURSES TAB -->
<?php elseif($tab==='courses'):?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
  <?php foreach($enrollments as $en):
    $colors=['#4361ee','#7209b7','#2dc653','#f4a261','#4cc9f0','#e63946'];
    $c=$colors[abs(crc32($en['code']))%count($colors)];
  ?>
  <div class="card">
    <div style="background:<?=$c?>;padding:16px 20px;color:#fff">
      <div style="font-size:.8rem;opacity:.8"><?=e($en['code'])?> &middot; <?=e($en['year'])?></div>
      <div style="font-weight:800;font-size:1rem;margin-top:2px"><?=e($en['course_name'])?></div>
      <div style="font-size:.8rem;opacity:.8;margin-top:2px">Section <?=e($en['section']??'—')?></div>
    </div>
    <div style="padding:14px 16px;font-size:.83rem">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
        <div class="avatar" style="width:28px;height:28px;font-size:.65rem"><?=strtoupper(substr($en['teacher_name'],0,2))?></div>
        <span style="font-weight:600"><?=e($en['teacher_name'])?></span>
      </div>
      <?php if($en['schedule']):?><div style="color:#666"><i class="fas fa-clock" style="color:var(--primary)"></i> <?=e($en['schedule'])?></div><?php endif;?>
      <?php if($en['room']):?><div style="color:#666"><i class="fas fa-map-marker-alt" style="color:var(--danger)"></i> <?=e($en['room'])?></div><?php endif;?>
    </div>
  </div>
  <?php endforeach;?>
  <?php if(!$enrollments):?><div class="card"><div class="card-body" style="text-align:center;color:var(--muted);padding:40px">Not enrolled in any courses.</div></div><?php endif;?>
</div>
<?php endif;?>

<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:60px;color:var(--muted)"><i class="fas fa-user-graduate" style="font-size:3rem;display:block;margin-bottom:16px;opacity:.3"></i>No children linked to your account.<br>Please contact the school administrator.</div></div>
<?php endif;?>
<?php require_once '../../includes/footer.php'; ?>
