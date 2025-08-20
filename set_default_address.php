<?php
header('Content-Type: application/json');

// Koneksi DB
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'dpr_bites';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit;
}

// Baca body JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id_users'], $input['id_alamat'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter id_users dan id_alamat wajib diisi']);
    exit;
}

$id_users  = (int)$input['id_users'];
$id_alamat = (int)$input['id_alamat'];

$conn->begin_transaction();
try {
    // Reset default semua alamat milik user
    $stmt1 = $conn->prepare('UPDATE alamat_pengantaran SET alamat_utama = 0 WHERE id_users = ?');
    $stmt1->bind_param('i', $id_users);
    if (!$stmt1->execute()) {
        throw new Exception('Gagal reset default: ' . $stmt1->error);
    }
    $stmt1->close();

    // Set alamat utama pada id_alamat milik user
    $stmt2 = $conn->prepare('UPDATE alamat_pengantaran SET alamat_utama = 1 WHERE id_users = ? AND id_alamat = ?');
    $stmt2->bind_param('ii', $id_users, $id_alamat);
    if (!$stmt2->execute()) {
        throw new Exception('Gagal set default: ' . $stmt2->error);
    }
    if ($stmt2->affected_rows < 1) {
        throw new Exception('Alamat tidak ditemukan untuk user ini');
    }
    $stmt2->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Alamat utama diperbarui']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}