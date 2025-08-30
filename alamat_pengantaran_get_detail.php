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
$id_alamat = isset($input['id_alamat']) ? intval($input['id_alamat']) : 0;

// Determine requesting user id securely:
// 1) Authorization: Bearer <user_id>
// 2) X-User-Id header
// 3) fallback to input['id_users'] (least trusted)
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
if ($req_user === 0 && isset($input['id_users'])) {
  $req_user = intval($input['id_users']);
}

if ($req_user <= 0 || $id_alamat <= 0) {
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
  if ($owner !== $req_user) {
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