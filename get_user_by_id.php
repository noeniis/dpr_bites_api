<?php
header('Content-Type: application/json');
require 'db.php'; 

$data = json_decode(file_get_contents('php://input'), true);
$id_users = $data['id_users'] ?? '';

if (!$id_users) {
    echo json_encode(['success' => false, 'message' => 'id_users is required']);
    exit;
}

// gunakan koneksi yang sudah ada dari db.php
$stmt = $conn->prepare("SELECT id_users, nama_lengkap, no_hp, email, step1, step2, step3 FROM users WHERE id_users = ?");
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
