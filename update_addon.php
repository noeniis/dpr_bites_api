<?php
include 'db.php';
header('Content-Type: application/json');

// --- Ambil data dari form-data ($_POST) ---
$id_addon   = $_POST['id_addon']   ?? null;
$nama_addon = $_POST['nama_addon'] ?? null;
$deskripsi  = $_POST['deskripsi']  ?? null;
$harga      = $_POST['harga']      ?? null;
$image_path = $_POST['image_path'] ?? null;
$tersedia   = $_POST['tersedia']   ?? null;

// --- Kalau $_POST kosong, coba baca JSON body ---
if ($id_addon === null && empty($_POST)) {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if ($data) {
        $id_addon   = $data['id_addon']   ?? null;
        $nama_addon = $data['nama_addon'] ?? null;
        $deskripsi  = $data['deskripsi']  ?? null;
        $harga      = $data['harga']      ?? null;
        $image_path = $data['image_path'] ?? null;
        $tersedia   = $data['tersedia']   ?? null;
    }
}

// --- Validasi minimal ---
if ($id_addon === null) {
    echo json_encode(["success" => false, "message" => "No data received"]);
    exit;
}

// Default value untuk tersedia
$tersedia = ($tersedia === "1" || $tersedia === 1 || $tersedia === true) ? 1 : 0;

// --- Update data ke DB ---
$query = "UPDATE addon SET 
            nama_addon = '$nama_addon',
            deskripsi = '$deskripsi',
            harga = '$harga',
            image_path = '$image_path',
            tersedia = '$tersedia'
          WHERE id_addon = '$id_addon'";

$result = mysqli_query($conn, $query);

if ($result) {
    echo json_encode(["success" => true, "message" => "Add-on updated"]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed", "error" => mysqli_error($conn)]);
}
?>
