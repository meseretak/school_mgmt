<?php
/**
 * Chapa Webhook Callback
 * Chapa POSTs to this URL after payment â€” verify and update DB
 */
require_once '../../includes/config.php';

$tx_ref = $_GET['trx_ref'] ?? $_POST['tx_ref'] ?? '';
if (!$tx_ref) { http_response_code(400); echo 'Missing tx_ref'; exit; }

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';
if (!$chapa_key) { http_response_code(500); echo 'Not configured'; exit; }

// Verify with Chapa
$ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($resp['status']) || $resp['status'] !== 'success') {
    http_response_code(400); echo 'Verification failed'; exit;
}

$data   = $resp['data'];
$status = $data['status'] ?? '';
$amount = (float)($data['amount'] ?? 0);

if ($status !== 'success') {
    http_response_code(200); echo 'Payment not successful'; exit;
}

// Find payment by tx_ref
$pay = $pdo->prepare("SELECT * FROM payments WHERE reference_no=?");
$pay->execute([$tx_ref]); $pay = $pay->fetch();

if (!$pay) { http_response_code(404); echo 'Payment not found'; exit; }

// Avoid double-processing
if ($pay['status'] === 'Paid') { http_response_code(200); echo 'Already processed'; exit; }

$new_paid   = $pay['amount_paid'] + $amount;
$new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa' WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $pay['id']]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $pay['id']);

// Send receipt email
$student = $pdo->prepare("SELECT u.email, u.name FROM users u JOIN students s ON s.user_id=u.id WHERE s.id=?");
$student->execute([$pay['student_id']]); $student = $student->fetch();
if ($student) {
    require_once '../../includes/mailer.php';
    $ft = $pdo->prepare("SELECT name FROM fee_types WHERE id=?");
    $ft->execute([$pay['fee_type_id']]); $ft = $ft->fetchColumn();
    $body = "<p>Dear <strong>{$student['name']}</strong>,</p>
    <p>Your Chapa payment of <strong>\$$amount</strong> for <strong>$ft</strong> was successful.</p>
    <p>Transaction Reference: <strong style='font-family:monospace'>$tx_ref</strong></p>
    <p>Status: <strong style='color:#2dc653'>$new_status</strong></p>";
    send_email($student['email'], $student['name'], APP_NAME.' â€” Payment Confirmed', email_template('Payment Confirmed', $body));
}

http_response_code(200);
echo json_encode(['status'=>'ok']);
SERVER['DOCUMENT_ROOT'].'/includes/config.php';

$tx_ref = $_GET['trx_ref'] ?? $_POST['tx_ref'] ?? '';
if (!$tx_ref) { http_response_code(400); echo 'Missing tx_ref'; exit; }

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';
if (!$chapa_key) { http_response_code(500); echo 'Not configured'; exit; }

// Verify with Chapa
$ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($resp['status']) || $resp['status'] !== 'success') {
    http_response_code(400); echo 'Verification failed'; exit;
}

$data   = $resp['data'];
$status = $data['status'] ?? '';
$amount = (float)($data['amount'] ?? 0);

if ($status !== 'success') {
    http_response_code(200); echo 'Payment not successful'; exit;
}

// Find payment by tx_ref
$pay = $pdo->prepare("SELECT * FROM payments WHERE reference_no=?");
$pay->execute([$tx_ref]); $pay = $pay->fetch();

if (!$pay) { http_response_code(404); echo 'Payment not found'; exit; }

// Avoid double-processing
if ($pay['status'] === 'Paid') { http_response_code(200); echo 'Already processed'; exit; }

$new_paid   = $pay['amount_paid'] + $amount;
$new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa' WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $pay['id']]);

require_once \<?php
/**
 * Chapa Webhook Callback
 * Chapa POSTs to this URL after payment â€” verify and update DB
 */
require_once '../../includes/config.php';

$tx_ref = $_GET['trx_ref'] ?? $_POST['tx_ref'] ?? '';
if (!$tx_ref) { http_response_code(400); echo 'Missing tx_ref'; exit; }

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';
if (!$chapa_key) { http_response_code(500); echo 'Not configured'; exit; }

// Verify with Chapa
$ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($resp['status']) || $resp['status'] !== 'success') {
    http_response_code(400); echo 'Verification failed'; exit;
}

$data   = $resp['data'];
$status = $data['status'] ?? '';
$amount = (float)($data['amount'] ?? 0);

if ($status !== 'success') {
    http_response_code(200); echo 'Payment not successful'; exit;
}

// Find payment by tx_ref
$pay = $pdo->prepare("SELECT * FROM payments WHERE reference_no=?");
$pay->execute([$tx_ref]); $pay = $pay->fetch();

if (!$pay) { http_response_code(404); echo 'Payment not found'; exit; }

// Avoid double-processing
if ($pay['status'] === 'Paid') { http_response_code(200); echo 'Already processed'; exit; }

$new_paid   = $pay['amount_paid'] + $amount;
$new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa' WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $pay['id']]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $pay['id']);

// Send receipt email
$student = $pdo->prepare("SELECT u.email, u.name FROM users u JOIN students s ON s.user_id=u.id WHERE s.id=?");
$student->execute([$pay['student_id']]); $student = $student->fetch();
if ($student) {
    require_once '../../includes/mailer.php';
    $ft = $pdo->prepare("SELECT name FROM fee_types WHERE id=?");
    $ft->execute([$pay['fee_type_id']]); $ft = $ft->fetchColumn();
    $body = "<p>Dear <strong>{$student['name']}</strong>,</p>
    <p>Your Chapa payment of <strong>\$$amount</strong> for <strong>$ft</strong> was successful.</p>
    <p>Transaction Reference: <strong style='font-family:monospace'>$tx_ref</strong></p>
    <p>Status: <strong style='color:#2dc653'>$new_status</strong></p>";
    send_email($student['email'], $student['name'], APP_NAME.' â€” Payment Confirmed', email_template('Payment Confirmed', $body));
}

http_response_code(200);
echo json_encode(['status'=>'ok']);
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
notify_payment_recorded($pdo, $pay['id']);

// Send receipt email
$student = $pdo->prepare("SELECT u.email, u.name FROM users u JOIN students s ON s.user_id=u.id WHERE s.id=?");
$student->execute([$pay['student_id']]); $student = $student->fetch();
if ($student) {
    require_once \<?php
/**
 * Chapa Webhook Callback
 * Chapa POSTs to this URL after payment â€” verify and update DB
 */
require_once '../../includes/config.php';

$tx_ref = $_GET['trx_ref'] ?? $_POST['tx_ref'] ?? '';
if (!$tx_ref) { http_response_code(400); echo 'Missing tx_ref'; exit; }

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';
if (!$chapa_key) { http_response_code(500); echo 'Not configured'; exit; }

// Verify with Chapa
$ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($resp['status']) || $resp['status'] !== 'success') {
    http_response_code(400); echo 'Verification failed'; exit;
}

$data   = $resp['data'];
$status = $data['status'] ?? '';
$amount = (float)($data['amount'] ?? 0);

if ($status !== 'success') {
    http_response_code(200); echo 'Payment not successful'; exit;
}

// Find payment by tx_ref
$pay = $pdo->prepare("SELECT * FROM payments WHERE reference_no=?");
$pay->execute([$tx_ref]); $pay = $pay->fetch();

if (!$pay) { http_response_code(404); echo 'Payment not found'; exit; }

// Avoid double-processing
if ($pay['status'] === 'Paid') { http_response_code(200); echo 'Already processed'; exit; }

$new_paid   = $pay['amount_paid'] + $amount;
$new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa' WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $pay['id']]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $pay['id']);

// Send receipt email
$student = $pdo->prepare("SELECT u.email, u.name FROM users u JOIN students s ON s.user_id=u.id WHERE s.id=?");
$student->execute([$pay['student_id']]); $student = $student->fetch();
if ($student) {
    require_once '../../includes/mailer.php';
    $ft = $pdo->prepare("SELECT name FROM fee_types WHERE id=?");
    $ft->execute([$pay['fee_type_id']]); $ft = $ft->fetchColumn();
    $body = "<p>Dear <strong>{$student['name']}</strong>,</p>
    <p>Your Chapa payment of <strong>\$$amount</strong> for <strong>$ft</strong> was successful.</p>
    <p>Transaction Reference: <strong style='font-family:monospace'>$tx_ref</strong></p>
    <p>Status: <strong style='color:#2dc653'>$new_status</strong></p>";
    send_email($student['email'], $student['name'], APP_NAME.' â€” Payment Confirmed', email_template('Payment Confirmed', $body));
}

http_response_code(200);
echo json_encode(['status'=>'ok']);
SERVER['DOCUMENT_ROOT'].'/includes/mailer.php';
    $ft = $pdo->prepare("SELECT name FROM fee_types WHERE id=?");
    $ft->execute([$pay['fee_type_id']]); $ft = $ft->fetchColumn();
    $body = "<p>Dear <strong>{$student['name']}</strong>,</p>
    <p>Your Chapa payment of <strong>\$$amount</strong> for <strong>$ft</strong> was successful.</p>
    <p>Transaction Reference: <strong style='font-family:monospace'>$tx_ref</strong></p>
    <p>Status: <strong style='color:#2dc653'>$new_status</strong></p>";
    send_email($student['email'], $student['name'], APP_NAME.' â€” Payment Confirmed', email_template('Payment Confirmed', $body));
}

http_response_code(200);
echo json_encode(['status'=>'ok']);
