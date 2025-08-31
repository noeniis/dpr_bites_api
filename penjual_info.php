<?php
// Koneksi ke MySQL
include 'db.php';  // Pastikan koneksi ke database sudah benar

// Mendapatkan data dari Flutter
$data = json_decode(file_get_contents('php://input'), true);

// Pastikan kunci yang diperlukan ada dalam data
if (isset($data['id_users'], $data['id_gerai'], $data['no_telepon_penjual'], $data['nik'], 
    $data['tempat_lahir'], $data['tanggal_lahir'], $data['jenis_kelamin'], 
    $data['foto_ktp_path'], $data['nama_penjual'], $data['email_penjual'], $data['nomor_opsional'])) {
    
    // Menangani data
    $id_users = $data['id_users'];
    $id_gerai = $data['id_gerai'];
    $no_telepon_penjual = $data['no_telepon_penjual'];
    $nik = $data['nik'];
    $tempat_lahir = $data['tempat_lahir'];
    $tanggal_lahir = $data['tanggal_lahir'];
    $jenis_kelamin = $data['jenis_kelamin'];
    $foto_ktp_path = $data['foto_ktp_path'];
    $nama_penjual = $data['nama_penjual'];
    $email_penjual = $data['email_penjual'];
    $nomor_opsional = $data['nomor_opsional'];

    // Query untuk menyimpan data ke tabel penjual_info
    $sql = "INSERT INTO penjual_info 
                (id_users, id_gerai, no_telepon_penjual, nik, tempat_lahir, tanggal_lahir, jenis_kelamin, foto_ktp_path, 
                nama_penjual, email_penjual, nomor_opsional, created_at, updated_at) 
            VALUES 
                ('$id_users', '$id_gerai', '$no_telepon_penjual', '$nik', '$tempat_lahir', '$tanggal_lahir', '$jenis_kelamin', 
                '$foto_ktp_path', '$nama_penjual', '$email_penjual', '$nomor_opsional', NOW(), NOW())";

    // Eksekusi query
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success", "message" => "Data berhasil disimpan"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan data ke tabel penjual_info", "error" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap. Pastikan semua field yang dibutuhkan sudah diisi"]);
}

// Menutup koneksi
$conn->close();
?>
