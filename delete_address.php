<?php
header('Content-Type: application/json');

// Koneksi DB
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'dpr_bites';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit;
}

// Baca body JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id_alamat'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter id_alamat wajib diisi']);
    exit;
}

$id_alamat = (int)$input['id_alamat'];

// Determine requester id
$req_user = 0;
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    if (isset($h['Authorization'])) $authHeader = $h['Authorization'];
}
if ($authHeader) {
    if (preg_match('/Bearer\s+(\d+)/i', $authHeader, $m)) {
        $req_user = intval($m[1]);
    }
}
if ($req_user === 0 && isset($_SERVER['HTTP_X_USER_ID'])) {
    $req_user = intval($_SERVER['HTTP_X_USER_ID']);
}

if ($req_user <= 0) {
    echo json_encode(['success' => false, 'message' => 'User tidak teridentifikasi']);
    exit;
}

$id_users = $req_user;

// Hapus alamat milik user
$stmt = $conn->prepare('DELETE FROM alamat_pengantaran WHERE id_users = ? AND id_alamat = ?');
$stmt->bind_param('ii', $id_users, $id_alamat);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

if ($stmt->affected_rows < 1) {
    echo json_encode(['success' => false, 'message' => 'Alamat tidak ditemukan untuk user ini']);
} else {
    echo json_encode(['success' => true, 'message' => 'Alamat berhasil dihapus']);
}

$stmt->close();
$conn->close();