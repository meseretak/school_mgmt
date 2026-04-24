<?php
/**
 * EduManage Pro â€” REST API v1
 * Base URL: /school_mgmt/api/v1.php
 *
 * Authentication: Bearer token in Authorization header
 *   Authorization: Bearer YOUR_API_KEY
 *
 * Endpoints:
 *   GET  /v1.php?endpoint=students          â€” list students
 *   GET  /v1.php?endpoint=students&id=1     â€” single student
 *   GET  /v1.php?endpoint=teachers          â€” list teachers
 *   GET  /v1.php?endpoint=payments          â€” list payments
 *   POST /v1.php?endpoint=payments          â€” create payment record
 *   POST /v1.php?endpoint=payment_confirm   â€” confirm payment (bank callback)
 *   GET  /v1.php?endpoint=classes           â€” list classes
 *   GET  /v1.php?endpoint=enrollments       â€” list enrollments
 *   GET  /v1.php?endpoint=ping              â€” health check
 */

require_once '../includes/config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function api_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $code < 400, 'data' => $data, 'timestamp' => date('c')]);
    exit;
}
function api_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message, 'timestamp' => date('c')]);
    exit;
}

// â”€â”€ Authentication â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$api_key_val = '';
if (preg_match('/Bearer\s+(.+)/i', $auth_header, $m)) $api_key_val = trim($m[1]);
elseif (!empty($_SERVER['HTTP_X_API_KEY'])) $api_key_val = trim($_SERVER['HTTP_X_API_KEY']);
elseif (!empty($_GET['api_key'])) $api_key_val = trim($_GET['api_key']);
elseif (!empty($_POST['api_key'])) $api_key_val = trim($_POST['api_key']);

if (empty($api_key_val)) api_error('Missing API key. Use Authorization: Bearer YOUR_KEY header.', 401);

$key_row = $pdo->prepare("SELECT * FROM api_keys WHERE api_key=? AND is_active=1");
$key_row->execute([$api_key_val]); $key_row = $key_row->fetch();
if (!$key_row) api_error('Invalid or inactive API key.', 401);

// Update last used
$pdo->prepare("UPDATE api_keys SET last_used_at=NOW() WHERE id=?")->execute([$key_row['id']]);

$permissions = json_decode($key_row['permissions'] ?? '[]', true) ?: [];
$endpoint = strtolower(trim($_GET['endpoint'] ?? ''));
$method   = $_SERVER['REQUEST_METHOD'];
$body     = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Log request
try {
    $pdo->prepare("INSERT INTO api_logs (api_key_id,endpoint,method,request_body,response_code,ip) VALUES (?,?,?,?,200,?)")
        ->execute([$key_row['id'], $endpoint, $method, $method==='POST'?json_encode($body):null, $_SERVER['REMOTE_ADDR']??'']);
} catch(Exception $e) {}

// â”€â”€ Permission check â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function has_perm($permissions, $perm) {
    return in_array('*', $permissions) || in_array($perm, $permissions);
}

// â”€â”€ Routes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

// Health check
if ($endpoint === 'ping') {
    api_response(['status' => 'ok', 'version' => '1.0', 'app' => APP_NAME]);
}

// â”€â”€ STUDENTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'students') {
    if (!has_perm($permissions, 'students:read')) api_error('Permission denied.', 403);
    if ($method === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $s = $pdo->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
            $s->execute([$id]); $s = $s->fetch();
            if (!$s) api_error('Student not found.', 404);
            api_response($s);
        }
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'Active';
        $sql = "SELECT s.id, s.student_code, s.first_name, s.last_name, s.gender, s.nationality, s.phone, s.status, u.email FROM students s JOIN users u ON s.user_id=u.id WHERE 1=1";
        $params = [];
        if ($status) { $sql .= " AND s.status=?"; $params[] = $status; }
        if ($search) { $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
        $sql .= " ORDER BY s.first_name LIMIT 100";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        api_response($stmt->fetchAll());
    }
}

// â”€â”€ TEACHERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'teachers') {
    if (!has_perm($permissions, 'teachers:read')) api_error('Permission denied.', 403);
    $stmt = $pdo->query("SELECT t.id, t.teacher_code, t.first_name, t.last_name, t.specialization, t.phone, t.status, u.email FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.status='Active' ORDER BY t.first_name LIMIT 100");
    api_response($stmt->fetchAll());
}

// â”€â”€ CLASSES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'classes') {
    if (!has_perm($permissions, 'classes:read')) api_error('Permission denied.', 403);
    $stmt = $pdo->query("SELECT cl.id, co.code, co.name AS course_name, CONCAT(t.first_name,' ',t.last_name) AS teacher, cl.section, cl.schedule, cl.status, ay.label AS academic_year, COUNT(e.id) AS enrolled FROM classes cl JOIN courses co ON cl.course_id=co.id JOIN teachers t ON cl.teacher_id=t.id JOIN academic_years ay ON cl.academic_year_id=ay.id LEFT JOIN enrollments e ON cl.id=e.class_id AND e.status='Enrolled' GROUP BY cl.id ORDER BY co.name LIMIT 100");
    api_response($stmt->fetchAll());
}

// â”€â”€ ENROLLMENTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'enrollments') {
    if (!has_perm($permissions, 'enrollments:read')) api_error('Permission denied.', 403);
    $student_id = (int)($_GET['student_id'] ?? 0);
    $class_id   = (int)($_GET['class_id'] ?? 0);
    $sql = "SELECT e.*, s.student_code, CONCAT(s.first_name,' ',s.last_name) AS student_name, co.code AS course_code, co.name AS course_name FROM enrollments e JOIN students s ON e.student_id=s.id JOIN classes cl ON e.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE 1=1";
    $params = [];
    if ($student_id) { $sql .= " AND e.student_id=?"; $params[] = $student_id; }
    if ($class_id)   { $sql .= " AND e.class_id=?";   $params[] = $class_id; }
    $sql .= " ORDER BY e.enrolled_at DESC LIMIT 200";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    api_response($stmt->fetchAll());
}

// â”€â”€ PAYMENTS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'payments') {
    if (!has_perm($permissions, 'payments:read') && !has_perm($permissions, 'payments:write')) api_error('Permission denied.', 403);

    // GET â€” list payments
    if ($method === 'GET') {
        if (!has_perm($permissions, 'payments:read')) api_error('Permission denied.', 403);
        $student_id = (int)($_GET['student_id'] ?? 0);
        $status     = $_GET['status'] ?? '';
        $sql = "SELECT p.*, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, ft.name AS fee_name FROM payments p JOIN students s ON p.student_id=s.id JOIN fee_types ft ON p.fee_type_id=ft.id WHERE 1=1";
        $params = [];
        if ($student_id) { $sql .= " AND p.student_id=?"; $params[] = $student_id; }
        if ($status)     { $sql .= " AND p.status=?";     $params[] = $status; }
        $sql .= " ORDER BY p.created_at DESC LIMIT 200";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        api_response($stmt->fetchAll());
    }

    // POST â€” create payment record
    if ($method === 'POST') {
        if (!has_perm($permissions, 'payments:write')) api_error('Permission denied.', 403);
        $required = ['student_id','fee_type_id','amount_due'];
        foreach ($required as $f) {
            if (empty($body[$f])) api_error("Missing required field: $f", 422);
        }
        $student = $pdo->prepare("SELECT id FROM students WHERE id=?"); $student->execute([(int)$body['student_id']]);
        if (!$student->fetch()) api_error('Student not found.', 404);
        $fee = $pdo->prepare("SELECT id FROM fee_types WHERE id=?"); $fee->execute([(int)$body['fee_type_id']]);
        if (!$fee->fetch()) api_error('Fee type not found.', 404);

        $ay = $pdo->query("SELECT id FROM academic_years WHERE is_current=1 LIMIT 1")->fetchColumn();
        $pdo->prepare("INSERT INTO payments (student_id,fee_type_id,academic_year_id,amount_due,amount_paid,due_date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([(int)$body['student_id'],(int)$body['fee_type_id'],$ay??null,(float)$body['amount_due'],(float)($body['amount_paid']??0),$body['due_date']??null,$body['status']??'Pending',$body['notes']??null,null]);
        $pid = $pdo->lastInsertId();
        api_response(['payment_id' => $pid, 'message' => 'Payment record created.'], 201);
    }
}

// â”€â”€ PAYMENT CONFIRM (bank/gateway callback) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'payment_confirm') {
    if (!has_perm($permissions, 'payments:write')) api_error('Permission denied.', 403);
    if ($method !== 'POST') api_error('POST required.', 405);

    $required = ['payment_id','amount_paid','transaction_ref'];
    foreach ($required as $f) {
        if (empty($body[$f])) api_error("Missing required field: $f", 422);
    }

    $pid = (int)$body['payment_id'];
    $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?"); $pay->execute([$pid]); $pay = $pay->fetch();
    if (!$pay) api_error('Payment not found.', 404);
    if ($pay['status'] === 'Paid') api_error('Payment already marked as paid.', 409);

    $amount_paid = (float)$body['amount_paid'];
    $new_status  = $amount_paid >= (float)$pay['amount_due'] ? 'Paid' : 'Partial';
    $notes       = ($pay['notes'] ? $pay['notes']."\n" : '') . "Confirmed via API. Ref: {$body['transaction_ref']}. Provider: ".($body['provider']??'API');

    $pdo->prepare("UPDATE payments SET amount_paid=?,status=?,notes=?,updated_at=NOW() WHERE id=?")
        ->execute([$amount_paid, $new_status, $notes, $pid]);

    // Notify student
    try {
        $student_uid = $pdo->prepare("SELECT u.id FROM students s JOIN users u ON s.user_id=u.id WHERE s.id=?");
        $student_uid->execute([$pay['student_id']]); $student_uid = $student_uid->fetchColumn();
        if ($student_uid) {
            require_once '../includes/notify.php';
            notify_user($pdo, $student_uid, 'âœ… Payment Confirmed', "Payment of \${$amount_paid} confirmed. Ref: {$body['transaction_ref']}. Status: {$new_status}.", 0);
        }
    } catch(Exception $e) {}

    api_response(['payment_id' => $pid, 'status' => $new_status, 'amount_paid' => $amount_paid, 'message' => 'Payment confirmed.']);
}

// â”€â”€ WEBHOOK (Chapa / bank push notifications) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'webhook') {
    $provider = $_GET['provider'] ?? 'unknown';
    $payload  = file_get_contents('php://input');
    $event    = $body['event'] ?? $body['type'] ?? 'payment';

    // Log webhook
    $pdo->prepare("INSERT INTO payment_webhooks (provider,event_type,payload,status) VALUES (?,?,?,'received')")
        ->execute([$provider, $event, $payload]);
    $webhook_id = $pdo->lastInsertId();

    // Process Chapa webhook
    if ($provider === 'chapa') {
        $tx_ref   = $body['data']['tx_ref'] ?? $body['tx_ref'] ?? null;
        $amount   = (float)($body['data']['amount'] ?? $body['amount'] ?? 0);
        $status   = $body['data']['status'] ?? $body['status'] ?? '';

        if ($tx_ref && $status === 'success' && $amount > 0) {
            // Find payment by transaction ref in notes
            $pay = $pdo->prepare("SELECT * FROM payments WHERE notes LIKE ?");
            $pay->execute(["%$tx_ref%"]); $pay = $pay->fetch();
            if ($pay) {
                $new_status = $amount >= (float)$pay['amount_due'] ? 'Paid' : 'Partial';
                $pdo->prepare("UPDATE payments SET amount_paid=?,status=? WHERE id=?")->execute([$amount,$new_status,$pay['id']]);
                $pdo->prepare("UPDATE payment_webhooks SET status='processed',payment_id=? WHERE id=?")->execute([$pay['id'],$webhook_id]);
            }
        }
    }

    // Process generic bank webhook
    if ($provider === 'bank') {
        $payment_id = (int)($body['payment_id'] ?? 0);
        $amount     = (float)($body['amount'] ?? 0);
        $ref        = $body['reference'] ?? '';
        if ($payment_id && $amount > 0) {
            $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?"); $pay->execute([$payment_id]); $pay = $pay->fetch();
            if ($pay) {
                $new_status = $amount >= (float)$pay['amount_due'] ? 'Paid' : 'Partial';
                $notes = ($pay['notes']?$pay['notes']."\n":'')."Bank webhook. Ref: $ref";
                $pdo->prepare("UPDATE payments SET amount_paid=?,status=?,notes=? WHERE id=?")->execute([$amount,$new_status,$notes,$payment_id]);
                $pdo->prepare("UPDATE payment_webhooks SET status='processed',payment_id=? WHERE id=?")->execute([$payment_id,$webhook_id]);
            }
        }
    }

    api_response(['received' => true, 'webhook_id' => $webhook_id]);
}

// â”€â”€ FEE TYPES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'fee_types') {
    if (!has_perm($permissions, 'payments:read')) api_error('Permission denied.', 403);
    api_response($pdo->query("SELECT * FROM fee_types WHERE is_active=1 ORDER BY name")->fetchAll());
}

// â”€â”€ ACADEMIC YEARS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'academic_years') {
    api_response($pdo->query("SELECT * FROM academic_years ORDER BY start_date DESC")->fetchAll());
}


// â”€â”€ CREATE STUDENT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'create_student') {
    if (!has_perm($permissions, 'students:write')) api_error('Permission denied.', 403);
    if ($method !== 'POST') api_error('POST required.', 405);
    $required = ['first_name','last_name','email','password'];
    foreach ($required as $f) if (empty($body[$f])) api_error("Missing: $f", 422);

    // Check duplicate email
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([trim($body['email'])]);
    if ($chk->fetch()) api_error('Email already exists.', 409);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'student')")
            ->execute([trim($body['first_name']).' '.trim($body['last_name']), trim($body['email']), password_hash($body['password'], PASSWORD_BCRYPT)]);
        $user_id = $pdo->lastInsertId();
        $student_code = APP_SHORT.'-STU-'.date('Y').'-'.str_pad($user_id,4,'0',STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO students (user_id,student_code,first_name,last_name,dob,gender,nationality,phone,address,enrollment_date) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$user_id,$student_code,trim($body['first_name']),trim($body['last_name']),$body['dob']??null,$body['gender']??null,$body['nationality']??null,$body['phone']??null,$body['address']??null,date('Y-m-d')]);
        $sid = $pdo->lastInsertId();
        $pdo->commit();
        api_response(['student_id'=>$sid,'student_code'=>$student_code,'user_id'=>$user_id,'message'=>'Student created.'], 201);
    } catch(Exception $e) { $pdo->rollBack(); api_error('Creation failed: '.$e->getMessage(), 500); }
}

// â”€â”€ CREATE TEACHER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'create_teacher') {
    if (!has_perm($permissions, 'teachers:write')) api_error('Permission denied.', 403);
    if ($method !== 'POST') api_error('POST required.', 405);
    $required = ['first_name','last_name','email','password'];
    foreach ($required as $f) if (empty($body[$f])) api_error("Missing: $f", 422);

    $chk = $pdo->prepare("SELECT id FROM users WHERE email=?"); $chk->execute([trim($body['email'])]);
    if ($chk->fetch()) api_error('Email already exists.', 409);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'teacher')")
            ->execute([trim($body['first_name']).' '.trim($body['last_name']), trim($body['email']), password_hash($body['password'], PASSWORD_BCRYPT)]);
        $user_id = $pdo->lastInsertId();
        $teacher_code = APP_SHORT.'-TCH-'.date('Y').'-'.str_pad($user_id,4,'0',STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO teachers (user_id,teacher_code,first_name,last_name,specialization,phone,hire_date) VALUES (?,?,?,?,?,?,?)")
            ->execute([$user_id,$teacher_code,trim($body['first_name']),trim($body['last_name']),$body['specialization']??null,$body['phone']??null,date('Y-m-d')]);
        $tid = $pdo->lastInsertId();
        $pdo->commit();
        api_response(['teacher_id'=>$tid,'teacher_code'=>$teacher_code,'user_id'=>$user_id,'message'=>'Teacher created.'], 201);
    } catch(Exception $e) { $pdo->rollBack(); api_error('Creation failed: '.$e->getMessage(), 500); }
}

// â”€â”€ UPDATE STUDENT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'update_student') {
    if (!has_perm($permissions, 'students:write')) api_error('Permission denied.', 403);
    if ($method !== 'POST') api_error('POST required.', 405);
    $id = (int)($body['student_id'] ?? 0);
    if (!$id) api_error('Missing student_id.', 422);
    $s = $pdo->prepare("SELECT * FROM students WHERE id=?"); $s->execute([$id]); $s = $s->fetch();
    if (!$s) api_error('Student not found.', 404);
    $fields = ['first_name','last_name','dob','gender','nationality','phone','address','status'];
    $sets = []; $params = [];
    foreach ($fields as $f) { if (isset($body[$f])) { $sets[] = "$f=?"; $params[] = $body[$f]; } }
    if (empty($sets)) api_error('No fields to update.', 422);
    $params[] = $id;
    $pdo->prepare("UPDATE students SET ".implode(',',$sets)." WHERE id=?")->execute($params);
    api_response(['student_id'=>$id,'message'=>'Student updated.']);
}

// â”€â”€ ENROLL STUDENT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'enroll') {
    if (!has_perm($permissions, 'enrollments:write')) api_error('Permission denied.', 403);
    if ($method !== 'POST') api_error('POST required.', 405);
    if (empty($body['student_id'])||empty($body['class_id'])) api_error('Missing student_id or class_id.', 422);
    $sid = (int)$body['student_id']; $cid = (int)$body['class_id'];
    $chk = $pdo->prepare("SELECT id FROM enrollments WHERE student_id=? AND class_id=?"); $chk->execute([$sid,$cid]);
    if ($chk->fetch()) api_error('Already enrolled.', 409);
    $pdo->prepare("INSERT INTO enrollments (student_id,class_id) VALUES (?,?)")->execute([$sid,$cid]);
    api_response(['enrollment_id'=>$pdo->lastInsertId(),'message'=>'Enrolled successfully.'], 201);
}

// â”€â”€ GRADES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'grades') {
    if (!has_perm($permissions, 'grades:read')) api_error('Permission denied.', 403);
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) api_error('Missing student_id.', 422);
    $grades = $pdo->prepare("SELECT g.*, ex.title AS exam_title, ex.total_marks, ex.type AS exam_type, co.name AS course_name, co.code FROM grades g JOIN enrollments en ON g.enrollment_id=en.id JOIN exams ex ON g.exam_id=ex.id JOIN classes cl ON en.class_id=cl.id JOIN courses co ON cl.course_id=co.id WHERE en.student_id=? ORDER BY ex.exam_date DESC");
    $grades->execute([$student_id]); api_response($grades->fetchAll());
}

// â”€â”€ ATTENDANCE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($endpoint === 'attendance') {
    if (!has_perm($permissions, 'attendance:read')) api_error('Permission denied.', 403);
    $student_id = (int)($_GET['student_id'] ?? 0);
    $class_id   = (int)($_GET['class_id'] ?? 0);
    if (!$student_id && !$class_id) api_error('Provide student_id or class_id.', 422);
    $sql = "SELECT a.*, s.student_code, CONCAT(s.first_name,' ',s.last_name) AS student_name FROM attendance a JOIN enrollments en ON a.enrollment_id=en.id JOIN students s ON en.student_id=s.id WHERE 1=1";
    $params = [];
    if ($student_id) { $sql .= " AND en.student_id=?"; $params[] = $student_id; }
    if ($class_id)   { $sql .= " AND en.class_id=?";   $params[] = $class_id; }
    $sql .= " ORDER BY a.date DESC LIMIT 200";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); api_response($stmt->fetchAll());
}

api_error("Unknown endpoint: '$endpoint'", 404);
