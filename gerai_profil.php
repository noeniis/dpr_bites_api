<?php
// Koneksi ke MySQL
include 'koneksi.php';  // Pastikan koneksi ke database sudah benar

// Mendapatkan data dari Flutter
$data = json_decode(file_get_contents('php://input'), true);

// Menangani data
$id_gerai = $data['id_gerai'];
$banner_path = $data['banner_path'];
$listing_path = $data['listing_path'];
$deskripsi_gerai = $data['deskripsi_gerai'];
$hari_buka = $data['hari_buka'];
$jam_buka = $data['jam_buka'];
$jam_tutup = $data['jam_tutup'];

// Query untuk menyimpan data ke tabel gerai_profil
$sql = "INSERT INTO gerai_profil (id_gerai, banner_path, listing_path, deskripsi_gerai, hari_buka, jam_buka, jam_tutup) 
        VALUES ('$id_gerai', '$banner_path', '$listing_path', '$deskripsi_gerai', '$hari_buka', '$jam_buka', '$jam_tutup')";

// Eksekusi query
if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
} else {
    echo json_encode(["status" => "error", "message" => "Gagal menyimpan data"]);
}

// Menutup koneksi
$conn->close();
?>
