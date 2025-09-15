<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__.'/protected.php'; // Sudah include JWT
require 'db.php';

$id = isset($id_users) ? (int)$id_users : 0;
if ($id <= 0) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$id_gerai = null;
$nama_gerai = null;
$detail_alamat = null;
$telepon = null;
$latitude = null;
$longitude = null;
$status_pengajuan = null;
$alasan_tolak = null;
$sertifikasi_halal = null;
$qris_path = null;

$query = "SELECT id_gerai, nama_gerai, detail_alamat, telepon, latitude, longitude, status_pengajuan, alasan_tolak, sertifikasi_halal, qris_path 
          FROM gerai WHERE id_users = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result(
    $id_gerai,
    $nama_gerai,
    $detail_alamat,
    $telepon,
    $latitude,
    $longitude,
    $status_pengajuan,
    $alasan_tolak,
    $sertifikasi_halal,
    $qris_path
);
$stmt->fetch();
$stmt->close();

echo json_encode([
    'success' => true,
    'data' => [
        'id_gerai' => $id_gerai,
        'nama_gerai' => $nama_gerai,
        'detail_alamat' => $detail_alamat,
        'telepon' => $telepon,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'status_pengajuan' => $status_pengajuan,
        'alasan_tolak' => $alasan_tolak,
        'sertifikasi_halal' => $sertifikasi_halal,
        'qris_path' => $qris_path
    ]
]);