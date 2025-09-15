<?php
require_once __DIR__ . '/protected.php';
include 'db.php';
header('Content-Type: application/json');

// Ensure JWT validated and $id_users available
if (!isset($id_users) || $id_users <= 0) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

// --- Ambil data dari form-data ($_POST) ---
$id_addon   = $_POST['id_addon']   ?? null;
$nama_addon = $_POST['nama_addon'] ?? null;
$deskripsi  = $_POST['deskripsi']  ?? null;
$harga      = $_POST['harga']      ?? null;
$image_path = $_POST['image_path'] ?? null;
$tersedia   = $_POST['tersedia']   ?? null;
$stok       = $_POST['stok']       ?? null;

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
        $stok       = $data['stok']       ?? null;
    }
}

// --- Validasi minimal ---
if ($id_addon === null) {
    echo json_encode(["success" => false, "message" => "No data received"]);
    exit;
}

// Default value untuk tersedia
// Normalize tersedia
$tersedia = ($tersedia === "1" || $tersedia === 1 || $tersedia === true) ? 1 : 0;
// Normalize stok (integer)
if ($stok !== null) {
    $stok = (int)$stok;
} else {
    $stok = null;
}

// --- Update data ke DB ---
$setParts = [];
// Build set parts safely (basic escaping)
$setParts[] = "nama_addon = '" . mysqli_real_escape_string($conn, $nama_addon) . "'";
$setParts[] = "deskripsi = '" . mysqli_real_escape_string($conn, $deskripsi) . "'";
$setParts[] = "harga = '" . mysqli_real_escape_string($conn, $harga) . "'";
$setParts[] = "image_path = '" . mysqli_real_escape_string($conn, $image_path) . "'";
$setParts[] = "tersedia = '" . mysqli_real_escape_string($conn, $tersedia) . "'";
if ($stok !== null) {
        $setParts[] = "stok = '" . mysqli_real_escape_string($conn, (string)$stok) . "'";
}
$setClause = implode(",\n    ", $setParts);
$query = "UPDATE addon SET \n    $setClause\n    WHERE id_addon = '" . mysqli_real_escape_string($conn, $id_addon) . "'";

$result = mysqli_query($conn, $query);

if ($result) {
    echo json_encode(["success" => true, "message" => "Add-on updated"]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed", "error" => mysqli_error($conn)]);
}
?>
