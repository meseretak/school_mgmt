<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher','student']);
$student_id = (int)($_GET['student_id'] ?? 0);
$year_id    = (int)($_GET['year_id'] ?? 0);
$type       = $_GET['type'] ?? 'completion';

// Students can only view their own certificate
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $student_id) {
        http_response_code(403); die('Access denied.');
    }
}

$student = $pdo->prepare("SELECT s.*, u.email, c.name AS country FROM students s LEFT JOIN users u ON s.user_id=u.id LEFT JOIN countries c ON s.country_id=c.id WHERE s.id=?");
$student->execute([$student_id]); $student = $student->fetch();
if (!$student) die('Student not found.');

$year = null;
if ($year_id) {
    $y = $pdo->prepare("SELECT * FROM academic_years WHERE id=?"); $y->execute([$year_id]); $year = $y->fetch();
}

$result = null;
if ($year_id) {
    $r = $pdo->prepare("SELECT * FROM year_results WHERE student_id=? AND academic_year_id=?");
    $r->execute([$student_id, $year_id]); $result = $r->fetch();
}

// Generate certificate number
$cert_no = 'CERT-'.date('Y').'-'.str_pad($student_id,5,'0',STR_PAD_LEFT).'-'.strtoupper(substr(md5($student_id.$year_id),0,6));

$branch = $pdo->query("SELECT * FROM branches WHERE is_main=1 LIMIT 1")->fetch();
$issued_date = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate â€” <?= e($student['first_name'].' '.$student['last_name']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Georgia', serif; background: #f5f5f5; display: flex; justify-content: center; padding: 30px; }
.no-print { margin-bottom: 20px; display: flex; gap: 10px; }
.no-print button { background: #4361ee; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; }
.no-print a { color: #666; text-decoration: none; padding: 10px; }
.cert {
  width: 900px; min-height: 640px; background: #fff;
  border: 12px solid #4361ee; border-radius: 4px;
  padding: 50px 60px; position: relative; text-align: center;
  box-shadow: 0 8px 40px rgba(0,0,0,.15);
}
.cert::before {
  content: ''; position: absolute; inset: 8px;
  border: 2px solid #c9d4ff; border-radius: 2px; pointer-events: none;
}
.cert-logo { font-size: 3rem; color: #4361ee; margin-bottom: 8px; }
.cert-school { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; letter-spacing: .05em; }
.cert-branch { font-size: .9rem; color: #888; margin-bottom: 24px; }
.cert-title { font-size: 2.2rem; color: #4361ee; font-style: italic; margin-bottom: 6px; letter-spacing: .08em; }
.cert-subtitle { font-size: .95rem; color: #666; margin-bottom: 28px; }
.cert-body { font-size: 1.05rem; color: #444; line-height: 1.9; margin-bottom: 24px; }
.cert-name { font-size: 2rem; font-weight: 700; color: #1a1a2e; border-bottom: 2px solid #4361ee; display: inline-block; padding: 0 20px 4px; margin: 8px 0; }
.cert-result { font-size: 1.3rem; font-weight: 700; color: #2dc653; margin: 12px 0; }
.cert-details { display: flex; justify-content: center; gap: 40px; margin: 20px 0; font-size: .88rem; color: #666; }
.cert-details div { text-align: center; }
.cert-details strong { display: block; font-size: 1.1rem; color: #1a1a2e; }
.cert-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; }
.cert-sig { text-align: center; }
.cert-sig .sig-line { width: 160px; border-top: 1.5px solid #333; margin: 0 auto 6px; }
.cert-sig p { font-size: .82rem; color: #666; }
.cert-no { font-size: .75rem; color: #bbb; position: absolute; bottom: 20px; right: 30px; }
.ribbon { position: absolute; top: 30px; right: 30px; width: 80px; height: 80px; }
.ribbon svg { width: 80px; height: 80px; }
<?php if ($result && $result['result'] === 'Distinction'): ?>
.cert { border-color: #f4a261; }
.cert::before { border-color: #fde8d0; }
.cert-title { color: #e76f51; }
<?php elseif ($result && $result['result'] === 'Merit'): ?>
.cert { border-color: #7209b7; }
.cert::before { border-color: #e8d0f5; }
.cert-title { color: #7209b7; }
<?php endif; ?>
@media print { .no-print { display: none; } body { background: #fff; padding: 0; } .cert { box-shadow: none; } }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()">ðŸ–¨ Print / Save PDF</button>
  <a href="javascript:history.back()">â† Back</a>
</div>

<div class="cert">
  <!-- Decorative ribbon -->
  <div class="ribbon">
    <svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="36" fill="none" stroke="<?= $result&&$result['result']==='Distinction'?'#f4a261':($result&&$result['result']==='Merit'?'#7209b7':'#4361ee') ?>" stroke-width="3"/><text x="40" y="28" text-anchor="middle" font-size="9" fill="<?= $result&&$result['result']==='Distinction'?'#f4a261':($result&&$result['result']==='Merit'?'#7209b7':'#4361ee') ?>" font-family="Georgia">â˜…</text><text x="40" y="44" text-anchor="middle" font-size="8" fill="#666" font-family="Georgia"><?= $result?e($result['result']):'CERT' ?></text><text x="40" y="56" text-anchor="middle" font-size="7" fill="#aaa" font-family="Georgia"><?= date('Y') ?></text></svg>
  </div>

  <div class="cert-logo">ðŸŽ“</div>
  <div class="cert-school"><?= APP_NAME ?></div>
  <div class="cert-branch"><?= e($branch['name']??'') ?><?= $branch&&$branch['address']?' â€” '.e($branch['address']):'' ?></div>

  <div class="cert-title">
    <?php if ($type === 'exam'): ?>Certificate of Examination
    <?php elseif ($result && $result['result'] === 'Distinction'): ?>Certificate of Distinction
    <?php elseif ($result && $result['result'] === 'Merit'): ?>Certificate of Merit
    <?php else: ?>Certificate of Completion<?php endif; ?>
  </div>
  <div class="cert-subtitle">This is to certify that</div>

  <div class="cert-name"><?= e($student['first_name'].' '.$student['last_name']) ?></div>

  <div class="cert-body">
    <?php if ($result): ?>
    has successfully completed the academic year <strong><?= e($year['label']??'') ?></strong>
    with an overall score of <strong><?= $result['overall_pct'] ?>%</strong>
    and has been awarded the grade of <strong><?= grade_letter($result['overall_pct']) ?></strong>.
    <?php else: ?>
    has successfully completed the required coursework and examinations
    at <?= APP_NAME ?>.
    <?php endif; ?>
  </div>

  <?php if ($result): ?>
  <div class="cert-result">
    <?= $result['result'] === 'Distinction' ? 'ðŸ† ' : ($result['result'] === 'Merit' ? 'â­ ' : 'âœ“ ') ?>
    <?= e($result['result']) ?>
  </div>
  <div class="cert-details">
    <div><strong><?= $result['overall_pct'] ?>%</strong>Overall Score</div>
    <div><strong><?= $result['gpa'] ?></strong>GPA</div>
    <div><strong><?= $result['passed_subjects'] ?>/<?= $result['total_subjects'] ?></strong>Subjects Passed</div>
    <?php if ($year): ?><div><strong><?= e($year['label']) ?></strong>Academic Year</div><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="cert-footer">
    <div class="cert-sig">
      <div class="sig-line"></div>
      <p>Principal / Director</p>
      <p><?= e($branch['principal']??APP_NAME) ?></p>
    </div>
    <div style="text-align:center">
      <div style="font-size:3rem">ðŸ«</div>
      <div style="font-size:.8rem;color:#aaa">Official Seal</div>
    </div>
    <div class="cert-sig">
      <div class="sig-line"></div>
      <p>Date of Issue</p>
      <p><?= $issued_date ?></p>
    </div>
  </div>

  <div class="cert-no">Certificate No: <?= $cert_no ?></div>
</div>
</body>
</html>