<?php
header('Content-Type: application/json');

// Enforce JWT auth (silent include to avoid output noise)
ob_start();
require_once __DIR__ . '/protected.php';
ob_end_clean();
// protected.php should set $id_users from token
$auth_user_id = isset($id_users) ? intval($id_users) : 0;
if ($auth_user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

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

// Body is optional, we'll trust token user id
$data = json_decode(file_get_contents('php://input'), true);

$req_user = $auth_user_id;

$sql = "SELECT * FROM alamat_pengantaran WHERE id_users = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $req_user);
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