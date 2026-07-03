<?php
// backend/auth/logout.php
require_once '../config/db.php';
require_once '../utils/helpers.php';
header('Content-Type: application/json'); session_secure();
$sess=require_auth();
audit_log(getDB(),$sess['user_id'],'user.logout','users',$sess['user_id']);
session_destroy(); setcookie(SESSION_NAME,'',time()-3600,'/');
json_ok(['success'=>true,'redirect'=>APP_URL.'/index.html#login']);
