<?php
// backend/auth/google.php — Google OAuth 2.0
// Requires: composer require google/apiclient
require_once '../config/db.php';
require_once '../utils/helpers.php';
$autoload = __DIR__.'/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Location: '.APP_URL.'/index.html#login');
    exit;
}
require_once $autoload;
session_secure();
$client=new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SEC);
$client->setRedirectUri(GOOGLE_REDIRECT);
$client->addScope('email'); $client->addScope('profile');
// Step 1: Redirect to Google
if(!isset($_GET['code'])){
    $state=bin2hex(random_bytes(16)); $_SESSION['oauth_state']=$state;
    $client->setState($state);
    header('Location: '.$client->createAuthUrl()); exit;
}
// Step 2: Handle callback
if(($_GET['state']??'')!==($_SESSION['oauth_state']??''))
    die(header('Location: '.APP_URL.'/index.html#login'));
unset($_SESSION['oauth_state']);
try {
    $tok=$client->fetchAccessTokenWithAuthCode($_GET['code']);
    if(isset($tok['error'])) throw new Exception($tok['error_description']??'OAuth error');
    $client->setAccessToken($tok);
} catch(Exception $e) {
    error_log('Google OAuth: '.$e->getMessage());
    header('Location: '.APP_URL.'/index.html#login'); exit;
}
$payload=$client->verifyIdToken($tok['id_token']??'');
if(!$payload){ header('Location: '.APP_URL.'/index.html#login'); exit; }
$gid=$payload['sub']; $email=filter_var($payload['email']??'',FILTER_VALIDATE_EMAIL);
$first=sanitize($payload['given_name']??'User'); $last=sanitize($payload['family_name']??'');
$avatar=$payload['picture']??'';
if(!$email){ header('Location: '.APP_URL.'/index.html#login'); exit; }
$db=getDB();
$s=$db->prepare('SELECT * FROM users WHERE google_id=? OR email=? LIMIT 1');
$s->execute([$gid,$email]); $user=$s->fetch();
if($user){
    if(!$user['google_id'])
        $db->prepare('UPDATE users SET google_id=?,avatar_url=?,is_verified=TRUE,last_login=NOW() WHERE id=?')
           ->execute([$gid,$avatar,$user['id']]);
    else
        $db->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
    if(!$user['is_active']){ header('Location: '.APP_URL.'/index.html#login'); exit; }
} else {
    if (db_driver() === 'mysql') {
        $s=$db->prepare('INSERT INTO users(first_name,last_name,email,google_id,avatar_url,role,is_verified,is_active) VALUES(?,?,?,?,?,\'donor\',1,1)');
        $s->execute([$first,$last,$email,$gid,$avatar]);
        $s=$db->prepare('SELECT id,uuid,role,first_name FROM users WHERE id=?');
        $s->execute([(int)$db->lastInsertId()]);
        $user=$s->fetch();
    } else {
        $s=$db->prepare('INSERT INTO users(first_name,last_name,email,google_id,avatar_url,role,is_verified,is_active) VALUES(?,?,?,?,?,\'donor\',TRUE,TRUE) RETURNING id,uuid,role,first_name');
        $s->execute([$first,$last,$email,$gid,$avatar]); $user=$s->fetch();
    }
    audit_log($db,$user['id'],'user.registered_google','users',$user['id'],null,['email'=>$email]);
}
session_regenerate_id(true);
$_SESSION['user_id']=$user['id']; $_SESSION['user_uuid']=$user['uuid'];
$_SESSION['user_role']=$user['role']; $_SESSION['user_name']=$user['first_name'];
audit_log($db,$user['id'],'user.login_google','users',$user['id']);
$redir=APP_URL.'/index.html#dashboard';
header('Location: '.$redir); exit;
