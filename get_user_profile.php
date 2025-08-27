<?php
header('Content-Type: application/json');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_users = $data['id_users'] ?? '';
if (!$id_users) {
    echo json_encode(['success' => false, 'message' => 'ID user wajib diisi']);
    exit;
}

$stmt = $conn->prepare('SELECT id_users, nama_lengkap, email, no_hp, role FROM users WHERE id_users = ?');
$stmt->bind_param('i', $id_users);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $row['id_users'],
            'nama' => $row['nama_lengkap'],
            'email' => $row['email'],
            'no_telp' => $row['no_hp'],
            'role' => $row['role'],
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
}