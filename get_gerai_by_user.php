<?php
header('Content-Type: application/json');
include 'db.php';

$id_users = isset($_POST['id_users']) ? $_POST['id_users'] : null;
$id_gerai = null;

// Debug
error_log('id_users dari Flutter: ' . $id_users);
error_log('Isi POST: ' . print_r($_POST, true));

if ($id_users) {
    $query = "SELECT id_gerai FROM gerai WHERE id_users = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_users); 
    $stmt->execute();
    $stmt->bind_result($id_gerai);
    $stmt->fetch();
    $stmt->close();
}

echo json_encode(['success' => true, 'id_gerai' => $id_gerai]);
?>