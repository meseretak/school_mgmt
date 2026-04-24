<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin']);
$page_title = 'Parent Management'; $active_page = 'students';
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='add_parent') {
        $fname=trim($_POST['first_name']); $lname=trim($_POST['last_name']);
        $email=trim($_POST['email']); $phone=trim($_POST['phone']??'');
        $rel=$_POST['relationship']??'Guardian';
        $occ=trim($_POST['occupation']??''); $addr=trim($_POST['address']??'');
        $sid=(int)$_POST['student_id']; $is_primary=(int)($_POST['is_primary']??0);

        // Create user account
        $exists=$pdo->prepare("SELECT id FROM users WHERE email=?"); $exists->execute([$email]);
        if ($exists->fetch()) { flash('Email already in use.','error'); header('Location: parents.php'); exit; }
        $pass=password_hash('parent123',PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name,email,password,role,is_active) VALUES (?,?,?,'parent',1)")->execute([$fname.' '.$lname,$email,$pass]);
        $uid=$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO parents (user_id,first_name,last_name,phone,email,relationship,occupation,address) VALUES (?,?,?,?,?,?,?,?)")->execute([$uid,$fname,$lname,$phone,$email,$rel,$occ,$addr]);
        $pid=$pdo->lastInsertId();
        if ($sid) {
            if ($is_primary) $pdo->prepare("UPDATE student_parents SET is_primary=0 WHERE student_id=?")->execute([$sid]);
            $pdo->prepare("INSERT IGNORE INTO student_parents (student_id,parent_id,is_primary) VALUES (?,?,?)")->execute([$sid,$pid,$is_primary]);
        }
        log_activity($pdo,'parent_added',"Parent $fname $lname added");
        flash("Parent account created. Login: $email / parent123");
        header('Location: parents.php'); exit;
    }

    if ($action==='link_parent') {
        $sid=(int)$_POST['student_id']; $pid=(int)$_POST['parent_id'];
        $is_primary=(int)($_POST['is_primary']??0);
        if ($is_primary) $pdo->prepare("UPDATE student_parents SET is_primary=0 WHERE student_id=?")->execute([$sid]);
        $pdo->prepare("INSERT IGNORE INTO student_parents (student_id,parent_id,is_primary) VALUES (?,?,?)")->execute([$sid,$pid,$is_primary]);
        flash('Parent linked to student.'); header('Location: parents.php'); exit;
    }

    if ($action==='unlink') {
        $pdo->prepare("DELETE FROM student_parents WHERE student_id=? AND parent_id=?")->execute([(int)$_POST['student_id'],(int)$_POST['parent_id']]);
        flash('Parent unlinked.'); header('Location: parents.php'); exit;
    }
}

$parents=$pdo->query("SELECT p.*,u.email AS login_email,u.is_active,
    GROUP_CONCAT(CONCAT(s.first_name,' ',s.last_name) ORDER BY sp.is_primary DESC SEPARATOR ', ') AS children,
    COUNT(DISTINCT sp.student_id) AS child_count
    FROM parents p JOIN users u ON p.user_id=u.id
    LEFT JOIN student_parents sp ON sp.parent_id=p.id
    LEFT JOIN students s ON sp.student_id=s.id
    GROUP BY p.id ORDER BY p.first_name")->fetchAll();

$students=$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-user-friends" style="color:var(--primary)"></i> Parent Management</h1><p style="color:var(--muted)">Add parents/guardians and link them to students</p></div>
  <button class="btn btn-primary" onclick="document.getElementById('addParentModal').style.display='flex'"><i class="fas fa-plus"></i> Add Parent</button>
</div>

<!-- Stats -->
<?php
$total_parents = count($parents);
$linked_parents = count(array_filter($parents, fn($p) => $p['child_count'] > 0));
$total_links = array_sum(array_column($parents, 'child_count'));
?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-friends"></i></div><div class="stat-info"><h3><?=$total_parents?></h3><p>Total Parents</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-link"></i></div><div class="stat-info"><h3><?=$linked_parents?></h3><p>Linked to Students</p></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-user-graduate"></i></div><div class="stat-info"><h3><?=$total_links?></h3><p>Total Student Links</p></div></div>
</div>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-users" style="color:var(--primary)"></i> All Parents/Guardians (<?=count($parents)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Login Email</th><th>Linked Children</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($parents as $p):?>
    <tr>
      <td><div style="font-weight:600"><?=e($p['first_name'].' '.$p['last_name'])?></div><?php if($p['occupation']):?><div style="font-size:.75rem;color:var(--muted)"><?=e($p['occupation'])?></div><?php endif;?></td>
      <td><span class="badge badge-info"><?=e($p['relationship'])?></span></td>
      <td><?=e($p['phone']??'—')?></td>
      <td style="font-size:.82rem"><?=e($p['login_email'])?></td>
      <td>
        <?php if($p['child_count']>0): ?>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="background:#4361ee;color:#fff;border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700;flex-shrink:0"><?=$p['child_count']?> child<?=$p['child_count']>1?'ren':''?></span>
          <span style="font-size:.8rem;color:#64748b"><?=e($p['children'])?></span>
        </div>
        <?php else: ?>
        <span style="color:#94a3b8;font-size:.82rem;font-style:italic">No students linked</span>
        <?php endif; ?>
      </td>
      <td><span class="badge badge-<?=$p['is_active']?'success':'danger'?>"><?=$p['is_active']?'Active':'Inactive'?></span></td>
      <td>
        <button onclick="openLink(<?=$p['id']?>,'<?=e(addslashes($p['first_name'].' '.$p['last_name']))?>')" class="btn btn-sm btn-secondary"><i class="fas fa-link"></i> Link Student</button>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$parents):?><tr><td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">No parents added yet.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- Add Parent Modal -->
<div id="addParentModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:560px;max-width:98vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-user-plus" style="color:var(--primary)"></i> Add Parent/Guardian</h3>
      <button onclick="document.getElementById('addParentModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="add_parent">
      <div class="form-grid">
        <div class="form-group"><label>First Name *</label><input name="first_name" required></div>
        <div class="form-group"><label>Last Name *</label><input name="last_name" required></div>
        <div class="form-group"><label>Email (login) *</label><input type="email" name="email" required placeholder="parent@email.com"></div>
        <div class="form-group"><label>Phone</label><input name="phone" placeholder="+251..."></div>
        <div class="form-group"><label>Relationship</label>
          <select name="relationship"><option>Father</option><option>Mother</option><option selected>Guardian</option><option>Other</option></select>
        </div>
        <div class="form-group"><label>Occupation</label><input name="occupation" placeholder="e.g. Engineer"></div>
        <div class="form-group full"><label>Address</label><input name="address"></div>
        <div class="form-group"><label>Link to Student</label>
          <select name="student_id"><option value="">None (link later)</option>
            <?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group"><label>Primary Guardian?</label><select name="is_primary"><option value="0">No</option><option value="1">Yes</option></select></div>
      </div>
      <div style="background:#fff8e1;border-radius:8px;padding:10px 14px;margin:12px 0;font-size:.82rem;color:#666"><i class="fas fa-info-circle" style="color:#f59e0b"></i> Default password: <strong>parent123</strong> — parent should change it after first login.</div>
      <div style="display:flex;gap:10px;margin-top:16px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Parent</button>
        <button type="button" onclick="document.getElementById('addParentModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Link Modal -->
<div id="linkModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:16px;padding:28px;width:480px;max-width:95vw;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-link" style="color:var(--primary)"></i> Link <span id="linkParentName"></span> to Student</h3>
      <button onclick="document.getElementById('linkModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <!-- Search box -->
    <div style="margin-bottom:14px">
      <input type="text" id="studentSearch" placeholder="🔍 Search by name or student code..." oninput="filterStudents(this.value)"
        style="width:100%;padding:10px 14px;border:1.5px solid #e0e0e0;border-radius:10px;font-size:.9rem;outline:none;transition:border-color .2s"
        onfocus="this.style.borderColor='#4361ee'" onblur="this.style.borderColor='#e0e0e0'">
    </div>
    <!-- Student list -->
    <div id="studentList" style="max-height:280px;overflow-y:auto;border:1px solid #f0f0f0;border-radius:10px;margin-bottom:14px">
      <?php foreach($students as $s): ?>
      <div class="student-item" data-name="<?=strtolower(e($s['name']))?>" data-code="<?=strtolower(e($s['student_code']??''))?>"
        onclick="selectStudent(<?=$s['id']?>,'<?=e(addslashes($s['name']))?>')"
        style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f8fafc;display:flex;align-items:center;gap:12px;transition:background .15s"
        onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background=this.classList.contains('selected')?'#eff6ff':'#fff'">
        <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#4361ee,#7209b7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0"><?=strtoupper(substr($s['name'],0,2))?></div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:.88rem;color:#1e293b"><?=e($s['name'])?></div>
          <div style="font-size:.75rem;color:#94a3b8;font-family:monospace"><?=e($s['student_code']??'—')?></div>
        </div>
        <i class="fas fa-check-circle" style="color:#4361ee;display:none" id="check-<?=$s['id']?>"></i>
      </div>
      <?php endforeach; ?>
      <?php if(!$students): ?>
      <div style="padding:30px;text-align:center;color:#94a3b8">No active students found.</div>
      <?php endif; ?>
    </div>
    <form method="POST" id="linkForm">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="link_parent">
      <input type="hidden" name="parent_id" id="linkParentId">
      <input type="hidden" name="student_id" id="selectedStudentId">
      <div style="background:#f8fafc;border-radius:10px;padding:12px 14px;margin-bottom:14px;min-height:44px;display:flex;align-items:center;gap:10px">
        <i class="fas fa-user-graduate" style="color:#4361ee"></i>
        <span id="selectedStudentName" style="font-size:.88rem;color:#64748b;font-style:italic">No student selected — click one above</span>
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label style="font-weight:600;font-size:.85rem;display:block;margin-bottom:6px">Primary Guardian?</label>
        <select name="is_primary" style="width:100%;padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.88rem">
          <option value="0">No — secondary guardian</option>
          <option value="1">Yes — primary guardian</option>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" id="linkSubmitBtn" disabled style="opacity:.5"><i class="fas fa-link"></i> Link Student</button>
        <button type="button" onclick="document.getElementById('linkModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openLink(id, name) {
  document.getElementById('linkParentId').value = id;
  document.getElementById('linkParentName').textContent = name;
  document.getElementById('selectedStudentId').value = '';
  document.getElementById('selectedStudentName').textContent = 'No student selected — click one above';
  document.getElementById('selectedStudentName').style.fontStyle = 'italic';
  document.getElementById('linkSubmitBtn').disabled = true;
  document.getElementById('linkSubmitBtn').style.opacity = '.5';
  document.getElementById('studentSearch').value = '';
  filterStudents('');
  // Uncheck all
  document.querySelectorAll('.student-item').forEach(el => {
    el.style.background = '#fff';
    el.classList.remove('selected');
    const id2 = el.getAttribute('onclick').match(/\d+/)[0];
    const chk = document.getElementById('check-'+id2);
    if (chk) chk.style.display = 'none';
  });
  document.getElementById('linkModal').style.display = 'flex';
  setTimeout(() => document.getElementById('studentSearch').focus(), 100);
}

function selectStudent(id, name) {
  // Unselect all
  document.querySelectorAll('.student-item').forEach(el => {
    el.style.background = '#fff';
    el.classList.remove('selected');
  });
  document.querySelectorAll('[id^="check-"]').forEach(el => el.style.display = 'none');
  // Select this one
  const item = document.querySelector('.student-item[onclick*="selectStudent('+id+',"]');
  if (item) { item.style.background = '#eff6ff'; item.classList.add('selected'); }
  const chk = document.getElementById('check-'+id);
  if (chk) chk.style.display = 'block';
  document.getElementById('selectedStudentId').value = id;
  document.getElementById('selectedStudentName').textContent = name;
  document.getElementById('selectedStudentName').style.fontStyle = 'normal';
  document.getElementById('selectedStudentName').style.color = '#1e293b';
  document.getElementById('selectedStudentName').style.fontWeight = '600';
  document.getElementById('linkSubmitBtn').disabled = false;
  document.getElementById('linkSubmitBtn').style.opacity = '1';
}

function filterStudents(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.student-item').forEach(el => {
    const name = el.getAttribute('data-name') || '';
    const code = el.getAttribute('data-code') || '';
    el.style.display = (!q || name.includes(q) || code.includes(q)) ? 'flex' : 'none';
  });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
