<?php
// backend/api/campaigns.php — Campaigns REST API
// GET  ?category=&region=&q=&limit=&offset=  → list campaigns
// GET  ?id=X                                  → single campaign
// POST {title,description,category,region,goal_amount,end_date} → create
// PUT  ?id=X                                  → update (owner/admin)
require_once '../config/db.php';
require_once '../utils/helpers.php';

header('Content-Type: application/json'); cors();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$isMysql = db_driver() === 'mysql';
$creatorNameSql = $isMysql
    ? "CONCAT(u.first_name,' ',u.last_name)"
    : 'u.first_name||chr(32)||u.last_name';
$pctSql = $isMysql
    ? 'ROUND((c.raised_amount/NULLIF(c.goal_amount,0))*100,1)'
    : 'ROUND((c.raised_amount::numeric/NULLIF(c.goal_amount,0))*100,1)';
$likeSql = $isMysql ? 'LIKE' : 'ILIKE';
$trueSql = $isMysql ? '1' : 'TRUE';

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $s = $db->prepare("SELECT c.*,
            $creatorNameSql AS creator_name,
            $pctSql AS pct
            FROM campaigns c JOIN users u ON c.created_by=u.id
            WHERE c.id=? AND c.status='active'");
        $s->execute([$id]); $c = $s->fetch();
        if (!$c) json_error('Campaign not found', 404);
        json_ok(['success'=>true,'data'=>$c]);
    }
    $where=['c.status=\'active\'']; $params=[];
    if (!empty($_GET['category'])) { $where[]='c.category=?'; $params[]=sanitize($_GET['category']); }
    if (!empty($_GET['region']))   { $where[]="c.region $likeSql ?"; $params[]='%'.sanitize($_GET['region']).'%'; }
    if (!empty($_GET['urgent']))   { $where[]="c.is_urgent=$trueSql"; }
    if (!empty($_GET['q'])) {
        $where[]="(c.title $likeSql ? OR c.description $likeSql ?)";
        $q='%'.sanitize($_GET['q']).'%'; $params[]=$q; $params[]=$q;
    }
    $limit  = min((int)($_GET['limit']  ?? 12), 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $order  = in_array($_GET['sort']??'',['raised_amount','created_at','donor_count'])
              ? $_GET['sort'] : 'created_at';
    $sql = 'SELECT c.id,c.uuid,c.title,c.slug,c.category,c.region,
                   c.goal_amount,c.raised_amount,c.donor_count,c.cover_image_url,
                   c.is_urgent,c.is_featured,c.end_date,c.status,
                   '.$pctSql.' AS pct
            FROM campaigns c WHERE '.implode(' AND ',$where).
           " ORDER BY c.is_featured DESC,c.$order DESC LIMIT $limit OFFSET $offset";
    $s=$db->prepare($sql); $s->execute($params); $camps=$s->fetchAll();
    $cnt=$db->prepare('SELECT COUNT(*) FROM campaigns c WHERE '.implode(' AND ',$where));
    $cnt->execute($params); $total=(int)$cnt->fetchColumn();
    json_ok(['success'=>true,'data'=>$camps,'total'=>$total,'limit'=>$limit,'offset'=>$offset]);
}

// ── POST (create) ─────────────────────────────────────────────
if ($method === 'POST') {
    $sess=require_auth();
    if (($sess['user_role'] ?? '') !== 'ngo') {
        json_error('Only NGO accounts can create campaigns.', 403);
    }
    $d=json_decode(file_get_contents('php://input'),true)??[];
    $title=sanitize($d['title']??''); $desc=sanitize($d['description']??'');
    $cat=sanitize($d['category']??''); $reg=sanitize($d['region']??'');
    $cover=sanitize($d['cover_image_url']??'');
    $goal=(int)($d['goal_amount']??0); $end=$d['end_date']??null;
    $errors=[];
    if(!$title)    $errors['title']='Title required';
    if(!$desc)     $errors['description']='Description required';
    if(!$cat)      $errors['category']='Category required';
    if($goal<1000) $errors['goal_amount']='Goal must be at least TZS 1,000';
    if(!empty($errors)) json_error('Validation failed',422,$errors);
    $slug=strtolower(preg_replace('/[^a-z0-9]+/i','-',$title)).'-'.time();
    if ($isMysql) {
        $s=$db->prepare('INSERT INTO campaigns
            (created_by,title,slug,description,category,region,goal_amount,end_date,cover_image_url)
            VALUES(?,?,?,?,?,?,?,?,?)');
        $s->execute([$sess['user_id'],$title,$slug,$desc,$cat,$reg,$goal,$end,$cover ?: null]);
        $s=$db->prepare('SELECT id,uuid FROM campaigns WHERE id=?');
        $s->execute([(int)$db->lastInsertId()]);
        $camp=$s->fetch();
    } else {
        $s=$db->prepare('INSERT INTO campaigns
            (created_by,title,slug,description,category,region,goal_amount,end_date,cover_image_url)
            VALUES(?,?,?,?,?,?,?,?,?) RETURNING id,uuid');
        $s->execute([$sess['user_id'],$title,$slug,$desc,$cat,$reg,$goal,$end,$cover ?: null]);
        $camp=$s->fetch();
    }
    audit_log($db,$sess['user_id'],'campaign.created','campaigns',$camp['id'],null,['title'=>$title]);
    $admins = $db->query("SELECT id FROM users WHERE role='admin' AND is_active=TRUE")->fetchAll();
    foreach ($admins as $admin) {
        create_notification(
            $db,
            (int)$admin['id'],
            'campaign',
            'New campaign submitted',
            "$title is waiting for admin approval.",
            '🎯',
            APP_URL.'/index.html#dashboard'
        );
    }
    json_ok(['success'=>true,'message'=>'Campaign submitted for review.','id'=>$camp['uuid']],201);
}

// ── PUT (update) ──────────────────────────────────────────────
if ($method === 'PUT' && $id) {
    $sess=require_auth();
    $d=json_decode(file_get_contents('php://input'),true)??[];
    // Only owner or admin can update
    $s=$db->prepare('SELECT created_by FROM campaigns WHERE id=?'); $s->execute([$id]); $c=$s->fetch();
    if(!$c) json_error('Campaign not found',404);
    if($c['created_by']!==$sess['user_id']&&$sess['user_role']!=='admin') json_error('Forbidden',403);
    $fields=[]; $params=[];
    foreach(['title','description','category','region'] as $f) {
        if(isset($d[$f])){ $fields[]="$f=?"; $params[]=sanitize($d[$f]); }
    }
    if(empty($fields)) json_error('No fields to update',422);
    $params[]=$id;
    $db->prepare('UPDATE campaigns SET '.implode(',',$fields).',updated_at=NOW() WHERE id=?')->execute($params);
    audit_log($db,$sess['user_id'],'campaign.updated','campaigns',$id);
    json_ok(['success'=>true]);
}

json_error('Method Not Allowed',405);
