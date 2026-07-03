<?php
// backend/auth/verify.php — Email Verification GET ?token=xxx
require_once '../config/db.php';
require_once '../utils/helpers.php';
$token=sanitize($_GET['token']??'');
if(!$token) { header('Location: '.APP_URL.'/index.html#login'); exit; }
$db=getDB();
$s=$db->prepare('SELECT id FROM users WHERE verify_token=? AND is_verified=FALSE'); $s->execute([$token]);
$user=$s->fetch();
if(!$user){ header('Location: '.APP_URL.'/index.html#login'); exit; }
$db->prepare('UPDATE users SET is_verified=TRUE,verify_token=NULL WHERE id=?')->execute([$user['id']]);
audit_log($db,$user['id'],'user.verified','users',$user['id']);
header('Location: '.APP_URL.'/index.html#login'); exit;
