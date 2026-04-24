<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student']);
$page_title = 'Document Management'; $active_page = 'documents';
$me = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$student_self = get_student_record($pdo);

// Upload document
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check(); $action=$_POST['action']??'';

    if ($action==='upload') {
        $sid=is_student()?$student_self['id']:(int)$_POST['student_id'];
        $type=$_POST['document_type']; $title=trim($_POST['title']);
        $notes=trim($_POST['notes']??'');

        if (empty($_FILES['document']['name'])) { flash('Please select a file.','error'); header('Location: index.php'); exit; }

        $file=$_FILES['document'];
        $allowed=['pdf','jpg','jpeg','png','doc','docx'];
        $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,$allowed)) { flash('File type not allowed. Use PDF, JPG, PNG, DOC.','error'); header('Location: index.php'); exit; }
        if ($file['size']>10*1024*1024) { flash('File too large. Max 10MB.','error'); header('Location: index.php'); exit; }

        $upload_dir='../../uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir,0755,true);
        $filename='doc_'.$sid.'_'.time().'_'.uniqid().'.'.$ext;
        $path=$upload_dir.$filename;

        if (move_uploaded_file($file['tmp_name'],$path)) {
            $pdo->prepare("INSERT INTO student_documents (student_id,document_type,title,file_path,file_size,uploaded_by,notes) VALUES (?,?,?,?,?,?,?)")
                ->execute([$sid,$type,$title,'uploads/documents/'.$filename,$file['size'],$me,$notes]);
            flash('Document uploaded successfully.');
        } else { flash('Upload failed. Check server permissions.','error'); }
        header('Location: index.php'.($sid?'?student_id='.$sid:'')); exit;
    }

    if ($action==='verify' && is_admin()) {
        $pdo->prepare("UPDATE student_documents SET is_verified=1,verified_by=? WHERE id=?")->execute([$me,(int)$_POST['doc_id']]);
        flash('Document verified.'); header('Location: index.php'); exit;
    }

    if ($action==='delete') {
        $doc=$pdo->prepare("SELECT * FROM student_documents WHERE id=?"); $doc->execute([(int)$_POST['doc_id']]); $doc=$doc->fetch();
        if ($doc && (is_admin()||$doc['uploaded_by']==$me)) {
            @unlink('../../'.$doc['file_path']);
            $pdo->prepare("DELETE FROM student_documents WHERE id=?")->execute([$doc['id']]);
            flash('Document deleted.');
        }
        header('Location: index.php'); exit;
    }
}

$filter_student=is_student()?$student_self['id']:(int)($_GET['student_id']??0);
$filter_type=$_GET['type']??'';

$sql="SELECT sd.*,CONCAT(s.first_name,' ',s.last_name) AS student_name,s.student_code,u.name AS uploaded_by_name FROM student_documents sd JOIN students s ON sd.student_id=s.id JOIN users u ON sd.uploaded_by=u.id WHERE 1=1";
$params=[];
if ($filter_student) { $sql.=" AND sd.student_id=?"; $params[]=$filter_student; }
if ($filter_type) { $sql.=" AND sd.document_type=?"; $params[]=$filter_type; }
$sql.=" ORDER BY sd.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $docs=$stmt->fetchAll();

$students=is_student()?[]:$pdo->query("SELECT id,student_code,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
$doc_types=['ID Card','Birth Certificate','Passport','Transcript','Medical','Photo','Other'];

require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="fas fa-folder-open" style="color:var(--primary)"></i> Document Management</h1><p style="color:var(--muted)">Upload and manage student documents securely</p></div>
  <button onclick="document.getElementById('uploadModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Document</button>
</div>

<!-- Filters -->
<?php if(!is_student()):?>
<div class="card" style="margin-bottom:16px"><div class="card-body">
  <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <select name="student_id" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Students</option>
      <?php foreach($students as $s):?><option value="<?=$s['id']?>" <?=$filter_student==$s['id']?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?>
    </select>
    <select name="type" onchange="this.form.submit()" style="padding:7px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:.85rem">
      <option value="">All Types</option>
      <?php foreach($doc_types as $t):?><option value="<?=$t?>" <?=$filter_type===$t?'selected':''?>><?=$t?></option><?php endforeach;?>
    </select>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
  </form>
</div></div>
<?php endif;?>

<div class="card">
  <div class="card-header"><h2><i class="fas fa-file-alt" style="color:var(--primary)"></i> Documents (<?=count($docs)?>)</h2></div>
  <div class="table-wrap"><table>
    <thead><tr>
      <?php if(!is_student()):?><th>Student</th><?php endif;?>
      <th>Document</th><th>Type</th><th>Size</th><th>Uploaded By</th><th>Date</th><th>Verified</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach($docs as $d):
      $ext=strtolower(pathinfo($d['file_path'],PATHINFO_EXTENSION));
      $icon=match($ext){'pdf'=>'fas fa-file-pdf','jpg','jpeg','png'=>'fas fa-file-image','doc','docx'=>'fas fa-file-word',default=>'fas fa-file'};
      $icon_color=match($ext){'pdf'=>'#e63946','jpg','jpeg','png'=>'#4361ee','doc','docx'=>'#2563eb',default=>'#888'};
    ?>
    <tr>
      <?php if(!is_student()):?>
      <td><div style="font-weight:600"><?=e($d['student_name'])?></div><div style="font-size:.75rem;font-family:monospace;color:var(--muted)"><?=e($d['student_code'])?></div></td>
      <?php endif;?>
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <i class="<?=$icon?>" style="font-size:1.4rem;color:<?=$icon_color?>;flex-shrink:0"></i>
          <div>
            <div style="font-weight:600;font-size:.88rem"><?=e($d['title'])?></div>
            <?php if($d['notes']):?><div style="font-size:.72rem;color:var(--muted)"><?=e(mb_substr($d['notes'],0,50))?></div><?php endif;?>
          </div>
        </div>
      </td>
      <td><span class="badge badge-info"><?=e($d['document_type'])?></span></td>
      <td style="font-size:.82rem"><?=$d['file_size']?number_format($d['file_size']/1024,1).' KB':'—'?></td>
      <td style="font-size:.82rem"><?=e($d['uploaded_by_name'])?></td>
      <td style="font-size:.82rem"><?=date('M j, Y',strtotime($d['created_at']))?></td>
      <td><?=$d['is_verified']?'<span class="badge badge-success"><i class="fas fa-check"></i> Verified</span>':'<span class="badge badge-secondary">Pending</span>'?></td>
      <td>
        <div style="display:flex;gap:4px">
          <a href="<?=BASE_URL?>/<?=e($d['file_path'])?>" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a>
          <a href="<?=BASE_URL?>/<?=e($d['file_path'])?>" download class="btn btn-sm btn-secondary"><i class="fas fa-download"></i></a>
          <?php if(is_admin()&&!$d['is_verified']):?>
          <form method="POST" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="verify"><input type="hidden" name="doc_id" value="<?=$d['id']?>"><button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button></form>
          <?php endif;?>
          <?php if(is_admin()||$d['uploaded_by']==$me):?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete document?')"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="doc_id" value="<?=$d['id']?>"><button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form>
          <?php endif;?>
        </div>
      </td>
    </tr>
    <?php endforeach;?>
    <?php if(!$docs):?><tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)"><i class="fas fa-folder-open" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3"></i>No documents found.</td></tr><?php endif;?>
    </tbody>
  </table></div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;width:500px;max-width:98vw">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3><i class="fas fa-upload" style="color:var(--primary)"></i> Upload Document</h3>
      <button onclick="document.getElementById('uploadModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="upload">
      <?php if(!is_student()):?>
      <div class="form-group" style="margin-bottom:12px"><label>Student *</label>
        <select name="student_id" required><option value="">Select student...</option>
          <?php foreach($students as $s):?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_code'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <?php endif;?>
      <div class="form-group" style="margin-bottom:12px"><label>Document Type *</label>
        <select name="document_type" required><?php foreach($doc_types as $t):?><option><?=$t?></option><?php endforeach;?></select>
      </div>
      <div class="form-group" style="margin-bottom:12px"><label>Title *</label><input name="title" required placeholder="e.g. National ID Card"></div>
      <div class="form-group" style="margin-bottom:12px">
        <label>File * <span style="color:#888;font-size:.78rem">(PDF, JPG, PNG, DOC — max 10MB)</span></label>
        <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%;padding:8px;border:1.5px solid #e0e0e0;border-radius:8px">
      </div>
      <div class="form-group" style="margin-bottom:16px"><label>Notes</label><input name="notes" placeholder="Optional notes..."></div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
        <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
