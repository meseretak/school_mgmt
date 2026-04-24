<?php
require_once '../../includes/config.php';
auth_check();
$page_title = 'Academic Calendar'; $active_page = 'calendar';
$role = $_SESSION['user']['role'];
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST' && is_admin()) {
    csrf_check(); $action=$_POST['action']??'';
    if ($action==='add_event') {
        $pdo->prepare("INSERT INTO calendar_events (title,description,event_date,end_date,event_type,audience,created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([trim($_POST['title']),trim($_POST['description']??''),$_POST['event_date'],$_POST['end_date']??null,$_POST['event_type']??'General',$_POST['audience']??'all',$me]);
        flash('Event added.'); header('Location: index.php'); exit;
    }
    if ($action==='delete_event') {
        $pdo->prepare("DELETE FROM calendar_events WHERE id=?")->execute([(int)$_POST['event_id']]);
        flash('Event deleted.'); header('Location: index.php'); exit;
    }
}

// Get events for current month view
$month=(int)($_GET['m']??date('n'));
$year=(int)($_GET['y']??date('Y'));
if ($month<1){$month=12;$year--;} if ($month>12){$month=1;$year++;}
$prev_m=$month-1; $prev_y=$year; if($prev_m<1){$prev_m=12;$prev_y--;}
$next_m=$month+1; $next_y=$year; if($next_m>12){$next_m=1;$next_y++;}

$events=$pdo->prepare("SELECT * FROM calendar_events WHERE YEAR(event_date)=? AND MONTH(event_date)=? ORDER BY event_date");
$events->execute([$year,$month]); $events=$events->fetchAll();

// Also get upcoming exams and assignments as events
$exams=$pdo->query("SELECT title,'Exam' AS event_type,exam_date AS event_date,'#e63946' AS color FROM exams WHERE exam_date >= CURDATE() ORDER BY exam_date LIMIT 20")->fetchAll();
$assignments=$pdo->query("SELECT title,'Assignment' AS event_type,due_date AS event_date,'#f4a261' AS color FROM assignments WHERE due_date >= CURDATE() AND status='Published' ORDER BY due_date LIMIT 20")->fetchAll();

// Group events by day
$by_day=[];
foreach ($events as $e) { $by_day[date('j',strtotime($e['event_date']))][]=$e; }
foreach ($exams as $e) { if(date('n',strtotime($e['event_date']))==$month&&date('Y',strtotime($e['event_date']))==$year) $by_day[date('j',strtotime($e['event_date']))][]=$e; }
foreach ($assignments as $e) { if($e['event_date']&&date('n',strtotime($e['event_date']))==$month&&date('Y',strtotime($e['event_date']))==$year) $by_day[date('j',strtotime($e['event_date']))][]=$e; }

$days_in_month=cal_days_in_month(CAL_GREGORIAN,$month,$year);
$first_dow=date('w',mktime(0,0,0,$month,1,$year)); // 0=Sun

$type_colors=['Holiday'=>'#2dc653','Exam'=>'#e63946','Assignment'=>'#f4a261','Meeting'=>'#7209b7','Event'=>'#4361ee','General'=>'#4cc9f0'];

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-calendar-alt" style="color:var(--primary)"></i> Academic Calendar</h1></div>
  <div style="display:flex;gap:8px;align-items:center">
    <?php if(is_admin()):?><button class="btn btn-primary btn-sm" onclick="document.getElementById('addEventModal').style.display='flex'"><i class="fas fa-plus"></i> Add Event</button><?php endif;?>
  </div>
</div>

<!-- Month navigation -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <a href="?m=<?=$prev_m?>&y=<?=$prev_y?>" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-left"></i></a>
  <h2 style="font-size:1.2rem;font-weight:700"><?=date('F Y',mktime(0,0,0,$month,1,$year))?></h2>
  <a href="?m=<?=$next_m?>&y=<?=$next_y?>" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-right"></i></a>
</div>

<!-- Calendar grid -->
<div class="card" style="margin-bottom:24px">
  <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:2px solid #f0f0f0">
    <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d):?>
    <div style="padding:10px;text-align:center;font-weight:700;font-size:.8rem;color:#888"><?=$d?></div>
    <?php endforeach;?>
  </div>
  <div style="display:grid;grid-template-columns:repeat(7,1fr)">
    <?php
    $today=date('j'); $today_m=date('n'); $today_y=date('Y');
    // Empty cells before first day
    for($i=0;$i<$first_dow;$i++): ?>
    <div style="min-height:90px;border:1px solid #f5f5f5;background:#fafafa"></div>
    <?php endfor;
    for($d=1;$d<=$days_in_month;$d++):
      $is_today=$d==$today&&$month==$today_m&&$year==$today_y;
      $day_events=$by_day[$d]??[];
    ?>
    <div style="min-height:90px;border:1px solid #f5f5f5;padding:6px;background:<?=$is_today?'#eff6ff':'#fff'?>;position:relative">
      <div style="font-weight:<?=$is_today?'800':'600'?>;font-size:.85rem;color:<?=$is_today?'#4361ee':'#333'?>;margin-bottom:4px;<?=$is_today?'background:#4361ee;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;':''?>"><?=$d?></div>
      <?php foreach(array_slice($day_events,0,3) as $ev):
        $color=$type_colors[$ev['event_type']]??'#4361ee';
      ?>
      <div style="background:<?=$color?>22;border-left:3px solid <?=$color?>;padding:2px 5px;border-radius:3px;font-size:.68rem;font-weight:600;color:<?=$color?>;margin-bottom:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="<?=e($ev['title'])?>"><?=e(mb_substr($ev['title'],0,18))?></div>
      <?php endforeach;?>
      <?php if(count($day_events)>3):?><div style="font-size:.65rem;color:#aaa">+<?=count($day_events)-3?> more</div><?php endif;?>
    </div>
    <?php endfor;
    // Fill remaining cells
    $total_cells=$first_dow+$days_in_month;
    $remaining=(7-($total_cells%7))%7;
    for($i=0;$i<$remaining;$i++):?>
    <div style="min-height:90px;border:1px solid #f5f5f5;background:#fafafa"></div>
    <?php endfor;?>
  </div>
</div>

<!-- Upcoming events list -->
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> This Month's Events</h2></div>
    <div style="max-height:300px;overflow-y:auto">
    <?php foreach($events as $ev):
      $color=$type_colors[$ev['event_type']]??'#4361ee';
    ?>
    <div style="padding:10px 16px;border-bottom:1px solid #f0f0f0;display:flex;gap:12px;align-items:flex-start">
      <div style="width:4px;background:<?=$color?>;border-radius:2px;align-self:stretch;flex-shrink:0"></div>
      <div style="flex:1">
        <div style="font-weight:600;font-size:.88rem"><?=e($ev['title'])?></div>
        <div style="font-size:.75rem;color:var(--muted)"><?=date('M j',strtotime($ev['event_date']))?><?=$ev['end_date']?' – '.date('M j',strtotime($ev['end_date'])):''?> · <span style="color:<?=$color?>"><?=e($ev['event_type'])?></span></div>
        <?php if($ev['description']):?><div style="font-size:.78rem;color:#666;margin-top:2px"><?=e(mb_substr($ev['description'],0,80))?></div><?php endif;?>
      </div>
      <?php if(is_admin()):?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="delete_event">
        <input type="hidden" name="event_id" value="<?=$ev['id']?>">
        <button class="btn btn-sm btn-danger btn-icon"><i class="fas fa-trash"></i></button>
      </form>
      <?php endif;?>
    </div>
    <?php endforeach;?>
    <?php if(!$events):?><div style="padding:30px;text-align:center;color:var(--muted)">No events this month.</div><?php endif;?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2><i class="fas fa-clock" style="color:var(--warning)"></i> Upcoming Exams & Deadlines</h2></div>
    <div style="max-height:300px;overflow-y:auto">
    <?php
    $upcoming=$pdo->query("SELECT title,'Exam' AS type,exam_date AS date,'#e63946' AS color FROM exams WHERE exam_date>=CURDATE() UNION ALL SELECT title,'Assignment Due' AS type,due_date AS date,'#f4a261' AS color FROM assignments WHERE due_date>=CURDATE() AND status='Published' ORDER BY date LIMIT 15")->fetchAll();
    foreach($upcoming as $u):?>
    <div style="padding:10px 16px;border-bottom:1px solid #f0f0f0;display:flex;gap:12px;align-items:center">
      <div style="background:<?=$u['color']?>22;color:<?=$u['color']?>;border-radius:8px;padding:6px 10px;font-size:.75rem;font-weight:700;white-space:nowrap"><?=date('M j',strtotime($u['date']))?></div>
      <div>
        <div style="font-weight:600;font-size:.85rem"><?=e($u['title'])?></div>
        <div style="font-size:.75rem;color:var(--muted)"><?=e($u['type'])?></div>
      </div>
    </div>
    <?php endforeach;?>
    <?php if(!$upcoming):?><div style="padding:30px;text-align:center;color:var(--muted)">Nothing upcoming.</div><?php endif;?>
    </div>
  </div>
</div>

<!-- Add Event Modal -->
<?php if(is_admin()):?>
<div id="addEventModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:480px;max-width:98vw">
    <h3 style="margin-bottom:16px"><i class="fas fa-calendar-plus" style="color:var(--primary)"></i> Add Calendar Event</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_event">
      <div class="form-grid">
        <div class="form-group full"><label>Title *</label><input name="title" required placeholder="Event title"></div>
        <div class="form-group"><label>Start Date *</label><input type="date" name="event_date" required value="<?=date('Y-m-d')?>"></div>
        <div class="form-group"><label>End Date</label><input type="date" name="end_date"></div>
        <div class="form-group"><label>Type</label>
          <select name="event_type">
            <?php foreach(['General','Holiday','Exam','Meeting','Event'] as $t):?><option><?=$t?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Audience</label>
          <select name="audience"><option value="all">Everyone</option><option value="students">Students</option><option value="teachers">Teachers</option><option value="staff">Staff</option></select>
        </div>
        <div class="form-group full"><label>Description</label><textarea name="description" rows="2" placeholder="Optional details..."></textarea></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Event</button>
        <button type="button" onclick="document.getElementById('addEventModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif;?>
<?php require_once '../../includes/footer.php'; ?>
