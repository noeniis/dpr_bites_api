<?php
require_once __DIR__.'/protected.php';
require 'db.php';
header('Content-Type: application/json');

// --- Ambil data ---
$data = [];

if (!empty($_POST)) {
    $data = $_POST;
} else {
    $input = file_get_contents("php://input");
    $json = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        $data = $json;
    }
}

// --- Validasi ---
$required = ['id_menu','id_addons'];
$missing = [];
foreach ($required as $key) {
    if (!isset($data[$key]) || $data[$key] === '') {
        $missing[] = $key;
    }
}

if (!empty($missing)) {
    echo json_encode([
        "success" => false,
        "error" => "Parameter tidak lengkap",
        "missing" => $missing,
        "received" => array_keys($data)
    ]);
    exit;
}

// --- Ambil variabel ---
$id_menu = intval($data['id_menu']);
$id_addons = is_string($data['id_addons']) ? json_decode($data['id_addons'], true) : $data['id_addons'];

if (!is_array($id_addons)) {
    echo json_encode(["success" => false, "error" => "id_addons harus berupa array"]);
    exit;
}

// --- Insert ---
$success = true;
$errors = [];

foreach ($id_addons as $id_addon) {
    $id_addon = intval($id_addon);
    $sql = "INSERT INTO menu_addon (id_menu, id_addon) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_menu, $id_addon);

    if (!$stmt->execute()) {
        $success = false;
        $errors[] = $stmt->error;
    }
}

if ($success) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $errors]);
}
