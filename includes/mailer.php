<?php
/**
 * send_email() — sends via Brevo REST API (HTTPS port 443, never blocked)
 */
function send_email($to, $to_name, $subject, $html_body) {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) return true;
    if (!defined('BREVO_API_KEY') || !BREVO_API_KEY) return 'BREVO_API_KEY not configured.';

    $payload = json_encode([
        'sender'     => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'         => [['email' => $to, 'name' => $to_name]],
        'subject'    => $subject,
        'htmlContent'=> $html_body,
        'textContent'=> strip_tags(str_replace(['<br>','<br/>','</p>','</div>'], "\n", $html_body)),
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return 'cURL error: ' . $err;

    $data = json_decode($resp, true);
    if ($code === 201 && isset($data['messageId'])) return true;

    $msg = $data['message'] ?? $resp;
    error_log('Brevo API error: ' . $msg);
    return 'Brevo error: ' . $msg;
}

function email_template($title, $body_html, $action_url = '', $action_label = '') {
    $app  = defined('APP_NAME') ? APP_NAME : 'EduManage Pro';
    $year = date('Y');
    $btn  = $action_url ? "<div style='text-align:center;margin:28px 0'><a href='$action_url' style='background:#4361ee;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px'>$action_label</a></div>" : '';
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='margin:0;padding:0;background:#f0f2f8;font-family:Segoe UI,sans-serif'>
<table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center' style='padding:32px 16px'>
<table width='560' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)'>
  <tr><td style='background:linear-gradient(135deg,#4361ee,#7209b7);padding:28px 32px;text-align:center'>
    <div style='font-size:28px'>🎓</div>
    <div style='color:#fff;font-size:20px;font-weight:800;margin-top:6px'>$app</div>
    <div style='color:rgba(255,255,255,.7);font-size:13px;margin-top:4px'>$title</div>
  </td></tr>
  <tr><td style='padding:32px'>$body_html $btn</td></tr>
  <tr><td style='background:#f8f9ff;padding:16px 32px;text-align:center;font-size:12px;color:#aaa'>
    © $year $app · This is an automated message, please do not reply.
  </td></tr>
</table></td></tr></table></body></html>";
}
