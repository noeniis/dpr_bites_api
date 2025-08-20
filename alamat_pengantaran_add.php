<?php
header('Content-Type: application/json');

// Koneksi ke database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'dpr_bites';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit;
}

// Ambil data dari request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_users'], $data['nama_penerima'], $data['nama_gedung'], $data['detail_pengantaran'], $data['latitude'], $data['longitude'], $data['no_hp'], $data['alamat_utama'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

$id_users = $data['id_users'];
$nama_penerima = $data['nama_penerima'];
$nama_gedung = $data['nama_gedung'];
$detail_pengantaran = $data['detail_pengantaran'];
$latitude = $data['latitude'];
$longitude = $data['longitude'];
$no_hp = $data['no_hp'];
$alamat_utama = $data['alamat_utama'] ? 1 : 0;

$conn->begin_transaction();
try {
    if ($alamat_utama == 1) {
        // Set semua alamat_utama user ini ke 0
        $sql_reset = "UPDATE alamat_pengantaran SET alamat_utama = 0 WHERE id_users = ?";
        $stmt_reset = $conn->prepare($sql_reset);
        $stmt_reset->bind_param('i', $id_users);
        $stmt_reset->execute();
        $stmt_reset->close();
    }

    $sql = "INSERT INTO alamat_pengantaran (id_users, nama_penerima, nama_gedung, detail_pengantaran, latitude, longitude, no_hp, alamat_utama) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssddsi', $id_users, $nama_penerima, $nama_gedung, $detail_pengantaran, $latitude, $longitude, $no_hp, $alamat_utama);
    $stmt->execute();
    $id_alamat = $stmt->insert_id;
    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'id_alamat' => $id_alamat]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>