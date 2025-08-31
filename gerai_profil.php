<?php    //tabel gerai
// Koneksi ke MySQL
// Koneksi ke MySQL
include 'db.php';  // Pastikan koneksi ke database sudah benar

// Mendapatkan data dari Flutter
$data = json_decode(file_get_contents('php://input'), true);

// Pastikan kunci yang diperlukan ada dalam data
if (isset($data['id_gerai'], $data['banner_path'], $data['listing_path'], $data['deskripsi_gerai'], $data['hari_buka'], $data['jam_buka'], $data['jam_tutup'])) {
    
    // Menangani data
    $id_gerai = $data['id_gerai'];
    $banner_path = $data['banner_path'];
    $listing_path = $data['listing_path'];
    $deskripsi_gerai = $data['deskripsi_gerai'];
    $hari_buka = $data['hari_buka'];
    $jam_buka = $data['jam_buka'];
    $jam_tutup = $data['jam_tutup'];

    // Query untuk menyimpan data ke tabel gerai_profil
    $sql = "INSERT INTO gerai_profil (id_gerai, banner_path, listing_path, deskripsi_gerai, hari_buka, jam_buka, jam_tutup, created_at, updated_at) 
            VALUES ('$id_gerai', '$banner_path', '$listing_path', '$deskripsi_gerai', '$hari_buka', '$jam_buka', '$jam_tutup', NOW(), NOW())";

    // Eksekusi query
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan data ke tabel gerai_profil", "error" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap. Pastikan semua field yang dibutuhkan sudah diisi"]);
}

// Menutup koneksi
$conn->close();
?>
