<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/protected.php'; // sets $id_users from JWT or exits 401

$conn = new mysqli("localhost", "root", "", "dpr_bites");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB connection error"]);
    exit;
}

// Ambil alamat utama dari tabel alamat_pengantaran untuk user dari token
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