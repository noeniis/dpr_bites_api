<?php
require_once __DIR__.'/protected.php';
require 'db.php';
header('Content-Type: application/json');

$id_menu = $_POST['id_menu'] ?? '';
if (!$id_menu || !ctype_digit((string)$id_menu)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID menu tidak valid']);
    exit;
}

$conn->begin_transaction();

try {
    // Hapus relasi dulu
    $stmt = $conn->prepare("DELETE FROM menu_addon WHERE id_menu = ?");
    $stmt->bind_param('i', $id_menu);
    $stmt->execute();
    $stmt->close();


    // Hapus menu utama
    $stmt = $conn->prepare("DELETE FROM menu WHERE id_menu = ?");
    $stmt->bind_param('i', $id_menu);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        throw new Exception('Menu tidak ditemukan atau sudah dihapus.');
    }

    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Menu berhasil dihapus']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(200); // biar Flutter tetap bisa baca JSON, tapi success=false
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
