<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student']);
$page_title = 'Timetable'; $active_page = 'timetable';
$me = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

if ($_SERVER['REQUEST_METHOD']==='POST' && is_admin()) {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='save_slot') {
        $class_id=(int)$_POST['class_id']; $day=$_POST['day'];
        $start=$_POST['start_time']; $end=$_POST['end_time'];
        $room=trim($_POST['room']??''); $ayid=(int)$_POST['academic_year_id'];
        // Conflict check: same room, same day, overlapping time
        if ($room) {
            $conflict=$pdo->prepare("SELECT ts.id,co.name AS course,cl.section FROM timetable_slots ts JOIN classes cl ON ts.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE ts.room=? AND ts.day_of_week=? AND ts.academic_year_id=? AND ts.class_id!=? AND ((ts.start_time<? AND ts.end_time>?) OR (ts.start_time<? AND ts.end_time>?) OR (ts.start_time>=? AND ts.end_time<=?))");
            $conflict->execute([$room,$day,$ayid,$class_id,$end,$start,$start,$start,$start,$end]);
            if ($c=$conflict->fetch()) { flash("Room conflict: {$c['course']} §{$c['section']} is in $room at that time.",'error'); header('Location: index.php'); exit; }
        }
        $pdo->prepare("INSERT INTO timetable_slots (class_id,day_of_week,start_time,end_time,room,academic_year_id,created_by) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE end_time=?,room=?")->execute([$class_id,$day,$start,$end,$room,$ayid,$me,$end,$room]);
        flash('Slot saved.'); header('Location: index.php?year='.$ayid); exit;
    }

    if ($action==='delete_slot') {
        $pdo->prepare("DELETE FROM timetable_slots WHERE id=?")->execute([(int)$_POST['slot_id']]);
        flash('Slot removed.'); header('Location: index.php'); exit;
    }
}

$years=$pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$year_id=(int)($_GET['year']??($pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn()??0));
if (!$year_id && $years) $year_id=$years[0]['id'];

// For teacher: show own classes only
if (is_teacher()) {
    $t=get_teacher_record($pdo);
    $classes=$t ? $pdo->prepare("SELECT cl.*,co.name AS course_name,co.code FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? AND cl.academic_year_id=? ORDER BY co.name") : null;
    if ($classes) { $classes->execute([$t['id'],$year_id]); $classes=$classes->fetchAll(); }
    else $classes=[];
} elseif (is_student()) {
    $s=get_student_record($pdo);
    $classes=$s ? $pdo->prepare("SELECT cl.*,co.name AS course_name,co.code FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? AND cl.academic_year_id=? AND en.status='Enrolled' ORDER BY co.name") : null;
    if ($classes) { $classes->execute([$s['id'],$year_id]); $classes=$classes->fetchAll(); }
    else $classes=[];
} else {
    $classes=$pdo->prepare("SELECT cl.*,co.name AS course_name,co.code,CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id WHERE cl.academic_year_id=? ORDER BY co.name");
    $classes->execute([$year_id]); $classes=$classes->fetchAll();
}

// Get all slots for this year
$slots=$pdo->prepare("SELECT ts.*,co.name AS course_name,co.code,cl.section,CONCAT(t.first_name,' ',t.last_name) AS teacher_name FROM timetable_slots ts JOIN classes cl ON ts.class_id=cl.id JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id WHERE ts.academic_year_id=? ORDER BY FIELD(ts.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),ts.start_time");
$slots->execute([$year_id]); $slots=$slots->fetchAll();

// Group by day
$by_day=[]; $days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
foreach ($days as $d) $by_day[$d]=[];
foreach ($slots as $s) $by_day[$s['day_of_week']][]=$s;

// Time slots 7am-6pm
$time_slots=[];
for ($h=7;$h<=18;$h++) { $time_slots[]=sprintf('%02d:00',$h); $time_slots[]=sprintf('%02d:30',$h); }

$colors=['#4361ee','#7209b7','#2dc653','#f4a261','#e63946','#4cc9f0','#f72585','#3a0ca3','#06d6a0','#ffd166'];

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-calendar-week" style="color:var(--primary)"></i> Timetable</h1>
    <p style="color:var(--muted)">Weekly class schedule — <?= e($years[array_search($year_id,array_column($years,'id'))]['label']??'') ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <?php foreach($years as $y):?>
    <a href="?year=<?=$y['id']?>" style="padding:6px 14px;border-radius:8px;text-decoration:none;font-size:.82rem;font-weight:600;background:<?=$y['id']==$year_id?'var(--primary)':'#f0f2f8'?>;color:<?=$y['id']==$year_id?'#fff':'#333'?>"><?=e($y['label'])?></a>
    <?php endforeach;?>
    <?php if(is_admin()):?>
    <button onclick="document.getElementById('addSlotModal').style.display='flex'" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Slot</button>
    <?php endif;?>
  </div>
</div>

<!-- Timetable Grid -->
<div class="card" style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;min-width:900px">
    <thead>
      <tr style="background:#f8f9ff">
        <th style="padding:10px 14px;text-align:left;font-size:.8rem;color:#888;width:80px;border-right:2px solid #eee">TIME</th>
        <?php foreach($days as $d):?>
        <th style="padding:10px 14px;text-align:center;font-size:.85rem;font-weight:700;color:#333;border-right:1px solid #f0f0f0"><?=$d?></th>
        <?php endforeach;?>
      </tr>
    </thead>
    <tbody>
    <?php foreach($time_slots as $ti=>$time):
      if (substr($time,-2)==='30') continue; // Show only hour rows for cleanliness
    ?>
    <tr style="border-bottom:1px solid #f5f5f5">
      <td style="padding:8px 14px;font-size:.78rem;color:#aaa;font-weight:600;border-right:2px solid #eee;white-space:nowrap"><?=date('g:i A',strtotime($time))?></td>
      <?php foreach($days as $d):?>
      <td style="padding:4px;vertical-align:top;border-right:1px solid #f5f5f5;min-height:50px">
        <?php foreach($by_day[$d] as $slot):
          if ($slot['start_time']>=$time && $slot['start_time']<date('H:i',strtotime($time)+3600)):
            $ci=crc32($slot['course_code'])%count($colors);
            $color=$colors[abs($ci)];
        ?>
        <div style="background:<?=$color?>22;border-left:3px solid <?=$color?>;border-radius:6px;padding:6px 8px;margin-bottom:3px;font-size:.75rem">
          <div style="font-weight:700;color:<?=$color?>"><?=e($slot['code'])?> §<?=e($slot['section'])?></div>
          <div style="color:#333;font-size:.72rem"><?=e(mb_substr($slot['course_name'],0,20))?></div>
          <div style="color:#888;font-size:.68rem"><?=date('g:i',strtotime($slot['start_time']))?>–<?=date('g:i A',strtotime($slot['end_time']))?></div>
          <?php if($slot['room']):?><div style="color:#aaa;font-size:.68rem"><i class="fas fa-map-marker-alt"></i> <?=e($slot['room'])?></div><?php endif;?>
          <?php if(is_admin()):?>
          <form method="POST" style="display:inline;margin-top:2px" onsubmit="return confirm('Remove slot?')">
            <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="delete_slot">
            <input type="hidden" name="slot_id" value="<?=$slot['id']?>">
            <button style="background:none;border:none;color:#e63946;cursor:pointer;font-size:.65rem;padding:0"><i class="fas fa-times"></i></button>
          </form>
          <?php endif;?>
        </div>
        <?php endif; endforeach;?>
      </td>
      <?php endforeach;?>
    </tr>
    <?php endforeach;?>
    </tbody>
  </table>
</div>

<!-- Add Slot Modal -->
<?php if(is_admin()):?>
<div id="addSlotModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:480px;max-width:98vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-clock" style="color:var(--primary)"></i> Add Timetable Slot</h3>
      <button onclick="document.getElementById('addSlotModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="save_slot">
      <input type="hidden" name="academic_year_id" value="<?=$year_id?>">
      <div class="form-grid">
        <div class="form-group full"><label>Class *</label>
          <select name="class_id" required>
            <option value="">Select class...</option>
            <?php foreach($classes as $cl):?><option value="<?=$cl['id']?>"><?=e($cl['code'].' — '.$cl['course_name'].' §'.$cl['section'])?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Day *</label>
          <select name="day" required>
            <?php foreach($days as $d):?><option><?=$d?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Room</label><input name="room" placeholder="e.g. Room 101"></div>
        <div class="form-group"><label>Start Time *</label><input type="time" name="start_time" required value="08:00"></div>
        <div class="form-group"><label>End Time *</label><input type="time" name="end_time" required value="09:30"></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Slot</button>
        <button type="button" onclick="document.getElementById('addSlotModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php'; ?>
