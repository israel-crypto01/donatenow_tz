<?php
// backend/notifications/email.php — PHPMailer Email Service
// composer require phpmailer/phpmailer
require_once __DIR__.'/../config/env.php';
$autoload = __DIR__.'/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}
use PHPMailer\PHPMailer\PHPMailer;

function mailer(): ?PHPMailer {
    if (!class_exists(PHPMailer::class)) {
        error_log('PHPMailer not installed. Run composer install to enable email.');
        return null;
    }
    $m = new PHPMailer(true);
    $m->isSMTP(); $m->Host=MAIL_HOST; $m->SMTPAuth=true;
    $m->Username=MAIL_USER; $m->Password=MAIL_PASS;
    $m->SMTPSecure=MAIL_ENC==='tls'?PHPMailer::ENCRYPTION_STARTTLS:PHPMailer::ENCRYPTION_SMTPS;
    $m->Port=MAIL_PORT; $m->setFrom(MAIL_USER,MAIL_FROM_NAME);
    $m->CharSet='UTF-8'; $m->isHTML(true); return $m;
}

function email_wrap(string $title, string $body): string {
    return <<<HTML
<!DOCTYPE html><html><body style="margin:0;padding:0;background:#0E0C09;font-family:'DM Sans',Arial,sans-serif;">
<table width="100%" style="padding:32px 20px;"><tr><td align="center">
<table width="580" style="background:#1E1A14;border-radius:12px;overflow:hidden;border:1px solid rgba(201,168,76,.15);">
<tr><td style="background:linear-gradient(135deg,#D94F1E,#B33E16);padding:22px 28px;">
<h1 style="color:#fff;margin:0;font-size:1.3rem;">🌍 DONATE<span style="color:#C9A84C">NOW</span> Tanzania</h1>
<p style="color:rgba(255,255,255,.6);margin:4px 0 0;font-size:.8rem;">Pamoja Tunaweza — Together We Can</p>
</td></tr>
<tr><td style="padding:28px;">{$body}</td></tr>
<tr><td style="padding:18px 28px;border-top:1px solid rgba(201,168,76,.12);text-align:center;">
<p style="color:#4A4540;font-size:.72rem;margin:0;">DONATE NOW Tanzania · Ohio St, Dar es Salaam · support@donatenow.co.tz</p>
<p style="color:#4A4540;font-size:.68rem;margin:5px 0 0;">BOT MFS Regulated · BRELA Registered · TRA Compliant</p>
</td></tr></table></td></tr></table></body></html>
HTML;
}

function send_verification_email(string $to, string $name, string $token): bool {
    $link = APP_URL.'/backend/auth/verify.php?token='.urlencode($token);
    $body = email_wrap('Verify Your Email', <<<HTML
<h2 style="color:#F0E8DC;font-size:1.4rem;margin:0 0 10px;">Karibu, {$name}! 👋</h2>
<p style="color:#7A6E60;line-height:1.8;">Thank you for registering on DONATE NOW Tanzania. Please verify your email address to activate your account and start making a difference.</p>
<div style="text-align:center;margin:28px 0;">
<a href="{$link}" style="display:inline-block;padding:14px 32px;background:#D94F1E;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.95rem;">✉️ Verify My Email</a>
</div>
<p style="color:#4A4540;font-size:.8rem;">This link expires in 24 hours. If you did not register, ignore this email.</p>
HTML);
    try {
        $m=mailer(); if (!$m) return false; $m->addAddress($to,$name);
        $m->Subject='Verify your DONATE NOW Tanzania account';
        $m->Body=$body; $m->AltBody="Karibu $name! Verify: $link"; $m->send(); return true;
    } catch(Throwable $e){ error_log('Verify email failed: '.$e->getMessage()); return false; }
}

function send_donation_receipt(string $to, string $name, string $ref,
    int $amount, string $campaign, string $method): bool {
    $fmt=number_format($amount); $date=date('d M Y H:i');
    $body = email_wrap('Donation Receipt', <<<HTML
<h2 style="color:#F0E8DC;font-size:1.3rem;margin:0 0 10px;">Asante sana, {$name}! 💝</h2>
<p style="color:#7A6E60;line-height:1.8;">Your donation has been received and confirmed. Here is your official receipt:</p>
<table width="100%" style="background:#16130E;border-radius:8px;border:1px solid rgba(201,168,76,.12);margin:20px 0;overflow:hidden;border-collapse:collapse;">
<tr><td style="padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.04);"><span style="color:#4A4540;font-size:.75rem;">Reference</span><div style="color:#F0E8DC;font-weight:700;font-family:monospace;margin-top:2px;">{$ref}</div></td></tr>
<tr><td style="padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.04);"><span style="color:#4A4540;font-size:.75rem;">Campaign</span><div style="color:#F0E8DC;font-weight:700;margin-top:2px;">{$campaign}</div></td></tr>
<tr><td style="padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.04);"><span style="color:#4A4540;font-size:.75rem;">Amount</span><div style="color:#2C5F2E;font-weight:700;font-size:1.3rem;font-family:monospace;margin-top:2px;">TZS {$fmt}</div></td></tr>
<tr><td style="padding:13px 16px;border-bottom:1px solid rgba(255,255,255,.04);"><span style="color:#4A4540;font-size:.75rem;">Payment Method</span><div style="color:#F0E8DC;margin-top:2px;">{$method}</div></td></tr>
<tr><td style="padding:13px 16px;"><span style="color:#4A4540;font-size:.75rem;">Date &amp; Time</span><div style="color:#F0E8DC;margin-top:2px;">{$date} EAT</div></td></tr>
</table>
<p style="color:#7A6E60;font-size:.82rem;line-height:1.8;">This receipt is valid for TRA tax deduction purposes. Thank you for helping build a better Tanzania.</p>
<div style="text-align:center;margin-top:22px;">
<a href="'.APP_URL.'/index.html#dashboard" style="display:inline-block;padding:12px 28px;background:#2C5F2E;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem;">View Dashboard 📊</a>
</div>
HTML);
    try {
        $m=mailer(); if (!$m) return false; $m->addAddress($to,$name);
        $m->Subject="🧾 Donation Receipt — TZS {$fmt} — {$ref}";
        $m->Body=$body; $m->AltBody="Receipt for TZS $fmt donated to $campaign. Ref: $ref."; $m->send(); return true;
    } catch(Throwable $e){ error_log('Receipt email failed: '.$e->getMessage()); return false; }
}

function send_password_reset_email(string $to, string $name, string $token): bool {
    $link=APP_URL.'/index.html#login';
    $body=email_wrap('Reset Your Password', <<<HTML
<h2 style="color:#F0E8DC;font-size:1.3rem;margin:0 0 10px;">Password Reset</h2>
<p style="color:#7A6E60;line-height:1.8;">Hi {$name}, we received a request to reset your DONATE NOW password. Click below to set a new one:</p>
<div style="text-align:center;margin:28px 0;">
<a href="{$link}" style="display:inline-block;padding:14px 32px;background:#D94F1E;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">🔐 Reset Password</a>
</div>
<p style="color:#4A4540;font-size:.8rem;">This link expires in 1 hour. If you did not request a reset, ignore this email — your account is safe.</p>
HTML);
    try {
        $m=mailer(); if (!$m) return false; $m->addAddress($to,$name);
        $m->Subject='Reset your DONATE NOW Tanzania password';
        $m->Body=$body; $m->AltBody="Reset: $link"; $m->send(); return true;
    } catch(Throwable $e){ error_log('Reset email: '.$e->getMessage()); return false; }
}

function send_campaign_status_email(string $to, string $name,
    string $campaign, string $status, string $reason=''): bool {
    $ok=$status==='approved'; $icon=$ok?'✅':'❌'; $col=$ok?'#2C5F2E':'#D94F1E';
    $msg=$ok?'Congratulations! Your campaign is now live.':
              "We're sorry, your campaign was not approved. Reason: $reason";
    $body=email_wrap("Campaign $status", <<<HTML
<h2 style="color:#F0E8DC;font-size:1.3rem;margin:0 0 10px;">{$icon} Campaign {$status}</h2>
<p style="color:#7A6E60;line-height:1.8;">Hi {$name}, your campaign <strong style="color:#F0E8DC;">&ldquo;{$campaign}&rdquo;</strong> has been reviewed.</p>
<div style="padding:16px;background:#16130E;border-left:3px solid {$col};border-radius:4px;margin:20px 0;">
<p style="color:#F0E8DC;margin:0;font-weight:500;">{$msg}</p>
</div>
<div style="text-align:center;margin-top:22px;">
<a href="'.APP_URL.'/index.html#dashboard" style="display:inline-block;padding:12px 28px;background:#D94F1E;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Go to Dashboard</a>
</div>
HTML);
    try {
        $m=mailer(); if (!$m) return false; $m->addAddress($to,$name);
        $m->Subject="{$icon} Your campaign has been {$status} — DONATE NOW";
        $m->Body=$body; $m->AltBody="Your campaign '$campaign' has been $status. $msg"; $m->send(); return true;
    } catch(Throwable $e){ error_log('Campaign email: '.$e->getMessage()); return false; }
}
