<?php
require_once __DIR__.'/protected.php';
require 'db.php';
header('Content-Type: application/json');

$id_addon = $_POST['id_addon'] ?? '';
if (!$id_addon || !ctype_digit((string)$id_addon)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID add-on tidak valid']);
    exit;
}

$conn->begin_transaction();

try {
    // Hapus relasi dari menu_addon
    $stmt = $conn->prepare("DELETE FROM menu_addon WHERE id_addon = ?");
    $stmt->bind_param('i', $id_addon);
    $stmt->execute();
    $stmt->close();

    // Hapus add-on
    $stmt = $conn->prepare("DELETE FROM addon WHERE id_addon = ?");
    $stmt->bind_param('i', $id_addon);
    $stmt->execute();

    if ($stmt->affected_rows < 1) {
        throw new Exception('Add-on tidak ditemukan atau sudah dihapus.');
    }

    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Add-on berhasil dihapus']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
