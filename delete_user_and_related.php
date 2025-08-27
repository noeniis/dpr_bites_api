<?php
header('Content-Type: application/json');
require_once 'db.php';

$id_users = $_POST['id_users'] ?? '';

if (!$id_users) {
    echo json_encode(['success' => false, 'message' => 'ID user wajib diisi']);
    exit;
}

$id_users = intval($id_users);

// Cari id_gerai milik user
$gerai_ids = [];
$res = $conn->query("SELECT id_gerai FROM gerai WHERE id_users = $id_users");
while ($row = $res->fetch_assoc()) {
    $gerai_ids[] = $row['id_gerai'];
}
$id_gerai_list = !empty($gerai_ids) ? implode(',', array_map('intval', $gerai_ids)) : '0';

// ==== Step 1: transaksi_item_addon ====
$conn->query("DELETE tia FROM transaksi_item_addon tia
              JOIN transaksi_item ti ON tia.id_transaksi_item = ti.id_transaksi_item
              JOIN transaksi t ON ti.id_transaksi = t.id_transaksi
              WHERE t.id_users = $id_users OR t.id_gerai IN ($id_gerai_list)");

// ==== Step 2: menu_addon ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE ma FROM menu_addon ma
                  JOIN menu m ON ma.id_menu = m.id_menu
                  WHERE m.id_gerai IN ($id_gerai_list)");
}

// ==== Step 3: transaksi_item ====
$conn->query("DELETE ti FROM transaksi_item ti
              JOIN transaksi t ON ti.id_transaksi = t.id_transaksi
              WHERE t.id_users = $id_users OR t.id_gerai IN ($id_gerai_list)");

// ==== Step 4: ulasan ====
$conn->query("DELETE FROM ulasan 
              WHERE id_users = $id_users 
              OR id_transaksi IN (SELECT id_transaksi FROM transaksi WHERE id_users = $id_users OR id_gerai IN ($id_gerai_list))");

// ==== Step 5: favorite ====
$conn->query("DELETE FROM favorite 
              WHERE id_users = $id_users 
              OR id_menu IN (SELECT id_menu FROM menu WHERE id_gerai IN ($id_gerai_list))");

// ==== Step 6: alamat_pengantaran ====
$conn->query("DELETE FROM alamat_pengantaran WHERE id_users = $id_users");

// ==== Step 7: transaksi ====
$conn->query("DELETE FROM transaksi WHERE id_users = $id_users OR id_gerai IN ($id_gerai_list)");

// ==== Step 8: addon ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE FROM addon WHERE id_gerai IN ($id_gerai_list)");
}

// ==== Step 9: menu ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE FROM menu WHERE id_gerai IN ($id_gerai_list)");
}

// ==== Step 10: etalase ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE FROM etalase WHERE id_gerai IN ($id_gerai_list)");
}

// ==== Step 11: gerai_profil ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE FROM gerai_profil WHERE id_gerai IN ($id_gerai_list)");
}

// ==== Step 12: penjual_info ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE FROM penjual_info WHERE id_users = $id_users OR id_gerai IN ($id_gerai_list)");
}

// ==== Step 13: gerai ====
if (!empty($gerai_ids)) {
    $conn->query("DELETE FROM gerai WHERE id_users = $id_users");
}

// ==== Step 14: users ====
$conn->query("DELETE FROM users WHERE id_users = $id_users");

echo json_encode(['success' => true, 'message' => 'Akun dan berhasil dihapus']);
