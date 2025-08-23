<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require 'db.php';

// Pastikan request POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Ambil data dari POST
$id_gerai    = $_POST['id_gerai'] ?? null;
$nama_addon  = $_POST['nama_addon'] ?? null;
$harga       = $_POST['harga'] ?? null;
$deskripsi   = $_POST['deskripsi'] ?? '';
$image_path  = $_POST['image_path'] ?? null;
$stok        = $_POST['stok'] ?? 0;
$tersedia    = isset($_POST['tersedia']) ? intval($_POST['tersedia']) : 0;

// Validasi
if (!$id_gerai || !$nama_addon || !$harga) {
    echo json_encode([
        'success' => false,
        'error'   => 'Data tidak lengkap',
        'debug'   => $_POST
    ]);
    exit;
}

// Query insert
$query = "INSERT INTO addon (id_gerai, nama_addon, harga, deskripsi, image_path, stok, tersedia) 
          VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

$stmt->bind_param("isissii", $id_gerai, $nama_addon, $harga, $deskripsi, $image_path, $stok, $tersedia);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id_addon' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>