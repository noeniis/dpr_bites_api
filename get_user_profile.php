<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Koneksi database gagal"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_users = $data['id_users'] ?? '';

if (!$id_users) {
    echo json_encode(["success" => false, "message" => "ID user wajib diisi"]);
    exit;
}

$stmt = $conn->prepare("SELECT id_users, nama_lengkap, username, email, no_hp, role, photo_path FROM users WHERE id_users=?");
$stmt->bind_param("i", $id_users);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode(["success" => true, "data" => $row]);
} else {
    echo json_encode(["success" => false, "message" => "User tidak ditemukan"]);
}