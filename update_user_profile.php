<?php
header('Content-Type: application/json');
require_once 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_users = $data['id_users'] ?? '';
$email = $data['email'] ?? null;
$no_hp = $data['no_hp'] ?? null;

if (!$id_users || (!$email && !$no_hp)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Cek email unik jika ingin update email
if ($email !== null) {
    $cek = $conn->prepare('SELECT id_users FROM users WHERE email=? AND id_users!=?');
    $cek->bind_param('si', $email, $id_users);
    $cek->execute();
    $cek->store_result();
    if ($cek->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email sudah digunakan user lain']);
        exit;
    }
}

$fields = [];
$params = [];
$types = '';
if ($email !== null) {
    $fields[] = 'email=?';
    $params[] = $email;
    $types .= 's';
}
if ($no_hp !== null) {
    $fields[] = 'no_hp=?';
    $params[] = $no_hp;
    $types .= 's';
}
$params[] = $id_users;
$types .= 'i';

$sql = 'UPDATE users SET ' . implode(',', $fields) . ' WHERE id_users=?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal update profil']);
}
