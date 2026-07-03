<?php
// backend/auth/forgot.php — Password Reset Request POST {email}
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once '../notifications/email.php';
header('Content-Type: application/json'); cors();
if($_SERVER['REQUEST_METHOD']!=='POST') json_error('Method Not Allowed',405);
$d=json_decode(file_get_contents('php://input'),true)??[];
$email=filter_var($d['email']??'',FILTER_VALIDATE_EMAIL);
if(!$email) json_error('Valid email required',422);
$db=getDB();
$s=$db->prepare('SELECT id,first_name FROM users WHERE email=? AND is_active=TRUE'); $s->execute([$email]);
$user=$s->fetch();
if($user){
    $token=bin2hex(random_bytes(32));
    $exp=date('Y-m-d H:i:s',time()+3600);
    $db->prepare('UPDATE users SET reset_token=?,reset_expires=? WHERE id=?')->execute([$token,$exp,$user['id']]);
    send_password_reset_email($email,$user['first_name'],$token);
}
json_ok(['success'=>true,'message'=>'If your email exists, a reset link has been sent.']);
