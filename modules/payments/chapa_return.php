<?php
require_once '../../includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/config.php';
auth_check();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$tx_ref     = $_GET['tx_ref'] ?? '';
$chapa_key  = defined('CHAPA_SECRET_KEY') ? CHAPA_SECRET_KEY : '';

// Verify payment status with Chapa
$verified = false; $amount = 0;
if ($tx_ref && $chapa_key) {
    $ch = curl_init('https://api.chapa.co/v1/transaction/verify/'.urlencode($tx_ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$chapa_key],
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!empty($resp['data']['status']) && $resp['data']['status'] === 'success') {
        $verified = true;
        $amount   = (float)($resp['data']['amount'] ?? 0);

        // Update payment if not already done by callback
        $pay = $pdo->prepare("SELECT * FROM payments WHERE id=?");
        $pay->execute([$payment_id]); $pay = $pay->fetch();

        if ($pay && $pay['status'] !== 'Paid') {
            $new_paid   = $pay['amount_paid'] + $amount;
            $new_status = $new_paid >= $pay['amount_due'] ? 'Paid' : 'Partial';
            $pdo->prepare("UPDATE payments SET amount_paid=?, status=?, paid_date=?, method='Chapa', reference_no=? WHERE id=?")
                ->execute([$new_paid, $new_status, date('Y-m-d'), $tx_ref, $payment_id]);
            require_once '../../includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
SERVER['DOCUMENT_ROOT'].'/includes/notify.php';
            notify_payment_recorded($pdo, $payment_id);
        }
    }
}

if ($verified) {
    header("Location: pay_success.php?id=$payment_id&ref=".urlencode($tx_ref)."&amount=$amount&method=Chapa");
} else {
    flash('Payment could not be verified. If you were charged, please contact support with reference: '.$tx_ref,'error');
    header("Location: pay.php?id=$payment_id");
}
exit;
