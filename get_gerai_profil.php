<?php
require 'db.php';
header('Content-Type: application/json');

// Ambil data dari JSON body
$input = json_decode(file_get_contents('php://input'), true);
$id_users = isset($input['id_users']) ? intval($input['id_users']) : null;

if (!$id_users) {
    echo json_encode(['success' => false, 'error' => 'id_users required']);
    exit;
}

global $conn;

// Cari id_gerai berdasarkan id_users
$sql_gerai = "SELECT id_gerai FROM gerai WHERE id_users = ? LIMIT 1";
$stmt_gerai = $conn->prepare($sql_gerai);
$stmt_gerai->bind_param('i', $id_users);
$stmt_gerai->execute();
$result_gerai = $stmt_gerai->get_result();

if ($result_gerai->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Gerai not found']);
    exit;
}

$id_gerai = $result_gerai->fetch_assoc()['id_gerai'];

// Cari data profil di gerai_profil
$sql_profil = "SELECT * FROM gerai_profil WHERE id_gerai = ? LIMIT 1";
$stmt_profil = $conn->prepare($sql_profil);
$stmt_profil->bind_param('i', $id_gerai);
$stmt_profil->execute();
$result_profil = $stmt_profil->get_result();

if ($result_profil->num_rows === 0) {
    // Profil belum ada → tetap return id_gerai
    echo json_encode([
        'success' => true,
        'id_gerai' => $id_gerai,
        'profil' => null
    ]);
    exit;
}

// Profil ada → return id_gerai + data profil
$data = $result_profil->fetch_assoc();
echo json_encode([
    'success' => true,
    'id_gerai' => $id_gerai,
    'profil' => $data
]);
