<?php
// update_status_pengajuan.php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__.'/protected.php'; // JWT validation
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$id_gerai = $data['id_gerai'] ?? null;
$status_pengajuan = $data['status_pengajuan'] ?? null;

if (!$id_gerai || !$status_pengajuan) {
    echo json_encode(['error' => 'id_gerai dan status_pengajuan required']);
    exit;
}

$sql = "UPDATE gerai SET status_pengajuan=?, updated_at=NOW() WHERE id_gerai=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $status_pengajuan, $id_gerai);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $stmt->error]);
}
?>
