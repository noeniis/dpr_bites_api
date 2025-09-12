<?php
require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = "secret123";
$payload = [
    "id_user" => 1,
    "role" => "seller",
    "iat" => time(),
    "exp" => time() + 3600
];

$jwt = JWT::encode($payload, $key, 'HS256');
echo "Generated token: " . $jwt . "\n";

$decoded = JWT::decode($jwt, new Key($key, 'HS256'));
print_r($decoded);
