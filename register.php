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

// Pastikan role hanya '0', '1', atau '2' (string, sesuai enum)
$role = isset($data->role) ? (string)$data->role : '0';
if (!in_array($role, ['0','1','2'])) {
    echo json_encode(["success" => false, "error" => "Role tidak valid, harus 0, 1, atau 2"]);
    exit;
}

// Default photo path jika belum ada upload
$default_photo = 'https://res.cloudinary.com/dip8i3f6x/image/upload/v1756293044/dummy-profile-pic-300x300_udkg39.png';

// Tambahkan kolom photo_path pada insert
$stmt = $conn->prepare("INSERT INTO users (nama_lengkap, username, email, no_hp, password_hash, role, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $nama_lengkap, $username, $email, $no_hp, $password_hash, $role, $default_photo);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "photo_path" => $default_photo]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
