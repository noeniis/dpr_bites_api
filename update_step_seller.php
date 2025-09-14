<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__.'/protected.php';
require 'db.php';

$step1 = $_POST['step1'] ?? null;
$step2 = $_POST['step2'] ?? null;
$step3 = $_POST['step3'] ?? null;

if (!isset($id_users) || intval($id_users) <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$fields = [];
$params = [];
$types = '';

if ($step1 !== null) {
    $fields[] = 'step1=?';
    $params[] = $step1;
    $types .= 'i';
}
if ($step2 !== null) {
    $fields[] = 'step2=?';
    $params[] = $step2;
    $types .= 'i';
}
if ($step3 !== null) {
    $fields[] = 'step3=?';
    $params[] = $step3;
    $types .= 'i';
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'error' => 'No step fields to update']);
    exit;
}

$params[] = intval($id_users);
$types .= 'i';

$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id_users=?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $success]);
?>