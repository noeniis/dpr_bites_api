<?php
date_default_timezone_set('Asia/Jakarta');
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$id_gerai = $data['id_gerai'] ?? null;
$banner_path = $data['banner_path'] ?? null;
$listing_path = $data['listing_path'] ?? null;
$deskripsi_gerai = $data['deskripsi_gerai'] ?? '';
$hari_buka = $data['hari_buka'] ?? '';
$jam_buka = $data['jam_buka'] ?? '';
$jam_tutup = $data['jam_tutup'] ?? '';

if (!$id_gerai) {
    echo json_encode(['error' => 'id_gerai required']);
    exit;
}

$sql = "UPDATE gerai_profil SET banner_path=?, listing_path=?, deskripsi_gerai=?, hari_buka=?, jam_buka=?, jam_tutup=?, updated_at=NOW() WHERE id_gerai=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssssssi', $banner_path, $listing_path, $deskripsi_gerai, $hari_buka, $jam_buka, $jam_tutup, $id_gerai);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => $stmt->error]);
}
?>
