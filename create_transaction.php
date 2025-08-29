<?php
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$host = 'localhost'; $user = 'root'; $pass = ''; $db = 'dpr_bites'; $port = 3306;
$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo json_encode([
        'success' => false,
        'message' => 'DB connect error: ' . $mysqli->connect_error,
    ]);
    exit;
}
$mysqli->set_charset('utf8mb4');
$raw = file_get_contents('php://input');
if (!$raw) { echo json_encode(['success'=>false,'message'=>'Empty body']); exit; }
$data = json_decode($raw, true);
if (!is_array($data)) { echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit; }
// Simple server-side log (ensure web server user can write tmp dir)
@file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." RAW=".$raw."\n", FILE_APPEND);

// Map & validate fields (DB schema)
$userId  = intval($data['id_users'] ?? $data['user_id'] ?? 0);
$geraiId = intval($data['id_gerai'] ?? $data['gerai_id'] ?? 0);
$metode  = ($data['metode_pembayaran'] ?? $data['payment_method'] ?? 'qris');
// Normalisasi jenis_pengantaran; fallback ke is_delivery (true=>pengantaran)
$rawJenis = $data['jenis_pengantaran'] ?? null;
if (is_string($rawJenis)) { $rawJenis = trim(strtolower($rawJenis)); }
$isDeliveryFlag = isset($data['is_delivery']) ? (bool)$data['is_delivery'] : null;
$jenis = $rawJenis ?: ($isDeliveryFlag === null ? null : ($isDeliveryFlag ? 'pengantaran' : 'pickup'));
$total   = intval($data['total_harga'] ?? 0);
$biaya   = intval($data['biaya_pengantaran'] ?? ($jenis === 'pengantaran' ? 5000 : 0));
$buktiBase64 = $data['bukti_base64'] ?? null; // base64 image string
$items = is_array($data['items'] ?? null) ? $data['items'] : [];

if ($userId <=0 || $geraiId <=0 || empty($items) || $total<=0) {
    echo json_encode(['success'=>false,'message'=>'Missing required fields','debug'=>['incoming_jenis'=>$rawJenis,'derived_jenis'=>$jenis]]);
    exit;
}

// Generate short booking_id (unique, lebih pendek)
// Format: F + 6 hex chars (24 bits randomness) -> contoh: F3A9BC1D
// Loop sampai unik (sangat jarang lebih dari 1 iterasi)
do {
    $bookingId = 'F-'.strtoupper(bin2hex(random_bytes(3))); // 1 + 6 chars
    $res = $mysqli->query("SELECT 1 FROM transaksi WHERE booking_id='".$mysqli->real_escape_string($bookingId)."' LIMIT 1");
    $exists = $res && $res->num_rows > 0;
    if ($res) $res->free();
} while ($exists);

// Determine initial STATUS
$status = 'konfirmasi_ketersediaan';

// Handle bukti pembayaran (required NOT NULL) -> save file if base64 provided, else blank placeholder
// Upload ke Cloudinary (unsigned) -> simpan secure_url
$cloudName = 'dip8i3f6x';
$uploadPreset = 'dpr_bites'; // unsigned preset
$buktiFilePath = ''; // Awal transaksi: belum ada bukti pembayaran (user belum bayar / masih cek ketersediaan)
// Simpan kosong; upload akan dilakukan setelah dialog pembayaran (QRIS) / konfirmasi seller (cash)
if ($buktiBase64) {
    if (preg_match('/^data:(image\/(png|jpe?g));base64,(.+)$/i', $buktiBase64, $m)) {
        $b64 = $m[3];
    } else {
        $b64 = preg_replace('/\s+/', '', $buktiBase64);
    }
    $binary = base64_decode($b64, true);
    if ($binary !== false) {
        // Kirim langsung sebagai data URI agar Cloudinary terima
        $tmpDataUri = 'data:image/png;base64,'.base64_encode($binary); // normalisasi
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/$cloudName/image/upload");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => $tmpDataUri,
            'upload_preset' => $uploadPreset,
            // optional folder: 'folder' => 'bukti_pembayaran'
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$err && $resp && $httpCode === 200) {
            $json = json_decode($resp, true);
            if (is_array($json) && !empty($json['secure_url'])) {
                $buktiFilePath = $json['secure_url'];
            }
        }
    }
}
// Biarkan kosong ("") jika belum ada bukti transaksi

$mysqli->begin_transaction();
try {
    // (Tidak perlu placeholder lokal lagi karena gunakan URL Cloudinary)

    $cartItemIdsToDelete = [];

    // Sanitasi jenis pengantaran agar selalu valid
    $jenis = ($jenis === 'pengantaran' || $jenis === 'pickup') ? $jenis : 'pickup';
    $stmt = $mysqli->prepare("INSERT INTO transaksi (booking_id, id_users, id_gerai, STATUS, metode_pembayaran, total_harga, biaya_pengantaran, jenis_pengantaran, bukti_pembayaran, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
    if (!$stmt) throw new Exception('Prepare transaksi failed: '.$mysqli->error);
    // Types: s (booking_id) i (id_users) i (id_gerai) s (STATUS) s (metode) i (total) i (biaya) s (jenis_pengantaran) s (bukti_pembayaran)
    // Bangun string tipe per karakter untuk menghindari kemungkinan karakter tak terlihat
    $types = 's'.'i'.'i'.'s'.'s'.'i'.'i'.'s'.'s'; // seharusnya menghasilkan siissiisss
    $params = [&$bookingId,&$userId,&$geraiId,&$status,&$metode,&$total,&$biaya,&$jenis,&$buktiFilePath];
    if (strlen($types) !== count($params)) {
        echo json_encode([
            'success'=>false,
            'message'=>'Type/param length mismatch (phase2)',
            'len_types'=>strlen($types),
            'param_count'=>count($params),
            'types'=>$types,
            'types_hex'=>bin2hex($types)
        ]);
        exit;
    }
    // Gunakan call_user_func_array agar eksplisit
    $bindOk = $stmt->bind_param($types, ...$params);
    if (!$bindOk) {
        echo json_encode([
            'success'=>false,
            'message'=>'bind_param failed phase2',
            'stmt_error'=>$stmt->error,
            'types'=>$types,
            'types_hex'=>bin2hex($types)
        ]);
        exit;
    }
    if (!$stmt->execute()) throw new Exception('Insert transaksi failed: '.$stmt->error);
    if ($stmt->affected_rows <= 0) {
        throw new Exception('Insert transaksi no rows (possible enum mismatch jenis_pengantaran='.$jenis.')');
    }
    $transaksiId = $stmt->insert_id;
    $stmt->close();

    $stmtItem = $mysqli->prepare("INSERT INTO transaksi_item (id_transaksi, id_menu, jumlah, harga_satuan, subtotal, note) VALUES (?,?,?,?,?,?)");
    if (!$stmtItem) throw new Exception('Prepare item failed: '.$mysqli->error);
    $stmtAddon = $mysqli->prepare("INSERT INTO transaksi_item_addon (id_transaksi_item, id_addon) VALUES (?,?)");
    if (!$stmtAddon) throw new Exception('Prepare addon failed: '.$mysqli->error);

    foreach ($items as $it) {
        $menuId = intval($it['id_menu'] ?? $it['menu_id'] ?? 0);
        $jumlah = intval($it['jumlah'] ?? $it['qty'] ?? 1);
        $hargaSatuan = intval($it['harga_satuan'] ?? 0);
        $subtotal = intval($it['subtotal'] ?? ($hargaSatuan * $jumlah));
        $note = $it['note'] ?? '';
        if ($menuId <= 0) continue;
        $stmtItem->bind_param('iiiiis', $transaksiId, $menuId, $jumlah, $hargaSatuan, $subtotal, $note);
        if (!$stmtItem->execute()) throw new Exception('Insert transaksi_item failed: '.$stmtItem->error);
        $tid = $stmtItem->insert_id;
        $addons = is_array($it['addons'] ?? null) ? $it['addons'] : [];
        foreach ($addons as $ad) {
            $adId = intval($ad);
            if ($adId <= 0) continue;
            $stmtAddon->bind_param('ii', $tid, $adId);
            if (!$stmtAddon->execute()) throw new Exception('Insert transaksi_item_addon failed: '.$stmtAddon->error);
        }
        // Kumpulkan id keranjang item untuk dihapus nanti
        if (!empty($it['cart_item_id'])) {
            $cid = intval($it['cart_item_id']);
            if ($cid > 0) {
                $cartItemIdsToDelete[] = $cid;
            }
        } elseif (!empty($it['id_keranjang_item'])) { // fallback nama lain
            $cid = intval($it['id_keranjang_item']);
            if ($cid > 0) {
                $cartItemIdsToDelete[] = $cid;
            }
        }
    }
    $stmtItem->close();
    $stmtAddon->close();

    // Hapus item keranjang yang sudah masuk transaksi (soft clearance)
    if (!empty($cartItemIdsToDelete)) {
        // Filter unique
        $cartItemIdsToDelete = array_values(array_unique($cartItemIdsToDelete));
        // Pastikan hanya item milik user & gerai ini untuk keamanan
        $idsStr = implode(',', array_map('intval', $cartItemIdsToDelete));
        // Dapatkan id_keranjang milik user & gerai
        $resK = $mysqli->query("SELECT id_keranjang FROM keranjang WHERE id_users=$userId AND id_gerai=$geraiId AND status='aktif' LIMIT 1");
        if ($resK && $resK->num_rows>0) {
            $rowK = $resK->fetch_assoc();
            $idKeranjang = intval($rowK['id_keranjang']);
            $resK->free();
            // Delete addons dulu (ON DELETE CASCADE juga bisa, tapi kita eksplisit)
            $mysqli->query("DELETE kia FROM keranjang_item_addon kia INNER JOIN keranjang_item ki ON kia.id_keranjang_item=ki.id_keranjang_item WHERE ki.id_keranjang_item IN ($idsStr) AND ki.id_keranjang=$idKeranjang");
            $mysqli->query("DELETE FROM keranjang_item WHERE id_keranjang_item IN ($idsStr) AND id_keranjang=$idKeranjang");
            // Update agregat keranjang (recalculate)
            $resAgg = $mysqli->query("SELECT SUM(subtotal) total_harga, SUM(qty) total_qty FROM keranjang_item WHERE id_keranjang=$idKeranjang");
            $totalHargaKeranjang = 0; $totalQtyKeranjang = 0;
            if ($resAgg) { $agg = $resAgg->fetch_assoc(); $totalHargaKeranjang = intval($agg['total_harga'] ?? 0); $totalQtyKeranjang = intval($agg['total_qty'] ?? 0); $resAgg->free(); }
            $newStatus = ($totalQtyKeranjang>0) ? 'aktif' : 'checkout'; // jika kosong tandai sudah checkout
            $stmtUpdK = $mysqli->prepare("UPDATE keranjang SET total_harga=?, total_qty=?, status=? WHERE id_keranjang=?");
            if ($stmtUpdK) {
                $stmtUpdK->bind_param('iisi', $totalHargaKeranjang, $totalQtyKeranjang, $newStatus, $idKeranjang);
                $stmtUpdK->execute();
                $stmtUpdK->close();
            }
        }
    }

    $mysqli->commit();
    echo json_encode(['success'=>true,'data'=>[
        'id_transaksi'=>$transaksiId,
        'booking_id'=>$bookingId,
        'status'=>$status,
        'jenis_pengantaran'=>$jenis,
        'bukti_pembayaran'=>$buktiFilePath,
    ],'debug'=>['incoming_jenis'=>$rawJenis,'final_jenis'=>$jenis]]);
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
