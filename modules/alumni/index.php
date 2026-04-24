<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title = 'Alumni Management'; $active_page = 'alumni';
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='add_alumni') {
        $sid=(int)$_POST['student_id'];
        $d=$_POST;
        try {
            $pdo->prepare("INSERT INTO alumni (student_id,graduation_year,graduation_date,final_gpa,degree_awarded,current_employer,current_position,current_city,current_country,linkedin_url,personal_email,phone,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE graduation_year=?,graduation_date=?,final_gpa=?,degree_awarded=?,current_employer=?,current_position=?,current_city=?,current_country=?,linkedin_url=?,personal_email=?,phone=?,notes=?")
                ->execute([$sid,$d['graduation_year'],$d['graduation_date']??null,$d['final_gpa']??null,$d['degree_awarded']??null,$d['current_employer']??null,$d['current_position']??null,$d['current_city']??null,$d['current_country']??null,$d['linkedin_url']??null,$d['personal_email']??null,$d['phone']??null,$d['notes']??null,
                $d['graduation_year'],$d['graduation_date']??null,$d['final_gpa']??null,$d['degree_awarded']??null,$d['current_employer']??null,$d['current_position']??null,$d['current_city']??null,$d['current_country']??null,$d['linkedin_url']??null,$d['personal_email']??null,$d['phone']??null,$d['notes']??null]);
            // Update student status to Graduated
            $pdo->prepare("UPDATE students SET status='Graduated' WHERE id=?")->execute([$sid]);
            flash('Alumni record added.');
        } catch(Exception $e) { flash('Error: '.$e->getMessage(),'error'); }
        header('Location: index.php'); exit;
    }

    if ($action==='verify') {
        $pdo->prepare("UPDATE alumni SET is_verified=1 WHERE id=?")->execute([(int)$_POST['alumni_id']]);
        flash('Alumni verified.'); header('Location: index.php'); exit;
    }
}

$search=trim($_GET['q']??''); $filter_year=$_GET['year']??''; $filter_country=$_GET['country']??'';
$sql="SELECT a.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,s.nationality FROM alumni a JOIN students s ON a.student_id=s.id WHERE 1=1";
$params=[];
if ($search) { $sql.=" AND (s.first_name LIKE ? OR s.last_name LIKE ? OR a.current_employer LIKE ? OR a.degree_awarded LIKE ?)"; $p="%$search%"; $params=array_merge($params,[$p,$p,$p,$p]); }
if ($filter_year) { $sql.=" AND a.graduation_year=?"; $params[]=$filter_year; }
if ($filter_country) { $sql.=" AND a.current_country=?"; $params[]=$filter_country; }
$sql.=" ORDER BY a.graduation_year DESC, s.first_name";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $alumni=$stmt->fetchAll();

$years=$pdo->query("SELECT DISTINCT graduation_year FROM alumni ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$countries=$pdo->query("SELECT DISTINCT current_country FROM alumni WHERE current_country IS NOT NULL ORDER BY current_country")->fetchAll(PDO::FETCH_COLUMN);
$graduated_students=$pdo->query("SELECT s.id,s.student_code,CONCAT(s.first_name,' ',s.last_name) AS name FROM students s WHERE s.status='Graduated' AND s.id NOT IN (SELECT student_id FROM alumni) ORDER BY s.first_name")->fetchAll();
$active_students=$pdo->query("SELECT s.id,s.student_code,CONCAT(s.first_name,' ',s.last_name) AS name FROM students s WHERE s.status='Active' ORDER BY s.first_name")->fetchAll();

// Stats
$total=count($alumni); $verified=count(array_filter($alumni,fn($a)=>$a['is_verified']));
$employed=count(array_filter($alumni,fn($a)=>!empty($a['current_employer'])));

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-user-graduate" style="color:var(--primary)"></i> Alumni Management</h1><p style="color:var(--muted)">Track graduates and their career progress</p></div>
  <button onclick="document.getElementById('addModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> Add Alumni</button>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?=$total?></h3><p>Total Alumni</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?=$verified?></h3><p>Verified</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-briefcase"></i></div><div class="stat-info"><h3><?=$employed?></h3><p>Employed</p></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-globe"></i></div><div class="stat-info"><h3><?=count($countries)?></h3><p>Countries</p></div></div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:16px"><div class="card-body">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <input name="q" value="<?=e($search)?>" placeholder="Search name, employer, degree..." style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem;min-width:220px">
    <select name="year" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Years</option>
      <?php foreach($years as $y):?><option value="<?=$y?>" <?=$filter_year==$y?'selected':''?>><?=$y?></option><?php endforeach;?>
    </select>
    <select name="country" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Countries</option>
      <?php foreach($countries as $c):?><option value="<?=e($c)?>" <?=$filter_country===$c?'selected':''?>><?=e($c)?></option><?php endforeach;?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-list" style="color:var(--primary)"></i> Alumni Directory (<?=count($alumni)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Graduate</th><th>Year</th><th>Degree</th><th>Current Position</th><th>Employer</th><th>Location</th><th>Contact</th><th>Verified</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($alumni as $a):?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div class="avatar" style="width:36px;height:36px;font-size:.8rem"><?=strtoupper(substr($a['student_name'],0,2))?></div>
          <div>
            <div style="font-weight:600"><?=e($a['student_name'])?></div>
            <div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($a['student_code'])?></div>
          </div>
        </div>
      </td>
      <td><strong><?=$a['graduation_year']?></strong><?=$a['graduation_date']?'<div style="font-size:.72rem;color:var(--muted)">'.date('M j, Y',strtotime($a['graduation_date'])).'</div>':''?></td>
      <td style="font-size:.83rem"><?=e($a['degree_awarded']??'—')?><?=$a['final_gpa']?'<div style="font-size:.72rem;color:var(--muted)">GPA: '.$a['final_gpa'].'</div>':''?></td>
      <td style="font-size:.83rem"><?=e($a['current_position']??'—')?></td>
      <td style="font-size:.83rem"><?=e($a['current_employer']??'—')?></td>
      <td style="font-size:.82rem"><?=e(implode(', ',array_filter([$a['current_city'],$a['current_country']])))?:('—')?></td>
      <td style="font-size:.78rem">
        <?php if($a['personal_email']):?><div><i class="fas fa-envelope" style="color:var(--primary)"></i> <?=e($a['personal_email'])?></div><?php endif;?>
        <?php if($a['phone']):?><div><i class="fas fa-phone" style="color:var(--success)"></i> <?=e($a['phone'])?></div><?php endif;?>
        <?php if($a['linkedin_url']):?><a href="<?=e($a['linkedin_url'])?>" target="_blank" style="color:#0077b5"><i class="fab fa-linkedin"></i> LinkedIn</a><?php endif;?>
      </td>
      <td style="text-align:center"><?=$a['is_verified']?'<span class="badge badge-success">Verified</span>':'<span class="badge badge-secondary">Unverified</span>'?></td>
      <td>
        <?php if(!$a['is_verified']):?>
        <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="verify"><input type="hidden" name="alumni_id" value="<?=$a['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i> Verify</button></form>
        <?php endif;?>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$alumni):?><tr><td colspan="9" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-user-graduate" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>No alumni records found.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- Add Modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:600px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-user-graduate" style="color:var(--primary)"></i> Add Alumni Record</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_alumni">
      <div class="form-grid">
        <div class="form-group full"><label>Student *</label>
          <select name="student_id" required>
            <option value="">Select student...</option>
            <optgroup label="Graduated Students">
              <?php foreach($graduated_students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
            </optgroup>
            <optgroup label="Active Students (will be marked Graduated)">
              <?php foreach($active_students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
            </optgroup>
          </select>
        </div>
        <div class="form-group"><label>Graduation Year *</label><input type="number" name="graduation_year" required value="<?=date('Y')?>" min="1990" max="<?=date('Y')+1?>"></div>
        <div class="form-group"><label>Graduation Date</label><input type="date" name="graduation_date"></div>
        <div class="form-group"><label>Degree Awarded</label><input name="degree_awarded" placeholder="e.g. Bachelor of Science"></div>
        <div class="form-group"><label>Final GPA</label><input type="number" step="0.01" name="final_gpa" min="0" max="4" placeholder="e.g. 3.75"></div>
        <div class="form-group"><label>Current Employer</label><input name="current_employer" placeholder="Company name"></div>
        <div class="form-group"><label>Current Position</label><input name="current_position" placeholder="Job title"></div>
        <div class="form-group"><label>Current City</label><input name="current_city"></div>
        <div class="form-group"><label>Current Country</label><input name="current_country"></div>
        <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email"></div>
        <div class="form-group"><label>Phone</label><input name="phone"></div>
        <div class="form-group"><label>LinkedIn URL</label><input name="linkedin_url" placeholder="https://linkedin.com/in/..."></div>
        <div class="form-group full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Alumni</button>
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
