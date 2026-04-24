<?php
require_once '../../includes/config.php';
auth_check(['teacher','admin']);
$page_title = 'Class Students'; $active_page = 'teacher_dashboard';

$teacher = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$_SESSION['user']['id']]); $teacher = $teacher->fetch();

$class_id = (int)($_GET['class_id'] ?? 0);

// Verify teacher owns this class (skip for admin)
if ($_SESSION['user']['role'] === 'teacher' && $class_id) {
    $owns = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
    $owns->execute([$class_id, $teacher['id']]);
    if (!$owns->fetch()) { flash('Access denied.','error'); header('Location: dashboard.php'); exit; }
}

$class = $pdo->prepare("SELECT cl.*, co.name AS course_name, co.code AS course_code, co.credits, ay.label AS year_label FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id WHERE cl.id=?");
$class->execute([$class_id]); $class = $class->fetch();

// Students with attendance summary and avg grade
$students = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.student_code, s.nationality, s.status,
           en.id AS enrollment_id, en.status AS enroll_status, en.enrolled_at,
           COUNT(DISTINCT a.id) AS att_total,
           SUM(CASE WHEN a.status='Present' THEN 1 ELSE 0 END) AS att_present,
           SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) AS att_absent,
           AVG(CASE WHEN g.marks_obtained IS NOT NULL THEN g.marks_obtained/ex.total_marks*100 END) AS avg_grade
    FROM enrollments en
    JOIN students s ON en.student_id=s.id
    LEFT JOIN attendance a ON a.enrollment_id=en.id
    LEFT JOIN grades g ON g.enrollment_id=en.id
    LEFT JOIN exams ex ON g.exam_id=ex.id
    WHERE en.class_id=?
    GROUP BY en.id ORDER BY s.first_name");
$students->execute([$class_id]); $students = $students->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><?= e($class['course_code'].' — '.$class['course_name']) ?></h1>
    <p>Section <?= e($class['section']??'—') ?> | <?= e($class['year_label']) ?> | <?= count($students) ?> students</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="attendance.php?class_id=<?= $class_id ?>" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Mark Attendance</a>
    <a href="exams.php?class_id=<?= $class_id ?>" class="btn btn-secondary"><i class="fas fa-file-alt"></i> Exams</a>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-users" style="color:var(--primary)"></i> Enrolled Students</h2></div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>#</th><th>Student</th><th>Code</th><th>Nationality</th>
        <th>Attendance</th><th>Att. Rate</th><th>Avg Grade</th><th>Status</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($students as $i => $s):
      $att_rate = $s['att_total'] > 0 ? round($s['att_present']/$s['att_total']*100) : null;
      $avg = $s['avg_grade'] !== null ? round($s['avg_grade'],1) : null;
    ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="avatar" style="width:34px;height:34px;font-size:.8rem"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
          <div>
            <div style="font-weight:600"><?= e($s['first_name'].' '.$s['last_name']) ?></div>
            <div style="font-size:.75rem;color:#888"><?= date('M j, Y', strtotime($s['enrolled_at'])) ?></div>
          </div>
        </div>
      </td>
      <td><?= e($s['student_code']??'—') ?></td>
      <td><?= e($s['nationality']??'—') ?></td>
      <td style="font-size:.85rem">
        <span style="color:var(--success)"><?= $s['att_present'] ?> P</span> /
        <span style="color:var(--danger)"><?= $s['att_absent'] ?> A</span> /
        <span style="color:#888"><?= $s['att_total'] ?> total</span>
      </td>
      <td>
        <?php if ($att_rate !== null): ?>
        <div style="display:flex;align-items:center;gap:6px">
          <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?= $att_rate ?>%;background:<?= $att_rate>=75?'var(--success)':($att_rate>=50?'var(--warning)':'var(--danger)') ?>"></div></div>
          <span style="font-size:.82rem;color:<?= $att_rate<75?'var(--danger)':'inherit' ?>"><?= $att_rate ?>%</span>
          <?php if ($att_rate < 75): ?><span title="Below 75% attendance" style="color:var(--danger)">⚠️</span><?php endif; ?>
        </div>
        <?php else: ?><span style="color:#aaa">—</span><?php endif; ?>
      </td>
      <td>
        <?php if ($avg !== null): ?>
        <strong style="color:<?= $avg>=50?'var(--success)':'var(--danger)' ?>"><?= $avg ?>% (<?= grade_letter($avg) ?>)</strong>
        <?php else: ?><span style="color:#aaa">No grades</span><?php endif; ?>
      </td>
      <td><span class="badge badge-<?= $s['enroll_status']==='Enrolled'?'success':'secondary' ?>"><?= e($s['enroll_status']) ?></span></td>
      <td>
        <a href="<?= BASE_URL ?>/modules/students/view.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-secondary" title="View Profile"><i class="fas fa-eye"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$students): ?><tr><td colspan="9" style="text-align:center;color:#aaa;padding:30px">No students enrolled.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require_once '../../includes/footer.php'; ?>