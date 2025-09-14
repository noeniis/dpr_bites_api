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

$id_users  = $auth_user_id;
$id_alamat = isset($input['id_alamat']) ? intval($input['id_alamat']) : 0;

$nama_penerima       = trim($input['nama_penerima'] ?? '');
$nama_gedung         = trim($input['nama_gedung'] ?? '');
$detail_pengantaran  = trim($input['detail_pengantaran'] ?? '');
$latitude            = isset($input['latitude']) ? floatval($input['latitude']) : null;
$longitude           = isset($input['longitude']) ? floatval($input['longitude']) : null;
$no_hp               = trim($input['no_hp'] ?? '');
$alamat_utama        = (isset($input['alamat_utama']) && intval($input['alamat_utama']) === 1) ? 1 : 0;

if ($id_alamat <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

$conn->begin_transaction();
try {
  if ($alamat_utama === 1) {
    $stmtReset = $conn->prepare("UPDATE alamat_pengantaran SET alamat_utama = 0 WHERE id_users = ?");
    $stmtReset->bind_param('i', $id_users);
    if (!$stmtReset->execute()) { throw new Exception('Failed resetting defaults'); }
    $stmtReset->close();
  }

  $stmt = $conn->prepare("
    UPDATE alamat_pengantaran
    SET nama_penerima = ?, nama_gedung = ?, detail_pengantaran = ?,
        latitude = ?, longitude = ?, no_hp = ?, alamat_utama = ?, updated_at = NOW()
    WHERE id_alamat = ? AND id_users = ?
  ");
  $stmt->bind_param(
    'sssddsiii',
    $nama_penerima,
    $nama_gedung,
    $detail_pengantaran,
    $latitude,
    $longitude,
    $no_hp,
    $alamat_utama,
    $id_alamat,
    $id_users
  );

  if (!$stmt->execute()) { throw new Exception('Update failed'); }
  $stmt->close();

  $conn->commit();
  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  $conn->rollback();
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
  $conn->close();
}