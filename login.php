<?php
date_default_timezone_set('Asia/Jakarta');
// Aktifkan error reporting untuk debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Ambil data JSON dari request
$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Koneksi ke database
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dpr_bites';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
    exit;
}

// Ambil data user, tambahkan id_users
$stmt = $conn->prepare("SELECT id_users, password_hash, role FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id_users, $hashed_password, $role);
    $stmt->fetch();
    if (password_verify($password, $hashed_password)) {
        echo json_encode(['success' => true, 'id_users' => $id_users, 'role' => $role]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Password salah']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Username tidak ditemukan']);
}
$stmt->close();
$conn->close();