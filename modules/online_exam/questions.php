<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher']);
$page_title = 'Exam Questions'; $active_page = 'online_exam';
$me = $_SESSION['user']['id'];
$exam_id=(int)($_GET['exam_id']??0);
$exam=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id WHERE oe.id=?");
$exam->execute([$exam_id]); $exam=$exam->fetch();
if (!$exam) { flash('Exam not found.','error'); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='add_question') {
        $qtext=trim($_POST['question_text']); $qtype=$_POST['question_type']??'MCQ'; $marks=(int)$_POST['marks'];
        $expl=trim($_POST['explanation']??'');
        $pdo->prepare("INSERT INTO online_exam_questions (exam_id,question_text,question_type,marks,explanation,sort_order) VALUES (?,?,?,?,?,(SELECT COALESCE(MAX(sort_order),0)+1 FROM online_exam_questions q2 WHERE q2.exam_id=?))")
            ->execute([$exam_id,$qtext,$qtype,$marks,$expl,$exam_id]);
        $qid=$pdo->lastInsertId();
        // Save options for MCQ/True-False
        if (in_array($qtype,['MCQ','True/False']) && !empty($_POST['options'])) {
            $correct=(int)($_POST['correct_option']??0);
            $ins=$pdo->prepare("INSERT INTO online_exam_options (question_id,option_text,is_correct,sort_order) VALUES (?,?,?,?)");
            foreach ($_POST['options'] as $i=>$opt) {
                if (trim($opt)) $ins->execute([$qid,trim($opt),$i==$correct,$i]);
            }
        }
        flash('Question added.'); header('Location: questions.php?exam_id='.$exam_id); exit;
    }

    if ($action==='delete_question') {
        $pdo->prepare("DELETE FROM online_exam_questions WHERE id=? AND exam_id=?")->execute([(int)$_POST['question_id'],$exam_id]);
        flash('Question deleted.'); header('Location: questions.php?exam_id='.$exam_id); exit;
    }
}

$questions=$pdo->prepare("SELECT q.*,(SELECT COUNT(*) FROM online_exam_options WHERE question_id=q.id) AS option_count FROM online_exam_questions q WHERE q.exam_id=? ORDER BY q.sort_order");
$questions->execute([$exam_id]); $questions=$questions->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-question-circle" style="color:var(--primary)"></i> Question Bank</h1>
    <p style="color:var(--muted)"><?=e($exam['title'])?> · <?=count($questions)?> questions · <?=array_sum(array_column($questions,'marks'))?>/<?=$exam['total_marks']?> marks assigned</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <button onclick="document.getElementById('addQModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Add Question</button>
  </div>
</div>

<!-- Progress bar -->
<?php $assigned=array_sum(array_column($questions,'marks')); $pct=$exam['total_marks']>0?min(100,round($assigned/$exam['total_marks']*100)):0; ?>
<div style="background:#f0f2f8;border-radius:8px;padding:12px 16px;margin-bottom:20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
  <div style="flex:1">
    <div style="font-size:.82rem;color:#888;margin-bottom:4px">Marks assigned: <?=$assigned?>/<?=$exam['total_marks']?></div>
    <div class="progress"><div class="progress-bar" style="width:<?=$pct?>%;background:<?=$pct>=100?'var(--success)':'var(--primary)'?>"></div></div>
  </div>
  <div style="font-size:.85rem;font-weight:600;color:<?=$pct>=100?'var(--success)':'var(--warning)'?>"><?=$pct?>%</div>
  <?php if($pct>=100&&$exam['status']==='Draft'):?>
  <form method="POST" action="index.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="publish"><input type="hidden" name="exam_id" value="<?=$exam_id?>"><button class="btn btn-success btn-sm"><i class="fas fa-globe"></i> Publish Exam</button></form>
  <?php endif;?>
</div>

<!-- Questions list -->
<?php foreach($questions as $i=>$q):
  $opts=$pdo->prepare("SELECT * FROM online_exam_options WHERE question_id=? ORDER BY sort_order");
  $opts->execute([$q['id']]); $opts=$opts->fetchAll();
?>
<div class="card" style="margin-bottom:12px">
  <div class="card-body">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
      <div style="flex:1">
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
          <span style="background:#4361ee;color:#fff;border-radius:50%;width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0"><?=$i+1?></span>
          <span class="badge badge-info"><?=e($q['question_type'])?></span>
          <span class="badge badge-secondary"><?=$q['marks']?> mark<?=$q['marks']>1?'s':''?></span>
        </div>
        <div style="font-weight:600;font-size:.95rem;margin-bottom:10px"><?=nl2br(e($q['question_text']))?></div>
        <?php if($opts):?>
        <div style="display:flex;flex-direction:column;gap:4px">
          <?php foreach($opts as $j=>$opt):?>
          <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;border-radius:6px;background:<?=$opt['is_correct']?'#f0fff4':'#f8f9ff'?>;border:1px solid <?=$opt['is_correct']?'#bbf7d0':'#e0e0e0'?>">
            <span style="width:22px;height:22px;border-radius:50%;background:<?=$opt['is_correct']?'#2dc653':'#e0e0e0'?>;color:<?=$opt['is_correct']?'#fff':'#888'?>;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?=chr(65+$j)?></span>
            <span style="font-size:.88rem"><?=e($opt['option_text'])?></span>
            <?php if($opt['is_correct']):?><span style="margin-left:auto;color:#2dc653;font-size:.75rem;font-weight:700"><i class="fas fa-check"></i> Correct</span><?php endif;?>
          </div>
          <?php endforeach;?>
        </div>
        <?php endif;?>
        <?php if($q['explanation']):?><div style="margin-top:8px;font-size:.8rem;color:#666;background:#fff8e1;padding:6px 10px;border-radius:6px"><i class="fas fa-lightbulb" style="color:#f59e0b"></i> <?=e($q['explanation'])?></div><?php endif;?>
      </div>
      <form method="POST" onsubmit="return confirm('Delete question?')">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="delete_question">
        <input type="hidden" name="question_id" value="<?=$q['id']?>">
        <button class="btn btn-sm btn-danger btn-icon"><i class="fas fa-trash"></i></button>
      </form>
    </div>
  </div>
</div>
<?php endforeach;?>
<?php if(!$questions):?>
<div class="card"><div class="card-body" style="text-align:center;padding:50px;color:var(--muted)"><i class="fas fa-question-circle" style="font-size:3rem;display:block;margin-bottom:12px;opacity:.3"></i>No questions yet. Add your first question.</div></div>
<?php endif;?>

<!-- Add Question Modal -->
<div id="addQModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:620px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-plus" style="color:var(--primary)"></i> Add Question</h3>
      <button onclick="document.getElementById('addQModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST" id="qForm">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_question">
      <div class="form-group" style="margin-bottom:12px">
        <label>Question Type</label>
        <select name="question_type" id="qType" onchange="toggleOptions(this.value)">
          <option value="MCQ">Multiple Choice (MCQ)</option>
          <option value="True/False">True / False</option>
          <option value="Short Answer">Short Answer</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Question Text *</label><textarea name="question_text" rows="3" required placeholder="Enter your question here..."></textarea></div>
      <div class="form-group" style="margin-bottom:12px"><label>Marks *</label><input type="number" name="marks" value="1" min="1" required style="max-width:100px"></div>

      <!-- MCQ Options -->
      <div id="optionsSection" style="margin-bottom:12px">
        <label style="font-weight:600;font-size:.85rem;display:block;margin-bottom:8px">Answer Options <span style="color:#888;font-weight:400">(select the correct answer)</span></label>
        <?php for($i=0;$i<4;$i++):?>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;margin-bottom:8px;transition:all .15s" onmouseover="this.style.borderColor='#4361ee'" onmouseout="this.style.borderColor='#e2e8f0'">
          <input type="radio" name="correct_option" value="<?=$i?>" <?=$i==0?'checked':''?> style="width:16px;height:16px;flex-shrink:0;accent-color:#4361ee">
          <span style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;flex-shrink:0"><?=chr(65+$i)?></span>
          <input type="text" name="options[]" placeholder="Type option <?=chr(65+$i)?> here..." style="flex:1;border:none;outline:none;font-size:.9rem;background:transparent">
        </label>
        <?php endfor;?>
        <div style="font-size:.75rem;color:#94a3b8;margin-top:4px"><i class="fas fa-info-circle"></i> Click the radio button next to the correct answer</div>
      </div>

      <!-- True/False Options -->
      <div id="tfSection" style="display:none;margin-bottom:12px">
        <label style="font-weight:600;font-size:.85rem;display:block;margin-bottom:8px">Correct Answer</label>
        <div style="display:flex;gap:12px">
          <label style="display:flex;align-items:center;gap:8px;padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;font-weight:600">
            <input type="radio" name="correct_option" value="0" checked style="accent-color:#2dc653"> ✓ True
          </label>
          <label style="display:flex;align-items:center;gap:8px;padding:10px 20px;border:1.5px solid #e2e8f0;border-radius:10px;cursor:pointer;font-weight:600">
            <input type="radio" name="correct_option" value="1" style="accent-color:#e63946"> ✗ False
          </label>
        </div>
        <input type="hidden" name="options[]" value="True">
        <input type="hidden" name="options[]" value="False">
      </div>

      <div class="form-group" style="margin-bottom:16px"><label>Explanation (shown after submission)</label><textarea name="explanation" rows="2" placeholder="Explain why this is the correct answer..."></textarea></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Question</button>
        <button type="button" onclick="document.getElementById('addQModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleOptions(type) {
  document.getElementById('optionsSection').style.display = type==='MCQ' ? 'block' : 'none';
  document.getElementById('tfSection').style.display = type==='True/False' ? 'block' : 'none';
}
</script>
<?php require_once '../../includes/footer.php'; ?>
