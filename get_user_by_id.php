<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$id_users = $data['id_users'] ?? '';

$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dpr_bites';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
    exit;
}

$stmt = $conn->prepare("SELECT nama_lengkap, no_hp, email FROM users WHERE id_users = ?");
$stmt->bind_param("s", $id_users);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
}

$stmt->close();
$conn->close();