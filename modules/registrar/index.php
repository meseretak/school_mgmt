<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title = 'Registrar'; $active_page = 'registrar';
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='add_year') {
        $label=trim($_POST['label']); $start=$_POST['start_date']; $end=$_POST['end_date'];
        $is_current=(int)($_POST['is_current']??0);
        if ($is_current) $pdo->query("UPDATE academic_years SET is_current=0");
        $pdo->prepare("INSERT INTO academic_years (label,start_date,end_date,is_current) VALUES (?,?,?,?)")->execute([$label,$start,$end,$is_current]);
        flash('Academic year added.'); header('Location: index.php?tab=years'); exit;
    }

    if ($action==='set_current') {
        $pdo->query("UPDATE academic_years SET is_current=0");
        $pdo->prepare("UPDATE academic_years SET is_current=1 WHERE id=?")->execute([(int)$_POST['year_id']]);
        flash('Current year updated.'); header('Location: index.php?tab=years'); exit;
    }

    if ($action==='archive_year') {
        $pdo->prepare("UPDATE academic_years SET is_archived=1 WHERE id=?")->execute([(int)$_POST['year_id']]);
        flash('Year archived.'); header('Location: index.php?tab=years'); exit;
    }

    if ($action==='register') {
        $sid=(int)$_POST['student_id']; $ayid=(int)$_POST['academic_year_id'];
        $sem=$_POST['semester']; $date=$_POST['registration_date']??date('Y-m-d');
        $notes=trim($_POST['notes']??'');
        try {
            $pdo->prepare("INSERT INTO semester_registrations (student_id,academic_year_id,semester,registration_date,registered_by,notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE registration_date=?,registered_by=?,notes=?,status='Registered'")->execute([$sid,$ayid,$sem,$date,$me,$notes,$date,$me,$notes]);
            flash('Student registered for semester.');
        } catch(Exception $e) { flash('Registration failed: '.$e->getMessage(),'error'); }
        header('Location: index.php?tab=registrations&year='.$ayid); exit;
    }

    if ($action==='generate_report') {
        $ayid=(int)$_POST['academic_year_id']; $sem=$_POST['semester'];
        $students_q=$pdo->prepare("SELECT DISTINCT en.student_id FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE cl.academic_year_id=? AND en.status='Enrolled'");
        $students_q->execute([$ayid]); $student_ids=array_column($students_q->fetchAll(),'student_id');
        $pass_pct=get_pass_pct(); $generated=0;
        foreach ($student_ids as $sid) {
            $gd=$pdo->prepare("SELECT AVG(g.marks_obtained/ex.total_marks*100) AS avg_pct, COUNT(DISTINCT cl.id) AS subjects FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id WHERE en.student_id=? AND cl.academic_year_id=?");
            $gd->execute([$sid,$ayid]); $gd=$gd->fetch();
            if (!$gd||!$gd['avg_pct']) continue;
            $overall=round($gd['avg_pct'],2); $subj=$gd['subjects'];
            $passed_q=$pdo->prepare("SELECT COUNT(DISTINCT cl.id) FROM (SELECT cl.id,AVG(g.marks_obtained/ex.total_marks*100) AS avg FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id WHERE en.student_id=? AND cl.academic_year_id=? GROUP BY cl.id HAVING avg>=?) AS p");
            $passed_q->execute([$sid,$ayid,$pass_pct]); $passed=(int)$passed_q->fetchColumn();
            $failed=$subj-$passed;
            $result=$overall>=90?'Distinction':($overall>=75?'Merit':($overall>=$pass_pct?'Pass':'Fail'));
            $scale=$pdo->query("SELECT gsi.* FROM grade_scale_items gsi JOIN grade_scales gs ON gsi.scale_id=gs.id WHERE gs.is_default=1 ORDER BY gsi.min_pct DESC")->fetchAll();
            $gpa=0; foreach($scale as $si){if($overall>=$si['min_pct']){$gpa=$si['gpa_points'];break;}}
            $rank_q=$pdo->prepare("SELECT COUNT(*)+1 FROM (SELECT en2.student_id,AVG(g2.marks_obtained/ex2.total_marks*100) AS avg2 FROM grades g2 JOIN enrollments en2 ON g2.enrollment_id=en2.id JOIN exams ex2 ON g2.exam_id=ex2.id JOIN classes cl2 ON en2.class_id=cl2.id WHERE cl2.academic_year_id=? GROUP BY en2.student_id HAVING avg2>?) AS r");
            $rank_q->execute([$ayid,$overall]); $rank=(int)$rank_q->fetchColumn();
            $pdo->prepare("INSERT INTO grade_reports (student_id,academic_year_id,semester,total_subjects,passed_subjects,failed_subjects,overall_pct,gpa,rank_in_class,total_in_class,result,generated_by,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE total_subjects=?,passed_subjects=?,failed_subjects=?,overall_pct=?,gpa=?,rank_in_class=?,total_in_class=?,result=?,generated_by=?,is_published=1")
                ->execute([$sid,$ayid,$sem,$subj,$passed,$failed,$overall,$gpa,$rank,count($student_ids),$result,$me,$subj,$passed,$failed,$overall,$gpa,$rank,count($student_ids),$result,$me]);
            $generated++;
        }
        flash("Grade reports generated for $generated students.");
        header('Location: index.php?tab=reports&year='.$ayid); exit;
    }

    if ($action==='export_reports') {
        $ayid=(int)$_POST['academic_year_id'];
        $rows=$pdo->prepare("SELECT CONCAT(s.first_name,' ',s.last_name) AS name,s.student_code,ay.label AS year,gr.semester,gr.total_subjects,gr.passed_subjects,gr.failed_subjects,gr.overall_pct,gr.gpa,gr.rank_in_class,gr.total_in_class,gr.result FROM grade_reports gr JOIN students s ON gr.student_id=s.id JOIN academic_years ay ON gr.academic_year_id=ay.id WHERE gr.academic_year_id=? ORDER BY gr.rank_in_class");
        $rows->execute([$ayid]); $rows=$rows->fetchAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="grade_reports_'.$ayid.'.csv"');
        $out=fopen('php://output','w');
        fputcsv($out,['Name','Student Code','Year','Semester','Subjects','Passed','Failed','Average %','GPA','Rank','Total','Result']);
        foreach($rows as $r) fputcsv($out,array_values($r));
        fclose($out); exit;
    }
}

$tab=$_GET['tab']??'years';
$show_archived=(int)($_GET['archived']??0);
$years=$pdo->query("SELECT * FROM academic_years ".($show_archived?'':'WHERE is_archived IS NULL OR is_archived=0')." ORDER BY start_date DESC")->fetchAll();
$year_id=(int)($_GET['year']??($pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn()??0));
if (!$year_id && $years) $year_id=$years[0]['id'];

$registrations=$pdo->prepare("SELECT sr.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,ay.label AS year_label,u.name AS registered_by_name FROM semester_registrations sr JOIN students s ON sr.student_id=s.id JOIN academic_years ay ON sr.academic_year_id=ay.id JOIN users u ON sr.registered_by=u.id WHERE sr.academic_year_id=? ORDER BY sr.registration_date DESC");
$registrations->execute([$year_id]); $registrations=$registrations->fetchAll();

$reports=$pdo->prepare("SELECT gr.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,ay.label AS year_label FROM grade_reports gr JOIN students s ON gr.student_id=s.id JOIN academic_years ay ON gr.academic_year_id=ay.id WHERE gr.academic_year_id=? ORDER BY gr.rank_in_class ASC");
$reports->execute([$year_id]); $reports=$reports->fetchAll();

$students=$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-university" style="color:var(--primary)"></i> Registrar</h1><p style="color:var(--muted)">Academic years, semester registrations, grade reports & archives</p></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #eee;overflow-x:auto">
  <?php foreach(['years'=>'Academic Years','registrations'=>'Registrations','reports'=>'Grade Reports'] as $t=>$lbl):?>
  <a href="?tab=<?=$t?>&year=<?=$year_id?>" style="padding:10px 18px;text-decoration:none;font-weight:600;font-size:.85rem;border-radius:8px 8px 0 0;color:<?=$tab===$t?'var(--primary)':'#888'?>;background:<?=$tab===$t?'#fff':'transparent'?>;border:2px solid <?=$tab===$t?'#eee':'transparent'?>;border-bottom:<?=$tab===$t?'2px solid #fff':'none'?>;margin-bottom:-2px;white-space:nowrap"><?=$lbl?></a>
  <?php endforeach;?>
</div>

<?php if ($tab==='years'): ?>
<!-- Academic Years Management -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <button onclick="document.getElementById('addYearModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Add Academic Year</button>
  <a href="?tab=years&archived=<?=$show_archived?0:1?>" class="btn btn-secondary btn-sm"><i class="fas fa-archive"></i> <?=$show_archived?'Hide':'Show'?> Archived</a>
</div>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-calendar-alt" style="color:var(--primary)"></i> Academic Years (<?=count($years)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Year</th><th>Start</th><th>End</th><th>Status</th><th>Students</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($years as $y):
      $stu_count=$pdo->prepare("SELECT COUNT(DISTINCT en.student_id) FROM enrollments en JOIN classes cl ON en.class_id=cl.id WHERE cl.academic_year_id=?");
      $stu_count->execute([$y['id']]); $stu_count=(int)$stu_count->fetchColumn();
    ?>
    <tr style="<?=($y['is_archived']??0)?'opacity:.6':''?>">
      <td><strong><?=e($y['label'])?></strong><?=$y['is_current']?' <span class="badge badge-success" style="font-size:.7rem">Current</span>':''?><?=($y['is_archived']??0)?' <span class="badge badge-secondary" style="font-size:.7rem">Archived</span>':''?></td>
      <td><?=$y['start_date']?date('M j, Y',strtotime($y['start_date'])):'—'?></td>
      <td><?=$y['end_date']?date('M j, Y',strtotime($y['end_date'])):'—'?></td>
      <td><span class="badge badge-<?=$y['is_current']?'success':'secondary'?>"><?=$y['is_current']?'Active':'Inactive'?></span></td>
      <td><?=$stu_count?> students</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="?tab=reports&year=<?=$y['id']?>" class="btn btn-sm btn-secondary"><i class="fas fa-chart-bar"></i> Reports</a>
        <?php if(!$y['is_current']):?>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="set_current"><input type="hidden" name="year_id" value="<?=$y['id']?>"><button class="btn btn-sm btn-primary" onclick="return confirm('Set as current year?')"><i class="fas fa-check"></i> Set Current</button></form>
        <?php endif;?>
        <?php if(!($y['is_archived']??0)):?>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="archive_year"><input type="hidden" name="year_id" value="<?=$y['id']?>"><button class="btn btn-sm btn-secondary" onclick="return confirm('Archive this year?')"><i class="fas fa-archive"></i> Archive</button></form>
        <?php endif;?>
      </td>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table></div>
</div>

<?php elseif($tab==='registrations'): ?>
<!-- Year selector -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <label style="font-weight:600;font-size:.85rem">Year:</label>
  <?php foreach($years as $y):?>
  <a href="?tab=registrations&year=<?=$y['id']?>" style="padding:6px 14px;border-radius:8px;text-decoration:none;font-size:.82rem;font-weight:600;background:<?=$y['id']==$year_id?'var(--primary)':'#f0f2f8'?>;color:<?=$y['id']==$year_id?'#fff':'#333'?>"><?=e($y['label'])?></a>
  <?php endforeach;?>
</div>
<div style="margin-bottom:16px">
  <button onclick="document.getElementById('regModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Register Student</button>
</div>
<div class="card">
  <div class="card-header"><h2>Semester Registrations (<?=count($registrations)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Year</th><th>Semester</th><th>Reg Date</th><th>Status</th><th>Registered By</th></tr></thead>
    <tbody>
    <?php foreach($registrations as $r):?>
    <tr>
      <td><div style="font-weight:600"><?=e($r['student_name'])?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($r['student_code'])?></div></td>
      <td><?=e($r['year_label'])?></td>
      <td><span class="badge badge-info"><?=e($r['semester'])?></span></td>
      <td><?=date('M j, Y',strtotime($r['registration_date']))?></td>
      <td><span class="badge badge-<?=$r['status']==='Registered'?'success':($r['status']==='Withdrawn'?'danger':'warning')?>"><?=e($r['status'])?></span></td>
      <td style="font-size:.82rem"><?=e($r['registered_by_name'])?></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$registrations):?><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">No registrations for this year.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<?php elseif($tab==='reports'): ?>
<!-- Year selector -->
<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
  <label style="font-weight:600;font-size:.85rem">Year:</label>
  <?php foreach($years as $y):?>
  <a href="?tab=reports&year=<?=$y['id']?>" style="padding:6px 14px;border-radius:8px;text-decoration:none;font-size:.82rem;font-weight:600;background:<?=$y['id']==$year_id?'var(--primary)':'#f0f2f8'?>;color:<?=$y['id']==$year_id?'#fff':'#333'?>"><?=e($y['label'])?></a>
  <?php endforeach;?>
</div>
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
    <input type="hidden" name="action" value="generate_report">
    <input type="hidden" name="academic_year_id" value="<?=$year_id?>">
    <select name="semester" style="padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.88rem"><option>Semester 1</option><option>Semester 2</option><option>Full Year</option></select>
    <button type="submit" class="btn btn-primary" onclick="return confirm('Generate grade reports for all students in this year?')"><i class="fas fa-cog"></i> Generate Reports</button>
  </form>
  <?php if($reports):?>
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
    <input type="hidden" name="action" value="export_reports">
    <input type="hidden" name="academic_year_id" value="<?=$year_id?>">
    <button type="submit" class="btn btn-secondary"><i class="fas fa-download"></i> Export CSV</button>
  </form>
  <?php endif;?>
</div>
<div class="card">
  <div class="card-header"><h2><i class="fas fa-trophy" style="color:var(--warning)"></i> Grade Reports — Ranked (<?=count($reports)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Rank</th><th>Student</th><th>Semester</th><th>Subjects</th><th>Passed</th><th>Failed</th><th>Avg %</th><th>GPA</th><th>Result</th><th>Transcript</th></tr></thead>
    <tbody>
    <?php foreach($reports as $r):?>
    <tr>
      <td><strong style="font-size:1.1rem;color:<?=$r['rank_in_class']<=3?'var(--warning)':'inherit'?>"><?=$r['rank_in_class']?>/<?=$r['total_in_class']?></strong></td>
      <td><div style="font-weight:600"><?=e($r['student_name'])?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($r['student_code'])?></div></td>
      <td><span class="badge badge-info"><?=e($r['semester'])?></span></td>
      <td><?=$r['total_subjects']?></td>
      <td style="color:var(--success);font-weight:600"><?=$r['passed_subjects']?></td>
      <td style="color:<?=$r['failed_subjects']>0?'var(--danger)':'var(--muted)'?>;font-weight:600"><?=$r['failed_subjects']?></td>
      <td><div style="display:flex;align-items:center;gap:8px"><strong><?=$r['overall_pct']?>%</strong><div class="progress" style="width:50px"><div class="progress-bar" style="width:<?=$r['overall_pct']?>%;background:<?=$r['overall_pct']>=get_pass_pct()?'var(--success)':'var(--danger)'?>"></div></div></div></td>
      <td><strong><?=$r['gpa']?></strong></td>
      <td><span class="badge badge-<?=match($r['result']){'Distinction'=>'success','Merit'=>'primary','Pass'=>'success','Fail'=>'danger',default=>'secondary'}?>"><?=e($r['result'])?></span></td>
      <td><a href="<?=BASE_URL?>/modules/students/transcript.php?id=<?=$r['student_id']?>" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-file-alt"></i></a></td>
    </tr>
    <?php endforeach;?>
    <?php if(!$reports):?><tr><td colspan="10" style="text-align:center;padding:30px;color:var(--muted)">No reports yet. Click "Generate Reports" above.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>
<?php endif;?>

<!-- Add Year Modal -->
<div id="addYearModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:420px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-calendar-plus" style="color:var(--primary)"></i> Add Academic Year</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_year">
      <div class="form-group" style="margin-bottom:12px"><label>Label (e.g. 2025-2026) *</label><input name="label" required placeholder="2025-2026"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Start Date *</label><input type="date" name="start_date" required></div>
      <div class="form-group" style="margin-bottom:12px"><label>End Date *</label><input type="date" name="end_date" required></div>
      <div class="form-group" style="margin-bottom:16px"><label>Set as Current Year</label><select name="is_current"><option value="0">No</option><option value="1">Yes</option></select></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Year</button>
        <button type="button" onclick="document.getElementById('addYearModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Register Modal -->
<div id="regModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:460px;max-width:95vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-user-plus" style="color:var(--primary)"></i> Register Student for Semester</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="register">
      <div class="form-group" style="margin-bottom:12px"><label>Student *</label>
        <select name="student_id" required><option value="">Select student...</option>
          <?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Academic Year *</label>
        <select name="academic_year_id" required><?php foreach($years as $y):?><option value="<?=$y['id']?>" <?=$y['id']==$year_id?'selected':''?>><?=e($y['label'])?></option><?php endforeach;?></select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Semester *</label>
        <select name="semester"><option>Semester 1</option><option>Semester 2</option><option>Full Year</option></select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Registration Date</label><input type="date" name="registration_date" value="<?=date('Y-m-d')?>"></div>
      <div class="form-group" style="margin-bottom:16px"><label>Notes</label><input name="notes" placeholder="Optional notes..."></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Register</button>
        <button type="button" onclick="document.getElementById('regModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
