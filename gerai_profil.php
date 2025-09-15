<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
@ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__.'/protected.php'; // Sudah include JWT
include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['id_gerai'])) {
    // Ambil & escape semua input
    $id_gerai = mysqli_real_escape_string($conn, $data['id_gerai']);
    $banner_path = mysqli_real_escape_string($conn, $data['banner_path'] ?? '');
    $listing_path = mysqli_real_escape_string($conn, $data['listing_path'] ?? '');
    $deskripsi_gerai = mysqli_real_escape_string($conn, $data['deskripsi_gerai'] ?? '');

    // Handle hari_buka (bisa string atau array)
    $hari_buka = '';
    if (isset($data['hari_buka'])) {
        if (is_array($data['hari_buka'])) {
            $hari_buka = implode(',', $data['hari_buka']);
        } else {
            $hari_buka = $data['hari_buka']; // sudah string "Senin,Selasa,Jumat"
        }
    }
    $hari_buka = mysqli_real_escape_string($conn, $hari_buka);

    $jam_buka = mysqli_real_escape_string($conn, $data['jam_buka'] ?? '');
    $jam_tutup = mysqli_real_escape_string($conn, $data['jam_tutup'] ?? '');

    // Query insert
    $sql = "INSERT INTO gerai_profil 
            (id_gerai, banner_path, listing_path, deskripsi_gerai, hari_buka, jam_buka, jam_tutup, created_at, updated_at) 
            VALUES 
            ('$id_gerai', '$banner_path', '$listing_path', '$deskripsi_gerai', '$hari_buka', '$jam_buka', '$jam_tutup', NOW(), NOW())";

    if ($conn->query($sql) === TRUE) {
        // Update step2 di tabel users untuk user yang terautentikasi
        if (isset($id_users) && intval($id_users) > 0) {
            $id_users_safe = intval($id_users);
            $conn->query("UPDATE users SET step2=1 WHERE id_users='$id_users_safe'");
        }
        echo json_encode([
            "status" => "success",
            "message" => "Data berhasil disimpan dan step2 user diupdate"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menyimpan data ke tabel gerai_profil",
            "error" => $conn->error
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "ID Gerai tidak ditemukan"
    ]);
}

$conn->close();
