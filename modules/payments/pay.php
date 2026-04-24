<?php
require_once '../../includes/config.php';
auth_check(['student','admin']);
$page_title = 'Make Payment'; $active_page = 'payments';
$payment_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, s.first_name, s.last_name, s.phone, u.email AS student_email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();
if (!$payment) { flash('Payment not found.','error'); header('Location: index.php'); exit; }
if (is_student()) { $own = get_student_record($pdo); if (!$own || $own['id'] !== $payment['student_id']) deny(); }
$balance = round($payment['amount_due'] - $payment['amount_paid'], 2);
if ($balance <= 0) { flash('Already fully paid.','success'); header('Location: index.php'); exit; }
require_once '../../includes/header.php';
?>
<style>
.pay-wrap{max-width:600px;margin:0 auto}
.pay-hero{background:linear-gradient(135deg,#4361ee,#7209b7);border-radius:16px;padding:28px 32px;color:#fff;margin-bottom:24px}
.pay-hero .amount{font-size:2.8rem;font-weight:900;margin:8px 0}
.method-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px}
.method-card{border:2px solid #e8e8e8;border-radius:14px;padding:20px 16px;text-align:center;cursor:pointer;transition:.2s;background:#fff;position:relative}
.method-card:hover{border-color:#4361ee;transform:translateY(-2px)}
.method-card.selected{border-color:#4361ee;background:#f0f4ff}
.check-badge{position:absolute;top:10px;right:10px;width:22px;height:22px;border-radius:50%;background:#4361ee;color:#fff;display:none;align-items:center;justify-content:center;font-size:.7rem}
.method-card.selected .check-badge{display:flex}
.pay-detail-box{background:#f8f9ff;border-radius:12px;padding:20px;margin-bottom:20px}
.pay-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;font-size:.88rem}
.pay-row:last-child{border:none;font-weight:700}
.pay-btn{width:100%;padding:15px;border:none;border-radius:12px;font-size:1rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;color:#fff;margin-top:4px}
.bank-info{background:#fff;border:2px solid #e8e8e8;border-radius:12px;padding:20px;margin-bottom:16px}
.bank-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:.88rem}
.bank-row:last-child{border:none}
.copy-btn{background:#f0f4ff;color:#4361ee;border:none;border-radius:6px;padding:3px 10px;font-size:.75rem;cursor:pointer;font-weight:600}
</style>
<div class="page-header">
  <div><h1><i class="fas fa-credit-card" style="color:var(--primary)"></i> Make Payment</h1></div>
  <a href="<?= is_student()?BASE_URL.'/modules/student/dashboard.php':'index.php' ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>
<div class="pay-wrap">
  <div class="pay-hero">
    <h2 style="font-size:1.2rem;font-weight:800;margin-bottom:4px"><?= e($payment['fee_name']) ?> <?= $payment['year']?'&mdash; '.e($payment['year']):'' ?></h2>
    <div class="amount">$<?= number_format($balance,2) ?></div>
    <p style="opacity:.8;font-size:.85rem"><i class="fas fa-user"></i> <?= e($payment['student_name']) ?> &middot; <?= e($payment['student_code']) ?></p>
  </div>
  <div class="pay-detail-box">
    <div class="pay-row"><span>Total Fee</span><span>$<?= number_format($payment['amount_due'],2) ?></span></div>
    <div class="pay-row"><span>Already Paid</span><span style="color:#2dc653">$<?= number_format($payment['amount_paid'],2) ?></span></div>
    <div class="pay-row"><span>Balance Due</span><span style="color:#e63946">$<?= number_format($balance,2) ?></span></div>
  </div>
  <div style="font-weight:700;margin-bottom:12px;font-size:.9rem">Choose Payment Method</div>
  <div class="method-grid">
    <div class="method-card selected" onclick="selectMethod('chapa',this)">
      <div class="check-badge"><i class="fas fa-check"></i></div>
      <span style="font-size:2rem;display:block;margin-bottom:8px">&#x1F7E2;</span>
      <div style="font-weight:700">Chapa</div><div style="font-size:.75rem;color:#888">Card, Mobile, Bank</div>
    </div>
    <div class="method-card" onclick="selectMethod('telebirr',this)">
      <div class="check-badge"><i class="fas fa-check"></i></div>
      <span style="font-size:2rem;display:block;margin-bottom:8px">&#x1F4F1;</span>
      <div style="font-weight:700">TeleBirr</div><div style="font-size:.75rem;color:#888">Ethio Telecom</div>
    </div>
    <div class="method-card" onclick="selectMethod('bank',this)">
      <div class="check-badge"><i class="fas fa-check"></i></div>
      <span style="font-size:2rem;display:block;margin-bottom:8px">&#x1F3E6;</span>
      <div style="font-weight:700">Bank Transfer</div><div style="font-size:.75rem;color:#888">CBE, Awash, Dashen</div>
    </div>
    <div class="method-card" onclick="selectMethod('cash',this)">
      <div class="check-badge"><i class="fas fa-check"></i></div>
      <span style="font-size:2rem;display:block;margin-bottom:8px">&#x1F4B5;</span>
      <div style="font-weight:700">Cash</div><div style="font-size:.75rem;color:#888">Finance office</div>
    </div>
  </div>
  <div id="panel_chapa">
    <div style="background:#e8f5e9;border-radius:12px;padding:14px;margin-bottom:16px;font-size:.85rem;color:#2e7d32"><i class="fas fa-shield-alt"></i> <strong>Chapa</strong> &mdash; Supports Visa, Mastercard, CBE Birr, M-Pesa and more.</div>
    <form method="POST" action="chapa_init.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
      <input type="hidden" name="amount" value="<?= $balance ?>">
      <button type="submit" class="pay-btn" style="background:#00c853"><i class="fas fa-external-link-alt"></i> Pay $<?= number_format($balance,2) ?> with Chapa</button>
    </form>
  </div>
  <div id="panel_telebirr" style="display:none">
    <div style="background:#fff0f0;border-radius:12px;padding:14px;margin-bottom:16px;font-size:.85rem;color:#c1121f"><i class="fas fa-mobile-alt"></i> <strong>TeleBirr</strong> &mdash; Ethio Telecom mobile money.</div>
    <form method="POST" action="bank_confirm.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
      <input type="hidden" name="amount_paid" value="<?= $balance ?>">
      <input type="hidden" name="method" value="TeleBirr">
      <div class="form-group" style="margin-bottom:16px"><label>TeleBirr Phone Number</label><input type="tel" name="reference" placeholder="09XXXXXXXX" required></div>
      <button type="submit" class="pay-btn" style="background:linear-gradient(135deg,#e63946,#c1121f)"><i class="fas fa-mobile-alt"></i> Pay $<?= number_format($balance,2) ?> with TeleBirr</button>
    </form>
  </div>
  <div id="panel_bank" style="display:none">
    <div class="bank-info">
      <div style="font-weight:700;margin-bottom:12px"><i class="fas fa-university" style="color:#4361ee"></i> Bank Account Details</div>
      <?php foreach([['Commercial Bank of Ethiopia','1000234567890'],['Awash Bank','0123456789'],['Dashen Bank','9876543210']] as [$bn,$ba]): ?>
      <div class="bank-row"><span><?= $bn ?></span><span style="font-family:monospace;font-weight:700"><?= $ba ?> <button class="copy-btn" onclick="copyText('<?= $ba ?>',this)">Copy</button></span></div>
      <?php endforeach; ?>
      <div class="bank-row"><span>Account Name</span><strong><?= APP_NAME ?></strong></div>
      <div class="bank-row"><span>Reference</span><strong style="color:#4361ee;font-family:monospace"><?= e($payment['student_code']) ?> <button class="copy-btn" onclick="copyText('<?= e($payment['student_code']) ?>',this)">Copy</button></strong></div>
    </div>
    <form method="POST" action="bank_confirm.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
      <input type="hidden" name="method" value="Bank Transfer">
      <div class="form-group" style="margin-bottom:12px"><label>Transaction Reference *</label><input type="text" name="reference" placeholder="Bank transaction reference" required></div>
      <div class="form-group" style="margin-bottom:16px"><label>Amount Transferred ($)</label><input type="number" name="amount_paid" step="0.01" value="<?= $balance ?>" min="1" max="<?= $balance ?>" required></div>
      <button type="submit" class="pay-btn" style="background:linear-gradient(135deg,#4361ee,#3a0ca3)"><i class="fas fa-check-circle"></i> Confirm Bank Transfer</button>
    </form>
  </div>
  <div id="panel_cash" style="display:none">
    <div style="background:#fff8e1;border-radius:12px;padding:20px;margin-bottom:16px;text-align:center">
      <div style="font-size:2.5rem;margin-bottom:10px">&#x1F3EB;</div>
      <div style="font-weight:700;margin-bottom:6px">Pay at Finance Office</div>
      <div style="font-size:.85rem;color:#666;line-height:1.7">Reference: <strong style="color:#4361ee;font-family:monospace"><?= e($payment['student_code']) ?></strong><br>Amount: <strong>$<?= number_format($balance,2) ?></strong></div>
    </div>
    <form method="POST" action="bank_confirm.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="payment_id" value="<?= $payment_id ?>">
      <input type="hidden" name="amount_paid" value="<?= $balance ?>">
      <input type="hidden" name="method" value="Cash">
      <input type="hidden" name="reference" value="CASH-<?= e($payment['student_code']) ?>-<?= date('Ymd') ?>">
      <button type="submit" class="pay-btn" style="background:linear-gradient(135deg,#f4a261,#e76f51)"><i class="fas fa-receipt"></i> Generate Cash Payment Slip</button>
    </form>
  </div>
</div>
<script>
function selectMethod(m,el){document.querySelectorAll('.method-card').forEach(c=>c.classList.remove('selected'));el.classList.add('selected');['chapa','telebirr','bank','cash'].forEach(p=>{document.getElementById('panel_'+p).style.display=p===m?'block':'none';});}
function copyText(t,btn){navigator.clipboard.writeText(t).then(()=>{btn.textContent='Copied!';setTimeout(()=>btn.textContent='Copy',2000);});}
</script>
<?php require_once '../../includes/footer.php'; ?>