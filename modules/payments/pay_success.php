<?php
require_once '../../includes/config.php';
auth_check();
$page_title = 'Payment Successful'; $active_page = 'payments';

$payment_id = (int)($_GET['id'] ?? 0);
$ref        = e($_GET['ref'] ?? '');
$amount     = (float)($_GET['amount'] ?? 0);
$method     = e($_GET['method'] ?? '');

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

require_once '../../includes/header.php';
?>
<style>
.success-wrap { max-width:560px; margin:0 auto; }
.success-card { background:#fff; border-radius:20px; box-shadow:0 8px 40px rgba(0,0,0,.1); overflow:hidden; }
.success-top { background:linear-gradient(135deg,#2dc653,#1a9e3f); padding:40px 32px; text-align:center; color:#fff; }
.success-icon { width:80px; height:80px; background:rgba(255,255,255,.2); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:2.2rem; }
.success-top h2 { font-size:1.6rem; font-weight:800; margin-bottom:6px; }
.success-top p { opacity:.85; font-size:.9rem; }
.receipt-body { padding:28px 32px; }
.receipt-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #f0f0f0; font-size:.9rem; }
.receipt-row:last-child { border:none; }
.receipt-row .label { color:#888; }
.receipt-row .value { font-weight:700; color:#1a1a2e; }
.receipt-footer { background:#f8f9ff; padding:20px 32px; text-align:center; }
.confetti { position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:9999; }
</style>

<!-- Confetti canvas -->
<canvas class="confetti" id="confetti"></canvas>

<div class="success-wrap">
  <div class="success-card">
    <div class="success-top">
      <div class="success-icon">✅</div>
      <h2>Payment Successful!</h2>
      <p>Your payment has been processed. A receipt has been sent to your email.</p>
    </div>
    <div class="receipt-body">
      <div style="text-align:center;margin-bottom:20px">
        <div style="font-size:.75rem;color:#aaa;text-transform:uppercase;letter-spacing:.08em">Transaction Reference</div>
        <div style="font-size:1.1rem;font-family:monospace;font-weight:800;color:#4361ee;background:#f0f4ff;padding:8px 16px;border-radius:8px;display:inline-block;margin-top:6px"><?= $ref ?></div>
      </div>
      <div class="receipt-row"><span class="label">Student</span><span class="value"><?= e($payment['student_name']??'') ?></span></div>
      <div class="receipt-row"><span class="label">Student ID</span><span class="value" style="font-family:monospace"><?= e($payment['student_code']??'') ?></span></div>
      <div class="receipt-row"><span class="label">Fee Type</span><span class="value"><?= e($payment['fee_name']??'') ?></span></div>
      <div class="receipt-row"><span class="label">Amount Paid</span><span class="value" style="color:#2dc653;font-size:1.1rem">$<?= number_format($amount,2) ?></span></div>
      <div class="receipt-row"><span class="label">Payment Method</span><span class="value"><?= $method ?></span></div>
      <div class="receipt-row"><span class="label">Date & Time</span><span class="value"><?= date('M j, Y — g:i A') ?></span></div>
      <?php if ($payment): $balance = $payment['amount_due'] - $payment['amount_paid']; ?>
      <div class="receipt-row"><span class="label">Remaining Balance</span>
        <span class="value" style="color:<?= $balance>0?'#e63946':'#2dc653' ?>">
          <?= $balance > 0 ? '$'.number_format($balance,2) : '✅ Fully Paid' ?>
        </span>
      </div>
      <?php endif; ?>
    </div>
    <div class="receipt-footer">
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print Receipt</button>
        <?php if ($payment && ($payment['amount_due']-$payment['amount_paid']) > 0): ?>
        <a href="pay.php?id=<?= $payment_id ?>" class="btn btn-primary"><i class="fas fa-credit-card"></i> Pay Remaining</a>
        <?php endif; ?>
        <a href="<?= is_student() ? BASE_URL.'/modules/student/dashboard.php' : 'index.php' ?>" class="btn btn-success"><i class="fas fa-home"></i> Dashboard</a>
      </div>
      <p style="font-size:.75rem;color:#aaa;margin-top:14px"><i class="fas fa-shield-alt"></i> This is an official payment receipt from <?= APP_NAME ?></p>
    </div>
  </div>
</div>

<script>
// Simple confetti animation
const canvas = document.getElementById('confetti');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth; canvas.height = window.innerHeight;
const pieces = Array.from({length:120}, () => ({
  x: Math.random()*canvas.width, y: Math.random()*canvas.height - canvas.height,
  w: Math.random()*10+5, h: Math.random()*6+3,
  color: ['#4361ee','#7209b7','#2dc653','#f4a261','#4cc9f0','#e63946'][Math.floor(Math.random()*6)],
  speed: Math.random()*3+1, angle: Math.random()*360, spin: Math.random()*4-2
}));
let frame = 0;
function draw() {
  ctx.clearRect(0,0,canvas.width,canvas.height);
  pieces.forEach(p => {
    ctx.save(); ctx.translate(p.x,p.y); ctx.rotate(p.angle*Math.PI/180);
    ctx.fillStyle = p.color; ctx.fillRect(-p.w/2,-p.h/2,p.w,p.h); ctx.restore();
    p.y += p.speed; p.angle += p.spin;
    if (p.y > canvas.height) { p.y = -10; p.x = Math.random()*canvas.width; }
  });
  frame++;
  if (frame < 180) requestAnimationFrame(draw);
  else ctx.clearRect(0,0,canvas.width,canvas.height);
}
draw();
</script>
<?php require_once '../../includes/footer.php'; ?>