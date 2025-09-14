<?php
header('Content-Type: application/json');

// Enforce JWT auth (silent include)
ob_start();
require_once __DIR__ . '/protected.php';
ob_end_clean();
$auth_user_id = isset($id_users) ? (int)$id_users : 0;
if ($auth_user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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
$id_users = $auth_user_id;

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