<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Koneksi database gagal"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_users = $data['id_users'] ?? '';
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

if (!$id_users || !$current_password || !$new_password) {
    echo json_encode(["success" => false, "message" => "Parameter tidak lengkap"]);
    exit;
}

// Ambil hash password lama
$stmt = $conn->prepare("SELECT password_hash FROM users WHERE id_users=?");
$stmt->bind_param("i", $id_users);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $hash = $row['password_hash'];
    if (!password_verify($current_password, $hash)) {
        echo json_encode(["success" => false, "message" => "Kata sandi lama salah"]);
        exit;
    }
    // Update password baru
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt2 = $conn->prepare("UPDATE users SET password_hash=? WHERE id_users=?");
    $stmt2->bind_param("si", $new_hash, $id_users);
    if ($stmt2->execute()) {
        echo json_encode(["success" => true, "message" => "Password berhasil diubah"]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal mengubah password"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "User tidak ditemukan"]);
}