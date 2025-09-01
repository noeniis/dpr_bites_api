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

// Determine requesting user: Authorization Bearer <id> or X-User-Id header or fallback to body
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
if ($req_user === 0 && isset($data['id_users'])) {
    $req_user = intval($data['id_users']);
}

if ($req_user <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter id_users tidak ditemukan atau tidak valid']);
    exit;
}

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