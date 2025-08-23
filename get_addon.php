<?php
require 'db.php';

$id_gerai = $_GET['id_gerai'] ?? null;
if (!$id_gerai) {
    echo json_encode(["success" => false, "addons" => []]);
    exit;
}

// Ambil data addon
$sql = "SELECT * FROM addon WHERE id_gerai = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id_gerai);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode([
    "success" => true,
    "addons" => $data
]);
?>
