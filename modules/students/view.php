<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher']);
$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT s.*, c.name AS country_name FROM students s LEFT JOIN countries c ON s.country_id=c.id WHERE s.id=?");
$s->execute([$id]); $student = $s->fetch();
if (!$student) { flash('Student not found.','error'); header('Location: index.php'); exit; }

$enrollments = $pdo->prepare("SELECT e.*, co.name AS course_name, co.code AS course_code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher FROM enrollments e JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id WHERE e.student_id=?");
$enrollments->execute([$id]); $enrollments = $enrollments->fetchAll();

$payments = $pdo->prepare("SELECT p.*, ft.name AS fee_name FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id WHERE p.student_id=? ORDER BY p.created_at DESC");
$payments->execute([$id]); $payments = $payments->fetchAll();

$grades = $pdo->prepare("SELECT g.*, ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, co.name AS course_name FROM grades g JOIN exams ex ON g.exam_id=ex.id JOIN enrollments en ON g.enrollment_id=en.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? ORDER BY g.graded_at DESC");
$grades->execute([$id]); $grades = $grades->fetchAll();

$page_title = $student['first_name'].' '.$student['last_name'];
$active_page = 'students';
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><?= e($student['first_name'].' '.$student['last_name']) ?></h1><p>Student Profile</p></div>
  <div style="display:flex;gap:10px;">
    <a href="transcript.php?id=<?= $id ?>" target="_blank" class="btn btn-secondary"><i class="fas fa-file-alt"></i> Transcript</a>
    <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="grid-2" style="margin-bottom:24px;">
  <div class="card">
    <div class="card-header"><h2>Personal Information</h2>
      <span class="badge badge-<?= $student['status']==='Active'?'success':'danger' ?>"><?= e($student['status']) ?></span>
    </div>
    <div class="card-body">
      <table style="width:100%">
        <tr><td style="color:#888;width:40%;padding:8px 0">Student Code</td><td><strong><?= e($student['student_code']??'—') ?></strong></td></tr>
        <tr><td style="color:#888;padding:8px 0">Date of Birth</td><td><?= $student['dob']?date('M j, Y',strtotime($student['dob'])):'—' ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Gender</td><td><?= e($student['gender']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Phone</td><td><?= e($student['phone']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Address</td><td><?= e($student['address']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Enrolled</td><td><?= $student['enrollment_date']?date('M j, Y',strtotime($student['enrollment_date'])):'—' ?></td></tr>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-globe" style="color:var(--primary)"></i> International Info</h2></div>
    <div class="card-body">
      <table style="width:100%">
        <tr><td style="color:#888;width:40%;padding:8px 0">Nationality</td><td><?= e($student['nationality']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Country</td><td><?= e($student['country_name']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Passport No.</td><td><?= e($student['passport_no']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Visa Type</td><td><?= e($student['visa_type']??'—') ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Visa Expiry</td><td><?= $student['visa_expiry']?date('M j, Y',strtotime($student['visa_expiry'])):'—' ?></td></tr>
        <tr><td style="color:#888;padding:8px 0">Emergency Contact</td><td><?= e($student['emergency_contact']??'—') ?> <?= $student['emergency_phone']?'('.$student['emergency_phone'].')':'' ?></td></tr>
      </table>
    </div>
  </div>
</div>

<!-- Enrollments -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><h2>Enrolled Classes</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Course</th><th>Section</th><th>Teacher</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($enrollments as $en): ?>
      <tr>
        <td><strong><?= e($en['course_code']) ?></strong> — <?= e($en['course_name']) ?></td>
        <td><?= e($en['section']??'—') ?></td>
        <td><?= e($en['teacher']) ?></td>
        <td><span class="badge badge-<?= $en['status']==='Enrolled'?'success':'secondary' ?>"><?= e($en['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$enrollments): ?><tr><td colspan="4" style="text-align:center;color:#aaa;padding:20px">Not enrolled in any class</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Grades -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header"><h2>Grades</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Course</th><th>Exam</th><th>Type</th><th>Marks</th><th>Total</th><th>%</th><th>Grade</th></tr></thead>
      <tbody>
      <?php foreach ($grades as $g):
        $pct = $g['total_marks'] > 0 ? round($g['marks_obtained']/$g['total_marks']*100,1) : 0;
      ?>
      <tr>
        <td><?= e($g['course_name']) ?></td>
        <td><?= e($g['exam_title']) ?></td>
        <td><span class="badge badge-info"><?= e($g['exam_type']) ?></span></td>
        <td><?= $g['marks_obtained'] ?></td>
        <td><?= $g['total_marks'] ?></td>
        <td><?= $pct ?>%</td>
        <td><strong style="color:<?= $pct>=50?'var(--success)':'var(--danger)' ?>"><?= e($g['grade_letter']) ?></strong></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$grades): ?><tr><td colspan="7" style="text-align:center;color:#aaa;padding:20px">No grades recorded</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Payments -->
<div class="card">
  <div class="card-header"><h2>Payment History</h2>
    <a href="../payments/add.php?student_id=<?= $id ?>" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Add Payment</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Fee Type</th><th>Amount Due</th><th>Amount Paid</th><th>Due Date</th><th>Method</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= e($p['fee_name']) ?></td>
        <td>$<?= number_format($p['amount_due'],2) ?></td>
        <td>$<?= number_format($p['amount_paid'],2) ?></td>
        <td><?= $p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—' ?></td>
        <td><?= e($p['method']) ?></td>
        <td><span class="badge badge-<?php $ps_=($p['status']??''); echo $ps_==='Paid'?'success':($ps_==='Pending'?'warning':($ps_==='Overdue'?'danger':($ps_==='Partial'?'info':'secondary'))); ?>"><?= e($p['status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No payment records</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>