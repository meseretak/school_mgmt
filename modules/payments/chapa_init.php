<?php
require_once '../../includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name,
    CONCAT(s.first_name,' ',s.last_name) AS student_name,
    s.student_code, s.first_name, s.last_name, s.phone,
    u.email
    FROM payments p
    JOIN students s ON p.student_id=s.id
    JOIN users u ON s.user_id=u.id
    JOIN fee_types ft ON p.fee_type_id=ft.id
    WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) {
    flash('Invalid payment.','error');
    header('Location: index.php'); exit;
}
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

if (!$chapa_key) {
    flash('Chapa is not configured. Please add CHAPA_SECRET_KEY to config.php.','error');
    header('Location: pay.php?id='.$payment_id); exit;
}

// Unique transaction reference
$tx_ref = 'SCH-'.$payment['student_code'].'-'.time();

$payload = json_encode([
    'amount'       => (string)$amount,
    'currency'     => 'ETB',
    'email'        => $payment['email'],
    'first_name'   => $payment['first_name'] ?? 'Student',
    'last_name'    => $payment['last_name'] ?? '',
    'phone_number' => $payment['phone'] ?: '0900000000',
    'tx_ref'       => $tx_ref,
    'callback_url' => BASE_URL.'/modules/payments/chapa_callback.php',
    'return_url'   => BASE_URL.'/modules/payments/chapa_return.php?payment_id='.$payment_id.'&tx_ref='.urlencode($tx_ref),
    'customization'=> (object)[
        'title'       => APP_NAME.' Fee Payment',
        'description' => $payment['fee_name'],
    ],
]);

$ch = curl_init('https://api.chapa.co/v1/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer '.$chapa_key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$resp = json_decode(curl_exec($ch), true);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    flash('Connection error: '.$err,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}

if (!empty($resp['status']) && $resp['status'] === 'success' && !empty($resp['data']['checkout_url'])) {
    // Store tx_ref so callback can match it
    $pdo->prepare("UPDATE payments SET reference_no=?, method='Chapa' WHERE id=?")
        ->execute([$tx_ref, $payment_id]);
    log_activity($pdo, 'chapa_initiated', "Chapa payment initiated. tx_ref: $tx_ref, amount: $amount");
    header('Location: '.$resp['data']['checkout_url']); exit;
} else {
    $msg = $resp['message'] ?? 'Unknown error from Chapa.';
    flash('Chapa error: '.$msg,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name,
    CONCAT(s.first_name,' ',s.last_name) AS student_name,
    s.student_code, s.first_name, s.last_name, s.phone,
    u.email
    FROM payments p
    JOIN students s ON p.student_id=s.id
    JOIN users u ON s.user_id=u.id
    JOIN fee_types ft ON p.fee_type_id=ft.id
    WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) {
    flash('Invalid payment.','error');
    header('Location: index.php'); exit;
}
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

if (!$chapa_key) {
    flash('Chapa is not configured. Please add CHAPA_SECRET_KEY to config.php.','error');
    header('Location: pay.php?id='.$payment_id); exit;
}

// Unique transaction reference
$tx_ref = 'SCH-'.$payment['student_code'].'-'.time();

$payload = json_encode([
    'amount'       => (string)$amount,
    'currency'     => 'ETB',
    'email'        => $payment['email'],
    'first_name'   => $payment['first_name'] ?? 'Student',
    'last_name'    => $payment['last_name'] ?? '',
    'phone_number' => $payment['phone'] ?: '0900000000',
    'tx_ref'       => $tx_ref,
    'callback_url' => BASE_URL.'/modules/payments/chapa_callback.php',
    'return_url'   => BASE_URL.'/modules/payments/chapa_return.php?payment_id='.$payment_id.'&tx_ref='.urlencode($tx_ref),
    'customization'=> (object)[
        'title'       => APP_NAME.' Fee Payment',
        'description' => $payment['fee_name'],
    ],
]);

$ch = curl_init('https://api.chapa.co/v1/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer '.$chapa_key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$resp = json_decode(curl_exec($ch), true);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    flash('Connection error: '.$err,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}

if (!empty($resp['status']) && $resp['status'] === 'success' && !empty($resp['data']['checkout_url'])) {
    // Store tx_ref so callback can match it
    $pdo->prepare("UPDATE payments SET reference_no=?, method='Chapa' WHERE id=?")
        ->execute([$tx_ref, $payment_id]);
    log_activity($pdo, 'chapa_initiated', "Chapa payment initiated. tx_ref: $tx_ref, amount: $amount");
    header('Location: '.$resp['data']['checkout_url']); exit;
} else {
    $msg = $resp['message'] ?? 'Unknown error from Chapa.';
    flash('Chapa error: '.$msg,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name,
    CONCAT(s.first_name,' ',s.last_name) AS student_name,
    s.student_code, s.first_name, s.last_name, s.phone,
    u.email
    FROM payments p
    JOIN students s ON p.student_id=s.id
    JOIN users u ON s.user_id=u.id
    JOIN fee_types ft ON p.fee_type_id=ft.id
    WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) {
    flash('Invalid payment.','error');
    header('Location: index.php'); exit;
}
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

if (!$chapa_key) {
    flash('Chapa is not configured. Please add CHAPA_SECRET_KEY to config.php.','error');
    header('Location: pay.php?id='.$payment_id); exit;
}

// Unique transaction reference
$tx_ref = 'SCH-'.$payment['student_code'].'-'.time();

$payload = json_encode([
    'amount'       => (string)$amount,
    'currency'     => 'ETB',
    'email'        => $payment['email'],
    'first_name'   => $payment['first_name'] ?? 'Student',
    'last_name'    => $payment['last_name'] ?? '',
    'phone_number' => $payment['phone'] ?: '0900000000',
    'tx_ref'       => $tx_ref,
    'callback_url' => BASE_URL.'/modules/payments/chapa_callback.php',
    'return_url'   => BASE_URL.'/modules/payments/chapa_return.php?payment_id='.$payment_id.'&tx_ref='.urlencode($tx_ref),
    'customization'=> (object)[
        'title'       => APP_NAME.' Fee Payment',
        'description' => $payment['fee_name'],
    ],
]);

$ch = curl_init('https://api.chapa.co/v1/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer '.$chapa_key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$resp = json_decode(curl_exec($ch), true);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    flash('Connection error: '.$err,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}

if (!empty($resp['status']) && $resp['status'] === 'success' && !empty($resp['data']['checkout_url'])) {
    // Store tx_ref so callback can match it
    $pdo->prepare("UPDATE payments SET reference_no=?, method='Chapa' WHERE id=?")
        ->execute([$tx_ref, $payment_id]);
    log_activity($pdo, 'chapa_initiated', "Chapa payment initiated. tx_ref: $tx_ref, amount: $amount");
    header('Location: '.$resp['data']['checkout_url']); exit;
} else {
    $msg = $resp['message'] ?? 'Unknown error from Chapa.';
    flash('Chapa error: '.$msg,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check(['student','admin']);
csrf_check();

$payment_id = (int)($_POST['payment_id'] ?? 0);
$amount     = (float)($_POST['amount'] ?? 0);

$stmt = $pdo->prepare("SELECT p.*, ft.name AS fee_name,
    CONCAT(s.first_name,' ',s.last_name) AS student_name,
    s.student_code, s.first_name, s.last_name, s.phone,
    u.email
    FROM payments p
    JOIN students s ON p.student_id=s.id
    JOIN users u ON s.user_id=u.id
    JOIN fee_types ft ON p.fee_type_id=ft.id
    WHERE p.id=?");
$stmt->execute([$payment_id]); $payment = $stmt->fetch();

if (!$payment || $amount <= 0) {
    flash('Invalid payment.','error');
    header('Location: index.php'); exit;
}
if (is_student()) {
    $own = get_student_record($pdo);
    if (!$own || $own['id'] !== $payment['student_id']) deny();
}

$chapa_key = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

if (!$chapa_key) {
    flash('Chapa is not configured. Please add CHAPA_SECRET_KEY to config.php.','error');
    header('Location: pay.php?id='.$payment_id); exit;
}

// Unique transaction reference
$tx_ref = 'SCH-'.$payment['student_code'].'-'.time();

$payload = json_encode([
    'amount'       => (string)$amount,
    'currency'     => 'ETB',
    'email'        => $payment['email'],
    'first_name'   => $payment['first_name'] ?? 'Student',
    'last_name'    => $payment['last_name'] ?? '',
    'phone_number' => $payment['phone'] ?: '0900000000',
    'tx_ref'       => $tx_ref,
    'callback_url' => BASE_URL.'/modules/payments/chapa_callback.php',
    'return_url'   => BASE_URL.'/modules/payments/chapa_return.php?payment_id='.$payment_id.'&tx_ref='.urlencode($tx_ref),
    'customization'=> (object)[
        'title'       => APP_NAME.' Fee Payment',
        'description' => $payment['fee_name'],
    ],
]);

$ch = curl_init('https://api.chapa.co/v1/transaction/initialize');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer '.$chapa_key,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);
$resp = json_decode(curl_exec($ch), true);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    flash('Connection error: '.$err,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}

if (!empty($resp['status']) && $resp['status'] === 'success' && !empty($resp['data']['checkout_url'])) {
    // Store tx_ref so callback can match it
    $pdo->prepare("UPDATE payments SET reference_no=?, method='Chapa' WHERE id=?")
        ->execute([$tx_ref, $payment_id]);
    log_activity($pdo, 'chapa_initiated', "Chapa payment initiated. tx_ref: $tx_ref, amount: $amount");
    header('Location: '.$resp['data']['checkout_url']); exit;
} else {
    $msg = $resp['message'] ?? 'Unknown error from Chapa.';
    flash('Chapa error: '.$msg,'error');
    header('Location: pay.php?id='.$payment_id); exit;
}
