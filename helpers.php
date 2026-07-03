<?php
// backend/utils/helpers.php — Shared utility functions
require_once __DIR__.'/../config/env.php';

function json_error(string $msg, int $code=400, array $errors=[]): never {
    http_response_code($code);
    echo json_encode(array_filter(['error'=>$msg,'code'=>$code,'errors'=>$errors?:null]));
    exit;
}
function json_ok(array $data, int $code=200): never {
    http_response_code($code); echo json_encode($data); exit;
}
function sanitize(string $val): string {
    return trim(htmlspecialchars(strip_tags($val), ENT_QUOTES, 'UTF-8'));
}
function cors(): void {
    $o = $_SERVER['HTTP_ORIGIN'] ?? '';
    if(in_array($o, CORS_ORIGINS)) header("Access-Control-Allow-Origin: $o");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
}
function session_secure(): void {
    if(session_status()===PHP_SESSION_ACTIVE) return;
    ini_set('session.cookie_httponly',1); ini_set('session.use_strict_mode',1);
    ini_set('session.cookie_samesite','Strict');
    if(SESSION_SECURE) ini_set('session.cookie_secure',1);
    session_name(SESSION_NAME); session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}
function require_auth(): array {
    session_secure();
    if(empty($_SESSION['user_id'])) json_error('Unauthorised',401);
    return $_SESSION;
}
function require_admin(): array {
    $s=require_auth();
    if($s['user_role']!=='admin') json_error('Forbidden',403);
    return $s;
}
function audit_log(PDO $db,?int $uid,string $action,string $etype='',?int $eid=null,
                   ?array $old=null,?array $new=null): void {
    try {
        $db->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,old_values,new_values,ip_address,user_agent)
                      VALUES(?,?,?,?,?,?,?,?)')->execute([
            $uid,$action,$etype,$eid,
            $old?json_encode($old):null,$new?json_encode($new):null,
            $_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null
        ]);
    } catch(Throwable $e){ error_log('audit_log: '.$e->getMessage()); }
}
function create_notification(PDO $db,int $uid,string $type,string $title,
                             string $body='',string $icon='🔔',string $url=''): void {
    $db->prepare('INSERT INTO notifications(user_id,type,title,body,icon,action_url) VALUES(?,?,?,?,?,?)')
       ->execute([$uid,$type,$title,$body,$icon,$url]);
}
function gen_ref(string $pfx='DN'): string { return strtoupper($pfx).'-'.bin2hex(random_bytes(5)); }
function tzs(int $n): string { return 'TZS '.number_format($n); }
function valid_phone(string $p): bool {
    $p=norm_phone($p);
    return (bool)preg_match('/^(255|0)(6[0-9]|7[0-9])\d{7}$/',$p);
}
function norm_phone(string $p): string {
    $p=preg_replace('/[\s+]/','', $p);
    if(str_starts_with($p,'0')) return '255'.substr($p,1);
    if(str_starts_with($p,'+')) return ltrim($p,'+');
    return $p;
}
function detect_provider(string $phone): string {
    $pref=substr(norm_phone($phone),3,2);
    return match(true){
        in_array($pref,['74','75','76'])=>'mpesa',
        in_array($pref,['68','69','78'])=>'airtel',
        in_array($pref,['67','71','65'])=>'tigo',
        default=>'unknown'
    };
}
function dev_log(string $msg, mixed $data=null): void {
    if(!APP_DEBUG) return;
    file_put_contents(__DIR__.'/../logs/app.log',
        date('[Y-m-d H:i:s] ').$msg.($data?' '.json_encode($data):'').PHP_EOL, FILE_APPEND);
}
