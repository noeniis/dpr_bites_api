<?php
date_default_timezone_set('Asia/Jakarta');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$conn = new mysqli("localhost", "root", "", "dpr_bites");
if ($conn->connect_error) {
  die(json_encode(["success" => false, "error" => "Koneksi gagal: ".$conn->connect_error]));
}

$data = json_decode(file_get_contents("php://input"));

// Ambil & validasi input
$id_users       = isset($data->id_users) ? (int)$data->id_users : 0;
$nama_gerai     = trim($data->nama_gerai ?? '');
$latitude       = (string)($data->latitude ?? '');
$longitude      = (string)($data->longitude ?? '');
$detail_alamat  = trim($data->detail_alamat ?? '');
$telepon        = trim($data->telepon ?? $data->nomor_telepon ?? ''); 
$qris_path      = trim($data->qris_path ?? '');  // QRIS Path 
$sertifikasi_halal = ($data->sertifikasi_halal === 'Ya') ? 'Ya' : 'Tidak';// Sertifikasi Halal 
$valid_statuses = ['pending', 'approved', 'rejected'];
$status_pengajuan = in_array($data->status_pengajuan, $valid_statuses) ? $data->status_pengajuan : 'pending';

if ($id_users <= 0 || $nama_gerai==='' || $latitude==='' || $longitude==='' || $detail_alamat==='' || $telepon==='') {
  echo json_encode(["success"=>false, "message"=>"Wajib: id_users, nama_gerai, latitude, longitude, detail_alamat, telepon."]);
  exit;
}

// Pastikan id_users ada di tabel users (hindari error FK)
$cek = $conn->prepare("SELECT 1 FROM users WHERE id_users=?");
$cek->bind_param("i", $id_users);
$cek->execute();
$cek->store_result();
if ($cek->num_rows === 0) {
  echo json_encode(["success"=>false, "message"=>"id_users tidak ditemukan di tabel users"]);
  exit;
}
$cek->close();

// Insert
$stmt = $conn->prepare("
  INSERT INTO gerai (id_users, nama_gerai, latitude, longitude, detail_alamat, telepon, qris_path, sertifikasi_halal, status_pengajuan)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if ($stmt === false) {
  echo json_encode(["success"=>false, "error"=>"Query preparation failed: ".$conn->error]);
  exit;
}

// Bind parameters (ubah 'i' ke 's' untuk sertifikasi_halal)
$stmt->bind_param("issssssss", $id_users, $nama_gerai, $latitude, $longitude, $detail_alamat, $telepon, $qris_path, $sertifikasi_halal, $status_pengajuan);

if ($stmt->execute()) {
  echo json_encode(["success"=>true, "id_gerai"=>$stmt->insert_id, "message"=>"Data berhasil disimpan"]);
} else {
  echo json_encode(["success"=>false, "error"=>$stmt->error]);
}

$stmt->close();
$conn->close();
