<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"), true);
$id_users = $data['id_users'] ?? '';
$nama_lengkap = $data['nama_lengkap'] ?? '';
$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$no_hp = $data['no_hp'] ?? '';

$photo_path = $data['photo_path'] ?? null; // opsional
$password = $data['password'] ?? null; // opsional, jika ingin ganti password


if (!$id_users || !$nama_lengkap || !$username || !$email || !$no_hp) {
    echo json_encode(["success" => false, "message" => "Lengkapi data"]);
    exit;
}


if ($password && strlen($password) >= 6) {
    // Update dengan password baru (hash dulu)
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=?, photo_path=?, password_hash=? WHERE id_users=?");
    $stmt->bind_param("ssssssi", $nama_lengkap, $username, $email, $no_hp, $photo_path, $password_hash, $id_users);
} else {
    // Update tanpa mengubah password
    $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, username=?, email=?, no_hp=?, photo_path=? WHERE id_users=?");
    $stmt->bind_param("sssssi", $nama_lengkap, $username, $email, $no_hp, $photo_path, $id_users);
}
$success = $stmt->execute();

if ($success) {
    echo json_encode(["success" => true, "message" => "Profil berhasil diupdate"]);
} else {
    echo json_encode(["success" => false, "message" => "Gagal update profil"]);
}