<?php 
require_once __DIR__.'/protected.php';
include 'db.php';

if (!isset($id_users) || $id_users <= 0) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

// Ambil JSON body
$data = json_decode(file_get_contents("php://input"), true);

$id_menu        = $data['id_menu'] ?? '';
$nama_menu      = $data['nama_menu'] ?? '';
$deskripsi_menu = $data['deskripsi_menu'] ?? '';
$harga          = $data['harga'] ?? 0;
$jumlah_stok    = $data['jumlah_stok'] ?? 0;
$gambar_menu    = $data['gambar_menu'] ?? '';
$kategori       = $data['kategori'] ?? '';
$id_etalase     = $data['etalase'] ?? '';
$addon          = $data['addon'] ?? '';
$tersedia       = $data['tersedia'] ?? 1;

// Update data menu utama
$query = "UPDATE menu SET 
    nama_menu='$nama_menu',
    deskripsi_menu='$deskripsi_menu',
    harga='$harga',
    jumlah_stok='$jumlah_stok',
    gambar_menu='$gambar_menu',
    kategori='$kategori',
    id_etalase='$id_etalase',
    tersedia='$tersedia'
    WHERE id_menu='$id_menu'";
$result = mysqli_query($conn, $query);

// Update relasi add-on
mysqli_query($conn, "DELETE FROM menu_addon WHERE id_menu='$id_menu'");
if (!empty($addon)) {
    $addOnArr = explode(',', $addon);
    foreach ($addOnArr as $id_addon) {
        $id_addon = trim($id_addon);
        if ($id_addon !== '') {
            mysqli_query($conn, "INSERT INTO menu_addon (id_menu, id_addon) VALUES ('$id_menu', '$id_addon')");
        }
    }
}

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Menu updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
