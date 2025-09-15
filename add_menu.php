<?php
header('Content-Type: application/json');
require_once __DIR__.'/protected.php';
require_once "db.php"; // koneksi ke database

// --- Ambil data ---
$data = [];

// 1. Coba ambil dari $_POST
if (!empty($_POST)) {
    $data = $_POST;
} else {
    // 2. Kalau kosong, coba ambil dari JSON body
    $input = file_get_contents("php://input");
    $json = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $data = $json;
    }
}

// --- Validasi ---
$required = ['id_gerai','nama_menu','gambar_menu','deskripsi_menu','kategori','harga','jumlah_stok','tersedia'];
$missing = [];
foreach ($required as $key) {
    if (!isset($data[$key]) || $data[$key] === '') {
        $missing[] = $key;
    }
}

if (!empty($missing)) {
    echo json_encode([
        "success" => false,
        "error" => "Parameter tidak lengkap",
        "missing" => $missing,
        "received" => array_keys($data)
    ]);
    exit;
}

// --- Ambil variabel ---
$id_gerai     = intval($data['id_gerai']);
$id_etalase   = !empty($data['id_etalase']) ? intval($data['id_etalase']) : null;
$nama_menu    = $data['nama_menu'];
$gambar_menu  = $data['gambar_menu'];
$deskripsi    = $data['deskripsi_menu'];
$kategori     = $data['kategori'];
$harga        = intval($data['harga']);
$stok         = intval($data['jumlah_stok']);
$tersedia     = intval($data['tersedia']);

// --- Insert ---
$sql = "INSERT INTO menu (id_gerai, id_etalase, nama_menu, gambar_menu, deskripsi_menu, kategori, harga, jumlah_stok, tersedia)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissssiii", $id_gerai, $id_etalase, $nama_menu, $gambar_menu, $deskripsi, $kategori, $harga, $stok, $tersedia);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id_menu" => $stmt->insert_id]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}
