<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__.'/protected.php';
require 'db.php';

// Read JSON body if present
$raw = file_get_contents('php://input');
$data = [];
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $data = $json;
}

$id_gerai = $data['id_gerai'] ?? $_POST['id_gerai'] ?? null;
$status = $data['status'] ?? $_POST['status'] ?? null;
$alasan = $data['alasan'] ?? $_POST['alasan'] ?? null;

// Require auth and check caller is gerai owner
$token_user_id = 0;
if (function_exists('getTokenUserId')) {
    $token_user_id = getTokenUserId();
} elseif (function_exists('requireAuth')) {
    $token_user_id = requireAuth();
} elseif (isset($id_users)) {
    $token_user_id = (int)$id_users;
}

if ($token_user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'id_users' => null]);
    exit;
}

if ($id_gerai === null || $status === null) {
    echo json_encode(['success' => false, 'error' => 'Missing id_gerai or status', 'id_users' => null]);
    exit;
}

// Verify caller owns the gerai
// Note: approval/rejection is performed by koperasi/admin workflow.
// Do not require gerai owner for this endpoint; only require authenticated user.

$sql = "UPDATE gerai SET status_pengajuan=?, alasan_tolak=? WHERE id_gerai=?";
$stmt = $conn->prepare($sql);
$id_gerai_int = (int)$id_gerai;
$stmt->bind_param("ssi", $status, $alasan, $id_gerai_int);
$success = $stmt->execute();
if (!$success) {
    error_log('update_pengajuan execute failed: ' . $stmt->error);
}

// Ambil id_users dari gerai
$id_users = null;
if ($success) {
    $sqlUser = "SELECT id_users FROM gerai WHERE id_gerai=?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("i", $id_gerai);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    if ($row = $resultUser->fetch_assoc()) {
        $id_users = $row['id_users'];
    }
    $stmtUser->close();
}

echo json_encode([
    'success' => $success,
    'id_users' => $id_users
]);
?>