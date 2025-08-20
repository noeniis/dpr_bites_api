<?php
date_default_timezone_set('Asia/Jakarta');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"));

$nama_lengkap = $data->nama_lengkap;
$username = $data->username;
$email = $data->email;
$no_hp = $data->no_hp;
$password_hash = password_hash($data->password, PASSWORD_DEFAULT);
$role = $data->role;

$stmt = $conn->prepare("INSERT INTO users (nama_lengkap, username, email, no_hp, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $nama_lengkap, $username, $email, $no_hp, $password_hash, $role);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
