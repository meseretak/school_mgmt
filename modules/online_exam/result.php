<?php
require_once '../../includes/config.php';
auth_check(['student']);
$student=get_student_record($pdo);
if (!$student) { flash('Not found.','error'); header('Location: index.php'); exit; }
$exam_id=(int)($_GET['exam_id']??0);
$exam=$pdo->prepare("SELECT oe.*,co.name AS course_name FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id WHERE oe.id=?");
$exam->execute([$exam_id]); $exam=$exam->fetch();
$attempt=$pdo->prepare("SELECT * FROM online_exam_attempts WHERE exam_id=? AND student_id=?");
$attempt->execute([$exam_id,$student['id']]); $attempt=$attempt->fetch();
if (!$attempt||!in_array($attempt['status'],['Submitted','Graded'])) { header('Location: index.php'); exit; }
$page_title='Result: '.$exam['title'];

$answers=$pdo->prepare("SELECT oea.*,q.question_text,q.question_type,q.marks,q.explanation,opt.option_text AS selected_text FROM online_exam_answers oea JOIN online_exam_questions q ON oea.question_id=q.id LEFT JOIN online_exam_options opt ON oea.selected_option_id=opt.id WHERE oea.attempt_id=? ORDER BY q.sort_order");
$answers->execute([$attempt['id']]); $answers=$answers->fetchAll();

$pass=$attempt['percentage']>=$exam['pass_marks']/$exam['total_marks']*100;
$correct=count(array_filter($answers,fn($a)=>$a['is_correct']===1||$a['is_correct']==='1'));
$wrong=count(array_filter($answers,fn($a)=>$a['is_correct']===0||$a['is_correct']==='0'));
$unanswered=count(array_filter($answers,fn($a)=>$a['is_correct']===null&&!$a['text_answer']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Result — <?=e($exam['title'])?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f4ff;min-height:100vh;padding:24px}
.container{max-width:860px;margin:0 auto}

/* Hero result card */
.result-hero{border-radius:24px;padding:40px;text-align:center;margin-bottom:28px;position:relative;overflow:hidden}
.result-hero.pass{background:linear-gradient(135deg,#0f4c2a,#166534,#15803d)}
.result-hero.fail{background:linear-gradient(135deg,#4c0f0f,#991b1b,#dc2626)}
.result-hero::before{content:'';position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.result-emoji{font-size:4rem;margin-bottom:12px;display:block;animation:bounceIn .6s ease}
@keyframes bounceIn{0%{transform:scale(0);opacity:0}60%{transform:scale(1.2)}100%{transform:scale(1);opacity:1}}
.result-verdict{font-size:1.8rem;font-weight:800;color:#fff;margin-bottom:6px}
.result-exam-name{color:rgba(255,255,255,.75);font-size:.95rem}
.score-grid{display:flex;justify-content:center;gap:20px;margin-top:24px;flex-wrap:wrap}
.score-item{background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border-radius:16px;padding:16px 24px;text-align:center;min-width:100px}
.score-val{font-size:2rem;font-weight:800;color:#fff}
.score-lbl{font-size:.72rem;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.06em;margin-top:2px}

/* Stats row */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px}
.stat-box{background:#fff;border-radius:16px;padding:18px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.stat-box-val{font-size:1.6rem;font-weight:800;margin-bottom:4px}
.stat-box-lbl{font-size:.78rem;color:#64748b;font-weight:600}

/* Review section */
.review-card{background:#fff;border-radius:16px;padding:20px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);border-left:4px solid #e2e8f0;transition:transform .15s}
.review-card:hover{transform:translateX(4px)}
.review-card.correct{border-left-color:#2dc653}
.review-card.wrong{border-left-color:#e63946}
.review-card.manual{border-left-color:#f59e0b}
.q-header{display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap}
.q-badge{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:#fff;flex-shrink:0}
.q-badge.correct{background:linear-gradient(135deg,#2dc653,#16a34a)}
.q-badge.wrong{background:linear-gradient(135deg,#e63946,#dc2626)}
.q-badge.manual{background:linear-gradient(135deg,#f59e0b,#d97706)}
.q-text-review{font-weight:600;font-size:.92rem;color:#1e293b;line-height:1.5;margin-bottom:10px}
.answer-row{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;font-size:.85rem;margin-bottom:4px}
.answer-row.your-answer{background:#f0f4ff;border:1px solid #c7d2fe}
.answer-row.correct-answer{background:#f0fff4;border:1px solid #bbf7d0}
.explanation-box{background:#fff8e1;border-radius:8px;padding:10px 14px;font-size:.82rem;color:#78350f;margin-top:8px;display:flex;gap:8px;align-items:flex-start}

/* Back button */
.back-btn{display:inline-flex;align-items:center;gap:8px;background:#fff;color:#4361ee;border:2px solid #4361ee;border-radius:12px;padding:10px 20px;text-decoration:none;font-weight:700;font-size:.88rem;transition:all .2s;margin-bottom:20px}
.back-btn:hover{background:#4361ee;color:#fff}
</style>
</head>
<body>
<div class="container">
  <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Exams</a>

  <!-- Hero -->
  <div class="result-hero <?=$pass?'pass':'fail'?>">
    <span class="result-emoji"><?=$pass?'🎉':'📚'?></span>
    <div class="result-verdict"><?=$pass?'Congratulations! You Passed!':'Keep Going — You Can Do Better!'?></div>
    <div class="result-exam-name"><?=e($exam['title'])?><?=$exam['course_name']?' · '.e($exam['course_name']):''?></div>
    <div class="score-grid">
      <div class="score-item"><div class="score-val"><?=$attempt['score']?>/<?=$attempt['total_marks']?></div><div class="score-lbl">Score</div></div>
      <div class="score-item"><div class="score-val"><?=$attempt['percentage']?>%</div><div class="score-lbl">Percentage</div></div>
      <div class="score-item"><div class="score-val"><?=e($attempt['grade_letter']??'—')?></div><div class="score-lbl">Grade</div></div>
      <div class="score-item"><div class="score-val"><?=$exam['pass_marks']?></div><div class="score-lbl">Pass Mark</div></div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box"><div class="stat-box-val" style="color:#2dc653"><?=$correct?></div><div class="stat-box-lbl"><i class="fas fa-check-circle" style="color:#2dc653"></i> Correct</div></div>
    <div class="stat-box"><div class="stat-box-val" style="color:#e63946"><?=$wrong?></div><div class="stat-box-lbl"><i class="fas fa-times-circle" style="color:#e63946"></i> Wrong</div></div>
    <div class="stat-box"><div class="stat-box-val" style="color:#f59e0b"><?=$unanswered?></div><div class="stat-box-lbl"><i class="fas fa-minus-circle" style="color:#f59e0b"></i> Unanswered</div></div>
  </div>

  <?php if($exam['show_result_immediately']): ?>
  <!-- Answer Review -->
  <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64748b;margin-bottom:14px"><i class="fas fa-list-check" style="color:#4361ee"></i> Answer Review</div>

  <?php foreach($answers as $i=>$a):
    $status=$a['is_correct']===null?'manual':($a['is_correct']?'correct':'wrong');
  ?>
  <div class="review-card <?=$status?>">
    <div class="q-header">
      <div class="q-badge <?=$status?>"><?=$i+1?></div>
      <span style="font-size:.75rem;font-weight:700;color:<?=$status==='correct'?'#2dc653':($status==='wrong'?'#e63946':'#f59e0b')?>"><?=$status==='correct'?'✓ Correct':($status==='wrong'?'✗ Wrong':'Manual Review')?></span>
      <span style="margin-left:auto;font-size:.75rem;color:#94a3b8"><?=$a['marks_awarded']?>/<?=$a['marks']?> marks</span>
    </div>
    <div class="q-text-review"><?=nl2br(e($a['question_text']))?></div>

    <?php if($a['selected_text']||$a['text_answer']): ?>
    <div class="answer-row your-answer">
      <i class="fas fa-user" style="color:#4361ee;font-size:.8rem"></i>
      <span style="font-weight:600;color:#4361ee;font-size:.8rem">Your answer:</span>
      <span><?=e($a['selected_text']??$a['text_answer']??'')?></span>
    </div>
    <?php else: ?>
    <div class="answer-row" style="background:#fff5f5;border:1px solid #fecaca"><i class="fas fa-ban" style="color:#e63946;font-size:.8rem"></i><span style="color:#e63946;font-size:.82rem">Not answered</span></div>
    <?php endif; ?>

    <?php if($status==='wrong'):
      $correct_opt=$pdo->prepare("SELECT option_text FROM online_exam_options WHERE question_id=? AND is_correct=1 LIMIT 1");
      $correct_opt->execute([$a['question_id']]); $correct_opt=$correct_opt->fetchColumn();
      if($correct_opt): ?>
    <div class="answer-row correct-answer">
      <i class="fas fa-check" style="color:#2dc653;font-size:.8rem"></i>
      <span style="font-weight:600;color:#2dc653;font-size:.8rem">Correct answer:</span>
      <span><?=e($correct_opt)?></span>
    </div>
    <?php endif; endif; ?>

    <?php if($a['explanation']): ?>
    <div class="explanation-box"><i class="fas fa-lightbulb" style="color:#f59e0b;flex-shrink:0;margin-top:1px"></i><span><?=e($a['explanation'])?></span></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
