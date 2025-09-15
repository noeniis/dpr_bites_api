<?php
require_once __DIR__.'/protected.php';
require 'db.php';
header('Content-Type: application/json');

$id_gerai = $_POST['id_gerai'] ?? null;
$nama_etalase = $_POST['nama_etalase'] ?? null;

if (!$id_gerai || !$nama_etalase) {
    echo json_encode(['success' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

$query = "INSERT INTO etalase (id_gerai, nama_etalase) VALUES (?, ?)";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}
$stmt->bind_param("is", $id_gerai, $nama_etalase);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id_etalase' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
?>