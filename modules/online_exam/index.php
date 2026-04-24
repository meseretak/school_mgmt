<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student']);
$page_title = 'Online Examinations'; $active_page = 'online_exam';
$me = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$teacher = get_teacher_record($pdo);
$student = get_student_record($pdo);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    // Create exam
    if ($action==='create_exam' && ($teacher||is_admin())) {
        $d=$_POST;
        $pdo->prepare("INSERT INTO online_exams (title,description,class_id,created_by,academic_year_id,exam_type,duration_minutes,total_marks,pass_marks,start_datetime,end_datetime,shuffle_questions,show_result_immediately,max_attempts,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Draft')")
            ->execute([trim($d['title']),$d['description']??null,$d['class_id']?:(null),$me,(int)$d['academic_year_id'],$d['exam_type']??'MCQ',(int)$d['duration'],(int)$d['total_marks'],(int)$d['pass_marks'],$d['start_datetime']??null,$d['end_datetime']??null,$d['shuffle']??1,$d['show_result']??1,$d['max_attempts']??1]);
        $eid=$pdo->lastInsertId();
        flash('Exam created. Now add questions.'); header('Location: questions.php?exam_id='.$eid); exit;
    }

    // Publish/unpublish
    if ($action==='publish' && ($teacher||is_admin())) {
        $eid=(int)$_POST['exam_id'];
        $q_count=$pdo->prepare("SELECT COUNT(*) FROM online_exam_questions WHERE exam_id=?"); $q_count->execute([$eid]);
        if ((int)$q_count->fetchColumn()<1) { flash('Add at least 1 question before publishing.','error'); header('Location: index.php'); exit; }
        $pdo->prepare("UPDATE online_exams SET status='Published' WHERE id=?")->execute([$eid]);
        flash('Exam published — students can now see it.'); header('Location: index.php'); exit;
    }

    if ($action==='close') {
        $pdo->prepare("UPDATE online_exams SET status='Closed' WHERE id=?")->execute([(int)$_POST['exam_id']]);
        flash('Exam closed.'); header('Location: index.php'); exit;
    }

    if ($action==='delete_exam' && is_admin()) {
        $pdo->prepare("DELETE FROM online_exams WHERE id=?")->execute([(int)$_POST['exam_id']]);
        flash('Exam deleted.'); header('Location: index.php'); exit;
    }
}

$years=$pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$year_id=(int)($_GET['year']??($pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn()??0));

if (is_student() && $student) {
    // Students see published exams for their enrolled classes
    $exams=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code,cl.section,oea.status AS attempt_status,oea.score,oea.percentage,oea.grade_letter FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id LEFT JOIN online_exam_attempts oea ON oea.exam_id=oe.id AND oea.student_id=? WHERE oe.status IN('Published','Active','Closed') AND (oe.class_id IS NULL OR oe.class_id IN (SELECT class_id FROM enrollments WHERE student_id=? AND status='Enrolled')) ORDER BY oe.start_datetime DESC");
    $exams->execute([$student['id'],$student['id']]); $exams=$exams->fetchAll();
} elseif (is_teacher() && $teacher) {
    $exams=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code,cl.section,(SELECT COUNT(*) FROM online_exam_attempts WHERE exam_id=oe.id AND status='Submitted') AS submitted,(SELECT COUNT(*) FROM online_exam_questions WHERE exam_id=oe.id) AS q_count FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id WHERE oe.created_by=? ORDER BY oe.created_at DESC");
    $exams->execute([$me]); $exams=$exams->fetchAll();
} else {
    $exams=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code,cl.section,u.name AS created_by_name,(SELECT COUNT(*) FROM online_exam_attempts WHERE exam_id=oe.id) AS attempts,(SELECT COUNT(*) FROM online_exam_questions WHERE exam_id=oe.id) AS q_count FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id LEFT JOIN users u ON oe.created_by=u.id WHERE oe.academic_year_id=? ORDER BY oe.created_at DESC");
    $exams->execute([$year_id]); $exams=$exams->fetchAll();
}

$classes=is_teacher()&&$teacher ? $pdo->prepare("SELECT cl.*,co.name AS course_name,co.code FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY co.name") : $pdo->query("SELECT cl.*,co.name AS course_name,co.code FROM classes cl JOIN courses co ON cl.course_id=co.id ORDER BY co.name");
if (is_teacher()&&$teacher) { $classes->execute([$teacher['id']]); } $classes=$classes->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-laptop-code" style="color:var(--primary)"></i> Online Examinations</h1>
    <p style="color:var(--muted)"><?= is_student() ? 'Your available exams' : 'Create and manage online exams' ?></p>
  </div>
  <?php if(!is_student()):?>
  <button onclick="document.getElementById('createExamModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Create Exam</button>
  <?php endif;?>
</div>

<!-- Stats for admin/teacher -->
<?php if(!is_student()): $total=count($exams); $published=count(array_filter($exams,fn($e)=>$e['status']==='Published')); $closed=count(array_filter($exams,fn($e)=>$e['status']==='Closed')); ?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-file-alt"></i></div><div class="stat-info"><h3><?=$total?></h3><p>Total Exams</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?=$published?></h3><p>Published</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-lock"></i></div><div class="stat-info"><h3><?=$closed?></h3><p>Closed</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?=array_sum(array_column($exams,'attempts'??0))?></h3><p>Total Attempts</p></div></div>
</div>
<?php endif;?>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> <?=is_student()?'Available Exams':'All Exams'?> (<?=count($exams)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Exam Title</th>
        <th>Course</th>
        <th>Type</th>
        <th>Duration</th>
        <th>Marks</th>
        <th>Window</th>
        <th>Status</th>
        <?php if(is_student()):?><th>My Result</th><?php else:?><th>Questions</th><th>Attempts</th><?php endif;?>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($exams as $ex):
      $now=time(); $start=$ex['start_datetime']?strtotime($ex['start_datetime']):0; $end=$ex['end_datetime']?strtotime($ex['end_datetime']):PHP_INT_MAX;
      $is_open=$ex['status']==='Published'&&($start==0||$now>=$start)&&$now<=$end;
    ?>
    <tr>
      <td>
        <div style="font-weight:700"><?=e($ex['title'])?></div>
        <?php if($ex['description']):?><div style="font-size:.75rem;color:var(--muted)"><?=e(mb_substr($ex['description'],0,60))?></div><?php endif;?>
      </td>
      <td><?=$ex['course_name']?e($ex['code'].' §'.$ex['section']):'<span style="color:var(--muted)">All</span>'?></td>
      <td><span class="badge badge-info"><?=e($ex['exam_type'])?></span></td>
      <td><?=$ex['duration_minutes']?> min</td>
      <td><?=$ex['total_marks']?> / Pass: <?=$ex['pass_marks']?></td>
      <td style="font-size:.78rem">
        <?=$ex['start_datetime']?date('M j, g:i A',strtotime($ex['start_datetime'])):'Anytime'?>
        <?=$ex['end_datetime']?'<br>→ '.date('M j, g:i A',strtotime($ex['end_datetime'])):''?>
      </td>
      <td>
        <span class="badge badge-<?=match($ex['status']){'Draft'=>'secondary','Published'=>'success','Active'=>'warning','Closed'=>'danger',default=>'secondary'}?>"><?=e($ex['status'])?></span>
        <?php if($is_open):?><div style="font-size:.7rem;color:var(--success);margin-top:2px"><i class="fas fa-circle" style="font-size:.5rem"></i> Live</div><?php endif;?>
      </td>
      <?php if(is_student()):?>
      <td>
        <?php if($ex['attempt_status']==='Submitted'||$ex['attempt_status']==='Graded'):?>
        <div style="font-weight:700;color:<?=$ex['percentage']>=$ex['pass_marks']/$ex['total_marks']*100?'var(--success)':'var(--danger)'?>"><?=$ex['score']?>/<?=$ex['total_marks']?> (<?=$ex['percentage']?>%)</div>
        <div style="font-size:.75rem"><?=e($ex['grade_letter']??'')?></div>
        <?php elseif($ex['attempt_status']==='In Progress'):?>
        <span class="badge badge-warning">In Progress</span>
        <?php else:?><span style="color:var(--muted);font-size:.82rem">Not attempted</span><?php endif;?>
      </td>
      <?php else:?>
      <td style="text-align:center"><strong><?=$ex['q_count']??0?></strong></td>
      <td style="text-align:center"><?=$ex['attempts']??0?></td>
      <?php endif;?>
      <td>
        <div style="display:flex;gap:4px;flex-wrap:wrap">
        <?php if(is_student()):?>
          <?php if($is_open&&!$ex['attempt_status']):?>
          <a href="take.php?exam_id=<?=$ex['id']?>" class="btn btn-sm btn-success"><i class="fas fa-play"></i> Start</a>
          <?php elseif($ex['attempt_status']==='In Progress'):?>
          <a href="take.php?exam_id=<?=$ex['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-play"></i> Continue</a>
          <?php elseif($ex['attempt_status']):?>
          <a href="result.php?exam_id=<?=$ex['id']?>" class="btn btn-sm btn-secondary"><i class="fas fa-eye"></i> Result</a>
          <?php endif;?>
        <?php else:?>
          <a href="questions.php?exam_id=<?=$ex['id']?>" class="btn btn-sm btn-primary"><i class="fas fa-question-circle"></i> Questions</a>
          <a href="results.php?exam_id=<?=$ex['id']?>" class="btn btn-sm btn-secondary"><i class="fas fa-chart-bar"></i> Results</a>
          <?php if($ex['status']==='Draft'):?>
          <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="exam_id" value="<?=$ex['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-globe"></i> Publish</button></form>
          <?php elseif($ex['status']==='Published'):?>
          <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="close"><input type="hidden" name="exam_id" value="<?=$ex['id']?>"><button class="btn btn-sm btn-warning"><i class="fas fa-lock"></i> Close</button></form>
          <?php endif;?>
          <?php if(is_admin()):?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete exam and all data?')"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete_exam"><input type="hidden" name="exam_id" value="<?=$ex['id']?>"><button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
          <?php endif;?>
        <?php endif;?>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$exams):?><tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-laptop-code" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>No exams yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- Create Exam Modal -->
<?php if(!is_student()):?>
<div id="createExamModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:600px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-plus" style="color:var(--primary)"></i> Create Online Exam</h3>
      <button onclick="document.getElementById('createExamModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="create_exam">
      <input type="hidden" name="academic_year_id" value="<?=$year_id?>">
      <div class="form-grid">
        <div class="form-group full"><label>Exam Title *</label><input name="title" required placeholder="e.g. Mathematics Midterm Exam"></div>
        <div class="form-group full"><label>Description</label><textarea name="description" rows="2" placeholder="Instructions for students..."></textarea></div>
        <div class="form-group"><label>Class (optional)</label>
          <select name="class_id"><option value="">All enrolled students</option>
            <?php foreach($classes as $cl):?><option value="<?=$cl['id']?>"><?=e($cl['code'].' — '.$cl['course_name'].' §'.$cl['section'])?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Exam Type</label>
          <select name="exam_type"><option>MCQ</option><option>Short Answer</option><option>Mixed</option></select>
        </div>
        <div class="form-group"><label>Duration (minutes) *</label><input type="number" name="duration" value="60" min="5" required></div>
        <div class="form-group"><label>Total Marks *</label><input type="number" name="total_marks" value="100" min="1" required></div>
        <div class="form-group"><label>Pass Marks *</label><input type="number" name="pass_marks" value="50" min="1" required></div>
        <div class="form-group"><label>Max Attempts</label><input type="number" name="max_attempts" value="1" min="1" max="5"></div>
        <div class="form-group"><label>Start Date/Time</label><input type="datetime-local" name="start_datetime"></div>
        <div class="form-group"><label>End Date/Time</label><input type="datetime-local" name="end_datetime"></div>
        <div class="form-group"><label>Shuffle Questions</label><select name="shuffle"><option value="1">Yes</option><option value="0">No</option></select></div>
        <div class="form-group"><label>Show Result Immediately</label><select name="show_result"><option value="1">Yes</option><option value="0">No</option></select></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Create & Add Questions</button>
        <button type="button" onclick="document.getElementById('createExamModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php'; ?>
