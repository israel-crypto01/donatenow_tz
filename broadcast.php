<?php
// backend/admin/broadcast.php — Broadcast Email/SMS to users
// POST {recipients: 'all'|'donors'|'ngos', channel: 'email'|'sms'|'both', subject, message}
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once '../notifications/email.php';
require_once '../notifications/sms.php';
header('Content-Type: application/json'); cors(); session_secure();
$sess = require_admin(); $db = getDB();
if ($_SERVER['REQUEST_METHOD']!=='POST') json_error('Method Not Allowed',405);
$d = json_decode(file_get_contents('php://input'),true)??[];
$recipients = sanitize($d['recipients']??'all');
$channel    = sanitize($d['channel']??'both');
$subject    = sanitize($d['subject']??'');
$message    = sanitize($d['message']??'');
if (!$subject||!$message) json_error('Subject and message required',422);

$where = match($recipients) {
    'donors' => "role='donor'",
    'ngos'   => "role='ngo'",
    default  => "role IN ('donor','ngo')",
};
$users = $db->query("SELECT first_name,email,phone FROM users WHERE is_active=TRUE AND $where")->fetchAll();

$emailSent = $smsSent = 0; $errors = [];
foreach ($users as $u) {
    if (in_array($channel,['email','both']) && $u['email']) {
        try {
            $m = mailer();
            if (!$m) {
                $errors[] = 'Email service unavailable. Run composer install and configure SMTP.';
                continue;
            }
            $m->addAddress($u['email'],$u['first_name']);
            $m->Subject = $subject;
            $m->Body    = email_wrap($subject,"<p style='color:#7A6E60;line-height:1.8;'>$message</p>");
            $m->AltBody = $message; $m->send(); $emailSent++;
        } catch(Throwable $e){ $errors[]=$e->getMessage(); }
    }
    if (in_array($channel,['sms','both']) && $u['phone']) {
        if (send_sms($u['phone'],$message)) $smsSent++;
    }
}
audit_log($db,$sess['user_id'],'admin.broadcast','broadcast',null,null,
    ['recipients'=>$recipients,'channel'=>$channel,'email_sent'=>$emailSent,'sms_sent'=>$smsSent]);
json_ok(['success'=>true,'email_sent'=>$emailSent,'sms_sent'=>$smsSent,'errors'=>count($errors)]);
