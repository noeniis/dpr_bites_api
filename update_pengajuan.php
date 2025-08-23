<?php
require 'db.php';

$id_gerai = $_POST['id_gerai'];
$status = $_POST['status'];
$alasan = $_POST['alasan'] ?? null;

$sql = "UPDATE gerai SET status_pengajuan=?, alasan_tolak=? WHERE id_gerai=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $alasan, $id_gerai);
$success = $stmt->execute();

echo json_encode(['success' => $success]);
?>