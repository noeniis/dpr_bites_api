<?php
// add_or_update_gerai.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__.'/protected.php'; // Sudah include JWT
require 'db.php';

$id_users        = isset($id_users) ? $id_users : ($_POST['id_users'] ?? null);
$id_gerai        = $_POST['id_gerai'] ?? null;
$nama_gerai      = $_POST['nama_gerai'] ?? null;
$latitude        = $_POST['latitude'] ?? null;
$longitude       = $_POST['longitude'] ?? null;
$detail_alamat   = $_POST['detail_alamat'] ?? null;
$telepon         = $_POST['telepon'] ?? null;
$qris_path       = $_POST['qris_path'] ?? null;
$sertifikasi_halal = $_POST['sertifikasi_halal'] ?? 0;

// Cast tipe data
$id_users_i = (int)$id_users;
$id_gerai_i = (int)$id_gerai;
$lat_f      = $latitude !== null ? (float)$latitude : null;
$lng_f      = $longitude !== null ? (float)$longitude : null;
$halal_i    = (int)$sertifikasi_halal;

// ===================== UPDATE KHUSUS HALAL =====================
if ($id_users && isset($_POST['sertifikasi_halal']) && !isset($_POST['nama_gerai'])) {
    $halal_i = intval($_POST['sertifikasi_halal']);
    $id_users_i = intval($id_users);

    $stmt = $conn->prepare('UPDATE gerai SET sertifikasi_halal=?, updated_at=NOW() WHERE id_users=?');
    $stmt->bind_param('ii', $halal_i, $id_users_i);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $success, 'action' => 'update_halal', 'id_users' => $id_users_i]);
    exit;
}
// ===================== UPDATE KHUSUS QRIS =====================
if ($id_gerai && isset($_POST['qris_path']) && !isset($_POST['nama_gerai'])) {
    $stmt = $conn->prepare('UPDATE gerai SET qris_path=?, updated_at=NOW() WHERE id_gerai=?');
    $stmt->bind_param('si', $qris_path, $id_gerai_i);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => $success, 'action' => 'update_qris', 'id_gerai' => $id_gerai_i]);
    exit;
}

// ===================== VALIDASI WAJIB =====================
if (!$id_users || !$nama_gerai || !$latitude || !$longitude) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// ===================== CEK SUDAH ADA GERAI BELUM =====================
$stmt = $conn->prepare('SELECT id_gerai FROM gerai WHERE id_users = ?');
$stmt->bind_param('i', $id_users_i);
$stmt->execute();
$result   = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

// ===================== UPDATE PENUH =====================
if ($existing) {
    $stmt = $conn->prepare('UPDATE gerai 
        SET nama_gerai=?, latitude=?, longitude=?, detail_alamat=?, telepon=?, updated_at=NOW() 
        WHERE id_users=?');
    $stmt->bind_param(
        'sddssi',
        $nama_gerai,
        $lat_f,
        $lng_f,
        $detail_alamat,
        $telepon,
        $id_users_i
    );
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'action'  => 'update',
        'id_gerai' => $existing['id_gerai']
    ]);
}// ===================== INSERT BARU =====================
else {
    $status_pengajuan = "pending";
    $alasan_tolak     = "";

    $stmt = $conn->prepare('INSERT INTO gerai 
        (id_users, nama_gerai, latitude, longitude, detail_alamat, telepon, qris_path, sertifikasi_halal, status_pengajuan, alasan_tolak) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param(
        'isddssisss',
        $id_users_i,
        $nama_gerai,
        $lat_f,
        $lng_f,
        $detail_alamat,
        $telepon,
        $qris_path,
        $halal_i,
        $status_pengajuan,
        $alasan_tolak
    );
    $success = $stmt->execute();
    $newId   = $conn->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => $success,
        'action'  => 'insert',
        'id_gerai'=> $newId
    ]);
}

$conn->close();
