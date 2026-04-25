<?php
require_once '../../includes/config.php';
auth_check(['admin','super_admin','accountant']);
// Students see payments on their own dashboard only
$page_title='Payments'; $active_page='payments';

$status    = $_GET['status'] ?? '';
$search    = trim($_GET['search'] ?? '');
$student_id = trim($_GET['student_id'] ?? '');
$year_id   = (int)($_GET['year_id'] ?? 0);
$fee_id    = (int)($_GET['fee_id'] ?? 0);

$sql = "SELECT p.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, ft.name AS fee_name, ay.label AS year FROM payments p JOIN students s ON p.student_id=s.id JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id WHERE 1=1";
$params = [];
if ($status)     { $sql .= " AND p.status=?";                                                    $params[] = $status; }
if ($search)     { $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($student_id) { $sql .= " AND s.student_code=?";                                              $params[] = $student_id; }
if ($year_id)    { $sql .= " AND p.academic_year_id=?";                                          $params[] = $year_id; }
if ($fee_id)     { $sql .= " AND p.fee_type_id=?";                                               $params[] = $fee_id; }
$sql .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $payments = $stmt->fetchAll();

// Filtered totals
$filtered_due  = array_sum(array_column($payments, 'amount_due'));
$filtered_paid = array_sum(array_column($payments, 'amount_paid'));

$totals = $pdo->query("SELECT SUM(amount_due) AS due, SUM(amount_paid) AS paid, COUNT(*) AS total FROM payments")->fetch();
$years    = $pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll();
$fee_types = $pdo->query("SELECT * FROM fee_types WHERE is_active=1 ORDER BY name")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="page-header">
  <div><h1>Payments</h1><p><?= count($payments) ?> record(s)</p></div>
  <div style="display:flex;gap:8px">
    <a href="remind.php" class="btn btn-warning"><i class="fas fa-envelope"></i> Send Reminders</a>
    <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Record Payment</a>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div><div class="stat-info"><h3>$<?= number_format($totals['due'],0) ?></h3><p>Total Due</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3>$<?= number_format($totals['paid'],0) ?></h3><p>Total Collected</p></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3>$<?= number_format($totals['due']-$totals['paid'],0) ?></h3><p>Outstanding</p></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-receipt"></i></div><div class="stat-info"><h3><?= $totals['total'] ?></h3><p>Total Records</p></div></div>
</div>

<div class="card"><div class="card-body">
  <form method="GET" class="search-bar" style="flex-wrap:wrap">
    <input name="student_id" placeholder="Student ID (e.g. EMP-STU-2025-0001)" value="<?= e($student_id) ?>" style="font-family:monospace;min-width:220px">
    <input name="search" placeholder="Search name..." value="<?= e($search) ?>" style="max-width:180px">
    <select name="status">
      <option value="">All Status</option>
      <?php foreach(['Pending','Partial','Paid','Overdue','Waived'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <select name="year_id">
      <option value="">All Years</option>
      <?php foreach ($years as $y): ?>
      <option value="<?= $y['id'] ?>" <?= $year_id==$y['id']?'selected':'' ?>><?= e($y['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="fee_id">
      <option value="">All Fee Types</option>
      <?php foreach ($fee_types as $ft): ?>
      <option value="<?= $ft['id'] ?>" <?= $fee_id==$ft['id']?'selected':'' ?>><?= e($ft['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
    <a href="index.php" class="btn btn-secondary btn-sm">Reset</a>
    <?php if ($student_id): ?>
    <a href="../students/lookup.php?student_id=<?= urlencode($student_id) ?>" class="btn btn-sm btn-secondary"><i class="fas fa-id-card"></i> Full Profile</a>
    <?php endif; ?>
  </form>
  <?php if ($student_id || $status || $year_id || $fee_id): ?>
  <div style="background:#f8f9ff;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:.85rem;display:flex;gap:20px">
    <span>Filtered: <strong><?= count($payments) ?> records</strong></span>
    <span style="color:var(--success)">Paid: <strong>$<?= number_format($filtered_paid,2) ?></strong></span>
    <span style="color:var(--danger)">Outstanding: <strong>$<?= number_format($filtered_due-$filtered_paid,2) ?></strong></span>
  </div>
  <?php endif; ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Fee Type</th><th>Year</th><th>Amount Due</th><th>Amount Paid</th><th>Balance</th><th>Due Date</th><th>Method</th><th>Reference</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($payments as $p):
      $balance = $p['amount_due'] - $p['amount_paid'];
    ?>
    <tr>
      <td><strong><?= e($p['student_name']) ?></strong><br><small style="color:#888"><?= e($p['student_code']??'') ?></small></td>
      <td><?= e($p['fee_name']) ?></td>
      <td><?= e($p['year']??'—') ?></td>
      <td>$<?= number_format($p['amount_due'],2) ?></td>
      <td>$<?= number_format($p['amount_paid'],2) ?></td>
      <td style="color:<?= $balance>0?'var(--danger)':'var(--success)' ?>;font-weight:600">$<?= number_format($balance,2) ?></td>
      <td><?= $p['due_date']?date('M j, Y',strtotime($p['due_date'])):'—' ?></td>
      <td><?= e($p['method']) ?></td>
      <td><?= e($p['reference_no']??'—') ?></td>
      <td><span class="badge badge-<?php $ps_=($p['status']??''); echo $ps_==='Paid'?'success':($ps_==='Pending'?'warning':($ps_==='Overdue'?'danger':($ps_==='Partial'?'info':'secondary'))); ?>"><?= e($p['status']) ?></span></td>
      <td>
        <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary btn-icon"><i class="fas fa-edit"></i></a>
        <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger btn-icon confirm-delete"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="11" style="text-align:center;color:#aaa;padding:30px">No payment records found</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div></div>
<?php require_once '../../includes/footer.php'; ?>