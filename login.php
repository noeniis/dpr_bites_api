<?php
date_default_timezone_set('Asia/Jakarta');
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'dpr_bites';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
    exit;
}

$stmt = $conn->prepare("SELECT id_users, password_hash, role, step1, step2, step3 FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($id_users, $hashed_password, $role, $step1, $step2, $step3);
    $stmt->fetch();

    if (password_verify($password, $hashed_password)) {
        $response = [
            'success' => true,
            'id_users' => (int)$id_users,
            'role'     => $role,
        ];

        if ($role === 'penjual') {
            // kembalikan progress & id_gerai
            $response['step1'] = (bool)$step1;
            $response['step2'] = (bool)$step2;
            $response['step3'] = (bool)$step3;

            // CARI id_gerai milik user ini
            $stmt2 = $conn->prepare("SELECT id_gerai FROM gerai WHERE id_users = ? ORDER BY id_gerai DESC LIMIT 1");
            $stmt2->bind_param("i", $id_users);
            $stmt2->execute();
            $stmt2->bind_result($idGerai);
            if ($stmt2->fetch()) {
                $response['id_gerai'] = (int)$idGerai;   // <— ini yang dipakai Flutter untuk prefs
            } else {
                $response['id_gerai'] = null;            // belum punya gerai
            }
            $stmt2->close();
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
