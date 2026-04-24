<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','teacher']);
$year_id    = (int)($_GET['year_id'] ?? 0);
$branch_id  = (int)($_GET['branch_id'] ?? 0);
$admin_branch = $_SESSION['user']['branch_id'] ?? 0;
if ($admin_branch && !is_super_admin()) $branch_id = $admin_branch;

$year = $pdo->prepare("SELECT * FROM academic_years WHERE id=?");
$year->execute([$year_id]); $year = $year->fetch();
if (!$year) { flash('Year not found.','error'); header('Location: index.php'); exit; }

$bw = $branch_id ? "AND yr.branch_id=$branch_id" : '';
$results = $pdo->query("
    SELECT yr.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code,
        b.name AS branch_name
    FROM year_results yr
    JOIN students s ON yr.student_id=s.id
    LEFT JOIN branches b ON yr.branch_id=b.id
    WHERE yr.academic_year_id=$year_id $bw
    ORDER BY yr.overall_pct DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Year Results â€” <?= e($year['label']) ?></title>
<style>
  body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; color: #333; }
  h1 { font-size: 1.4rem; margin-bottom: 4px; }
  p { color: #888; font-size: .85rem; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; font-size: .85rem; }
  th { background: #4361ee; color: #fff; padding: 10px 12px; text-align: left; }
  td { padding: 9px 12px; border-bottom: 1px solid #eee; }
  tr:nth-child(even) td { background: #f8f9ff; }
  .pass { color: #2dc653; font-weight: 700; }
  .fail { color: #e63946; font-weight: 700; }
  @media print { button { display: none; } }
</style>
</head>
<body>
<button onclick="window.print()" style="margin-bottom:16px;padding:8px 20px;background:#4361ee;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.88rem">
  ðŸ–¨ Print
</button>
<h1>Year-End Results â€” <?= e($year['label']) ?></h1>
<p>Generated on <?= date('F j, Y') ?><?= $branch_id ? ' Â· Branch filtered' : ' Â· All branches' ?></p>
<table>
  <thead>
    <tr><th>#</th><th>Student</th><th>ID</th><th>Branch</th><th>Subjects</th><th>Passed</th><th>Failed</th><th>Overall %</th><th>GPA</th><th>Result</th></tr>
  </thead>
  <tbody>
  <?php foreach ($results as $i => $r): ?>
  <tr>
    <td><?= $i+1 ?></td>
    <td><?= e($r['student_name']) ?></td>
    <td style="font-family:monospace;font-size:.78rem"><?= e($r['student_code']) ?></td>
    <td><?= e($r['branch_name']??'â€”') ?></td>
    <td><?= $r['total_subjects'] ?></td>
    <td class="pass"><?= $r['passed_subjects'] ?></td>
    <td class="fail"><?= $r['failed_subjects'] ?></td>
    <td><strong><?= $r['overall_pct'] ?>%</strong></td>
    <td><?= $r['gpa'] ?></td>
    <td class="<?= in_array($r['result'],['Pass','Merit','Distinction'])?'pass':'fail' ?>"><?= e($r['result']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$results): ?>
  <tr><td colspan="10" style="text-align:center;color:#aaa;padding:30px">No results found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</body>
</html>