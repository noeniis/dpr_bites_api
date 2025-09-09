<?php
// get_penjual_info.php
require 'db.php'; 
header('Content-Type: application/json');

// Ambil id_users dari POST
$id_users = $_POST['id_users'] ?? null;
if (!$id_users) {
    echo json_encode(['success' => false, 'error' => 'Missing id_users']);
    exit;
}

$stmt = $conn->prepare('SELECT * FROM penjual_info WHERE id_users = ?');
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('i', $id_users);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'error' => 'Data not found']);
}

$stmt->close();
$conn->close();
