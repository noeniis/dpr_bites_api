<?php
require 'db.php';
$id_gerai = $_POST['id_gerai'] ?? null;
$status = $_POST['status'] ?? null;
$alasan = $_POST['alasan'] ?? null;

if ($id_gerai === null || $status === null) {
    echo json_encode(['success' => false, 'error' => 'Missing id_gerai or status', 'id_users' => null]);
    exit;
}


$sql = "UPDATE gerai SET status_pengajuan=?, alasan_tolak=? WHERE id_gerai=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $status, $alasan, $id_gerai);
$success = $stmt->execute();

// Ambil id_users dari gerai
$id_users = null;
if ($success) {
    $sqlUser = "SELECT id_users FROM gerai WHERE id_gerai=?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("i", $id_gerai);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();
    if ($row = $resultUser->fetch_assoc()) {
        $id_users = $row['id_users'];
    }
}

echo json_encode([
    'success' => $success,
    'id_users' => $id_users
]);
?>