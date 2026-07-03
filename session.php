<?php
// backend/auth/session.php - Current authenticated user
require_once '../config/db.php';
require_once '../utils/helpers.php';

header('Content-Type: application/json');
cors();
session_secure();

if (empty($_SESSION['user_id'])) {
    json_ok(['success' => true, 'authenticated' => false]);
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $first = sanitize($d['first_name'] ?? '');
    $last  = sanitize($d['last_name'] ?? '');
    $phone = sanitize($d['phone'] ?? '');
    $reg   = sanitize($d['region'] ?? '');

    $errors = [];
    if (!$first) $errors['first_name'] = 'First name required';
    if (!$last)  $errors['last_name'] = 'Last name required';
    if ($phone && !valid_phone($phone)) $errors['phone'] = 'Invalid Tanzanian phone number';
    if ($errors) json_error('Validation failed', 422, $errors);

    if (db_driver() === 'mysql') {
        $s = $db->prepare('UPDATE users
            SET first_name=?, last_name=?, phone=?, region=?, updated_at=NOW()
            WHERE id=? AND is_active=TRUE');
        $s->execute([$first, $last, $phone ? norm_phone($phone) : null, $reg, $_SESSION['user_id']]);
        $s = $db->prepare('SELECT first_name,last_name,email,phone,region,role,is_verified,last_login,created_at
            FROM users WHERE id=? AND is_active=TRUE');
        $s->execute([$_SESSION['user_id']]);
        $user = $s->fetch();
    } else {
        $s = $db->prepare('UPDATE users
            SET first_name=?, last_name=?, phone=?, region=?, updated_at=NOW()
            WHERE id=? AND is_active=TRUE
            RETURNING first_name,last_name,email,phone,region,role,is_verified,last_login,created_at');
        $s->execute([$first, $last, $phone ? norm_phone($phone) : null, $reg, $_SESSION['user_id']]);
        $user = $s->fetch();
    }
    if (!$user) json_error('User not found', 404);

    $_SESSION['user_name'] = $user['first_name'];
    audit_log($db, $_SESSION['user_id'], 'user.profile.updated', 'users', $_SESSION['user_id'], null, [
        'first_name' => $first,
        'last_name' => $last,
        'phone' => $phone,
        'region' => $reg,
    ]);
    json_ok(['success' => true, 'authenticated' => true, 'user' => $user]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method Not Allowed', 405);
}

$s = $db->prepare('SELECT first_name,last_name,email,phone,region,role,is_verified,last_login,created_at
                   FROM users WHERE id=? AND is_active=TRUE');
$s->execute([$_SESSION['user_id']]);
$user = $s->fetch();

if (!$user) {
    session_destroy();
    json_ok(['success' => true, 'authenticated' => false]);
}

json_ok([
    'success' => true,
    'authenticated' => true,
    'user' => $user,
]);
