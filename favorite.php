<?php
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
// Allow custom header used by the app
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$out = ['success'=>false,'message'=>'','favorited'=>false];
$mysqli = @new mysqli('localhost','root','','dpr_bites');
if ($mysqli->connect_errno) { $out['message']='DB connection failed'; echo json_encode($out); exit; }
$mysqli->set_charset('utf8mb4');

$method = $_SERVER['REQUEST_METHOD'];
$userId = 0; $menuId = 0; $action = '';
// Read from GET params for GET, or from JSON/form for POST
if ($method === 'GET') {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $menuId = isset($_GET['menu_id']) ? (int)$_GET['menu_id'] : 0;
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
} else {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw,true);
    if (!is_array($payload)) $payload = $_POST;
    $userId = isset($payload['user_id']) ? (int)$payload['user_id'] : 0;
    $menuId = isset($payload['menu_id']) ? (int)$payload['menu_id'] : 0;
    $action = isset($payload['action']) ? trim($payload['action']) : '';
}

// Also accept X-User-Id header as fallback (some clients send user id in header)
$headers = function_exists('getallheaders') ? getallheaders() : [];
if (empty($userId) && isset($headers['X-User-Id'])) {
    $userId = (int)$headers['X-User-Id'];
} elseif (empty($userId) && isset($headers['x-user-id'])) {
    $userId = (int)$headers['x-user-id'];
}

// Accept alternative keys for menu id to be resilient to different client payload shapes
if (empty($menuId)) {
    // check common variations
    if (!empty($payload) && is_array($payload)) {
        if (isset($payload['id_menu'])) $menuId = (int)$payload['id_menu'];
        elseif (isset($payload['id'])) $menuId = (int)$payload['id'];
        elseif (isset($payload['menuId'])) $menuId = (int)$payload['menuId'];
        elseif (isset($payload['menu_id'])) $menuId = (int)$payload['menu_id'];
    }
    // also check GET params as fallback
    if (empty($menuId) && isset($_GET['id'])) $menuId = (int)$_GET['id'];
    if (empty($menuId) && isset($_GET['id_menu'])) $menuId = (int)$_GET['id_menu'];
}

if ($userId<=0 || $menuId<=0) { $out['message']='Missing user_id/menu_id'; echo json_encode($out); exit; }

// Ensure menu exists
$chk = $mysqli->prepare("SELECT id_menu FROM menu WHERE id_menu=? LIMIT 1");
if (!$chk) { $out['message'] = 'Prepare failed (menu check)'; echo json_encode($out); exit; }
$chk->bind_param('i',$menuId);
if (!$chk->execute()) { $out['message'] = 'Execute failed (menu check)'; echo json_encode($out); exit; }
$rs=$chk->get_result();
if (!$rs || $rs->num_rows===0) { $out['message']='Menu not found'; echo json_encode($out); exit; }
$chk->close();

// Check existing favorite
$favExists = false; $favId = null;
$q = $mysqli->prepare("SELECT id_favorite FROM favorite WHERE id_users=? AND id_menu=? LIMIT 1");
if ($q) {
    $q->bind_param('ii',$userId,$menuId);
    $q->execute(); $rq=$q->get_result();
    if ($rq && $rq->num_rows>0) { $row=$rq->fetch_assoc(); $favExists=true; $favId=(int)$row['id_favorite']; }
    $q->close();
}

// Determine desired behavior
// action: add | remove | toggle | '' (empty => toggle)
if ($method==='GET' && $action==='') {
    $out['success']=true; $out['favorited']=$favExists; echo json_encode($out); exit;
}

if ($action==='') $action='toggle';

try {
    // include what we received for easier client-side debugging
    $out['received'] = ['user_id'=>$userId, 'menu_id'=>$menuId, 'action'=>$action];
    // Append to debug log for server-side inspection
    $log = '['.date('Y-m-d H:i:s').'] REQUEST ' . json_encode([ 'method'=>$method, 'user_id'=>$userId, 'menu_id'=>$menuId, 'action'=>$action, 'headers'=> (function_exists('getallheaders')?getallheaders():[]), 'raw'=>substr(@file_get_contents('php://input'),0,4096)]) . "\n";
    @file_put_contents(__DIR__ . '/favorite_debug.log', $log, FILE_APPEND);
    if ($action==='add') {
        if (!$favExists) {
            $ins = $mysqli->prepare("INSERT INTO favorite (id_users,id_menu) VALUES (?,?)");
            if ($ins) { $ins->bind_param('ii',$userId,$menuId); $ins->execute(); $ins->close(); $favExists = true; }
            else { throw new Exception('Prepare failed (insert favorite)'); }
        }
    } elseif ($action==='remove') {
        if ($favExists) {
            $del = $mysqli->prepare("DELETE FROM favorite WHERE id_users=? AND id_menu=?");
            if ($del) { $del->bind_param('ii',$userId,$menuId); $del->execute(); $del->close(); $favExists = false; }
            else { throw new Exception('Prepare failed (delete favorite)'); }
        }
    } elseif ($action==='toggle') {
        if ($favExists) {
            $del = $mysqli->prepare("DELETE FROM favorite WHERE id_users=? AND id_menu=?");
            if ($del) { $del->bind_param('ii',$userId,$menuId); $del->execute(); $del->close(); $favExists = false; }
            else { throw new Exception('Prepare failed (delete favorite)'); }
        } else {
            $ins = $mysqli->prepare("INSERT INTO favorite (id_users,id_menu) VALUES (?,?)");
            if ($ins) { $ins->bind_param('ii',$userId,$menuId); $ins->execute(); $ins->close(); $favExists = true; }
            else { throw new Exception('Prepare failed (insert favorite)'); }
        }
    } else {
        $out['message']='Invalid action'; echo json_encode($out); exit;
    }
    $out['success']=true;
    $out['favorited']=$favExists;
    // Friendly message for client
    $out['message'] = $favExists ? 'Favorited' : 'Unfavorited';
    // Log result
    $log = '['.date('Y-m-d H:i:s').'] RESULT ' . json_encode(['success'=>$out['success'],'favorited'=>$out['favorited'],'message'=>$out['message']]) . "\n";
    @file_put_contents(__DIR__ . '/favorite_debug.log', $log, FILE_APPEND);
} catch (Throwable $e) {
    $out['message']='Error: '.$e->getMessage();
    // Log exception
    $elog = '['.date('Y-m-d H:i:s').'] ERROR ' . $e->getMessage() . "\n";
    @file_put_contents(__DIR__ . '/favorite_debug.log', $elog, FILE_APPEND);
}

echo json_encode($out);
?>