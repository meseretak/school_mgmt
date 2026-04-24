<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher']);
$exam_id=(int)($_GET['exam_id']??0);
$exam=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code,cl.section FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id WHERE oe.id=?");
$exam->execute([$exam_id]); $exam=$exam->fetch();
if (!$exam) { flash('Exam not found.','error'); header('Location: index.php'); exit; }

$questions=$pdo->prepare("SELECT q.*,(SELECT COUNT(*) FROM online_exam_options WHERE question_id=q.id) AS opt_count FROM online_exam_questions q WHERE q.exam_id=? ORDER BY q.sort_order");
$questions->execute([$exam_id]); $questions=$questions->fetchAll();
$page_title='Preview: '.$exam['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Exam Preview — <?=e($exam['title'])?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f0f4ff;min-height:100vh;padding:24px}
.container{max-width:800px;margin:0 auto}
.exam-header{background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;border-radius:16px;padding:28px;margin-bottom:24px;box-shadow:0 8px 32px rgba(67,97,238,.3)}
.exam-title{font-size:1.4rem;font-weight:800;margin-bottom:6px}
.exam-meta{display:flex;gap:16px;flex-wrap:wrap;opacity:.85;font-size:.85rem;margin-bottom:16px}
.meta-item{display:flex;align-items:center;gap:6px}
.exam-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px}
.info-box{background:rgba(255,255,255,.15);border-radius:10px;padding:10px 14px;text-align:center;backdrop-filter:blur(10px)}
.info-val{font-size:1.3rem;font-weight:800}
.info-lbl{font-size:.7rem;opacity:.75;text-transform:uppercase;letter-spacing:.05em}

.q-card{background:#fff;border-radius:14px;padding:22px;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,0,0,.07);border-left:4px solid #4361ee}
.q-header{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.q-num{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0}
.q-type{padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.q-type-mcq{background:#eff6ff;color:#2563eb}
.q-type-tf{background:#f0fdf4;color:#16a34a}
.q-type-sa{background:#fdf4ff;color:#9333ea}
.q-marks{background:#fff8e1;color:#d97706;border-radius:20px;padding:3px 10px;font-size:.72rem;font-weight:700;margin-left:auto}
.q-text{font-size:1rem;font-weight:600;color:#1e293b;line-height:1.6;margin-bottom:16px}

.option{display:flex;align-items:center;gap:12px;padding:11px 16px;border:2px solid #e2e8f0;border-radius:10px;margin-bottom:8px;background:#fff}
.option-letter{width:30px;height:30px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:#64748b;flex-shrink:0}
.option-circle{width:18px;height:18px;border-radius:50%;border:2px solid #cbd5e1;flex-shrink:0}
.option-text{font-size:.9rem;color:#334155}
.sa-box{border:2px dashed #e2e8f0;border-radius:10px;padding:16px;color:#94a3b8;font-size:.88rem;min-height:80px;display:flex;align-items:center;justify-content:center}

.toolbar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;border:none;cursor:pointer;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s}
.btn-primary{background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;box-shadow:0 4px 14px rgba(67,97,238,.3)}
.btn-secondary{background:#fff;color:#4361ee;border:2px solid #4361ee}
.btn:hover{transform:translateY(-1px)}
@media print{.toolbar{display:none}body{background:#fff;padding:10px}.q-card{box-shadow:none;border:1px solid #e0e0e0;break-inside:avoid}}
</style>
</head>
<body>
<div class="container">
  <div class="toolbar">
    <a href="questions.php?exam_id=<?=$exam_id?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Questions</a>
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print / PDF</button>
  </div>

  <!-- Exam Header -->
  <div class="exam-header">
    <div style="font-size:.8rem;opacity:.7;margin-bottom:6px;text-transform:uppercase;letter-spacing:.08em"><?= APP_NAME ?> — Official Examination</div>
    <div class="exam-title"><?=e($exam['title'])?></div>
    <div class="exam-meta">
      <?php if($exam['course_name']):?><div class="meta-item"><i class="fas fa-book"></i> <?=e($exam['course_name'])?> <?=$exam['code']?'('.e($exam['code']).')':''?></div><?php endif;?>
      <div class="meta-item"><i class="fas fa-calendar"></i> <?=$exam['start_datetime']?date('M j, Y',strtotime($exam['start_datetime'])):date('M j, Y')?></div>
      <div class="meta-item"><i class="fas fa-clock"></i> <?=$exam['duration_minutes']?> minutes</div>
    </div>
    <div class="exam-info-grid">
      <div class="info-box"><div class="info-val"><?=count($questions)?></div><div class="info-lbl">Questions</div></div>
      <div class="info-box"><div class="info-val"><?=$exam['total_marks']?></div><div class="info-lbl">Total Marks</div></div>
      <div class="info-box"><div class="info-val"><?=$exam['pass_marks']?></div><div class="info-lbl">Pass Marks</div></div>
      <div class="info-box"><div class="info-val"><?=$exam['duration_minutes']?> min</div><div class="info-lbl">Duration</div></div>
    </div>
    <?php if($exam['description']):?>
    <div style="margin-top:14px;background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;font-size:.85rem;line-height:1.6"><?=nl2br(e($exam['description']))?></div>
    <?php endif;?>
  </div>

  <!-- Questions -->
  <?php foreach($questions as $i=>$q):
    $opts=$pdo->prepare("SELECT * FROM online_exam_options WHERE question_id=? ORDER BY sort_order");
    $opts->execute([$q['id']]); $opts=$opts->fetchAll();
    $typeClass=match($q['question_type']){'MCQ'=>'q-type-mcq','True/False'=>'q-type-tf',default=>'q-type-sa'};
  ?>
  <div class="q-card">
    <div class="q-header">
      <div class="q-num"><?=$i+1?></div>
      <span class="q-type <?=$typeClass?>"><?=e($q['question_type'])?></span>
      <span class="q-marks"><i class="fas fa-star" style="font-size:.6rem"></i> <?=$q['marks']?> mark<?=$q['marks']>1?'s':''?></span>
    </div>
    <div class="q-text"><?=nl2br(e($q['question_text']))?></div>

    <?php if($opts): foreach($opts as $j=>$opt): ?>
    <div class="option">
      <div class="option-circle"></div>
      <div class="option-letter"><?=chr(65+$j)?></div>
      <div class="option-text"><?=e($opt['option_text'])?></div>
    </div>
    <?php endforeach; else: ?>
    <div class="sa-box"><i class="fas fa-pen" style="margin-right:8px"></i> Write your answer here...</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if(!$questions): ?>
  <div style="background:#fff;border-radius:14px;padding:50px;text-align:center;color:#94a3b8">
    <i class="fas fa-question-circle" style="font-size:3rem;display:block;margin-bottom:12px;opacity:.3"></i>
    No questions added yet.
  </div>
  <?php endif; ?>
</div>
</body>
</html>
