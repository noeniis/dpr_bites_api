<?php
date_default_timezone_set('Asia/Jakarta');
require 'db.php';
header('Content-Type: application/json');

// Ambil input dari Flutter
$data = json_decode(file_get_contents('php://input'), true);

$id_gerai        = $data['id_gerai'] ?? null;
$banner_path     = $data['banner_path'] ?? null;
$listing_path    = $data['listing_path'] ?? null;
$deskripsi_gerai = $data['deskripsi_gerai'] ?? '';
$hari_buka       = $data['hari_buka'] ?? '';
$jam_buka        = $data['jam_buka'] ?? '';
$jam_tutup       = $data['jam_tutup'] ?? '';

if (!$id_gerai) {
    echo json_encode(['success' => false, 'error' => 'id_gerai required']);
    exit;
}

// Normalisasi jam_tutup
if ($jam_tutup === '24:00') {
    $jam_tutup = '00:00';
}

// Jika hari_buka array, ubah jadi string "Senin,Selasa"
if (is_array($hari_buka)) {
    $hari_buka = implode(',', $hari_buka);
}

// Ambil data lama biar nggak null waktu update
$sql_get = "SELECT banner_path, listing_path, hari_buka FROM gerai_profil WHERE id_gerai=? LIMIT 1";
$stmt_get = $conn->prepare($sql_get);
$stmt_get->bind_param('i', $id_gerai);
$stmt_get->execute();
$result_get = $stmt_get->get_result();
$row = $result_get->fetch_assoc();
$stmt_get->close();

// Gunakan data lama kalau field tidak dikirim
$banner_path  = $banner_path  ?? ($row['banner_path'] ?? null);
$listing_path = $listing_path ?? ($row['listing_path'] ?? null);
$hari_buka    = $hari_buka    ?: ($row['hari_buka'] ?? '');

// Update data
$sql = "UPDATE gerai_profil 
        SET banner_path=?, listing_path=?, deskripsi_gerai=?, hari_buka=?, jam_buka=?, jam_tutup=?, updated_at=NOW() 
        WHERE id_gerai=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'ssssssi',
    $banner_path,
    $listing_path,
    $deskripsi_gerai,
    $hari_buka,
    $jam_buka,
    $jam_tutup,
    $id_gerai
);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
