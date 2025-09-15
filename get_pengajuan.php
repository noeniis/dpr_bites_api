<?php
require_once __DIR__.'/protected.php';
require 'db.php';

$status = $_GET['status'] ?? 'pending';
$sql = "SELECT 
            g.id_gerai, 
            u.nama_lengkap, 
            g.nama_gerai, 
            g.status_pengajuan, 
            g.detail_alamat, 
            g.qris_path, 
            g.sertifikasi_halal, 
            gp.hari_buka, 
            gp.jam_buka, 
            gp.jam_tutup, 
            pi.nik, 
            pi.tempat_lahir, 
            pi.tanggal_lahir, 
            pi.jenis_kelamin, 
            pi.foto_ktp_path,
            u.step1,
            u.step2
        FROM gerai g
        JOIN users u ON g.id_users = u.id_users
        JOIN penjual_info pi ON pi.id_gerai = g.id_gerai
        JOIN gerai_profil gp ON gp.id_gerai = g.id_gerai
        WHERE g.status_pengajuan = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $status);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>