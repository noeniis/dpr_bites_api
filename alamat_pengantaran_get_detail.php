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
$id_alamat = isset($input['id_alamat']) ? intval($input['id_alamat']) : 0;

if ($id_alamat <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
  $conn->close();
  exit;
}

// Fetch address by id_alamat and verify ownership against requesting user
$stmt = $conn->prepare("SELECT id_alamat, id_users, nama_penerima, nama_gedung, detail_pengantaran, latitude, longitude, no_hp, alamat_utama FROM alamat_pengantaran WHERE id_alamat = ? LIMIT 1");
$stmt->bind_param('i', $id_alamat);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Query failed']);
  $stmt->close();
  $conn->close();
  exit;
}

$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  $owner = isset($row['id_users']) ? intval($row['id_users']) : 0;
  if ($owner !== $auth_user_id) {
    // Ownership mismatch - deny access
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    $stmt->close();
    $conn->close();
    exit;
  }
  echo json_encode(['success' => true, 'address' => $row]);
} else {
  http_response_code(404);
  echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
}

$stmt->close();
$conn->close();