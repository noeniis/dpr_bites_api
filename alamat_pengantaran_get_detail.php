<?php
header('Content-Type: application/json');

// Inline DB connection
$host = 'localhost'; $user = 'root'; $pass = ''; $db = 'dpr_bites'; $port = 3306;
$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB connection failed: ' . $conn->connect_error]);
  exit;
}
$conn->set_charset('utf8mb4');

// Read input
$input = json_decode(file_get_contents('php://input'), true);
$id_users  = isset($input['id_users']) ? intval($input['id_users']) : 0;
$id_alamat = isset($input['id_alamat']) ? intval($input['id_alamat']) : 0;

if ($id_users <= 0 || $id_alamat <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

$stmt = $conn->prepare("SELECT id_alamat, id_users, nama_penerima, nama_gedung, detail_pengantaran, latitude, longitude, no_hp, alamat_utama FROM alamat_pengantaran WHERE id_alamat = ? AND id_users = ? LIMIT 1");
$stmt->bind_param('ii', $id_alamat, $id_users);

if (!$stmt->execute()) {
  echo json_encode(['success' => false, 'message' => 'Query failed']); exit;
}

$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  echo json_encode(['success' => true, 'address' => $row]);
} else {
  echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}

$stmt->close();
$conn->close();