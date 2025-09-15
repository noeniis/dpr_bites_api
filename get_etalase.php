<?php
require_once __DIR__.'/protected.php';
require 'db.php';
header('Content-Type: application/json');

$id_gerai = $_GET['id_gerai'] ?? null;
if (!$id_gerai) {
    echo json_encode(['success' => false, 'error' => 'id_gerai diperlukan']);
    exit;
}
$query = "SELECT * FROM etalase WHERE id_gerai = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_gerai);
$stmt->execute();
$result = $stmt->get_result();
$etalase = [];
while ($row = $result->fetch_assoc()) {
    $etalase[] = $row;
}
echo json_encode(['success' => true, 'etalase' => $etalase]);
$stmt->close();
$conn->close();
?>