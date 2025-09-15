<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__.'/protected.php';
require 'db.php';

// Read JSON body if present
$raw = file_get_contents('php://input');
$data = [];
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $data = $json;
}

$step1 = $data['step1'] ?? $_POST['step1'] ?? null;
$step2 = $data['step2'] ?? $_POST['step2'] ?? null;
$step3 = $data['step3'] ?? $_POST['step3'] ?? null;

// Determine authenticated user id
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Determine target user to update: prefer id_users from request (koperasi/admin action), otherwise use token user
$target_user_id = null;
$target_user_id = isset($data['id_users']) ? intval($data['id_users']) : (isset($_POST['id_users']) ? intval($_POST['id_users']) : null);
// Debug: log incoming data for diagnostics (remove in production)
error_log('update_step_seller called. data: ' . json_encode($data) . ' POST: ' . json_encode($_POST));
if ($target_user_id === null || $target_user_id === 0) {
    $target_user_id = $token_user_id;
}

$fields = [];
$params = [];
$types = '';

if ($step1 !== null) {
    $fields[] = 'step1=?';
    $params[] = intval($step1);
    $types .= 'i';
}
if ($step2 !== null) {
    $fields[] = 'step2=?';
    $params[] = intval($step2);
    $types .= 'i';
}
if ($step3 !== null) {
    $fields[] = 'step3=?';
    $params[] = intval($step3);
    $types .= 'i';
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'No step fields to update']);
    exit;
}

$params[] = intval($target_user_id);
$types .= 'i';

$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id_users=?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
?>