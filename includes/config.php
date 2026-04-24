<?php
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'school_mgmt');
define('APP_NAME', 'EduManage Pro');
define('APP_VERSION', '1.0.0');
define('APP_SHORT', 'EMP');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/school_mgmt');
// â”€â”€ Email (Brevo REST API over HTTPS port 443) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
define('MAIL_ENABLED',   true);
define('MAIL_FROM',      'meseretak5247@gmail.com');
define('MAIL_FROM_NAME', APP_NAME);
define('BREVO_API_KEY',  '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');

// â”€â”€ Chapa Payment Gateway â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Get your key from: https://dashboard.chapa.co â†’ Settings â†’ API Keys
// Test key starts with CHASECK_TEST-, live key with CHASECK-
define('CHAPA_SECRET_KEY',     'CHASECK_TEST-JGWv8cFVHSI0twVmG15zN0SXp1mfbMfR');
define('CHAPA_PUBLIC_KEY',     'CHAPUBK_TEST-sWFaEWblZ40IecwHYiDGFJoOqRuffyR2');
define('CHAPA_ENCRYPTION_KEY', 'YdYs8QD9tJn8qeNcFcH75MkT');

$pdo = new PDO(
    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_PERSISTENT         => true,
     PDO::ATTR_EMULATE_PREPARES   => false]
);

session_start();

// Refresh branch_id from DB only once per session, not every request
if (isset($_SESSION['user']['id']) && !isset($_SESSION['branch_refreshed'])) {
    try {
        $s = $pdo->prepare("SELECT branch_id FROM users WHERE id=?");
        $s->execute([$_SESSION['user']['id']]);
        $fresh = $s->fetch();
        if ($fresh && $fresh['branch_id']) {
            $_SESSION['user']['branch_id'] = $fresh['branch_id'];
        }
        $_SESSION['branch_refreshed'] = true;
    } catch(Exception $e) {}
}

function auth_check($roles = []) {
    if (!isset($_SESSION['user'])) {
        header('Location: '.BASE_URL.'/index.php'); exit;
    }
    $role = $_SESSION['user']['role'];
    // Super admin can access everything
    if ($role === 'super_admin') return;
    if ($roles && !in_array($role, $roles)) {
        if ($role === 'teacher') {
            header('Location: '.BASE_URL.'/modules/teacher/dashboard.php'); exit;
        }
        if ($role === 'student') {
            header('Location: '.BASE_URL.'/modules/students/dashboard.php'); exit;
        }
        if ($role === 'librarian') {
            header('Location: '.BASE_URL.'/modules/library/librarian.php'); exit;
        }
        if ($role === 'parent') {
            header('Location: '.BASE_URL.'/modules/parent/dashboard.php'); exit;
        }
        http_response_code(403);
        include __DIR__.'/403.php'; exit;
    }
}

// Check if current user is admin
function is_super_admin() { return ($_SESSION['user']['role'] ?? '') === 'super_admin'; }
function is_admin() { return in_array($_SESSION['user']['role'] ?? '', ['admin','super_admin']); }
function is_teacher() { return ($_SESSION['user']['role'] ?? '') === 'teacher'; }
function is_student() { return ($_SESSION['user']['role'] ?? '') === 'student'; }
function is_librarian() { return ($_SESSION['user']['role'] ?? '') === 'librarian'; }

// Get teacher record for logged-in teacher (cached in session)
function get_teacher_record($pdo) {
    if (!is_teacher()) return null;
    static $t = null;
    if ($t === null) {
        $s = $pdo->prepare("SELECT * FROM teachers WHERE user_id=?");
        $s->execute([$_SESSION['user']['id']]); $t = $s->fetch() ?: false;
    }
    return $t ?: null;
}

// Get student record for logged-in student
function get_student_record($pdo) {
    if (!is_student()) return null;
    static $s = null;
    if ($s === null) {
        $q = $pdo->prepare("SELECT * FROM students WHERE user_id=?");
        $q->execute([$_SESSION['user']['id']]); $s = $q->fetch() ?: false;
    }
    return $s ?: null;
}

// Verify teacher owns a class â€” redirect with error if not
function teacher_owns_class($pdo, $class_id, $teacher_id) {
    $q = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
    $q->execute([$class_id, $teacher_id]);
    return (bool)$q->fetch();
}

// Deny access with a nice message
function deny($msg = 'You do not have permission to access this page.') {
    http_response_code(403);
    include __DIR__.'/403.php'; exit;
}

function grade_letter($pct, $scale_items = null) {
    global $pdo;
    if (!$scale_items) {
        static $default_scale = null;
        if ($default_scale === null) {
            try {
                $s = $pdo->query("SELECT gsi.* FROM grade_scale_items gsi JOIN grade_scales gs ON gsi.scale_id=gs.id WHERE gs.is_default=1 ORDER BY gsi.min_pct DESC");
                $default_scale = $s ? $s->fetchAll() : [];
            } catch(Exception $e) { $default_scale = []; }
        }
        $scale_items = $default_scale;
    }
    foreach ($scale_items as $item) {
        if ($pct >= $item['min_pct'] && $pct <= $item['max_pct']) return $item['grade_letter'];
    }
    // fallback hardcoded
    if ($pct >= 90) return 'A+'; if ($pct >= 85) return 'A'; if ($pct >= 80) return 'A-';
    if ($pct >= 75) return 'B+'; if ($pct >= 70) return 'B'; if ($pct >= 65) return 'B-';
    if ($pct >= 60) return 'C+'; if ($pct >= 55) return 'C'; if ($pct >= 50) return 'D';
    return 'F';
}

function get_pass_pct() {
    global $pdo;
    static $pass = null;
    if ($pass === null) {
        try { $pass = (float)$pdo->query("SELECT pass_percentage FROM grade_scales WHERE is_default=1 LIMIT 1")->fetchColumn(); }
        catch(Exception $e) { $pass = 50.0; }
    }
    return $pass ?: 50.0;
}

function get_current_branch() {
    global $pdo;
    // Allow admin to switch branch via URL param
    if (is_admin() && isset($_GET['switch_branch'])) {
        $bid = (int)$_GET['switch_branch'];
        if ($bid > 0) {
            $_SESSION['branch_id'] = $bid;
        } else {
            unset($_SESSION['branch_id']); // 0 = all branches
        }
    }
    if (!empty($_SESSION['branch_id'])) return (int)$_SESSION['branch_id'];
    return null; // null = all branches (super admin view)
}

// Generate standard student ID: EMP-STU-2025-0001
function generate_student_id($pdo, $user_id) {
    $abbr = strtoupper(APP_SHORT);
    $year = date('Y');
    $seq  = str_pad($user_id, 4, '0', STR_PAD_LEFT);
    return "{$abbr}-STU-{$year}-{$seq}";
}

// Generate standard teacher ID: EMP-TCH-2025-0001
function generate_teacher_id($pdo, $user_id) {
    $abbr = strtoupper(APP_SHORT);
    $year = date('Y');
    $seq  = str_pad($user_id, 4, '0', STR_PAD_LEFT);
    return "{$abbr}-TCH-{$year}-{$seq}";
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }
function flash($msg, $type='success') { $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type]; }
function get_flash() {
    if (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_check() {
    if (($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403); die('Invalid CSRF token.');
    }
}

// Log user activity
function log_activity($pdo, $action, $description = '') {
    if (empty($_SESSION['user']['id'])) return;
    try {
        $pdo->prepare("INSERT INTO activity_log (user_id, role, action, description, ip) VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['user']['id'], $_SESSION['user']['role'], $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch(Exception $e) {}
}
