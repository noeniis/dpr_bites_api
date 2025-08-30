<?php
header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "dpr_bites");

$data = json_decode(file_get_contents("php://input"), true);

// Determine requesting user id from Authorization or X-User-Id header, fallback to body
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
    echo json_encode(["success" => false, "message" => "ID user wajib diisi atau berada di header"]);
    exit;
}

// Ambil alamat utama dari tabel alamat_pengantaran untuk user yang sedang login
$stmt = $conn->prepare("SELECT nama_gedung, detail_pengantaran FROM alamat_pengantaran WHERE id_users=? AND alamat_utama=1 LIMIT 1");
$stmt->bind_param("i", $req_user);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        "success" => true,
        "has_address" => true,
        "nama_gedung" => $row['nama_gedung'],
        "detail_pengantaran" => $row['detail_pengantaran']
    ]);
} else {
    echo json_encode(["success" => true, "has_address" => false]);
}