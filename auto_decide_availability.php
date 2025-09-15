<?php
// Endpoint untuk seller mengkonfirmasi ketersediaan otomatis ataupun menolak jika stok addon/menu kurang
// Body: {"booking_id"|"id_transaksi", "available":true/false, "alasan"?}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'dpr_bites';
$port = 3306;

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Ambil request body
$raw = file_get_contents('php://input');
if (!$raw) {
    echo json_encode(['success' => false, 'message' => 'Empty body']);
    exit;
}
$req = json_decode($raw, true);
if (!is_array($req)) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$idTransaksi = intval($req['id_transaksi'] ?? 0);
$bookingId   = trim($req['booking_id'] ?? '');
$available   = isset($req['available']) ? (bool)$req['available'] : null;
$alasan      = trim($req['alasan'] ?? '');

// Validate JWT and get $id_users
require_once __DIR__ . '/protected.php';
if (!isset($id_users) || $id_users <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($available === null) {
    echo json_encode(['success' => false, 'message' => 'available boolean wajib']);
    exit;
}

// Tentukan filter transaksi
$where = '';
if ($idTransaksi > 0) {
    $where = 'id_transaksi=' . $idTransaksi;
} elseif ($bookingId !== '') {
    $where = "booking_id='" . $mysqli->real_escape_string($bookingId) . "'";
} else {
    echo json_encode(['success' => false, 'message' => 'id_transaksi atau booking_id wajib']);
    exit;
}

// Ambil transaksi
$res = $mysqli->query("SELECT id_transaksi, STATUS FROM transaksi WHERE $where LIMIT 1");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
    exit;
}
$row = $res->fetch_assoc();
$res->free();

// Validasi status
if ($row['STATUS'] !== 'konfirmasi_ketersediaan') {
    echo json_encode(['success' => false, 'message' => 'Status sekarang bukan konfirmasi_ketersediaan, sekarang=' . $row['STATUS']]);
    exit;
}

// Tentukan status baru
$newStatus = $available ? 'konfirmasi_pembayaran' : 'dibatalkan';
if (!$available && $alasan === '') {
    $alasan = 'Stok tidak tersedia';
}

// Jika available true, lakukan pengurangan stok menu dan addon
if ($available) {
    $resI = $mysqli->query("SELECT ti.id_transaksi_item, ti.id_menu, ti.jumlah 
                            FROM transaksi_item ti 
                            WHERE ti.id_transaksi=" . (int)$row['id_transaksi']);
    if ($resI) {
        while ($rowI = $resI->fetch_assoc()) {
            $id_menu = (int)$rowI['id_menu'];
            $qty     = (int)$rowI['jumlah'];

            // Kurangi stok menu
            $mysqli->query("UPDATE menu 
                            SET jumlah_stok = GREATEST(jumlah_stok - $qty, 0) 
                            WHERE id_menu = $id_menu");

            // Kalau stok = 0, set tersedia = 0
            $mysqli->query("UPDATE menu 
                            SET tersedia = 0 
                            WHERE id_menu = $id_menu AND jumlah_stok <= 0");

            if ($mysqli->error) {
                error_log("Error update menu: " . $mysqli->error);
            }

            // Kurangi stok addon jika ada
            $id_transaksi_item = (int)$rowI['id_transaksi_item'];
            $resAd = $mysqli->query("SELECT tia.id_addon 
                                     FROM transaksi_item_addon tia 
                                     WHERE tia.id_transaksi_item = $id_transaksi_item");
            if ($resAd) {
                while ($rowAd = $resAd->fetch_assoc()) {
                    $id_addon = (int)$rowAd['id_addon'];

                    // Setiap addon dianggap 1 per item menu → stok berkurang sebanyak $qty
                    $mysqli->query("UPDATE addon 
                                    SET stok = GREATEST(stok - $qty, 0) 
                                    WHERE id_addon = $id_addon");

                    // Kalau stok addon = 0, set tersedia = 0
                    $mysqli->query("UPDATE addon 
                                    SET tersedia = 0 
                                    WHERE id_addon = $id_addon AND stok <= 0");

                    if ($mysqli->error) {
                        error_log("Error update addon: " . $mysqli->error);
                    }
                }
                $resAd->free();
            }
        }
        $resI->free();
    }
}

// Update status transaksi
$stmt = $mysqli->prepare("UPDATE transaksi 
                          SET STATUS=?, 
                              catatan_pembatalan=IF(?='',catatan_pembatalan,?) 
                          WHERE id_transaksi=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare fail: ' . $mysqli->error]);
    exit;
}
$stmt->bind_param('sssi', $newStatus, $alasan, $alasan, $row['id_transaksi']);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Update fail: ' . $stmt->error]);
    exit;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'data'    => [
        'id_transaksi' => $row['id_transaksi'],
        'from'         => 'konfirmasi_ketersediaan',
        'to'           => $newStatus,
        'alasan'       => $alasan
    ]
]);
