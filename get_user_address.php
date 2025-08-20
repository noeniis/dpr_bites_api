<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"), true);
$id_users = $data['id_users'] ?? '';

if (!$id_users) {
    echo json_encode(["success" => false, "message" => "ID user wajib diisi"]);
    exit;
}

// Ambil alamat utama dari tabel alamat_pengantaran
$stmt = $conn->prepare("SELECT nama_gedung, detail_pengantaran FROM alamat_pengantaran WHERE id_users=? AND alamat_utama=1 LIMIT 1");
$stmt->bind_param("i", $id_users);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        "success" => true,
        "has_address" => true,
        "nama_gedung" => $row['nama_gedung'],
        "detail_pengantaran" => $row['detail_pengantaran']
    ]);
} else {
    echo json_encode(["success" => true, "has_address" => false]);
}