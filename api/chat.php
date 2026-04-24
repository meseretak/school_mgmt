<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../includes/config.php';
if (!isset($_SESSION['user'])) { echo json_encode(['error'=>'unauthenticated']); exit; }
header('Content-Type: application/json');

$uid  = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get contact list: admins, teachers, and group channels
if ($action === 'contacts') {
    $contacts = [];

    // Admins
    $admins = $pdo->query("SELECT id, name FROM users WHERE role='admin' AND is_active=1 ORDER BY name")->fetchAll();
    foreach ($admins as $u) {
        if ($u['id'] != $uid) $contacts[] = ['type'=>'user','id'=>$u['id'],'name'=>$u['name'],'role'=>'admin','avatar'=>strtoupper(substr($u['name'],0,2))];
    }

    // Teachers
    $teachers = $pdo->query("SELECT id, name FROM users WHERE role='teacher' AND is_active=1 ORDER BY name")->fetchAll();
    foreach ($teachers as $u) {
        if ($u['id'] != $uid) $contacts[] = ['type'=>'user','id'=>$u['id'],'name'=>$u['name'],'role'=>'teacher','avatar'=>strtoupper(substr($u['name'],0,2))];
    }

    // Librarians
    $librarians = $pdo->query("SELECT id, name FROM users WHERE role='librarian' AND is_active=1 ORDER BY name")->fetchAll();
    foreach ($librarians as $u) {
        if ($u['id'] != $uid) $contacts[] = ['type'=>'user','id'=>$u['id'],'name'=>$u['name'],'role'=>'librarian','avatar'=>strtoupper(substr($u['name'],0,2))];
    }

    // Students (visible to admin, teacher, librarian)
    if (in_array($role, ['admin','super_admin','teacher','librarian'])) {
        $students = $pdo->query("SELECT id, name FROM users WHERE role='student' AND is_active=1 ORDER BY name")->fetchAll();
        foreach ($students as $u) {
            if ($u['id'] != $uid) $contacts[] = ['type'=>'user','id'=>$u['id'],'name'=>$u['name'],'role'=>'student','avatar'=>strtoupper(substr($u['name'],0,2))];
        }
    }

    // Group: All Students
    $contacts[] = ['type'=>'group','id'=>'students','name'=>'All Students','role'=>'group','avatar'=>'ST','icon'=>'fas fa-users'];

    // Group: All Teachers
    $contacts[] = ['type'=>'group','id'=>'teachers','name'=>'All Teachers','role'=>'group','avatar'=>'TC','icon'=>'fas fa-chalkboard-teacher'];

    // Group: All Staff (admin+teacher)
    $contacts[] = ['type'=>'group','id'=>'staff','name'=>'All Staff','role'=>'group','avatar'=>'SF','icon'=>'fas fa-user-tie'];

    // Class groups for teacher
    if ($role === 'teacher') {
        $t = $pdo->prepare("SELECT id FROM teachers WHERE user_id=?"); $t->execute([$uid]); $t = $t->fetch();
        if ($t) {
            $cls = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM classes cl JOIN courses co ON cl.course_id=co.id WHERE cl.teacher_id=? ORDER BY co.name");
            $cls->execute([$t['id']]);
            foreach ($cls->fetchAll() as $cl) {
                $contacts[] = ['type'=>'group','id'=>'class_'.$cl['id'],'name'=>$cl['code'].' — '.$cl['course_name'].' §'.$cl['section'],'role'=>'class','avatar'=>substr($cl['code'],0,2),'icon'=>'fas fa-door-open'];
            }
        }
    }

    // Students can see their class groups
    if ($role === 'student') {
        $s = $pdo->prepare("SELECT id FROM students WHERE user_id=?"); $s->execute([$uid]); $s = $s->fetch();
        if ($s) {
            $cls = $pdo->prepare("SELECT cl.id, co.code, co.name AS course_name, cl.section FROM enrollments en JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? AND en.status='Enrolled' ORDER BY co.name");
            $cls->execute([$s['id']]);
            foreach ($cls->fetchAll() as $cl) {
                $contacts[] = ['type'=>'group','id'=>'class_'.$cl['id'],'name'=>$cl['code'].' — '.$cl['course_name'].' §'.$cl['section'],'role'=>'class','avatar'=>substr($cl['code'],0,2),'icon'=>'fas fa-door-open'];
            }
        }
    }

    echo json_encode($contacts);
    exit;
}

// Get messages for a conversation
if ($action === 'get_messages') {
    $with = $_GET['with'] ?? '';
    $msgs = [];

    if (str_starts_with($with, 'class_')) {
        $class_id = (int)substr($with, 6);
        $stmt = $pdo->prepare("SELECT m.id, m.body, m.created_at, u.name AS sender_name, m.sender_id, IF(m.sender_id=?, 1, 0) AS is_mine FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.class_id=? AND m.recipient_type='group_class' ORDER BY m.created_at DESC LIMIT 50");
        $stmt->execute([$uid, $class_id]);
    } elseif ($with === 'students') {
        $stmt = $pdo->prepare("SELECT m.id, m.body, m.created_at, u.name AS sender_name, m.sender_id, IF(m.sender_id=?, 1, 0) AS is_mine FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.recipient_type='group_students' ORDER BY m.created_at DESC LIMIT 50");
        $stmt->execute([$uid]);
    } elseif ($with === 'teachers') {
        $stmt = $pdo->prepare("SELECT m.id, m.body, m.created_at, u.name AS sender_name, m.sender_id, IF(m.sender_id=?, 1, 0) AS is_mine FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.recipient_type='group_teachers' ORDER BY m.created_at DESC LIMIT 50");
        $stmt->execute([$uid]);
    } elseif ($with === 'staff') {
        $stmt = $pdo->prepare("SELECT m.id, m.body, m.created_at, u.name AS sender_name, m.sender_id, IF(m.sender_id=?, 1, 0) AS is_mine FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.recipient_type='broadcast' ORDER BY m.created_at DESC LIMIT 50");
        $stmt->execute([$uid]);
    } else {
        $other_id = (int)$with;
        $stmt = $pdo->prepare("SELECT m.id, m.body, m.created_at, u.name AS sender_name, m.sender_id, IF(m.sender_id=?, 1, 0) AS is_mine FROM messages m JOIN users u ON m.sender_id=u.id LEFT JOIN message_recipients mr ON mr.message_id=m.id WHERE m.recipient_type='user' AND ((m.sender_id=? AND mr.user_id=?) OR (m.sender_id=? AND mr.user_id=?)) ORDER BY m.created_at DESC LIMIT 50");
        $stmt->execute([$uid, $uid, $other_id, $other_id, $uid]);
    }
    $msgs = array_reverse($stmt->fetchAll());
    echo json_encode($msgs);
    exit;
}

// Send a message
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');
    $to   = $_POST['to'] ?? '';
    if (!$body) { echo json_encode(['ok'=>false]); exit; }

    $rtype = 'user'; $class_id = null;
    if ($to === 'students')  $rtype = 'group_students';
    elseif ($to === 'teachers') $rtype = 'group_teachers';
    elseif ($to === 'staff')    $rtype = 'broadcast';
    elseif (str_starts_with($to, 'class_')) { $rtype = 'group_class'; $class_id = (int)substr($to, 6); }

    $pdo->prepare("INSERT INTO messages (sender_id,subject,body,recipient_type,class_id) VALUES (?,?,?,?,?)")
        ->execute([$uid, '', $body, $rtype, $class_id]);
    $mid = $pdo->lastInsertId();

    // Add recipients
    if ($rtype === 'user') {
        $other_id = (int)$to;
        $pdo->prepare("INSERT IGNORE INTO message_recipients (message_id,user_id) VALUES (?,?)")->execute([$mid, $other_id]);
    } elseif ($rtype === 'group_class' && $class_id) {
        $q = $pdo->prepare("SELECT DISTINCT u.id FROM users u LEFT JOIN students s ON s.user_id=u.id LEFT JOIN enrollments en ON en.student_id=s.id LEFT JOIN teachers t ON t.user_id=u.id LEFT JOIN classes cl ON cl.teacher_id=t.id WHERE (en.class_id=? AND en.status='Enrolled') OR cl.id=?");
        $q->execute([$class_id, $class_id]);
        $ins = $pdo->prepare("INSERT IGNORE INTO message_recipients (message_id,user_id) VALUES (?,?)");
        foreach ($q->fetchAll() as $r) { if ($r['id'] != $uid) $ins->execute([$mid, $r['id']]); }
    } elseif ($rtype === 'group_students') {
        $q = $pdo->query("SELECT id FROM users WHERE role='student' AND is_active=1");
        $ins = $pdo->prepare("INSERT IGNORE INTO message_recipients (message_id,user_id) VALUES (?,?)");
        foreach ($q->fetchAll() as $r) { if ($r['id'] != $uid) $ins->execute([$mid, $r['id']]); }
    } elseif ($rtype === 'group_teachers') {
        $q = $pdo->query("SELECT id FROM users WHERE role='teacher' AND is_active=1");
        $ins = $pdo->prepare("INSERT IGNORE INTO message_recipients (message_id,user_id) VALUES (?,?)");
        foreach ($q->fetchAll() as $r) { if ($r['id'] != $uid) $ins->execute([$mid, $r['id']]); }
    } elseif ($rtype === 'broadcast') {
        $q = $pdo->query("SELECT id FROM users WHERE is_active=1");
        $ins = $pdo->prepare("INSERT IGNORE INTO message_recipients (message_id,user_id) VALUES (?,?)");
        foreach ($q->fetchAll() as $r) { if ($r['id'] != $uid) $ins->execute([$mid, $r['id']]); }
    }

    echo json_encode(['ok'=>true,'id'=>$mid,'time'=>date('g:i A')]);
    exit;
}

// Unread count
if ($action === 'unread') {
    $c = $pdo->prepare("SELECT COUNT(*) FROM message_recipients WHERE user_id=? AND is_read=0");
    $c->execute([$uid]);
    echo json_encode(['count'=>(int)$c->fetchColumn()]);
    exit;
}

echo json_encode(['error'=>'unknown action']);
