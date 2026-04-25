<?php
require_once '../../includes/config.php';
auth_check(['admin','teacher','accountant']);
$page_title = 'Student Lookup'; $active_page = 'students';

$student_id = trim($_GET['student_id'] ?? '');
$student = null; $payments = []; $grades = []; $attendance = []; $enrollments = [];

if ($student_id) {
    $s = $pdo->prepare("SELECT s.*, u.email, c.name AS country FROM students s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN countries c ON s.country_id=c.id WHERE s.student_code=?");
    $s->execute([$student_id]); $student = $s->fetch();

    if ($student) {
        $sid = $student['id'];

        // All payments
        $p = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id WHERE p.student_id=? ORDER BY p.created_at DESC");
        $p->execute([$sid]); $payments = $p->fetchAll();

        // All grades
        $g = $pdo->prepare("SELECT g.*, ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, ex.exam_date, co.name AS course_name, co.code AS course_code, cl.section, ay.label AS year FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id WHERE en.student_id=? ORDER BY ay.label, co.name, ex.exam_date");
        $g->execute([$sid]); $grades = $g->fetchAll();

        // Attendance summary
        $a = $pdo->prepare("SELECT co.name AS course_name, co.code, cl.section, ay.label AS year, COUNT(att.id) AS total, SUM(att.status='Present') AS present, SUM(att.status='Absent') AS absent, SUM(att.status='Late') AS late FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id LEFT JOIN attendance att ON att.enrollment_id=en.id WHERE en.student_id=? GROUP BY en.id ORDER BY ay.label, co.name");
        $a->execute([$sid]); $attendance = $a->fetchAll();

        // Enrollments
        $e = $pdo->prepare("SELECT en.*, co.name AS course_name, co.code, cl.section, ay.label AS year, CONCAT(t.first_name,' ',t.last_name) AS teacher FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id JOIN teachers t ON cl.teacher_id=t.id WHERE en.student_id=? ORDER BY ay.label DESC");
        $e->execute([$sid]); $enrollments = $e->fetchAll();
    }
}

$pay_total_due  = array_sum(array_column($payments, 'amount_due'));
$pay_total_paid = array_sum(array_column($payments, 'amount_paid'));
$overall_pct    = count($grades) ? round(array_sum(array_map(fn($g) => $g['total_marks']>0?$g['marks_obtained']/$g['total_marks']*100:0, $grades))/count($grades),1) : null;

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-id-card" style="color:var(--primary)"></i> Student Lookup</h1><p>Search by Student ID to see full profile</p></div>
</div>

<!-- Search bar -->
<div class="card" style="margin-bottom:24px">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div class="form-group" style="flex:1;min-width:220px;margin:0">
        <label>Student ID</label>
        <input name="student_id" value="<?= e($student_id) ?>" placeholder="e.g. EMP-STU-2025-0001" style="font-family:monospace;font-size:1rem" autofocus>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Lookup</button>
      <?php if ($student_id): ?><a href="lookup.php" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<?php if ($student_id && !$student): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> No student found with ID <strong><?= e($student_id) ?></strong></div>

<?php elseif ($student): ?>

<!-- Student profile header -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="display:flex;gap:24px;align-items:center;flex-wrap:wrap">
    <div class="avatar" style="width:72px;height:72px;font-size:1.6rem;flex-shrink:0"><?= strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)) ?></div>
    <div style="flex:1">
      <div style="font-size:1.4rem;font-weight:800;color:var(--dark)"><?= e($student['first_name'].' '.$student['last_name']) ?></div>
      <div style="font-family:monospace;font-size:1rem;color:var(--primary);font-weight:700;margin:4px 0"><?= e($student['student_code']) ?></div>
      <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:.85rem;color:#666;margin-top:4px">
        <span><i class="fas fa-envelope" style="color:var(--primary)"></i> <?= e($student['email']) ?></span>
        <span><i class="fas fa-globe" style="color:var(--teal)"></i> <?= e($student['nationality']??'—') ?></span>
        <span><i class="fas fa-flag" style="color:var(--warning)"></i> <?= e($student['country']??'—') ?></span>
        <span><i class="fas fa-phone" style="color:var(--success)"></i> <?= e($student['phone']??'—') ?></span>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <span class="badge badge-<?= $student['status']==='Active'?'success':'danger' ?>" style="font-size:.9rem;padding:6px 14px"><?= e($student['status']) ?></span>
      <a href="view.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Full Profile</a>
      <a href="transcript.php?id=<?= $student['id'] ?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-file-alt"></i> Transcript</a>
    </div>
  </div>
</div>

<!-- Quick stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-book-open"></i></div><div class="stat-info"><h3><?= count($enrollments) ?></h3><p>Enrolled Courses</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-dollar-sign"></i></div><div class="stat-info"><h3>$<?= number_format($pay_total_paid,0) ?></h3><p>Total Paid</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation"></i></div><div class="stat-info"><h3>$<?= number_format($pay_total_due-$pay_total_paid,0) ?></h3><p>Outstanding</p></div></div>
  <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-star"></i></div><div class="stat-info"><h3><?= $overall_pct !== null ? $overall_pct.'%' : '—' ?></h3><p>Overall Grade</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-calendar-check"></i></div>
    <div class="stat-info">
      <?php $att_total = array_sum(array_column($attendance,'total')); $att_present = array_sum(array_column($attendance,'present')); ?>
      <h3><?= $att_total > 0 ? round($att_present/$att_total*100).'%' : '—' ?></h3><p>Attendance Rate</p>
    </div>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #eee">
  <?php foreach(['payments'=>['fas fa-credit-card','Payments'],'grades'=>['fas fa-star','Grades'],'attendance'=>['fas fa-calendar-check','Attendance'],'courses'=>['fas fa-book-open','Courses']] as $t=>[$ico,$lbl]): ?>
  <a href="#tab-<?= $t ?>" onclick="showTab('<?= $t ?>')" id="tabnav-<?= $t ?>" style="padding:10px 18px;text-decoration:none;font-weight:600;font-size:.88rem;border-radius:8px 8px 0 0;cursor:pointer;color:#888;border:2px solid transparent;border-bottom:none;margin-bottom:-2px">
    <i class="<?= $ico ?>"></i> <?= $lbl ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Payments tab -->
<div id="tab-payments" class="tab-content">
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-credit-card" style="color:var(--success)"></i> Payment History</h2>
      <?php if (is_admin()): ?>
      <a href="../payments/add.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Payment</a>
      <?php endif; ?>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>Fee Type</th><th>Year</th><th>Due</th><th>Paid</th><th>Balance</th><th>Due Date</th><th>Method</th><th>Ref</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): $bal = $p['amount_due']-$p['amount_paid']; ?>
      <tr>
        <td style="font-weight:600"><?= e($p['fee_name']) ?></td>
        <td><?= e($p['year']??'—') ?></td>
        <td>$<?= number_format($p['amount_due'],2) ?></td>
        <td style="color:var(--success)">$<?= number_format($p['amount_paid'],2) ?></td>
        <td style="color:<?= $bal>0?'var(--danger)':'var(--success)' ?>;font-weight:700">$<?= number_format($bal,2) ?></td>
        <td><?= $p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—' ?></td>
        <td><?= e($p['method']) ?></td>
        <td style="font-size:.8rem;font-family:monospace"><?= e($p['reference_no']??'—') ?></td>
        <td><span class="badge badge-<?php $ps_=($p['status']??''); echo $ps_==='Paid'?'success':($ps_==='Pending'?'warning':($ps_==='Overdue'?'danger':($ps_==='Partial'?'info':'secondary'))); ?>"><?= e($p['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px">No payment records</td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <?php if ($payments): ?>
    <div style="padding:14px 20px;background:#f8f9ff;display:flex;gap:24px;font-size:.88rem;border-top:1px solid #eee">
      <span>Total Due: <strong>$<?= number_format($pay_total_due,2) ?></strong></span>
      <span style="color:var(--success)">Total Paid: <strong>$<?= number_format($pay_total_paid,2) ?></strong></span>
      <span style="color:var(--danger)">Outstanding: <strong>$<?= number_format($pay_total_due-$pay_total_paid,2) ?></strong></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Grades tab -->
<div id="tab-grades" class="tab-content" style="display:none">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-star" style="color:var(--gold)"></i> Academic Grades</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Year</th><th>Course</th><th>Exam</th><th>Type</th><th>Date</th><th>Marks</th><th>Total</th><th>%</th><th>Grade</th></tr></thead>
      <tbody>
      <?php foreach ($grades as $g):
        $pct = $g['total_marks']>0 ? round($g['marks_obtained']/$g['total_marks']*100,1) : 0;
      ?>
      <tr>
        <td><?= e($g['year']) ?></td>
        <td><?= e($g['course_code'].' — '.$g['course_name']) ?></td>
        <td style="font-weight:600"><?= e($g['exam_title']) ?></td>
        <td><span class="badge badge-info"><?= e($g['exam_type']) ?></span></td>
        <td><?= $g['exam_date']?date('M j, Y',strtotime($g['exam_date'])):'—' ?></td>
        <td><?= $g['marks_obtained'] ?></td>
        <td><?= $g['total_marks'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <?= $pct ?>%
            <div class="progress" style="width:50px"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=get_pass_pct()?'var(--success)':'var(--danger)' ?>"></div></div>
          </div>
        </td>
        <td style="font-weight:700;color:<?= $g['grade_letter']==='F'?'var(--danger)':'var(--success)' ?>"><?= e($g['grade_letter']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$grades): ?><tr><td colspan="9" style="text-align:center;color:#aaa;padding:20px">No grades recorded</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- Attendance tab -->
<div id="tab-attendance" class="tab-content" style="display:none">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-calendar-check" style="color:var(--success)"></i> Attendance Summary</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Year</th><th>Course</th><th>Section</th><th>Total Sessions</th><th>Present</th><th>Absent</th><th>Late</th><th>Rate</th></tr></thead>
      <tbody>
      <?php foreach ($attendance as $a):
        $rate = $a['total']>0 ? round($a['present']/$a['total']*100) : 0;
      ?>
      <tr>
        <td><?= e($a['year']) ?></td>
        <td><?= e($a['code'].' — '.$a['course_name']) ?></td>
        <td><?= e($a['section']) ?></td>
        <td><?= $a['total'] ?></td>
        <td style="color:var(--success);font-weight:600"><?= $a['present'] ?></td>
        <td style="color:var(--danger);font-weight:600"><?= $a['absent'] ?></td>
        <td style="color:var(--warning);font-weight:600"><?= $a['late'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?= $rate ?>%;background:<?= $rate>=75?'var(--success)':($rate>=50?'var(--warning)':'var(--danger)') ?>"></div></div>
            <span style="color:<?= $rate<75?'var(--danger)':'inherit' ?>;font-weight:<?= $rate<75?'700':'400' ?>"><?= $rate ?>%<?= $rate<75?' ⚠️':'' ?></span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$attendance): ?><tr><td colspan="8" style="text-align:center;color:#aaa;padding:20px">No attendance records</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<!-- Courses tab -->
<div id="tab-courses" class="tab-content" style="display:none">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-book-open" style="color:var(--teal)"></i> Enrolled Courses</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Year</th><th>Course</th><th>Section</th><th>Teacher</th><th>Enrolled</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($enrollments as $en): ?>
      <tr>
        <td><?= e($en['year']) ?></td>
        <td style="font-weight:600"><?= e($en['code'].' — '.$en['course_name']) ?></td>
        <td><?= e($en['section']) ?></td>
        <td><?= e($en['teacher']) ?></td>
        <td style="font-size:.82rem"><?= date('M j, Y', strtotime($en['enrolled_at'])) ?></td>
        <td><span class="badge badge-<?= $en['status']==='Enrolled'?'success':($en['status']==='Dropped'?'danger':'secondary') ?>"><?= e($en['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$enrollments): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No enrollments</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<?php endif; ?>

<script>
function showTab(name) {
  document.querySelectorAll('.tab-content').forEach(t => t.style.display='none');
  document.querySelectorAll('[id^="tabnav-"]').forEach(t => {
    t.style.color='#888'; t.style.background='transparent';
    t.style.borderColor='transparent'; t.style.borderBottomColor='transparent';
  });
  document.getElementById('tab-'+name).style.display='block';
  const nav = document.getElementById('tabnav-'+name);
  nav.style.color='var(--primary)'; nav.style.background='#fff';
  nav.style.borderColor='#eee'; nav.style.borderBottomColor='#fff';
}
showTab('payments');
</script>
<?php require_once '../../includes/footer.php'; ?>