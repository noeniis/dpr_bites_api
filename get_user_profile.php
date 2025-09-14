<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__.'/protected.php';

$conn = new mysqli("localhost", "root", "", "dpr_bites");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Koneksi database gagal"]);
    exit;
}

$id = isset($id_users) ? (int)$id_users : 0;
if ($id <= 0) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$stmt = $conn->prepare("SELECT id_users, nama_lengkap, username, email, no_hp, role, photo_path FROM users WHERE id_users=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode(["success" => true, "data" => $row]);
} else {
    echo json_encode(["success" => false, "message" => "User tidak ditemukan"]);
}