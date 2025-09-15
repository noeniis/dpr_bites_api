<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

require_once __DIR__.'/protected.php';
require 'db.php';

$id_gerai = isset($_GET['id_gerai']) ? (int)$_GET['id_gerai'] : 0;
if ($id_gerai <= 0) {
    echo json_encode(["success" => false, "addons" => [], "message" => "id_gerai required"]);
    exit;
}

$sql = "SELECT id_addon, id_gerai, nama_addon, harga, image_path, tersedia, stok FROM addon WHERE id_gerai = ?";
$stmt = $conn->prepare($sql);
if(!$stmt){ echo json_encode(["success"=>false,"message"=>"prepare failed"]); exit; }
$stmt->bind_param("i", $id_gerai);
if(!$stmt->execute()){ echo json_encode(["success"=>false,"message"=>"execute failed"]); exit; }
$result = $stmt->get_result();
if(!$result){ echo json_encode(["success"=>false,"addons"=>[]]); exit; }
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode([
    "success" => true,
    "addons" => $data
]);
?>
