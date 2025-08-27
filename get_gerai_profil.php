<?php
require 'db.php';
header('Content-Type: application/json');

$id_users = isset($_GET['id_users']) ? $_GET['id_users'] : null;
if (!$id_users) {
    echo json_encode(['error' => 'id_users required']);
    exit;
}

// Cari id_gerai berdasarkan id_users
global $conn;
$sql_gerai = "SELECT id_gerai FROM gerai WHERE id_users = ? LIMIT 1";
$stmt_gerai = $conn->prepare($sql_gerai);
$stmt_gerai->bind_param('i', $id_users);
$stmt_gerai->execute();
$result_gerai = $stmt_gerai->get_result();
if ($result_gerai->num_rows === 0) {
    echo json_encode(['error' => 'Gerai not found']);
    exit;
}
$id_gerai = $result_gerai->fetch_assoc()['id_gerai'];

// Ambil data gerai_profil
$sql_profil = "SELECT * FROM gerai_profil WHERE id_gerai = ? LIMIT 1";
$stmt_profil = $conn->prepare($sql_profil);
$stmt_profil->bind_param('i', $id_gerai);
$stmt_profil->execute();
$result_profil = $stmt_profil->get_result();
if ($result_profil->num_rows === 0) {
    echo json_encode(['error' => 'Gerai profil not found']);
    exit;
}
$data = $result_profil->fetch_assoc();
echo json_encode($data);
?>
