<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student']);
$page_title = 'Assignments'; $active_page = 'assignments';
$uid = $_SESSION['user']['id']; $role = $_SESSION['user']['role'];
$is_admin_view = in_array($role, ['admin','super_admin']);

$teacher = get_teacher_record($pdo);
$student = get_student_record($pdo);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' && $teacher) {
        $d = $_POST;
        $class_id = (int)$d['class_id'];
        if (is_teacher() && !teacher_owns_class($pdo, $class_id, $teacher['id'])) deny();
        $pdo->prepare("INSERT INTO assignments (class_id,teacher_id,title,description,due_date,due_time,total_marks,pass_marks,allow_late,status) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$class_id,$teacher['id'],$d['title'],$d['description']??null,$d['due_date']??null,$d['due_time']??null,$d['total_marks']??100,$d['pass_marks']??50,$d['allow_late']??0,$d['status']??'Published']);
        $aid = $pdo->lastInsertId();
        if (($d['status']??'Published') === 'Published') {
            require_once '../../includes/notify.php';
            notify_assignment_posted($pdo, $aid);
        }
        log_activity($pdo, 'assignment_created', "Assignment: {$d['title']}");
        flash('Assignment posted.');
        header('Location: index.php'); exit;
    }

    if ($action === 'submit' && $student) {
        $aid = (int)$_POST['assignment_id'];
        $text = trim($_POST['submission_text'] ?? '');

        // Get assignment details for validation
        $asgn = $pdo->prepare("SELECT * FROM assignments WHERE id=?");
        $asgn->execute([$aid]); $asgn = $asgn->fetch();

        if (!$asgn) { flash('Assignment not found.', 'error'); header('Location: index.php'); exit; }
        if ($asgn['status'] !== 'Published') { flash('This assignment is not open for submission.', 'error'); header('Location: index.php'); exit; }

        // Deadline check
        if ($asgn['due_date'] && !$asgn['allow_late']) {
            $deadline = $asgn['due_time']
                ? strtotime($asgn['due_date'].' '.$asgn['due_time'])
                : strtotime($asgn['due_date'].' 23:59:59');
            if (time() > $deadline) {
                flash('The deadline for this assignment has passed. Late submissions are not allowed.', 'error');
                header('Location: index.php?view='.$aid); exit;
            }
        }

        if (empty($text)) { flash('Submission cannot be empty.', 'error'); header('Location: index.php?view='.$aid); exit; }

        $pdo->prepare("INSERT INTO assignment_submissions (assignment_id,student_id,submission_text,status) VALUES (?,?,?,'Submitted') ON DUPLICATE KEY UPDATE submission_text=?,submitted_at=NOW(),status='Submitted'")
            ->execute([$aid,$student['id'],$text,$text]);
        flash('Assignment submitted successfully.');
        header('Location: index.php'); exit;
    }

    if ($action === 'grade' && $teacher) {
        $sid   = (int)$_POST['sub_id'];
        $marks = $_POST['marks'];
        $aid   = (int)$_POST['assignment_id'];

        // Validate submission exists
        $sub = $pdo->prepare("SELECT * FROM assignment_submissions WHERE id=?");
        $sub->execute([$sid]); $sub = $sub->fetch();
        if (!$sub) { flash('Submission not found.', 'error'); header('Location: index.php?view='.$aid); exit; }

        // Validate marks
        $asgn = $pdo->prepare("SELECT total_marks FROM assignments WHERE id=?");
        $asgn->execute([$aid]); $asgn = $asgn->fetch();
        $total = (float)($asgn['total_marks'] ?? 100);

        if ($marks === '' || $marks === null) { flash('Marks are required.', 'error'); header('Location: index.php?view='.$aid); exit; }
        $marks = (float)$marks;
        if ($marks < 0 || $marks > $total) { flash("Marks must be between 0 and {$total}.", 'error'); header('Location: index.php?view='.$aid); exit; }

        $pct    = $total > 0 ? $marks/$total*100 : 0;
        $letter = grade_letter($pct);
        $pdo->prepare("UPDATE assignment_submissions SET marks_obtained=?,grade_letter=?,feedback=?,graded_by=?,graded_at=NOW(),status='Graded' WHERE id=?")
            ->execute([$marks,$letter,$_POST['feedback']??null,$uid,$sid]);
        flash('Submission graded successfully.');
        header('Location: index.php?view='.$aid); exit;
    }

    if ($action === 'delete' && ($role === 'admin' || $teacher)) {
        $pdo->prepare("DELETE FROM assignment_submissions WHERE assignment_id=?")->execute([(int)$_POST['assignment_id']]);
        $pdo->prepare("DELETE FROM assignments WHERE id=?")->execute([(int)$_POST['assignment_id']]);
        flash('Assignment deleted.');
        header('Location: index.php'); exit;
    }
}

// View single assignment
$view_id = (int)($_GET['view'] ?? 0);
$view_assignment = null; $submissions = [];
if ($view_id) {
    $va = $pdo->prepare("SELECT a.*, co.name AS course_name, co.code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON a.teacher_id=t.id WHERE a.id=?");
    $va->execute([$view_id]); $view_assignment = $va->fetch();
    if ($view_assignment) {
        $submissions = $pdo->prepare("SELECT sub.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code FROM assignment_submissions sub JOIN students s ON sub.student_id=s.id WHERE sub.assignment_id=? ORDER BY sub.submitted_at");
        $submissions->execute([$view_id]); $submissions = $submissions->fetchAll();
    }
}

// List assignments
if ($role === 'teacher' && $teacher) {
    $assignments = $pdo->prepare("SELECT a.*, co.name AS course_name, co.code, cl.section, COUNT(sub.id) AS submitted, COUNT(en.id) AS total_students FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id LEFT JOIN enrollments en ON en.class_id=cl.id AND en.status='Enrolled' WHERE a.teacher_id=? GROUP BY a.id ORDER BY a.created_at DESC");
    $assignments->execute([$teacher['id']]); $assignments = $assignments->fetchAll();
    $my_classes = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY co.name");
    $my_classes->execute([$teacher['id']]); $my_classes = $my_classes->fetchAll();
} elseif ($role === 'student' && $student) {
    $assignments = $pdo->prepare("SELECT a.*, co.name AS course_name, co.code, cl.section, CONCAT(t.first_name,' ',t.last_name) AS teacher_name, sub.status AS sub_status, sub.marks_obtained, sub.grade_letter, sub.id AS sub_id FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON a.teacher_id=t.id JOIN enrollments en ON en.class_id=cl.id AND en.student_id=? AND en.status='Enrolled' LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id AND sub.student_id=? WHERE a.status='Published' ORDER BY a.due_date ASC");
    $assignments->execute([$student['id'],$student['id']]); $assignments = $assignments->fetchAll();
} else {
    // Admin / Super Admin: see all assignments with branch + submission stats
    $assignments = $pdo->query("
        SELECT a.*, co.name AS course_name, co.code, cl.section,
            CONCAT(t.first_name,' ',t.last_name) AS teacher_name,
            b.name AS branch_name,
            COUNT(DISTINCT sub.id) AS submitted,
            COUNT(DISTINCT en.id) AS total_students,
            COUNT(DISTINCT CASE WHEN sub.status='Graded' THEN sub.id END) AS graded
        FROM assignments a
        JOIN classes cl ON a.class_id=cl.id
        JOIN courses co ON cl.course_id=co.id
        JOIN teachers t ON a.teacher_id=t.id
        LEFT JOIN branches b ON cl.branch_id=b.id
        LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id
        LEFT JOIN enrollments en ON en.class_id=cl.id AND en.status='Enrolled'
        GROUP BY a.id ORDER BY a.created_at DESC
    ")->fetchAll();
}

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-tasks" style="color:var(--primary)"></i> Assignments</h1></div>
</div>

<?php if ($view_assignment && ($role === 'teacher' || $role === 'admin')): ?>
<!-- Teacher: view submissions -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div>
      <h2><?= e($view_assignment['title']) ?></h2>
      <p style="font-size:.85rem;color:#888"><?= e($view_assignment['code'].' — '.$view_assignment['course_name'].' | Section '.$view_assignment['section']) ?> | Due: <?= $view_assignment['due_date']?date('M j, Y',strtotime($view_assignment['due_date'])):'No deadline' ?></p>
    </div>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Submitted</th><th>Status</th><th>Marks</th><th>Grade</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($submissions as $sub): ?>
    <tr>
      <td style="font-weight:600"><?= e($sub['student_name']) ?> <small style="color:#888"><?= e($sub['student_code']) ?></small></td>
      <td style="font-size:.82rem"><?= $sub['submitted_at']?date('M j, Y g:i A',strtotime($sub['submitted_at'])):'—' ?></td>
      <td><span class="badge badge-<?= match($sub['status']??''){'Graded'=>'success','Submitted'=>'info','Late'=>'warning','Missing'=>'danger',default=>'secondary'} ?>"><?= e($sub['status']??'—') ?></span></td>
      <td><?= $sub['marks_obtained']??'—' ?></td>
      <td style="font-weight:700;color:var(--success)"><?= e($sub['grade_letter']??'—') ?></td>
      <td>
        <button onclick="openGrade(<?= $sub['id'] ?>,<?= $view_id ?>,<?= $sub['marks_obtained']??0 ?>,'<?= e($sub['feedback']??'') ?>')" class="btn btn-sm btn-primary"><i class="fas fa-star"></i> Grade</button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$submissions): ?><tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">No submissions yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<!-- Grade modal -->
<div id="gradeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:400px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-star" style="color:var(--warning)"></i> Grade Submission</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="grade">
      <input type="hidden" name="sub_id" id="g_sub_id">
      <input type="hidden" name="assignment_id" value="<?= $view_id ?>">
      <div class="form-group" style="margin-bottom:12px"><label>Marks (out of <?= $view_assignment['total_marks'] ?>)</label><input type="number" step="0.5" name="marks" id="g_marks" min="0" max="<?= $view_assignment['total_marks'] ?>" required></div>
      <div class="form-group" style="margin-bottom:16px"><label>Feedback</label><textarea name="feedback" id="g_feedback" rows="3"></textarea></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Grade</button>
        <button type="button" onclick="document.getElementById('gradeModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openGrade(sid, aid, marks, feedback) {
  document.getElementById('g_sub_id').value = sid;
  document.getElementById('g_marks').value = marks;
  document.getElementById('g_feedback').value = feedback;
  document.getElementById('gradeModal').style.display = 'flex';
}
</script>

<?php elseif ($role === 'student' && $student && $view_id): ?>
<!-- Student: submit assignment -->
<?php $va2 = $pdo->prepare("SELECT a.*, co.name AS course_name, sub.submission_text, sub.status AS sub_status, sub.marks_obtained, sub.grade_letter, sub.feedback FROM assignments a JOIN classes cl ON a.class_id=cl.id JOIN courses co ON cl.course_id=co.id LEFT JOIN assignment_submissions sub ON sub.assignment_id=a.id AND sub.student_id=? WHERE a.id=?"); $va2->execute([$student['id'],$view_id]); $va2 = $va2->fetch(); ?>
<?php if ($va2): ?>
<div class="card">
  <div class="card-header"><h2><?= e($va2['title']) ?></h2><a href="index.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
  <div class="card-body">
    <?php if ($va2['description']): ?><div style="margin-bottom:16px;padding:14px;background:#f8f9ff;border-radius:8px;line-height:1.6"><?= nl2br(e($va2['description'])) ?></div><?php endif; ?>
    <?php if ($va2['sub_status'] === 'Graded'): ?>
    <div style="background:#d4edda;border-radius:10px;padding:16px;margin-bottom:16px">
      <strong>Graded:</strong> <?= $va2['marks_obtained'] ?>/<?= $va2['total_marks'] ?> — <strong><?= e($va2['grade_letter']) ?></strong>
      <?php if ($va2['feedback']): ?><div style="margin-top:6px;color:#555"><?= e($va2['feedback']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!in_array($va2['sub_status']??'', ['Graded'])): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="submit">
      <input type="hidden" name="assignment_id" value="<?= $view_id ?>">
      <div class="form-group" style="margin-bottom:14px"><label>Your Answer / Submission</label><textarea name="submission_text" rows="8" required><?= e($va2['submission_text']??'') ?></textarea></div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> <?= $va2['sub_status']?'Update Submission':'Submit Assignment' ?></button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- List view -->
<?php if ($role === 'teacher' && $teacher): ?>
<!-- Create assignment form -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><h2><i class="fas fa-plus" style="color:var(--success)"></i> Post New Assignment</h2></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group full"><label>Title *</label><input name="title" required placeholder="Assignment title"></div>
        <div class="form-group"><label>Class *</label>
          <select name="class_id" required>
            <option value="">Select Class</option>
            <?php foreach ($my_classes as $cl): ?>
            <option value="<?= $cl['id'] ?>"><?= e($cl['code'].' — '.$cl['course_name'].' ('.$cl['section'].')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Due Date</label><input type="date" name="due_date"></div>
        <div class="form-group"><label>Due Time</label><input type="time" name="due_time"></div>
        <div class="form-group"><label>Total Marks</label><input type="number" name="total_marks" value="100"></div>
        <div class="form-group"><label>Pass Marks</label><input type="number" name="pass_marks" value="50"></div>
        <div class="form-group"><label>Allow Late Submission</label>
          <select name="allow_late"><option value="0">No</option><option value="1">Yes</option></select>
        </div>
        <div class="form-group"><label>Status</label>
          <select name="status"><option value="Published">Published</option><option value="Draft">Draft</option></select>
        </div>
        <div class="form-group full"><label>Description / Instructions</label><textarea name="description" rows="4" placeholder="Describe the assignment..."></textarea></div>
      </div>
      <div style="margin-top:16px"><button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Assignment</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> <?= $role==='student'?'My Assignments':'All Assignments' ?> (<?= count($assignments) ?>)</h2></div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Title</th><th>Course</th>
        <?php if ($is_admin_view): ?><th>Branch</th><?php endif; ?>
        <?php if ($role !== 'student'): ?><th>Teacher</th><?php endif; ?>
        <th>Due Date</th><th>Marks</th>
        <?php if ($role === 'student'): ?>
          <th>My Status</th><th>Grade</th>
        <?php elseif ($is_admin_view): ?>
          <th>Submitted</th><th>Graded</th><th>Status</th>
        <?php else: ?>
          <th>Submitted</th><th>Status</th>
        <?php endif; ?>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($assignments as $a):
      $overdue = $a['due_date'] && strtotime($a['due_date']) < time();
    ?>
    <tr>
      <td style="font-weight:600"><?= e($a['title']) ?></td>
      <td><?= e($a['code'].' — '.$a['course_name']) ?><br><small style="color:#888">Section <?= e($a['section']) ?></small></td>
      <?php if ($is_admin_view): ?>
      <td><span class="badge badge-info" style="font-size:.75rem"><?= e($a['branch_name']??'—') ?></span></td>
      <?php endif; ?>
      <?php if ($role !== 'student'): ?><td><?= e($a['teacher_name']??'—') ?></td><?php endif; ?>
      <td style="color:<?= $overdue?'var(--danger)':'inherit' ?>">
        <?= $a['due_date']?date('M j, Y',strtotime($a['due_date'])):'No deadline' ?>
        <?php if ($overdue): ?><span class="badge badge-danger" style="margin-left:4px">Overdue</span><?php endif; ?>
      </td>
      <td><?= $a['total_marks'] ?></td>
      <?php if ($role === 'student'): ?>
      <td><span class="badge badge-<?= match($a['sub_status']??''){'Graded'=>'success','Submitted'=>'info','Late'=>'warning','Missing'=>'danger',default=>'secondary'} ?>"><?= $a['sub_status']??'Not Submitted' ?></span></td>
      <td style="font-weight:700;color:var(--success)"><?= $a['grade_letter']??'—' ?></td>
      <?php elseif ($is_admin_view): ?>
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <?= ($a['submitted']??0) ?>/<?= ($a['total_students']??0) ?>
          <?php if (($a['total_students']??0) > 0): $pct = round(($a['submitted']/$a['total_students'])*100); ?>
          <div class="progress" style="width:50px"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
          <?php endif; ?>
        </div>
      </td>
      <td><span class="badge badge-<?= ($a['graded']??0)>0?'success':'secondary' ?>"><?= $a['graded']??0 ?> graded</span></td>
      <td><span class="badge badge-<?= $a['status']==='Published'?'success':($a['status']==='Draft'?'secondary':'danger') ?>"><?= e($a['status']) ?></span></td>
      <?php else: ?>
      <td><?= $a['submitted']??0 ?>/<?= $a['total_students']??'?' ?></td>
      <td><span class="badge badge-<?= $a['status']==='Published'?'success':($a['status']==='Draft'?'secondary':'danger') ?>"><?= e($a['status']) ?></span></td>
      <?php endif; ?>
      <td>
        <?php if ($role === 'student'): ?>
        <a href="?view=<?= $a['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-<?= $a['sub_id']?'eye':'paper-plane' ?>"></i> <?= $a['sub_id']?'View':'Submit' ?></a>
        <?php else: ?>
        <a href="?view=<?= $a['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Submissions</a>
        <?php if ($role === 'teacher' || $is_admin_view): ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this assignment?')">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
          <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$assignments): ?><tr><td colspan="9" style="text-align:center;color:#aaa;padding:30px">No assignments yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>