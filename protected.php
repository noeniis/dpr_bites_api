<?php
require __DIR__ . '/vendor/autoload.php';
require 'config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
$headers = getallheaders();

if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["error" => "No token provided"]);
    exit;
}

$jwt = str_replace("Bearer ", "", $headers['Authorization']);

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    // Ambil id_users dari token
    $id_users = $decoded->id_users;
    // Contoh query data user
    $host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'dpr_bites';
    $conn = new mysqli($host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->connect_error]);
        exit;
    }
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE id_users = ?");
    $stmt->bind_param("i", $id_users);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    echo json_encode(["message" => "Access granted", "user" => $user]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token", "details" => $e->getMessage()]);
}
