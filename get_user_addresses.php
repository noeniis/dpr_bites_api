<?php
header('Content-Type: application/json');

// Koneksi database
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

if (!isset($data['id_users'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter id_users tidak ditemukan']);
    exit;
}

$id_users = $data['id_users'];

$sql = "SELECT * FROM alamat_pengantaran WHERE id_users = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_users);
$stmt->execute();
$result = $stmt->get_result();

$addresses = [];
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'addresses' => $addresses
]);