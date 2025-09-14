<?php
date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', 1);


require 'vendor/autoload.php';
require 'config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

// Ambil data user, role sekarang enum '0','1','2'
$stmt = $conn->prepare("SELECT id_users, password_hash, role, step1, step2, step3 FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id_users, $hashed_password, $role, $step1, $step2, $step3);
    $stmt->fetch();
    if (password_verify($password, $hashed_password)) {
        $roleInt = (int)$role; // enum value '0','1','2' as int
        // Generate JWT
        $payload = [
            'iss' => 'dpr_bites',
            'iat' => time(),
            // Expire in 14 days
            'exp' => time() + (14 * 24 * 60 * 60),
            'id_users' => $id_users,
            'role' => $roleInt
        ];
    $jwt = JWT::encode($payload, JWT_SECRET, 'HS256');

        $response = [
            'success' => true,
            'id_users' => $id_users,
            'role' => $roleInt,
            'token' => $jwt
        ];
        if ($roleInt === 1) { // 1 = penjual
            $response['step1'] = (bool)$step1;
            $response['step2'] = (bool)$step2;
            $response['step3'] = (bool)$step3;
        }
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Password salah']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Username tidak ditemukan']);
}
$stmt->close();
$conn->close();