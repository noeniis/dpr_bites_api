<?php
// delete_etalase.php (MySQLi)
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/protected.php';
require 'db.php'; // harus menghasilkan $conn (mysqli)

// delete_etalase.php (MySQLi)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

ini_set('display_errors', '0'); // jangan tampilkan HTML error
error_reporting(E_ALL);

require_once 'db.php'; // harus menghasilkan $conn (mysqli)

function respond($ok, $msg=null) {
  echo json_encode(['success'=>$ok, 'message'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method');

$id_etalase = $_POST['id_etalase'] ?? null;
$id_etalase = filter_var($id_etalase, FILTER_VALIDATE_INT);
if (!$id_etalase) respond(false, 'id_etalase is required or invalid');

if ($conn->connect_error) respond(false, 'DB connect error: '.$conn->connect_error);

$conn->begin_transaction();
try {
  // Hapus relasi jika tabelnya ada
  $hasRel = $conn->query("SHOW TABLES LIKE 'menu_etalase'");
  if ($hasRel && $hasRel->num_rows > 0) {
    $stmt = $conn->prepare("DELETE FROM menu_etalase WHERE id_etalase = ?");
    if (!$stmt) throw new Exception('Prepare relasi: '.$conn->error);
    $stmt->bind_param('i', $id_etalase);
    if (!$stmt->execute()) throw new Exception('Exec relasi: '.$stmt->error);
    $stmt->close();
  }

  // Hapus etalase
  $stmt = $conn->prepare("DELETE FROM etalase WHERE id_etalase = ?");
  if (!$stmt) throw new Exception('Prepare etalase: '.$conn->error);
  $stmt->bind_param('i', $id_etalase);
  if (!$stmt->execute()) throw new Exception('Exec etalase: '.$stmt->error);

  if ($stmt->affected_rows < 1) {
    $stmt->close();
    $conn->rollback();
    respond(false, 'Etalase tidak ditemukan');
  }
  $stmt->close();

  $conn->commit();
  respond(true, 'Deleted');
} catch (Throwable $e) {
  if ($conn->errno) { /* noop */ }
  if ($conn->error || $conn->errno) { /* noop */ }
  $conn->rollback();
  // Jangan HTTP 500; kirim JSON success:false supaya Flutter bisa tampilkan
  respond(false, 'DB error: '.$e->getMessage());
}
