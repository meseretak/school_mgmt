<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher']);
$page_title = 'Exam Results'; $active_page = 'online_exam';
$exam_id=(int)($_GET['exam_id']??0);
$exam=$pdo->prepare("SELECT oe.*,co.name AS course_name,co.code,cl.section FROM online_exams oe LEFT JOIN classes cl ON oe.class_id=cl.id LEFT JOIN courses co ON cl.course_id=co.id WHERE oe.id=?");
$exam->execute([$exam_id]); $exam=$exam->fetch();
if (!$exam) { flash('Exam not found.','error'); header('Location: index.php'); exit; }

$attempts=$pdo->prepare("SELECT oea.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code FROM online_exam_attempts oea JOIN students s ON oea.student_id=s.id WHERE oea.exam_id=? ORDER BY oea.percentage DESC");
$attempts->execute([$exam_id]); $attempts=$attempts->fetchAll();

$total=count($attempts);
$submitted=count(array_filter($attempts,fn($a)=>in_array($a['status'],['Submitted','Graded'])));
$passed=count(array_filter($attempts,fn($a)=>$a['percentage']>=$exam['pass_marks']/$exam['total_marks']*100));
$avg=$total>0?round(array_sum(array_column($attempts,'percentage'))/$total,1):0;

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div>
    <h1><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Exam Results</h1>
    <p style="color:var(--muted)"><?=e($exam['title'])?> · <?=$exam['course_name']?e($exam['code'].' §'.$exam['section']):'All students'?></p>
  </div>
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?=$total?></h3><p>Attempted</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-paper-plane"></i></div><div class="stat-info"><h3><?=$submitted?></h3><p>Submitted</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?=$passed?></h3><p>Passed</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-times-circle"></i></div><div class="stat-info"><h3><?=$submitted-$passed?></h3><p>Failed</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-percentage"></i></div><div class="stat-info"><h3><?=$avg?>%</h3><p>Class Average</p></div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-list-ol" style="color:var(--primary)"></i> Student Results — Ranked</h2>
    <?php if($attempts):?>
    <a href="export.php?exam_id=<?=$exam_id?>" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Export CSV</a>
    <?php endif;?>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Rank</th><th>Student</th><th>Score</th><th>Percentage</th><th>Grade</th><th>Status</th><th>Time Taken</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($attempts as $i=>$a):
      $pass=$a['percentage']>=$exam['pass_marks']/$exam['total_marks']*100;
      $duration=$a['submitted_at']&&$a['started_at']?round((strtotime($a['submitted_at'])-strtotime($a['started_at']))/60).'min':'—';
    ?>
    <tr>
      <td>
        <strong style="font-size:1.1rem;color:<?=$i<3?'var(--warning)':'inherit'?>"><?=$i+1?></strong>
        <?php if($i===0):?> 🥇<?php elseif($i===1):?> 🥈<?php elseif($i===2):?> 🥉<?php endif;?>
      </td>
      <td>
        <div style="font-weight:600"><?=e($a['student_name'])?></div>
        <div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($a['student_code'])?></div>
      </td>
      <td style="font-weight:700"><?=$a['score']?>/<?=$a['total_marks']?></td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <strong style="color:<?=$pass?'var(--success)':'var(--danger)'?>"><?=$a['percentage']?>%</strong>
          <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?=$a['percentage']?>%;background:<?=$pass?'var(--success)':'var(--danger)'?>"></div></div>
        </div>
      </td>
      <td style="font-weight:700;color:<?=$pass?'var(--success)':'var(--danger)'?>"><?=e($a['grade_letter']??'—')?></td>
      <td><span class="badge badge-<?=match($a['status']){'Submitted'=>'success','Graded'=>'primary','In Progress'=>'warning','Timed Out'=>'danger',default=>'secondary'}?>"><?=e($a['status'])?></span></td>
      <td style="font-size:.82rem;color:var(--muted)"><?=$duration?></td>
      <td>
        <a href="review.php?attempt_id=<?=$a['id']?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Review</a>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$attempts):?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">No attempts yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php require_once '../../includes/footer.php'; ?>
