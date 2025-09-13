<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

require_once __DIR__ . '/protected.php';
include 'db.php';

if (isset($_POST['update_tersedia'])) {
    // Update tersedia untuk menu atau addon
    $tersedia = isset($_POST['tersedia']) ? intval($_POST['tersedia']) : 0;
    if (isset($_POST['id_menu'])) {
        $id_menu = intval($_POST['id_menu']);
        $sql = "UPDATE menu SET tersedia = $tersedia WHERE id_menu = $id_menu";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Status menu diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update menu']);
        }
        exit;
    } else if (isset($_POST['id_addon'])) {
        $id_addon = intval($_POST['id_addon']);
        $sql = "UPDATE addon SET tersedia = $tersedia WHERE id_addon = $id_addon";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Status addon diupdate']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update addon']);
        }
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
        exit;
    }
}

$id_gerai = isset($_POST['id_gerai']) ? $_POST['id_gerai'] : null;
$filter = isset($_POST['filter']) ? $_POST['filter'] : 'all';
$data = [];

if ($id_gerai) {
    if ($filter === 'utama') {
        // Hanya menu utama
        $query = "SELECT *, 0 as is_addon FROM menu WHERE id_gerai = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_gerai);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Etalase (detail)
            $etalase = [];
            $qe = mysqli_query($conn, "SELECT e.* FROM menu me JOIN etalase e ON me.id_etalase = e.id_etalase WHERE me.id_menu = '{$row['id_menu']}'");
            while ($re = mysqli_fetch_assoc($qe)) {
                $etalase[] = $re;
            }
            $row['etalase'] = $etalase;
            // Add-on (detail)
            $addons = [];
            $qa = mysqli_query($conn, "SELECT a.* FROM menu_addon ma JOIN addon a ON ma.id_addon = a.id_addon WHERE ma.id_menu = '{$row['id_menu']}'");
            while ($ra = mysqli_fetch_assoc($qa)) {
                $addons[] = $ra;
            }
            $row['add_ons'] = $addons;
            $data[] = $row;
        }
        $stmt->close();
    } else if ($filter === 'addon') {
        // Hanya add-on
        $query = "SELECT *, 1 as is_addon FROM addon WHERE id_gerai = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_gerai);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    } else {
        // All: menu utama + add-on
        $query = "SELECT *, 0 as is_addon FROM menu WHERE id_gerai = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_gerai);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Etalase (detail)
            $etalase = [];
            $qe = mysqli_query($conn, "SELECT e.* FROM menu me JOIN etalase e ON me.id_etalase = e.id_etalase WHERE me.id_menu = '{$row['id_menu']}'");
            while ($re = mysqli_fetch_assoc($qe)) {
                $etalase[] = $re;
            }
            $row['etalase'] = $etalase;
            // Add-on (detail)
            $addons = [];
            $qa = mysqli_query($conn, "SELECT a.* FROM menu_addon ma JOIN addon a ON ma.id_addon = a.id_addon WHERE ma.id_menu = '{$row['id_menu']}'");
            while ($ra = mysqli_fetch_assoc($qa)) {
                $addons[] = $ra;
            }
            $row['add_ons'] = $addons;
            $data[] = $row;
        }
        $stmt->close();
        // Ambil semua add-on
        $query = "SELECT *, 1 as is_addon FROM addon WHERE id_gerai = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_gerai);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    }
}

echo json_encode(['success' => true, 'data' => $data]);
?>