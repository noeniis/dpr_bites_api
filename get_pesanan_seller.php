<?php
header('Content-Type: application/json');
require_once __DIR__ . '/protected.php';
require_once 'db.php';

// Ensure JWT validated
if (!isset($id_users) || $id_users <= 0) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$id_gerai = $_GET['id_gerai'] ?? $_POST['id_gerai'] ?? '';
$id_gerai = trim($id_gerai);
$tanggal = $_GET['tanggal'] ?? $_POST['tanggal'] ?? '';

if ($id_gerai === '') {
  echo json_encode(['success' => false, 'message' => 'id_gerai diperlukan']);
  exit;
}

$whereTanggal = '';
$params = [$id_gerai];
$types = 'i';
if ($tanggal !== '') {
  if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $tanggal)) {
    $whereTanggal = " AND DATE(t.created_at) = ?";
    $params[] = $tanggal;
    $types .= 's';
  }
}


$sql = "SELECT t.id_transaksi, t.booking_id, t.status, t.id_users, u.nama_lengkap, t.bukti_pembayaran, t.metode_pembayaran
  FROM transaksi t
  JOIN users u ON t.id_users = u.id_users
  WHERE t.id_gerai = ? $whereTanggal
  ORDER BY t.id_transaksi DESC";

$stmt = $conn->prepare($sql);
if (count($params) === 2) {
  $stmt->bind_param($types, $params[0], $params[1]);
} else {
  $stmt->bind_param($types, $params[0]);
}

$stmt->execute();
$result = $stmt->get_result();

$pesanan = [];
while ($row = $result->fetch_assoc()) {
  if (!isset($row['bukti_pembayaran']) || $row['bukti_pembayaran'] === null) {
    $row['bukti_pembayaran'] = '';
  }
  if (!isset($row['metode_pembayaran']) || $row['metode_pembayaran'] === null) {
    $row['metode_pembayaran'] = '';
  }
  $pesanan[] = $row;
}

echo json_encode(['success' => true, 'pesanan' => $pesanan]);
$stmt->close();
$conn->close();