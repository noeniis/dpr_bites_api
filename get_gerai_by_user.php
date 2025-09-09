<?php
header('Content-Type: application/json');
include 'db.php';

$id_users = isset($_POST['id_users']) ? $_POST['id_users'] : null;
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

// Debug
error_log('id_users dari Flutter: ' . $id_users);
error_log('Isi POST: ' . print_r($_POST, true));

if ($id_users) {
    $query = "SELECT id_gerai, nama_gerai, detail_alamat, telepon, latitude, longitude, status_pengajuan, alasan_tolak, sertifikasi_halal, qris_path 
              FROM gerai WHERE id_users = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_users);
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
}

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
?>