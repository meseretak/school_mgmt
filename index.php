<?php
require_once 'includes/config.php';
if (isset($_SESSION['user'])) {
    $r = $_SESSION['user']['role'];
    if ($r === 'super_admin') { header('Location: '.BASE_URL.'/modules/super_admin/dashboard.php'); exit; }
    if ($r === 'teacher')    { header('Location: '.BASE_URL.'/modules/teacher/dashboard.php'); exit; }
    if ($r === 'student')    { header('Location: '.BASE_URL.'/modules/students/dashboard.php'); exit; }
    if ($r === 'librarian')  { header('Location: '.BASE_URL.'/modules/library/librarian.php'); exit; }
    if ($r === 'parent')     { header('Location: '.BASE_URL.'/modules/parent/dashboard.php'); exit; }
    header('Location: '.BASE_URL.'/dashboard.php'); exit;
}

$error = ''; $field_error = [];
$max_attempts = 5; $lockout_minutes = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

    if (empty($email))  $field_error['email'] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $field_error['email'] = 'Enter a valid email.';
    if (empty($pass))   $field_error['password'] = 'Password is required.';
    elseif (strlen($pass) < 6) $field_error['password'] = 'At least 6 characters.';

    if (!$field_error) {
        $attempts = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $attempts->execute([$ip, $lockout_minutes]);
        $attempt_count = (int)$attempts->fetchColumn();

        if ($attempt_count >= $max_attempts) {
            $error = "Too many failed attempts. Please wait {$lockout_minutes} minute(s).";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND is_active=1");
            $stmt->execute([$email]); $user = $stmt->fetch();
            if ($user && password_verify($pass, $user['password'])) {
                $pdo->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]);
                $_SESSION['user'] = $user;
                if (!empty($user['branch_id'])) $_SESSION['branch_id'] = $user['branch_id'];
                log_activity($pdo, 'login', 'Logged in from '.$ip);
                if ($user['role'] === 'super_admin') $dest = BASE_URL.'/modules/super_admin/dashboard.php';
                elseif ($user['role'] === 'teacher')   $dest = BASE_URL.'/modules/teacher/dashboard.php';
                elseif ($user['role'] === 'student')   $dest = BASE_URL.'/modules/students/dashboard.php';
                elseif ($user['role'] === 'librarian') $dest = BASE_URL.'/modules/library/librarian.php';
                elseif ($user['role'] === 'parent')    $dest = BASE_URL.'/modules/parent/dashboard.php';
                else $dest = BASE_URL.'/dashboard.php';
                header('Location: '.$dest); exit;
            } else {
                $pdo->prepare("INSERT INTO login_attempts (email, ip) VALUES (?,?)")->execute([$email, $ip]);
                $remaining = max(0, $max_attempts - $attempt_count - 1);
                $error = $remaining > 0 ? "Invalid email or password. {$remaining} attempt(s) left." : "Too many failed attempts. Please wait.";
            }
        }
    }
}

try {
    $sc = $pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
    $tc = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
    $cc = $pdo->query("SELECT COUNT(*) FROM courses WHERE is_active=1")->fetchColumn();
} catch(Exception $e) { $sc=$tc=$cc=0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/favicon.svg">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;min-height:100vh;display:flex;overflow:hidden}

/* ── LEFT PANEL ─────────────────────────────────────────────── */
.left{
  flex:1;position:relative;display:flex;flex-direction:column;
  align-items:center;justify-content:center;padding:48px 56px;
  background:#0a0f1e;overflow:hidden;
}

/* Animated gradient orbs */
.orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.35;animation:float 8s ease-in-out infinite;}
.orb1{width:400px;height:400px;background:radial-gradient(circle,#4361ee,transparent);top:-80px;left:-80px;animation-delay:0s;}
.orb2{width:350px;height:350px;background:radial-gradient(circle,#7209b7,transparent);bottom:-60px;right:-60px;animation-delay:-3s;}
.orb3{width:250px;height:250px;background:radial-gradient(circle,#06b6d4,transparent);top:50%;left:50%;transform:translate(-50%,-50%);animation-delay:-5s;}
@keyframes float{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-20px) scale(1.05)}}
.orb3{animation:float2 10s ease-in-out infinite;}
@keyframes float2{0%,100%{transform:translate(-50%,-50%) scale(1)}50%{transform:translate(-50%,-55%) scale(1.08)}}

/* Grid dots overlay */
.left::before{
  content:'';position:absolute;inset:0;
  background-image:radial-gradient(rgba(255,255,255,.07) 1px,transparent 1px);
  background-size:32px 32px;
}

.left-content{position:relative;z-index:2;color:#fff;max-width:460px;width:100%}

.brand-row{display:flex;align-items:center;gap:14px;margin-bottom:40px}
.brand-logo{
  width:48px;height:48px;border-radius:14px;flex-shrink:0;
  background:linear-gradient(135deg,#4361ee,#7209b7);
  display:flex;align-items:center;justify-content:center;font-size:1.3rem;
  box-shadow:0 8px 24px rgba(67,97,238,.5);
}
.brand-name{font-size:1.2rem;font-weight:800;letter-spacing:-.02em}
.brand-tag{font-size:.72rem;opacity:.5;margin-top:1px}

.left-headline{font-size:2.4rem;font-weight:800;line-height:1.15;letter-spacing:-.03em;margin-bottom:14px}
.left-headline span{
  background:linear-gradient(90deg,#60a5fa,#a78bfa,#f472b6);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.left-sub{font-size:.9rem;opacity:.55;line-height:1.7;margin-bottom:36px;max-width:380px}

/* Feature pills */
.features{display:flex;flex-direction:column;gap:10px;margin-bottom:40px}
.feat{
  display:flex;align-items:center;gap:12px;
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
  border-radius:12px;padding:11px 16px;
  backdrop-filter:blur(10px);transition:background .2s;
}
.feat:hover{background:rgba(255,255,255,.09)}
.feat-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.feat-text{font-size:.83rem;opacity:.8;font-weight:500}

/* Stats row */
.stats-row{display:flex;gap:12px}
.stat-pill{
  flex:1;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
  border-radius:14px;padding:14px 16px;text-align:center;backdrop-filter:blur(10px);
}
.stat-pill .num{font-size:1.6rem;font-weight:800;background:linear-gradient(135deg,#60a5fa,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.stat-pill .lbl{font-size:.7rem;opacity:.5;margin-top:3px;font-weight:500;text-transform:uppercase;letter-spacing:.06em}

/* ── RIGHT PANEL ────────────────────────────────────────────── */
.right{
  width:460px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
  padding:40px 44px;background:#fff;position:relative;
  box-shadow:-20px 0 60px rgba(0,0,0,.15);
}

.login-box{width:100%}

.top-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:#eff6ff;color:#2563eb;border-radius:20px;
  padding:5px 13px;font-size:.73rem;font-weight:700;
  margin-bottom:22px;border:1px solid #bfdbfe;
}

.login-box h2{font-size:1.75rem;font-weight:800;color:#0f172a;margin-bottom:5px;letter-spacing:-.03em}
.login-box .subtitle{color:#94a3b8;font-size:.875rem;margin-bottom:28px;font-weight:400}

/* Error alert */
.alert-err{
  display:flex;align-items:flex-start;gap:10px;
  background:#fef2f2;border:1px solid #fecaca;color:#dc2626;
  padding:12px 14px;border-radius:10px;font-size:.84rem;
  margin-bottom:20px;font-weight:500;
}
.alert-err i{margin-top:1px;flex-shrink:0}

/* Form */
.fg{margin-bottom:16px}
.fl{display:block;font-size:.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:.01em}
.iw{position:relative}
.ii{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#cbd5e1;font-size:.85rem;pointer-events:none;transition:color .2s}
.fi{
  width:100%;padding:11px 13px 11px 40px;
  border:1.5px solid #e2e8f0;border-radius:10px;
  font-size:.9rem;font-family:inherit;color:#0f172a;
  background:#f8fafc;transition:all .2s;
}
.fi:focus{outline:none;border-color:#4361ee;background:#fff;box-shadow:0 0 0 3px rgba(67,97,238,.12)}
.iw:focus-within .ii{color:#4361ee}
.fi.err{border-color:#ef4444;background:#fff8f8}
.fi::placeholder{color:#cbd5e1}
.fe{color:#ef4444;font-size:.75rem;margin-top:4px;display:flex;align-items:center;gap:4px;font-weight:500}

.pw-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#cbd5e1;font-size:.85rem;padding:4px;transition:color .2s}
.pw-eye:hover{color:#4361ee}

/* Strength bar */
.str-bar{height:3px;background:#f1f5f9;border-radius:2px;margin-top:6px;overflow:hidden}
.str-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0%}

/* Submit button */
.btn-submit{
  width:100%;padding:13px;margin-top:4px;
  background:linear-gradient(135deg,#4361ee 0%,#7209b7 100%);
  color:#fff;border:none;border-radius:10px;
  font-size:.93rem;font-weight:700;cursor:pointer;font-family:inherit;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:opacity .2s,transform .15s,box-shadow .2s;
  box-shadow:0 4px 20px rgba(67,97,238,.35);
  letter-spacing:-.01em;
}
.btn-submit:hover{opacity:.93;transform:translateY(-1px);box-shadow:0 6px 28px rgba(67,97,238,.45)}
.btn-submit:active{transform:scale(.99)}
.btn-submit:disabled{opacity:.55;cursor:not-allowed;transform:none}

/* Divider */
.divider{display:flex;align-items:center;gap:10px;margin:20px 0;color:#e2e8f0;font-size:.75rem;color:#94a3b8}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#f1f5f9}

/* Demo box */
.demo-box{background:#f8fafc;border-radius:12px;padding:14px 16px;border:1px solid #f1f5f9}
.demo-title{font-size:.75rem;font-weight:700;color:#64748b;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.demo-row{
  display:flex;align-items:center;gap:8px;padding:7px 10px;
  border-radius:8px;cursor:pointer;transition:background .15s;margin-bottom:3px;
}
.demo-row:last-child{margin-bottom:0}
.demo-row:hover{background:#eff6ff}
.demo-avatar{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#fff;flex-shrink:0;font-weight:700}
.demo-info{flex:1;min-width:0}
.demo-name{font-size:.78rem;font-weight:600;color:#1e293b}
.demo-email{font-size:.7rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.demo-use{font-size:.72rem;color:#4361ee;font-weight:600;white-space:nowrap}

@media(max-width:900px){.left{display:none}.right{width:100%;box-shadow:none;padding:32px 24px}}
</style>
</head>
<body>

<!-- LEFT -->
<div class="left">
  <div class="orb orb1"></div>
  <div class="orb orb2"></div>
  <div class="orb orb3"></div>

  <div class="left-content">
    <div class="brand-row">
      <div class="brand-logo"><img src="<?= BASE_URL ?>/assets/favicon.svg" alt="logo" style="width:28px;height:28px;filter:brightness(0) invert(1)"></div>
      <div>
        <div class="brand-name"><?= APP_NAME ?></div>
        <div class="brand-tag">School Management Platform</div>
      </div>
    </div>

    <div class="left-headline">
      Manage your school<br><span>smarter & faster</span>
    </div>
    <p class="left-sub">A complete platform for students, teachers, classes, exams, library, payments and more — all in one place.</p>

    <div class="features">
      <?php $feats = [
        ['#60a5fa','fas fa-user-graduate','Student Registration & Enrollment'],
        ['#34d399','fas fa-chalkboard-teacher','Teacher & Class Management'],
        ['#fbbf24','fas fa-file-alt','Exams, Grades & Academic Reports'],
        ['#a78bfa','fas fa-bell','Smart Notifications & Messaging'],
        ['#06b6d4','fas fa-book-open','Library Management & Book Borrowing'],
      ]; foreach ($feats as [$c,$i,$t]): ?>
      <div class="feat">
        <div class="feat-icon" style="background:<?= $c ?>22;color:<?= $c ?>"><i class="<?= $i ?>"></i></div>
        <span class="feat-text"><?= $t ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="stats-row">
      <div class="stat-pill"><div class="num"><?= $sc ?></div><div class="lbl">Students</div></div>
      <div class="stat-pill"><div class="num"><?= $tc ?></div><div class="lbl">Teachers</div></div>
      <div class="stat-pill"><div class="num"><?= $cc ?></div><div class="lbl">Courses</div></div>
    </div>
  </div>
</div>

<!-- RIGHT -->
<div class="right">
  <div class="login-box">
    <div class="top-badge"><i class="fas fa-shield-alt"></i> Secure Login</div>
    <h2>Welcome back</h2>
    <p class="subtitle">Sign in to your <?= APP_NAME ?> account</p>

    <?php if ($error): ?>
    <div class="alert-err"><i class="fas fa-exclamation-circle"></i><span><?= e($error) ?></span></div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="fg">
        <label class="fl" for="email">Email Address</label>
        <div class="iw">
          <input type="email" id="email" name="email"
            class="fi <?= isset($field_error['email'])?'err':'' ?>"
            placeholder="you@school.com"
            value="<?= e($_POST['email']??'') ?>" autocomplete="email">
          <i class="fas fa-envelope ii"></i>
        </div>
        <?php if (isset($field_error['email'])): ?>
        <div class="fe"><i class="fas fa-circle-exclamation"></i><?= e($field_error['email']) ?></div>
        <?php endif; ?>
      </div>

      <div class="fg">
        <label class="fl" for="password">Password</label>
        <div class="iw">
          <input type="password" id="password" name="password"
            class="fi <?= isset($field_error['password'])?'err':'' ?>"
            placeholder="••••••••" autocomplete="current-password">
          <i class="fas fa-lock ii"></i>
          <button type="button" class="pw-eye" onclick="togglePw()"><i class="fas fa-eye" id="pwIcon"></i></button>
        </div>
        <?php if (isset($field_error['password'])): ?>
        <div class="fe"><i class="fas fa-circle-exclamation"></i><?= e($field_error['password']) ?></div>
        <?php endif; ?>
        <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
      </div>

      <button type="submit" class="btn-submit" id="loginBtn">
        <i class="fas fa-arrow-right-to-bracket"></i> Sign In
      </button>
    </form>

    <div class="divider">Quick Demo Access</div>

    <div class="demo-box">
      <div class="demo-title"><i class="fas fa-bolt" style="color:#f59e0b"></i> One-click login (password: <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px">password</code>)</div>
      <?php foreach([
        ['Super Admin','superadmin@school.com','super_admin','#f59e0b','SA'],
        ['Admin',      'admin@school.com',      'admin',      '#4361ee','AD'],
        ['Teacher',    'teacher@school.com',    'teacher',    '#7209b7','TC'],
        ['Student',    'student@school.com',    'student',    '#0ead69','ST'],
        ['Librarian',  'librarian@school.com',  'librarian',  '#06b6d4','LB'],
        ['Parent',     'parent@school.com',     'parent',     '#e63946','PA'],
      ] as [$rname,$remail,$rrole,$rcolor,$rav]): ?>
      <div class="demo-row" onclick="fillLogin('<?= $remail ?>','password')">
        <div class="demo-avatar" style="background:<?= $rcolor ?>"><?= $rav ?></div>
        <div class="demo-info">
          <div class="demo-name"><?= $rname ?></div>
          <div class="demo-email"><?= $remail ?></div>
        </div>
        <span class="demo-use">Use →</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function togglePw() {
  const pw = document.getElementById('password');
  const ic = document.getElementById('pwIcon');
  pw.type = pw.type === 'password' ? 'text' : 'password';
  ic.className = pw.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

function fillLogin(email, pass) {
  document.getElementById('email').value = email;
  document.getElementById('password').value = pass;
  document.getElementById('loginBtn').focus();
}

document.getElementById('password').addEventListener('input', function() {
  const v = this.value; let s = 0;
  if (v.length >= 6) s += 25;
  if (v.length >= 10) s += 25;
  if (/[A-Z]/.test(v)) s += 25;
  if (/[0-9!@#$%^&*]/.test(v)) s += 25;
  const f = document.getElementById('strFill');
  f.style.width = s + '%';
  f.style.background = s <= 25 ? '#ef4444' : s <= 50 ? '#f59e0b' : s <= 75 ? '#fbbf24' : '#0ead69';
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
  const email = document.getElementById('email').value.trim();
  const pass  = document.getElementById('password').value;
  let ok = true;
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    document.getElementById('email').classList.add('err'); ok = false;
  }
  if (!pass || pass.length < 6) {
    document.getElementById('password').classList.add('err'); ok = false;
  }
  if (!ok) { e.preventDefault(); return; }
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
});

['email','password'].forEach(id => {
  document.getElementById(id).addEventListener('input', function() {
    this.classList.remove('err');
  });
});
</script>
</body>
</html>
