<?php
date_default_timezone_set('Asia/Jakarta');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"));

// Ambil data yang dimasukkan oleh pengguna
$nama_gerai = $data->nama_gerai;
$pilih_lokasi_gerai = $data->pilih_lokasi_gerai;
$detai_alamat = $data->detai_alamat;
$nomor_telepon = isset($data->nomor_telepon) ? $data->nomor_telepon : NULL; // Jika tidak diisi, bisa NULL
$kategori = $data->kategori; // Ambil kategori

// Siapkan query untuk memasukkan data ke dalam tabel informasi_gerai
$stmt = $conn->prepare("INSERT INTO informasi_gerai (nama_gerai, pilih_lokasi_gerai, detai_alamat, nomor_telepon, kategori) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $nama_gerai, $pilih_lokasi_gerai, $detai_alamat, $nomor_telepon, $kategori);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
