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

$data = json_decode(file_get_contents('php://input'), true);

// Determine requesting user from Authorization Bearer <id> or X-User-Id header, fallback to body
$req_user = 0;
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $h = apache_request_headers();
    if (isset($h['Authorization'])) $authHeader = $h['Authorization'];
}
if ($authHeader) {
    if (preg_match('/Bearer\s+(\d+)/i', $authHeader, $m)) {
        $req_user = intval($m[1]);
    }
}
if ($req_user === 0 && isset($_SERVER['HTTP_X_USER_ID'])) {
    $req_user = intval($_SERVER['HTTP_X_USER_ID']);
}

if (!isset($data['nama_penerima'], $data['nama_gedung'], $data['detail_pengantaran'], $data['latitude'], $data['longitude'], $data['no_hp'], $data['alamat_utama'])) {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
}

// Use requester identity, ignore any id_users client tried to send
$id_users = $req_user > 0 ? $req_user : (isset($data['id_users']) ? intval($data['id_users']) : 0);
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