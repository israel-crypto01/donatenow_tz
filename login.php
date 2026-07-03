<?php
// backend/auth/login.php
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); cors(); session_secure();
if($_SERVER['REQUEST_METHOD']!=='POST') json_error('Method Not Allowed',405);
$d=json_decode(file_get_contents('php://input'),true)??[];
$email=filter_var($d['email']??'',FILTER_VALIDATE_EMAIL);
$pass=$d['password']??'';
if(!$email||!$pass) json_error('Email and password required',422);
// Rate limiting
$ip=$_SERVER['REMOTE_ADDR']??'unknown';
$rlk=sys_get_temp_dir().'/rl_'.md5($ip);
$cnt=file_exists($rlk)?(int)file_get_contents($rlk):0;
if($cnt>=RATE_LIMIT_LOGIN) json_error('Too many attempts. Try again in 15 minutes.',429);
$db=getDB();
$s=$db->prepare('SELECT * FROM users WHERE email=? AND is_active=TRUE'); $s->execute([$email]);
$user=$s->fetch();
if(!$user||!password_verify($pass,$user['password_hash'])){
    file_put_contents($rlk,$cnt+1);
    json_error('Invalid email or password',401);
}
if(!$user['is_verified']) json_error('Please verify your email first.',403);
@unlink($rlk);
session_regenerate_id(true);
$_SESSION['user_id']=$user['id']; $_SESSION['user_uuid']=$user['uuid'];
$_SESSION['user_role']=$user['role']; $_SESSION['user_name']=$user['first_name'];
$_SESSION['first_name']=$user['first_name']; $_SESSION['email']=$user['email'];
$db->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
audit_log($db,$user['id'],'user.login','users',$user['id']);
json_ok(['success'=>true,'role'=>$user['role'],'name'=>$user['first_name'],
         'user'=>[
             'first_name'=>$user['first_name'],
             'last_name'=>$user['last_name'],
             'email'=>$user['email'],
             'phone'=>$user['phone'],
             'region'=>$user['region'],
             'role'=>$user['role']
         ],
         'redirect'=>APP_URL.'/index.html#dashboard']);
