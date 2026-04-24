<?php
require_once '../../includes/config.php';
auth_check(['student']);
$student = get_student_record($pdo);
if (!$student) { flash('Student profile not found.','error'); header('Location: index.php'); exit; }

$exam_id=(int)($_GET['exam_id']??0);
$exam=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id WHERE oe.id=? AND oe.status IN('Published','Active')");
$exam->execute([$exam_id]); $exam=$exam->fetch();
if (!$exam) { flash('Exam not available.','error'); header('Location: index.php'); exit; }

$now=time();
if ($exam['start_datetime'] && strtotime($exam['start_datetime'])>$now) { flash('Exam has not started yet.','error'); header('Location: index.php'); exit; }
if ($exam['end_datetime'] && strtotime($exam['end_datetime'])<$now) { flash('Exam window has closed.','error'); header('Location: index.php'); exit; }

$attempt=$pdo->prepare("SELECT * FROM online_exam_attempts WHERE exam_id=? AND student_id=?");
$attempt->execute([$exam_id,$student['id']]); $attempt=$attempt->fetch();

if ($attempt && in_array($attempt['status'],['Submitted','Graded','Timed Out'])) {
    header('Location: result.php?exam_id='.$exam_id); exit;
}
if (!$attempt) {
    $pdo->prepare("INSERT INTO online_exam_attempts (exam_id,student_id,ip_address,total_marks) VALUES (?,?,?,?)")->execute([$exam_id,$student['id'],$_SERVER['REMOTE_ADDR']??'',$exam['total_marks']]);
    $attempt=$pdo->prepare("SELECT * FROM online_exam_attempts WHERE exam_id=? AND student_id=?");
    $attempt->execute([$exam_id,$student['id']]); $attempt=$attempt->fetch();
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    if (isset($_POST['autosave'])) {
        // Save answers without submitting
        $answers=$_POST['answers']??[];
        foreach ($answers as $qid=>$ans) {
            if (is_array($ans)) continue;
            $q=$pdo->prepare("SELECT question_type FROM online_exam_questions WHERE id=?"); $q->execute([(int)$qid]); $q=$q->fetch();
            if (!$q) continue;
            if ($q['question_type']==='Short Answer') {
                $pdo->prepare("INSERT INTO online_exam_answers (attempt_id,question_id,text_answer,marks_awarded) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE text_answer=?")->execute([$attempt['id'],(int)$qid,trim($ans),trim($ans)]);
            } else {
                $pdo->prepare("INSERT INTO online_exam_answers (attempt_id,question_id,selected_option_id,marks_awarded) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE selected_option_id=?")->execute([$attempt['id'],(int)$qid,(int)$ans,(int)$ans]);
            }
        }
        echo json_encode(['ok'=>true]); exit;
    }

    $answers=$_POST['answers']??[];
    $score=0;
    $questions=$pdo->prepare("SELECT * FROM online_exam_questions WHERE exam_id=? ORDER BY sort_order");
    $questions->execute([$exam_id]); $questions=$questions->fetchAll();
    foreach ($questions as $q) {
        $ans=$answers[$q['id']]??null;
        $is_correct=null; $marks=0;
        if (in_array($q['question_type'],['MCQ','True/False'])) {
            if ($ans) {
                $opt=$pdo->prepare("SELECT is_correct FROM online_exam_options WHERE id=? AND question_id=?");
                $opt->execute([(int)$ans,$q['id']]); $opt=$opt->fetch();
                $is_correct=$opt?($opt['is_correct']?1:0):0;
                $marks=$is_correct?$q['marks']:0; $score+=$marks;
            }
            $pdo->prepare("INSERT INTO online_exam_answers (attempt_id,question_id,selected_option_id,is_correct,marks_awarded) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE selected_option_id=?,is_correct=?,marks_awarded=?")->execute([$attempt['id'],$q['id'],$ans?:(null),$is_correct,$marks,$ans?:(null),$is_correct,$marks]);
        } else {
            $text=trim($ans??'');
            $pdo->prepare("INSERT INTO online_exam_answers (attempt_id,question_id,text_answer,marks_awarded) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE text_answer=?")->execute([$attempt['id'],$q['id'],$text,$text]);
        }
    }
    $pct=$exam['total_marks']>0?round($score/$exam['total_marks']*100,2):0;
    $pdo->prepare("UPDATE online_exam_attempts SET status='Submitted',submitted_at=NOW(),score=?,percentage=?,grade_letter=? WHERE id=?")->execute([$score,$pct,grade_letter($pct),$attempt['id']]);
    header('Location: result.php?exam_id='.$exam_id); exit;
}

$questions=$pdo->prepare("SELECT * FROM online_exam_questions WHERE exam_id=? ORDER BY ".($exam['shuffle_questions']?'RAND()':'sort_order'));
$questions->execute([$exam_id]); $questions=$questions->fetchAll();
$elapsed=time()-strtotime($attempt['started_at']);
$remaining=max(0,$exam['duration_minutes']*60-$elapsed);
$page_title='Taking: '.$exam['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($exam['title']) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f172a;min-height:100vh;color:#1e293b}
.exam-layout{display:grid;grid-template-columns:1fr 300px;gap:0;min-height:100vh}
.exam-main{padding:24px;overflow-y:auto}
.exam-sidebar{background:#1e293b;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column;gap:16px}

/* Header bar */
.exam-topbar{background:linear-gradient(135deg,#4361ee,#7209b7);border-radius:16px;padding:16px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 8px 32px rgba(67,97,238,.4)}
.exam-title{color:#fff;font-size:1.1rem;font-weight:800}
.exam-meta{color:rgba(255,255,255,.75);font-size:.82rem;margin-top:2px}
.timer-box{background:rgba(255,255,255,.15);border-radius:12px;padding:10px 18px;text-align:center;backdrop-filter:blur(10px)}
.timer-label{color:rgba(255,255,255,.7);font-size:.7rem;text-transform:uppercase;letter-spacing:.08em}
.timer-val{color:#fff;font-size:1.6rem;font-weight:800;font-family:monospace;letter-spacing:.05em}
.timer-val.warning{color:#fbbf24;animation:pulse 1s infinite}
.timer-val.danger{color:#f87171;animation:pulse .5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

/* Question cards */
.q-card{background:#fff;border-radius:16px;padding:24px;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,0,0,.08);border:2px solid transparent;transition:all .2s;position:relative;overflow:hidden}
.q-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;background:linear-gradient(180deg,#4361ee,#7209b7)}
.q-card.answered{border-color:#4361ee22;background:#f8f9ff}
.q-card.answered::before{background:linear-gradient(180deg,#2dc653,#16a34a)}
.q-num{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;font-size:.8rem;font-weight:800;flex-shrink:0}
.q-type-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.q-type-mcq{background:#eff6ff;color:#2563eb}
.q-type-tf{background:#f0fdf4;color:#16a34a}
.q-type-sa{background:#fdf4ff;color:#9333ea}
.q-marks{background:#fff8e1;color:#d97706;border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:700}
.q-text{font-size:1rem;font-weight:600;line-height:1.6;color:#1e293b;margin:12px 0 16px}

/* Options */
.option-item{display:flex;align-items:center;gap:12px;padding:12px 16px;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;margin-bottom:8px;transition:all .2s;background:#fff}
.option-item:hover{border-color:#4361ee;background:#f0f4ff;transform:translateX(4px)}
.option-item:has(input:checked){border-color:#4361ee;background:linear-gradient(135deg,#eff6ff,#f0f4ff);box-shadow:0 2px 12px rgba(67,97,238,.15)}
.option-letter{width:32px;height:32px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:#64748b;flex-shrink:0;transition:all .2s}
.option-item:has(input:checked) .option-letter{background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff}
.option-text{font-size:.9rem;color:#334155;font-weight:500}
input[type=radio]{display:none}

/* Textarea */
.answer-textarea{width:100%;border:2px solid #e2e8f0;border-radius:12px;padding:14px;font-size:.9rem;font-family:inherit;resize:vertical;min-height:100px;transition:border-color .2s;outline:none;color:#1e293b}
.answer-textarea:focus{border-color:#4361ee;box-shadow:0 0 0 3px rgba(67,97,238,.1)}

/* Sidebar */
.sidebar-title{color:#94a3b8;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.progress-ring-wrap{display:flex;flex-direction:column;align-items:center;gap:8px}
.progress-ring{transform:rotate(-90deg)}
.progress-ring-bg{fill:none;stroke:#334155;stroke-width:8}
.progress-ring-fill{fill:none;stroke:url(#grad);stroke-width:8;stroke-linecap:round;transition:stroke-dashoffset .5s ease}
.progress-text{font-size:1.4rem;font-weight:800;color:#fff;text-align:center}
.progress-sub{font-size:.75rem;color:#94a3b8;text-align:center}

/* Question navigator */
.q-nav-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
.q-nav-btn{width:100%;aspect-ratio:1;border-radius:8px;border:none;cursor:pointer;font-size:.78rem;font-weight:700;transition:all .2s;background:#334155;color:#94a3b8}
.q-nav-btn.answered{background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;box-shadow:0 2px 8px rgba(67,97,238,.4)}
.q-nav-btn.current{ring:2px solid #fbbf24;outline:2px solid #fbbf24}

/* Submit button */
.submit-btn{width:100%;padding:14px;background:linear-gradient(135deg,#2dc653,#16a34a);color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;box-shadow:0 4px 16px rgba(45,198,83,.4)}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(45,198,83,.5)}
.submit-btn:active{transform:scale(.98)}

/* Legend */
.legend{display:flex;gap:10px;flex-wrap:wrap}
.legend-item{display:flex;align-items:center;gap:5px;font-size:.72rem;color:#94a3b8}
.legend-dot{width:10px;height:10px;border-radius:3px}

@media(max-width:768px){.exam-layout{grid-template-columns:1fr}.exam-sidebar{position:fixed;bottom:0;left:0;right:0;height:auto;flex-direction:row;overflow-x:auto;padding:12px 16px;z-index:100}}
</style>
</head>
<body>
<form method="POST" id="examForm">
<input type="hidden" name="csrf_token" value="<?=csrf_token()?>">

<div class="exam-layout">
  <!-- Main content -->
  <div class="exam-main">
    <!-- Top bar -->
    <div class="exam-topbar">
      <div>
        <div class="exam-title"><i class="fas fa-laptop-code"></i> <?=e($exam['title'])?></div>
        <div class="exam-meta"><?=count($questions)?> Questions · <?=$exam['total_marks']?> Marks · Pass: <?=$exam['pass_marks']?><?=$exam['course_name']?' · '.e($exam['course_name']):''?></div>
      </div>
      <div class="timer-box">
        <div class="timer-label"><i class="fas fa-clock"></i> Time Remaining</div>
        <div class="timer-val" id="timerVal"><?=gmdate('H:i:s',$remaining)?></div>
      </div>
    </div>

    <!-- Questions -->
    <?php foreach($questions as $i=>$q):
      $opts=$pdo->prepare("SELECT * FROM online_exam_options WHERE question_id=? ORDER BY sort_order");
      $opts->execute([$q['id']]); $opts=$opts->fetchAll();
      $saved=$pdo->prepare("SELECT * FROM online_exam_answers WHERE attempt_id=? AND question_id=?");
      $saved->execute([$attempt['id'],$q['id']]); $saved=$saved->fetch();
      $is_answered=$saved&&($saved['selected_option_id']||$saved['text_answer']);
    ?>
    <div class="q-card <?=$is_answered?'answered':''?>" id="qcard-<?=$q['id']?>" data-qid="<?=$q['id']?>">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap">
        <span class="q-num"><?=$i+1?></span>
        <span class="q-type-badge q-type-<?=strtolower(str_replace(['/','True','False','Short Answer','MCQ'],['','-tf','-tf','-sa','-mcq'],$q['question_type']))?><?=$q['question_type']==='MCQ'?'q-type-mcq':($q['question_type']==='True/False'?'q-type-tf':'q-type-sa')?>"><?=e($q['question_type'])?></span>
        <span class="q-marks"><i class="fas fa-star" style="font-size:.65rem"></i> <?=$q['marks']?> mark<?=$q['marks']>1?'s':''?></span>
        <?php if($is_answered):?><span style="color:#2dc653;font-size:.75rem;font-weight:700;margin-left:auto"><i class="fas fa-check-circle"></i> Answered</span><?php endif;?>
      </div>
      <div class="q-text"><?=nl2br(e($q['question_text']))?></div>

      <?php if($opts): ?>
      <div>
        <?php foreach($opts as $j=>$opt): ?>
        <label class="option-item" onclick="markAnswered(<?=$q['id']?>,<?=$i?>)">
          <input type="radio" name="answers[<?=$q['id']?>]" value="<?=$opt['id']?>" <?=$saved&&$saved['selected_option_id']==$opt['id']?'checked':''?>>
          <span class="option-letter"><?=chr(65+$j)?></span>
          <span class="option-text"><?=e($opt['option_text'])?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <textarea class="answer-textarea" name="answers[<?=$q['id']?>]" placeholder="Type your answer here..." oninput="markAnswered(<?=$q['id']?>,<?=$i?>)"><?=e($saved['text_answer']??'')?></textarea>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <!-- Bottom submit -->
    <div style="background:#fff;border-radius:16px;padding:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 2px 12px rgba(0,0,0,.08)">
      <div style="font-size:.9rem;color:#64748b"><span id="answeredCountBottom" style="font-weight:800;color:#4361ee;font-size:1.1rem">0</span>/<?=count($questions)?> answered</div>
      <button type="submit" class="submit-btn" style="width:auto;padding:12px 28px" onclick="return confirmSubmit()"><i class="fas fa-paper-plane"></i> Submit Exam</button>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="exam-sidebar">
    <!-- Progress ring -->
    <div class="progress-ring-wrap">
      <div class="sidebar-title">Progress</div>
      <svg width="100" height="100" class="progress-ring">
        <defs><linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#4361ee"/><stop offset="100%" style="stop-color:#7209b7"/></linearGradient></defs>
        <circle class="progress-ring-bg" cx="50" cy="50" r="40"/>
        <circle class="progress-ring-fill" id="progressRing" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2"/>
      </svg>
      <div class="progress-text"><span id="answeredCount">0</span>/<?=count($questions)?></div>
      <div class="progress-sub">Questions Answered</div>
    </div>

    <!-- Question navigator -->
    <div>
      <div class="sidebar-title">Question Navigator</div>
      <div class="q-nav-grid">
        <?php foreach($questions as $i=>$q): ?>
        <button type="button" class="q-nav-btn <?=$saved?'answered':''?>" id="nav-<?=$q['id']?>" onclick="scrollToQ(<?=$q['id']?>)"><?=$i+1?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Legend -->
    <div>
      <div class="sidebar-title">Legend</div>
      <div class="legend">
        <div class="legend-item"><div class="legend-dot" style="background:linear-gradient(135deg,#4361ee,#7209b7)"></div> Answered</div>
        <div class="legend-item"><div class="legend-dot" style="background:#334155"></div> Not answered</div>
      </div>
    </div>

    <!-- Submit -->
    <button type="submit" class="submit-btn" onclick="return confirmSubmit()"><i class="fas fa-paper-plane"></i> Submit Exam</button>
  </div>
</div>
</form>

<script>
let remaining = <?=$remaining?>;
const timerEl = document.getElementById('timerVal');
let answeredSet = new Set();

// Init answered from saved
<?php foreach($questions as $i=>$q):
  $saved2=$pdo->prepare("SELECT * FROM online_exam_answers WHERE attempt_id=? AND question_id=?");
  $saved2->execute([$attempt['id'],$q['id']]); $saved2=$saved2->fetch();
  if ($saved2&&($saved2['selected_option_id']||$saved2['text_answer'])): ?>
answeredSet.add(<?=$q['id']?>);
document.getElementById('nav-<?=$q['id']?>').classList.add('answered');
document.getElementById('qcard-<?=$q['id']?>').classList.add('answered');
<?php endif; endforeach; ?>
updateProgress();

function pad(n){return n.toString().padStart(2,'0');}
function updateTimer(){
  if(remaining<=0){document.getElementById('examForm').submit();return;}
  const h=Math.floor(remaining/3600),m=Math.floor((remaining%3600)/60),s=remaining%60;
  timerEl.textContent=(h>0?pad(h)+':':'')+pad(m)+':'+pad(s);
  timerEl.className='timer-val'+(remaining<=300?' danger':remaining<=600?' warning':'');
  remaining--;
}
setInterval(updateTimer,1000);

function markAnswered(qid,idx){
  answeredSet.add(qid);
  document.getElementById('nav-'+qid).classList.add('answered');
  document.getElementById('qcard-'+qid).classList.add('answered');
  updateProgress();
}

function updateProgress(){
  const count=answeredSet.size;
  const total=<?=count($questions)?>;
  document.getElementById('answeredCount').textContent=count;
  document.getElementById('answeredCountBottom').textContent=count;
  const pct=total>0?count/total:0;
  const circumference=251.2;
  document.getElementById('progressRing').style.strokeDashoffset=circumference*(1-pct);
}

function scrollToQ(qid){
  document.getElementById('qcard-'+qid).scrollIntoView({behavior:'smooth',block:'center'});
}

function confirmSubmit(){
  const unanswered=<?=count($questions)?>-answeredSet.size;
  if(unanswered>0){return confirm('You have '+unanswered+' unanswered question(s). Submit anyway?');}
  return confirm('Submit exam? You cannot change answers after submission.');
}

// Auto-save every 30s
setInterval(function(){
  const fd=new FormData(document.getElementById('examForm'));
  fd.append('autosave','1');
  fetch('',{method:'POST',body:fd}).catch(()=>{});
},30000);
</script>
</body>
</html>
