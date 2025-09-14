<?php
require __DIR__ . '/vendor/autoload.php';
require 'config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Side-effect free: do not echo or set headers on success.
// On failure, respond with 401 + JSON then exit.

// Get Authorization header robustly across environments
$authHeader = null;
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) { // some servers lowercase
        $authHeader = $headers['authorization'];
    }
}
if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}
if (!$authHeader && isset($_SERVER['Authorization'])) {
    $authHeader = $_SERVER['Authorization'];
}

if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(["error" => "No token provided"]);
    exit;
}

$jwt = trim(substr($authHeader, 7));

try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET, 'HS256'));
    // Set id_users from token payload
    $id_users = isset($decoded->id_users) ? (int)$decoded->id_users : 0;
    if ($id_users <= 0) {
        throw new Exception('Token payload missing id_users');
    }

    // Optional: verify user exists; no output on success
    $host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'dpr_bites';
    $conn = @new mysqli($host, $db_user, $db_pass, $db_name);
    if ($conn && !$conn->connect_error) {
        if ($stmt = $conn->prepare("SELECT 1 FROM users WHERE id_users = ? LIMIT 1")) {
            $stmt->bind_param('i', $id_users);
            $stmt->execute();
            $res = $stmt->get_result();
            if (!$res || $res->num_rows === 0) {
                // User not found
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(["error" => "Unauthorized user"]);
                $stmt->close();
                $conn->close();
                exit;
            }
            $stmt->close();
        }
        $conn->close();
    }
    // Success path: no echo, no headers; caller continues with $id_users
} catch (Exception $e) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid token", "details" => $e->getMessage()]);
    exit;
}
