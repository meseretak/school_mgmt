<?php
require_once '../../includes/config.php';
auth_check(['student']);
$page_title = 'My Dashboard'; $active_page = 'student_dashboard';

$student = get_student_record($pdo);
if (!$student) {
    // Don't redirect back to dashboard â€” that causes a loop
    require_once '../../includes/header.php';
    echo '<div class="card"><div class="card-body" style="text-align:center;padding:60px">
        <i class="fas fa-exclamation-triangle" style="font-size:3rem;color:var(--warning);display:block;margin-bottom:16px"></i>
        <h2 style="margin-bottom:10px">Student Profile Not Found</h2>
        <p style="color:#888;margin-bottom:20px">Your account exists but no student profile is linked to it.<br>Please contact your administrator.</p>
        <a href="'.BASE_URL.'/logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div></div>';
    require_once '../../includes/footer.php';
    exit;
}
$sid = $student['id'];

// My enrollments with teacher info
$enrollments = $pdo->prepare("SELECT en.*, co.name AS course_name, co.code, co.credits, cl.section, cl.schedule, cl.room, ay.label AS year, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, t.specialization, t.phone AS teacher_phone FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id JOIN teachers t ON cl.teacher_id=t.id WHERE en.student_id=? AND en.status='Enrolled' ORDER BY ay.is_current DESC, co.name");
$enrollments->execute([$sid]); $enrollments = $enrollments->fetchAll();

// My grades summary
$grades_summary = $pdo->prepare("SELECT co.name AS course_name, co.code, AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(g.id) AS exams_taken, MIN(g.marks_obtained/ex.total_marks*100) AS min_pct, MAX(g.marks_obtained/ex.total_marks*100) AS max_pct FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? GROUP BY co.id ORDER BY co.name");
$grades_summary->execute([$sid]); $grades_summary = $grades_summary->fetchAll();

// Recent grades
$recent_grades = $pdo->prepare("SELECT g.*, ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, ex.exam_date, co.name AS course_name, co.code FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? ORDER BY g.graded_at DESC LIMIT 10");
$recent_grades->execute([$sid]); $recent_grades = $recent_grades->fetchAll();

// Upcoming exams
$upcoming_exams = $pdo->prepare("SELECT e.*, co.name AS course_name, co.code, cl.section, cl.room FROM exams e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN enrollments en ON en.class_id=cl.id WHERE en.student_id=? AND e.exam_date >= CURDATE() ORDER BY e.exam_date LIMIT 8");
$upcoming_exams->execute([$sid]); $upcoming_exams = $upcoming_exams->fetchAll();

// Payments â€” only student's own
$payments = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id WHERE p.student_id=? ORDER BY p.status='Overdue' DESC, p.status='Pending' DESC, p.created_at DESC");
$payments->execute([$sid]); $payments = $payments->fetchAll();
$total_due    = array_sum(array_column($payments,'amount_due'));
$total_paid   = array_sum(array_column($payments,'amount_paid'));
$pending_fees = array_filter($payments, fn($p) => in_array($p['status'],['Pending','Overdue','Partial']));
$pending_count = count($pending_fees);
$pending_amount = array_sum(array_map(fn($p) => $p['amount_due']-$p['amount_paid'], $pending_fees));

// Assignments
$assignments = $pdo->prepare("SELECT a.id, a.title, a.description, a.due_date, a.due_time, a.total_marks, a.pass_marks, a.status AS assignment_status, co.name AS course_name, co.code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, sub.status AS sub_status, sub.marks_obtained, sub.grade_letter FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON a.teacher_id=t.id JOIN enrollments en ON en.class_id=cl.id AND en.student_id=? AND en.status='Enrolled' LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id AND sub.student_id=? WHERE a.status='Published' ORDER BY a.due_date ASC LIMIT 10");
$assignments->execute([$sid,$sid]); $assignments = $assignments->fetchAll();

// Attendance summary
$att_summary = $pdo->prepare("SELECT COUNT(*) AS total, SUM(a.status='Present') AS present, SUM(a.status='Absent') AS absent, SUM(a.status='Late') AS late, SUM(a.status='Excused') AS excused FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id WHERE en.student_id=?");
$att_summary->execute([$sid]); $att_summary = $att_summary->fetch();
$att_rate = $att_summary['total'] > 0 ? round($att_summary['present']/$att_summary['total']*100) : null;

// Attendance detail per course
$att_detail = $pdo->prepare("SELECT co.name AS course_name, co.code, cl.section, COUNT(a.id) AS total, SUM(a.status='Present') AS present, SUM(a.status='Absent') AS absent, SUM(a.status='Late') AS late FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? GROUP BY cl.id ORDER BY co.name");
$att_detail->execute([$sid]); $att_detail = $att_detail->fetchAll();

// Year-end results for student
$my_results = $pdo->prepare("SELECT yr.*, ay.label AS year_label FROM year_results yr JOIN academic_years ay ON yr.academic_year_id=ay.id WHERE yr.student_id=? ORDER BY ay.start_date DESC");
$my_results->execute([$sid]); $my_results = $my_results->fetchAll();

// Notices for students
$notices = $pdo->query("SELECT * FROM notices WHERE is_active=1 AND (audience='all' OR audience='students') AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY post_date DESC LIMIT 5")->fetchAll();

// Overall grade
$overall = $pdo->prepare("SELECT AVG(g.marks_obtained/ex.total_marks*100) AS avg FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id WHERE en.student_id=?");
$overall->execute([$sid]); $overall_pct = round((float)$overall->fetchColumn(),1);

require_once '../../includes/header.php';

?>
<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b">My Dashboard</h1>
    <p style="color:#64748b;font-size:.88rem;margin-top:2px">Welcome, <strong><?= e($student['first_name'].' '.$student['last_name']) ?></strong> · <?= e($student['student_code']) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/modules/students/transcript.php?id=<?= $sid ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-file-alt"></i> Transcript</a>
    <div style="font-size:.82rem;color:#94a3b8;background:#f8fafc;padding:8px 14px;border-radius:10px;border:1px solid #e2e8f0"><i class="fas fa-calendar-alt" style="color:var(--primary)"></i> <?= date('l, F j, Y') ?></div>
  </div>
</div>

<!-- Payment alert -->
<?php if ($pending_count > 0): ?>
<div style="background:linear-gradient(135deg,#e63946,#c1121f);color:#fff;border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <i class="fas fa-exclamation-circle" style="font-size:1.2rem;flex-shrink:0"></i>
  <span><strong><?= $pending_count ?> pending payment<?= $pending_count>1?'s':'' ?></strong> totalling $<?= number_format($pending_amount,2) ?> outstanding.</span>
  <a href="#payments" style="margin-left:auto;background:rgba(255,255,255,.2);color:#fff;padding:5px 14px;border-radius:8px;text-decoration:none;font-size:.82rem;font-weight:600;white-space:nowrap">Pay Now →</a>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px">
<?php foreach([
  [count($enrollments),'fas fa-book-open','Enrolled Courses','#4361ee'],
  [$overall_pct>0?$overall_pct.'%':'—','fas fa-star','Overall Grade','#f59e0b'],
  [$att_rate!==null?$att_rate.'%':'—','fas fa-calendar-check',$att_rate!==null&&$att_rate<75?'⚠ Attendance':'Attendance',$att_rate!==null&&$att_rate<75?'#e63946':'#10b981'],
  ['$'.number_format($total_paid,0),'fas fa-dollar-sign','Total Paid','#10b981'],
  ['$'.number_format($total_due-$total_paid,0),'fas fa-exclamation-circle','Balance Due',$total_due-$total_paid>0?'#e63946':'#10b981'],
  [count($upcoming_exams),'fas fa-file-alt','Upcoming Exams','#6366f1'],
] as [$val,$icon,$lbl,$color]): ?>
<div style="background:#fff;border-radius:14px;padding:18px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);border-top:3px solid <?=$color?>">
  <div style="width:38px;height:38px;border-radius:10px;background:<?=$color?>18;display:flex;align-items:center;justify-content:center;margin-bottom:10px">
    <i class="<?=$icon?>" style="color:<?=$color?>;font-size:.95rem"></i>
  </div>
  <div style="font-size:1.5rem;font-weight:800;color:#1e293b;line-height:1"><?=$val?></div>
  <div style="font-size:.75rem;color:#64748b;margin-top:4px;font-weight:500"><?=$lbl?></div>
</div>
<?php endforeach; ?>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-bottom:24px">
  <!-- Grade per course -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-chart-bar" style="color:#4361ee;margin-right:6px"></i>Grades by Course</div>
    <div style="height:180px;position:relative">
      <?php if($grades_summary): ?><canvas id="gradeChart" style="width:100%;height:180px"></canvas>
      <?php else: ?><div style="display:flex;align-items:center;justify-content:center;height:100%;color:#cbd5e1;font-size:.85rem">No grades yet</div><?php endif; ?>
    </div>
  </div>
  <!-- Attendance pie -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-chart-pie" style="color:#10b981;margin-right:6px"></i>Attendance</div>
    <div style="height:180px;position:relative;display:flex;align-items:center;justify-content:center">
      <?php if($att_summary['total']>0): ?><canvas id="attChart" style="max-width:180px;max-height:180px"></canvas>
      <?php else: ?><div style="text-align:center;color:#cbd5e1"><i class="fas fa-calendar" style="font-size:2rem"></i><p style="font-size:.78rem;margin-top:6px">No records</p></div><?php endif; ?>
    </div>
  </div>
  <!-- Payment status -->
  <div style="background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b;margin-bottom:14px"><i class="fas fa-chart-pie" style="color:#f59e0b;margin-right:6px"></i>Payment Status</div>
    <div style="height:180px;position:relative;display:flex;align-items:center;justify-content:center">
      <?php if($payments): ?><canvas id="payChart" style="max-width:180px;max-height:180px"></canvas>
      <?php else: ?><div style="text-align:center;color:#cbd5e1"><i class="fas fa-credit-card" style="font-size:2rem"></i><p style="font-size:.78rem;margin-top:6px">No payments</p></div><?php endif; ?>
    </div>
  </div>
</div>

<!-- My Courses -->
<div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;margin-bottom:12px"><i class="fas fa-book-open" style="color:var(--primary)"></i> My Courses</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:24px">
  <?php $colors=['#4361ee','#7209b7','#10b981','#f59e0b','#06b6d4','#ef4444'];
  foreach($enrollments as $i=>$en): $c=$colors[$i%count($colors)]; ?>
  <div style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06)">
    <div style="background:<?=$c?>;padding:14px 18px;color:#fff">
      <div style="font-size:.75rem;opacity:.8"><?=e($en['code'])?> · <?=e($en['year'])?></div>
      <div style="font-weight:800;font-size:.95rem;margin-top:2px"><?=e($en['course_name'])?></div>
      <div style="font-size:.75rem;opacity:.8;margin-top:2px">Section <?=e($en['section']??'—')?></div>
    </div>
    <div style="padding:12px 18px;font-size:.82rem;color:#64748b">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
        <div style="width:28px;height:28px;border-radius:7px;background:<?=$c?>22;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:<?=$c?>"><?=strtoupper(substr($en['teacher_name'],0,2))?></div>
        <span style="font-weight:600;color:#1e293b"><?=e($en['teacher_name'])?></span>
      </div>
      <?php if($en['schedule']):?><div><i class="fas fa-clock" style="color:<?=$c?>"></i> <?=e($en['schedule'])?></div><?php endif;?>
      <?php if($en['room']):?><div><i class="fas fa-map-marker-alt" style="color:#e63946"></i> <?=e($en['room'])?></div><?php endif;?>
    </div>
  </div>
  <?php endforeach;?>
  <?php if(!$enrollments):?><div style="background:#fff;border-radius:14px;padding:40px;text-align:center;color:#94a3b8;grid-column:1/-1">Not enrolled in any courses yet.</div><?php endif;?>
</div>

<!-- Bottom tables -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:24px">
  <!-- Recent Grades -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.9rem;color:#1e293b"><i class="fas fa-star" style="color:#f59e0b;margin-right:6px"></i>Recent Exam Results</div>
    <table style="width:100%;border-collapse:collapse;font-size:.83rem">
      <thead><tr style="background:#f8fafc"><th style="padding:8px 14px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Exam</th><th style="padding:8px 14px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Course</th><th style="padding:8px 14px;text-align:center;font-size:.72rem;color:#64748b;font-weight:600">Score</th><th style="padding:8px 14px;text-align:center;font-size:.72rem;color:#64748b;font-weight:600">Grade</th></tr></thead>
      <tbody>
      <?php foreach(array_slice($recent_grades,0,6) as $g): $pct=$g['total_marks']>0?round($g['marks_obtained']/$g['total_marks']*100,1):0; $pass=$pct>=get_pass_pct(); ?>
      <tr style="border-top:1px solid #f8fafc">
        <td style="padding:8px 14px;font-weight:600;color:#1e293b"><?=e(mb_substr($g['exam_title'],0,20))?></td>
        <td style="padding:8px 14px;color:#64748b;font-size:.78rem"><?=e($g['code'])?></td>
        <td style="padding:8px 14px;text-align:center"><?=$g['marks_obtained']?>/<?=$g['total_marks']?></td>
        <td style="padding:8px 14px;text-align:center;font-weight:800;color:<?=$pass?'#10b981':'#e63946'?>"><?=e($g['grade_letter'])?></td>
      </tr>
      <?php endforeach;?>
      <?php if(!$recent_grades):?><tr><td colspan="4" style="padding:24px;text-align:center;color:#94a3b8">No grades yet.</td></tr><?php endif;?>
      </tbody>
    </table>
  </div>

  <!-- Upcoming Exams -->
  <div style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.9rem;color:#1e293b"><i class="fas fa-file-alt" style="color:#6366f1;margin-right:6px"></i>Upcoming Exams</div>
    <table style="width:100%;border-collapse:collapse;font-size:.83rem">
      <thead><tr style="background:#f8fafc"><th style="padding:8px 14px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Exam</th><th style="padding:8px 14px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Course</th><th style="padding:8px 14px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Date</th></tr></thead>
      <tbody>
      <?php foreach($upcoming_exams as $ex): $days=(int)((strtotime($ex['exam_date'])-time())/86400); ?>
      <tr style="border-top:1px solid #f8fafc">
        <td style="padding:8px 14px;font-weight:600;color:#1e293b"><?=e(mb_substr($ex['title'],0,22))?><?php if($days<=3):?> <span style="background:#fef2f2;color:#e63946;border-radius:4px;padding:1px 5px;font-size:.68rem">Soon!</span><?php endif;?></td>
        <td style="padding:8px 14px;color:#64748b;font-size:.78rem"><?=e($ex['code'])?></td>
        <td style="padding:8px 14px;color:<?=$days<=3?'#e63946':'#64748b'?>;font-weight:<?=$days<=3?'700':'400'?>"><?=date('M j',strtotime($ex['exam_date']))?></td>
      </tr>
      <?php endforeach;?>
      <?php if(!$upcoming_exams):?><tr><td colspan="3" style="padding:24px;text-align:center;color:#94a3b8">No upcoming exams.</td></tr><?php endif;?>
      </tbody>
    </table>
  </div>
</div>

<!-- Payments section -->
<div id="payments" style="background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:hidden;margin-bottom:24px">
  <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:700;font-size:.9rem;color:#1e293b"><i class="fas fa-credit-card" style="color:#10b981;margin-right:6px"></i>My Payments</div>
    <div style="font-size:.82rem;color:#64748b">Paid: <strong style="color:#10b981">$<?=number_format($total_paid,2)?></strong> · Due: <strong style="color:#e63946">$<?=number_format($total_due-$total_paid,2)?></strong></div>
  </div>
  <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;font-size:.83rem">
    <thead><tr style="background:#f8fafc"><th style="padding:10px 16px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Fee</th><th style="padding:10px 16px;text-align:left;font-size:.72rem;color:#64748b;font-weight:600">Year</th><th style="padding:10px 16px;text-align:right;font-size:.72rem;color:#64748b;font-weight:600">Due</th><th style="padding:10px 16px;text-align:right;font-size:.72rem;color:#64748b;font-weight:600">Paid</th><th style="padding:10px 16px;text-align:right;font-size:.72rem;color:#64748b;font-weight:600">Balance</th><th style="padding:10px 16px;text-align:center;font-size:.72rem;color:#64748b;font-weight:600">Status</th></tr></thead>
    <tbody>
    <?php foreach($payments as $p): $bal=$p['amount_due']-$p['amount_paid']; ?>
    <tr style="border-top:1px solid #f8fafc">
      <td style="padding:10px 16px;font-weight:600;color:#1e293b"><?=e($p['fee_name'])?></td>
      <td style="padding:10px 16px;color:#64748b"><?=e($p['year']??'—')?></td>
      <td style="padding:10px 16px;text-align:right">$<?=number_format($p['amount_due'],2)?></td>
      <td style="padding:10px 16px;text-align:right;color:#10b981">$<?=number_format($p['amount_paid'],2)?></td>
      <td style="padding:10px 16px;text-align:right;font-weight:700;color:<?=$bal>0?'#e63946':'#10b981'?>">$<?=number_format($bal,2)?></td>
      <td style="padding:10px 16px;text-align:center">
        <?php $sc=match($p['status']){'Paid'=>['#dcfce7','#16a34a'],'Pending'=>['#fef9c3','#ca8a04'],'Overdue'=>['#fee2e2','#dc2626'],'Partial'=>['#dbeafe','#2563eb'],default=>['#f1f5f9','#64748b']}; ?>
        <span style="padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;background:<?=$sc[0]?>;color:<?=$sc[1]?>"><?=e($p['status'])?></span>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$payments):?><tr><td colspan="6" style="padding:24px;text-align:center;color:#94a3b8">No payment records.</td></tr><?php endif;?>
    </tbody>
  </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if($grades_summary): ?>
new Chart(document.getElementById('gradeChart'),{type:'bar',data:{
  labels:<?=json_encode(array_map(fn($g)=>$g['code'],$grades_summary))?>,
  datasets:[{label:'Avg %',data:<?=json_encode(array_map(fn($g)=>round($g['avg_pct'],1),$grades_summary))?>,
    backgroundColor:<?=json_encode(array_map(fn($g)=>$g['avg_pct']>=get_pass_pct()?'rgba(16,185,129,.8)':'rgba(239,68,68,.8)',$grades_summary))?>,
    borderRadius:6}]},
  options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%',font:{size:10}},grid:{color:'#f1f5f9'}},x:{ticks:{font:{size:9}},grid:{display:false}}}}});
<?php endif; ?>
<?php if($att_summary['total']>0): ?>
new Chart(document.getElementById('attChart'),{type:'doughnut',data:{
  labels:['Present','Absent','Late'],
  datasets:[{data:[<?=$att_summary['present']??0?>,<?=$att_summary['absent']??0?>,<?=$att_summary['late']??0?>],
    backgroundColor:['#10b981','#ef4444','#f59e0b'],borderWidth:0}]},
  options:{maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}}}});
<?php endif; ?>
<?php if($payments):
  $pay_labels=[]; $pay_data=[]; $pay_colors=[];
  $pay_groups=['Paid'=>[0,'#10b981'],'Pending'=>[0,'#f59e0b'],'Overdue'=>[0,'#ef4444'],'Partial'=>[0,'#3b82f6']];
  foreach($payments as $p){ if(isset($pay_groups[$p['status']])) $pay_groups[$p['status']][0]++; }
  foreach($pay_groups as $lbl=>[$cnt,$col]){ if($cnt>0){$pay_labels[]=$lbl;$pay_data[]=$cnt;$pay_colors[]=$col;} }
?>
new Chart(document.getElementById('payChart'),{type:'doughnut',data:{
  labels:<?=json_encode($pay_labels)?>,
  datasets:[{data:<?=json_encode($pay_data)?>,backgroundColor:<?=json_encode($pay_colors)?>,borderWidth:0}]},
  options:{maintainAspectRatio:false,cutout:'65%',plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:10}}}}});
<?php endif; ?>
</script>
<?php require_once '../../includes/footer.php'; ?>
