<?php
require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount_paid'] ?? $_POST['amount'] ?? 0);
$reference  = trim($_POST['reference'] ?? '');
$method     = $_POST['method'] ?? 'Bank Transfer';

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name, ay.label AS year, CONCAT(s.first_name,' ',s.last_name) AS student_name, s.student_code, u.email FROM payments p JOIN fee_types ft ON p.fee_type_id=ft.id LEFT JOIN academic_years ay ON p.academic_year_id=ay.id JOIN students s ON p.student_id=s.id JOIN users u ON s.user_id=u.id WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) { flash('Invalid payment.','error'); header('Location: index.php'); exit; }
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$new_paid   = $payment['amount_paid'] + $amount;
$new_status = $new_paid >= $payment['amount_due'] ? 'Paid' : 'Partial';
$ref        = $reference ?: 'TXN-'.strtoupper(substr(md5(uniqid()),0,10));

$pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method=?, reference_no=? WHERE id=?")
    ->execute([$new_paid, $new_status, date('Y-m-d'), $method, $ref, $payment_id]);

require_once '../../includes/notify.php';
notify_payment_recorded($pdo, $payment_id);
log_activity($pdo, 'payment_made', "Payment \$$amount via $method. Ref: $ref");

// Send receipt email
require_once '../../includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
SERVER['DOCUMENT_ROOT'].'/includes/mailer.php';
if (MAIL_ENABLED && $payment['email']) {
    $body = "<p>Dear <strong>{$payment['student_name']}</strong>,</p>
    <p>Your payment has been received.</p>
    <table style='width:100%;border-collapse:collapse;font-size:.9rem;margin:16px 0'>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Fee</strong></td><td style='padding:10px;border:1px solid #eee'>{$payment['fee_name']}</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Amount</strong></td><td style='padding:10px;border:1px solid #eee;color:#2dc653'><strong>\$$amount</strong></td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Method</strong></td><td style='padding:10px;border:1px solid #eee'>$method</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Reference</strong></td><td style='padding:10px;border:1px solid #eee;font-family:monospace'>$ref</td></tr>
      <tr style='background:#f8f9ff'><td style='padding:10px;border:1px solid #eee'><strong>Status</strong></td><td style='padding:10px;border:1px solid #eee'>$new_status</td></tr>
      <tr><td style='padding:10px;border:1px solid #eee'><strong>Date</strong></td><td style='padding:10px;border:1px solid #eee'>".date('F j, Y')."</td></tr>
    </table>";
    send_email($payment['email'], $payment['student_name'], APP_NAME.' â€” Payment Receipt', email_template('Payment Receipt', $body));
}

header("Location: pay_success.php?id=$payment_id&ref=".urlencode($ref)."&amount=$amount&method=".urlencode($method));
exit;
