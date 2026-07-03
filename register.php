<?php
// backend/auth/register.php
require_once '../config/db.php';
require_once '../utils/helpers.php';
require_once '../notifications/email.php';
header('Content-Type: application/json'); cors(); session_secure();
if($_SERVER['REQUEST_METHOD']!=='POST') json_error('Method Not Allowed',405);
$d=json_decode(file_get_contents('php://input'),true)??[];
$first=sanitize($d['first_name']??''); $last=sanitize($d['last_name']??'');
$email=filter_var($d['email']??'',FILTER_VALIDATE_EMAIL);
$pass=$d['password']??''; $phone=sanitize($d['phone']??''); $reg=sanitize($d['region']??'');
$role=in_array($d['role']??'donor',['donor','ngo'])?$d['role']:'donor';
$errors=[];
if(!$first) $errors['first_name']='First name required';
if(!$last)  $errors['last_name']='Last name required';
if(!$email) $errors['email']='Valid email required';
if(strlen($pass)<8) $errors['password']='Password min 8 characters';
if(!empty($errors)) json_error('Validation failed',422,$errors);
$db=getDB();
$s=$db->prepare('SELECT id FROM users WHERE email=?'); $s->execute([$email]);
if($s->fetch()) json_error('Email already registered. Please login.',409);
$hash=password_hash($pass,PASSWORD_BCRYPT,['cost'=>12]);
$token=bin2hex(random_bytes(32));
if (db_driver() === 'mysql') {
    $s=$db->prepare('INSERT INTO users(first_name,last_name,email,password_hash,phone,region,role,is_verified,verify_token) VALUES(?,?,?,?,?,?,?,?,?)');
    $s->execute([$first,$last,$email,$hash,$phone,$reg,$role,APP_ENV==='development' ? 1 : 0,$token]);
    $s=$db->prepare('SELECT id,uuid,first_name,last_name,email,phone,region,role FROM users WHERE id=?');
    $s->execute([(int)$db->lastInsertId()]);
    $user=$s->fetch();
} else {
    $s=$db->prepare('INSERT INTO users(first_name,last_name,email,password_hash,phone,region,role,is_verified,verify_token) VALUES(?,?,?,?,?,?,?,?,?) RETURNING id,uuid,first_name,last_name,email,phone,region,role');
    $s->execute([$first,$last,$email,$hash,$phone,$reg,$role,APP_ENV==='development',$token]);
    $user=$s->fetch();
}
audit_log($db,null,'user.registered','users',$user['id'],null,['email'=>$email,'role'=>$role]);

// Notify all admins of the new registration
$admins = $db->query("SELECT id FROM users WHERE role='admin' AND is_active=TRUE")->fetchAll();
$roleLabel = $role === 'ngo' ? 'NGO' : 'Donor';
foreach ($admins as $admin) {
    create_notification($db, $admin['id'], 'new_member',
        "👤 New {$roleLabel} Registered",
        "{$first} {$last} ({$email}) just joined as a {$roleLabel}.",
        '👤', APP_URL.'/dashboard.html#usersView');
}

$sent=send_verification_email($email,$first,$token);
session_regenerate_id(true);
$_SESSION['user_id']=$user['id']; $_SESSION['user_uuid']=$user['uuid'];
$_SESSION['user_role']=$user['role']; $_SESSION['user_name']=$user['first_name'];
$_SESSION['first_name']=$user['first_name']; $_SESSION['email']=$user['email'];
json_ok(['success'=>true,'message'=>'Account created.','user'=>$user,'user_id'=>$user['uuid'],'email_sent'=>$sent,'redirect'=>APP_URL.'/index.html#dashboard']);
