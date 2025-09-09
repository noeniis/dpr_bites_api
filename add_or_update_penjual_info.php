<?php
date_default_timezone_set('Asia/Jakarta');
// add_or_update_penjual_info.php
require 'db.php';
header('Content-Type: application/json');

// Ambil data dari POST
$id_users          = $_POST['id_users'] ?? null;
$id_gerai          = $_POST['id_gerai'] ?? null;
$no_telepon        = $_POST['no_telepon_penjual'] ?? null;
$nik               = $_POST['nik'] ?? null;
$tempat_lahir      = $_POST['tempat_lahir'] ?? null;
$tanggal_lahir     = $_POST['tanggal_lahir'] ?? null;
$jenis_kelamin     = $_POST['jenis_kelamin'] ?? null;
$foto_ktp_path     = $_POST['foto_ktp_path'] ?? null;

// Validasi field wajib (silakan sesuaikan jika ada yang opsional)
if (!$id_users || !$id_gerai || !$no_telepon || !$nik || !$tempat_lahir || !$tanggal_lahir || !$jenis_kelamin) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Cek apakah sudah ada data penjual untuk user ini
$stmt = $conn->prepare("SELECT id_penjual_info FROM penjual_info WHERE id_users = ?");
$stmt->bind_param("i", $id_users);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

if ($existing) {
    // UPDATE
    $stmt = $conn->prepare("UPDATE penjual_info 
        SET id_gerai=?, no_telepon_penjual=?, nik=?, tempat_lahir=?, tanggal_lahir=?, jenis_kelamin=?, foto_ktp_path=?, updated_at=NOW() 
        WHERE id_users=?");
    $stmt->bind_param("issssssi", $id_gerai, $no_telepon, $nik, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $foto_ktp_path, $id_users);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'action' => 'update',
        'id_penjual_info' => $existing['id_penjual_info']
    ]);
} else {
    // INSERT
    $stmt = $conn->prepare("INSERT INTO penjual_info 
        (id_users, id_gerai, no_telepon_penjual, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, foto_ktp_path, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("iissssss", $id_users, $id_gerai, $no_telepon, $nik, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $foto_ktp_path);
    $success = $stmt->execute();
    $id_penjual_info = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'action' => 'insert',
        'id_penjual_info' => $id_penjual_info
    ]);
}


$conn->close();
