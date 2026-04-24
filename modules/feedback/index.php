<?php
require_once '../../includes/config.php';
auth_check(['teacher','admin','super_admin']);
$page_title = 'Student Feedback'; $active_page = 'feedback';
$me = $_SESSION['user']['id'];
$teacher = get_teacher_record($pdo);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='save') {
        $d=$_POST;
        $sid=(int)$d['student_id']; $cid=(int)$d['class_id']; $ayid=(int)$d['academic_year_id'];
        $tid=$teacher?$teacher['id']:(int)$d['teacher_id_override'];
        $pdo->prepare("INSERT INTO student_feedback (student_id,teacher_id,class_id,academic_year_id,semester,behavior_rating,participation_rating,effort_rating,comments,strengths,areas_for_improvement,recommendation,is_shared_with_parent)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE semester=?,behavior_rating=?,participation_rating=?,effort_rating=?,comments=?,strengths=?,areas_for_improvement=?,recommendation=?,is_shared_with_parent=?,updated_at=NOW()")
            ->execute([$sid,$tid,$cid,$ayid,$d['semester'],$d['behavior'],$d['participation'],$d['effort'],$d['comments']??null,$d['strengths']??null,$d['improvements']??null,$d['recommendation'],$d['share_parent']??1,
                $d['semester'],$d['behavior'],$d['participation'],$d['effort'],$d['comments']??null,$d['strengths']??null,$d['improvements']??null,$d['recommendation'],$d['share_parent']??1]);

        // Notify student
        $student_user=$pdo->prepare("SELECT user_id FROM students WHERE id=?"); $student_user->execute([$sid]); $suid=$student_user->fetchColumn();
        if ($suid) { require_once '../../includes/notify.php'; notify_user($pdo,$suid,'📝 New Teacher Feedback','Your teacher has submitted feedback for you. Check your dashboard.',0); }

        // Notify parent if shared
        if (($d['share_parent']??1)==1) {
            $parent_users=$pdo->prepare("SELECT u.id FROM users u JOIN parents p ON p.user_id=u.id JOIN student_parents sp ON sp.parent_id=p.id WHERE sp.student_id=?");
            $parent_users->execute([$sid]); $parent_users=$parent_users->fetchAll();
            foreach ($parent_users as $pu) {
                require_once '../../includes/notify.php';
                $sname=$pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM students WHERE id=?"); $sname->execute([$sid]); $sname=$sname->fetchColumn();
                notify_user($pdo,$pu['id'],'👨‍🏫 Teacher Feedback for '.$sname,'A teacher has submitted feedback for your child. Log in to view it.',0);
            }
        }

        flash('Feedback saved.'); header('Location: index.php'); exit;
    }
}

// Get classes for teacher
if ($teacher) {
    $classes=$pdo->prepare("SELECT cl.id,co.code,co.name AS course_name,cl.section,ay.label AS year,ay.id AS year_id FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id WHERE cl.teacher_id=? ORDER BY ay.is_current DESC,co.name");
    $classes->execute([$teacher['id']]); $classes=$classes->fetchAll();
} else {
    $classes=$pdo->query("SELECT cl.id,co.code,co.name AS course_name,cl.section,ay.label AS year,ay.id AS year_id FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON cl.academic_year_id=ay.id ORDER BY ay.is_current DESC,co.name")->fetchAll();
}

$class_id=(int)($_GET['class_id']??0);
$students=[];
if ($class_id) {
    $students=$pdo->prepare("SELECT s.id,s.first_name,s.last_name,s.student_code,sf.id AS feedback_id,sf.recommendation,sf.behavior_rating,sf.effort_rating FROM enrollments en JOIN students s ON en.student_id=s.id LEFT JOIN student_feedback sf ON sf.student_id=s.id AND sf.class_id=? WHERE en.class_id=? AND en.status='Enrolled' ORDER BY s.first_name");
    $students->execute([$class_id,$class_id]); $students=$students->fetchAll();
}

// Recent feedback list
$recent=$pdo->query("SELECT sf.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name,co.name AS course_name,ay.label AS year FROM student_feedback sf JOIN students s ON sf.student_id=s.id JOIN teachers t ON sf.teacher_id=t.id JOIN classes cl ON sf.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN academic_years ay ON sf.academic_year_id=ay.id ORDER BY sf.created_at DESC LIMIT 30")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-comment-alt" style="color:var(--primary)"></i> Student Feedback</h1><p style="color:var(--muted)">Write and manage teacher feedback for students</p></div>
</div>

<div class="grid-2" style="align-items:start">
  <!-- Class selector + student list -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-body">
        <form method="GET">
          <label style="font-weight:600;font-size:.85rem;display:block;margin-bottom:6px">Select Class</label>
          <select name="class_id" onchange="this.form.submit()" style="width:100%">
            <option value="">— Choose a class —</option>
            <?php foreach($classes as $cl):?>
            <option value="<?=$cl['id']?>" <?=$class_id==$cl['id']?'selected':''?>><?=e($cl['code'].' — '.$cl['course_name'].' §'.$cl['section'].' ('.$cl['year'].')')?></option>
            <?php endforeach;?>
          </select>
        </form>
      </div>
    </div>

    <?php if ($students): ?>
    <div class="card">
      <div class="card-header"><h2>Students (<?=count($students)?>)</h2></div>
      <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Feedback</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($students as $s):?>
        <tr>
          <td><div style="font-weight:600"><?=e($s['first_name'].' '.$s['last_name'])?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($s['student_code'])?></div></td>
          <td><?php if($s['feedback_id']):?><span class="badge badge-success">Done</span><?php else:?><span class="badge badge-secondary">Pending</span><?php endif;?></td>
          <td><button onclick="openFeedback(<?=$s['id']?>,'<?=e(addslashes($s['first_name'].' '.$s['last_name']))?>')" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> <?=$s['feedback_id']?'Edit':'Write'?></button></td>
        </tr>
        <?php endforeach;?>
        </tbody>
      </table></div>
    </div>
    <?php endif;?>
  </div>

  <!-- Recent feedback -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-history" style="color:var(--primary)"></i> Recent Feedback</h2></div>
    <div style="max-height:600px;overflow-y:auto">
    <?php foreach($recent as $f):?>
    <div style="padding:12px 16px;border-bottom:1px solid #f0f0f0">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <div style="font-weight:600;font-size:.88rem"><?=e($f['student_name'])?></div>
        <span class="badge badge-<?=match($f['recommendation']){'Excellent'=>'success','Good'=>'primary','Satisfactory'=>'info','Needs Improvement'=>'warning',default=>'danger'}?>" style="font-size:.7rem"><?=e($f['recommendation'])?></span>
      </div>
      <div style="font-size:.78rem;color:var(--muted)"><?=e($f['course_name'])?> · <?=e($f['year'])?> · <?=e($f['semester'])?></div>
      <?php if($f['comments']):?><div style="font-size:.82rem;color:#555;margin-top:4px"><?=e(mb_substr($f['comments'],0,80)).(strlen($f['comments'])>80?'...':'')?></div><?php endif;?>
    </div>
    <?php endforeach;?>
    <?php if(!$recent):?><div style="padding:30px;text-align:center;color:var(--muted)">No feedback yet.</div><?php endif;?>
    </div>
  </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:560px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-comment-alt" style="color:var(--primary)"></i> Feedback for <span id="fbStudentName"></span></h3>
      <button onclick="document.getElementById('feedbackModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="student_id" id="fbStudentId">
      <input type="hidden" name="class_id" value="<?=$class_id?>">
      <?php $sel_class=null; foreach($classes as $cl){if($cl['id']==$class_id){$sel_class=$cl;break;}} ?>
      <input type="hidden" name="academic_year_id" value="<?=$sel_class?$sel_class['year_id']:0?>">
      <div class="form-grid">
        <div class="form-group"><label>Semester</label><select name="semester"><option>Semester 1</option><option>Semester 2</option><option>Full Year</option></select></div>
        <div class="form-group"><label>Recommendation</label><select name="recommendation"><option>Excellent</option><option selected>Good</option><option>Satisfactory</option><option>Needs Improvement</option><option>Unsatisfactory</option></select></div>
        <div class="form-group"><label>Behavior (1-5)</label><input type="number" name="behavior" min="1" max="5" value="3" required></div>
        <div class="form-group"><label>Participation (1-5)</label><input type="number" name="participation" min="1" max="5" value="3" required></div>
        <div class="form-group"><label>Effort (1-5)</label><input type="number" name="effort" min="1" max="5" value="3" required></div>
        <div class="form-group"><label>Share with Parent</label><select name="share_parent"><option value="1">Yes</option><option value="0">No</option></select></div>
        <div class="form-group full"><label>General Comments</label><textarea name="comments" rows="3" placeholder="Overall assessment of the student..."></textarea></div>
        <div class="form-group full"><label>Strengths</label><textarea name="strengths" rows="2" placeholder="What the student does well..."></textarea></div>
        <div class="form-group full"><label>Areas for Improvement</label><textarea name="improvements" rows="2" placeholder="What the student needs to work on..."></textarea></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Feedback</button>
        <button type="button" onclick="document.getElementById('feedbackModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openFeedback(id,name){
  document.getElementById('fbStudentId').value=id;
  document.getElementById('fbStudentName').textContent=name;
  document.getElementById('feedbackModal').style.display='flex';
}
</script>
<?php require_once '../../includes/footer.php'; ?>
