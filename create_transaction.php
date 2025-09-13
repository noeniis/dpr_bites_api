<?php
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
// Require JWT and get user id from token
require_once __DIR__ . '/protected.php';
$token_user_id = isset($id_users) ? (int)$id_users : 0;
// Simple server-side log (ensure web server user can write tmp dir)
@file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." RAW=".$raw."\n", FILE_APPEND);

// Map & validate fields (DB schema)
$userId  = $token_user_id; // always use token user
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
// Alamat pengantaran yang dipilih user (opsional; hanya relevan jika jenis = pengantaran)
$idAlamat = 0;
$idAlamatReason = 'not_applicable';
if ($jenis === 'pengantaran') {
    $idAlamatReason = 'not_sent';
    $rawAlamat = $data['id_alamat'] ?? null;
    if ($rawAlamat !== null && $rawAlamat !== '') {
        $tmp = intval($rawAlamat);
        if ($tmp > 0) {
            // Validasi: alamat harus milik user yang sama
            if ($stmtAddr = $mysqli->prepare('SELECT 1 FROM alamat_pengantaran WHERE id_alamat=? AND id_users=? LIMIT 1')) {
                $stmtAddr->bind_param('ii', $tmp, $userId);
                if ($stmtAddr->execute()) {
                    $stmtAddr->store_result();
                    if ($stmtAddr->num_rows > 0) {
                        $idAlamat = $tmp; // valid
                        $idAlamatReason = 'provided_and_valid';
                    } else {
                        $idAlamatReason = 'provided_but_not_owned_or_not_found';
                    }
                } else {
                    $idAlamatReason = 'validation_query_failed:'.$stmtAddr->error;
                }
                $stmtAddr->close();
            } else {
                $idAlamatReason = 'prepare_validation_failed:'.$mysqli->error;
            }
        } else {
            $idAlamatReason = 'provided_but_not_positive_int';
        }
    }
    // Fallback: jika belum valid, otomatis pilih alamat default / terbaru milik user agar id_alamat tidak null
    if ($idAlamat <= 0) {
        // Fallback real schema: alamat_utama sebagai penanda default
        $fallbackTried = false; $fallbackSuccess = false; $fallbackError = null;
        $queries = [
            // alamat_utama = 1 (default)
            "SELECT id_alamat FROM alamat_pengantaran WHERE id_users=$userId AND alamat_utama=1 ORDER BY id_alamat DESC LIMIT 1",
            // terbaru milik user
            "SELECT id_alamat FROM alamat_pengantaran WHERE id_users=$userId ORDER BY id_alamat DESC LIMIT 1",
        ];
        foreach ($queries as $q) {
            $fallbackTried = true;
            if ($resF = @$mysqli->query($q)) {
                if ($resF->num_rows > 0) {
                    $rowF = $resF->fetch_assoc();
                    $cand = intval($rowF['id_alamat'] ?? 0);
                    if ($cand > 0) { $idAlamat = $cand; $fallbackSuccess = true; $idAlamatReason .= '|fallback_assigned'; }
                }
                $resF->free();
                if ($fallbackSuccess) break;
            } else {
                $fallbackError = $mysqli->error; // simpan error terakhir
            }
        }
        if (!$fallbackSuccess) {
            $idAlamatReason .= '|fallback_failed'.($fallbackError?':'.$fallbackError:'');
        }
    }
}

// Log after resolution which id_alamat will be used
@file_put_contents(
    __DIR__.'/create_transaction_debug.log',
    date('c')." RESOLVED_ID_ALAMAT={$idAlamat} REASON={$idAlamatReason}\n",
    FILE_APPEND
);

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

// Enforce rule: pickup tidak boleh cash
$metode = strtolower($metode);
if ($jenis === 'pickup' && $metode === 'cash') {
    $metode = 'qris';
}
// Normalize allowed methods
if (!in_array($metode, ['qris','cash'], true)) { $metode = 'qris'; }

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
    // Dynamic insert (sertakan id_alamat hanya jika valid >0 agar mudah gunakan FK yang boleh NULL)
    $insertBranch = 'unknown';
    if ($idAlamat > 0) {
        $sqlIns = "INSERT INTO transaksi (booking_id, id_users, id_gerai, id_alamat, STATUS, metode_pembayaran, total_harga, biaya_pengantaran, jenis_pengantaran, bukti_pembayaran, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())";
        $stmt = $mysqli->prepare($sqlIns);
        if (!$stmt) throw new Exception('Prepare transaksi (with alamat) failed: '.$mysqli->error);
    // Types (10 params): s,i,i,i,s,s,i,i,s,s
    $types = 'siiissiiss';
    @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." PARAMS_BRANCH=with_alamat types=$types booking_id=$bookingId userId=$userId geraiId=$geraiId idAlamat=$idAlamat status=$status metode=$metode total=$total biaya=$biaya jenis=$jenis buktiLen=".strlen($buktiFilePath)."\n", FILE_APPEND);
        if (!$stmt->bind_param($types, $bookingId, $userId, $geraiId, $idAlamat, $status, $metode, $total, $biaya, $jenis, $buktiFilePath)) {
            throw new Exception('bind_param gagal (with alamat): '.$stmt->error.' types='.$types);
        }
        if (strlen($types) !== 10) {
            throw new Exception('Type string length mismatch (with alamat) len='.strlen($types));
        }
        $insertBranch = 'with_alamat';
    } else {
        $sqlIns = "INSERT INTO transaksi (booking_id, id_users, id_gerai, STATUS, metode_pembayaran, total_harga, biaya_pengantaran, jenis_pengantaran, bukti_pembayaran, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())";
        $stmt = $mysqli->prepare($sqlIns);
        if (!$stmt) throw new Exception('Prepare transaksi (no alamat) failed: '.$mysqli->error);
    // Types (9 params): s,i,i,s,s,i,i,s,s
    $types = 'siissiiss';
    @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." PARAMS_BRANCH=no_alamat types=$types booking_id=$bookingId userId=$userId geraiId=$geraiId status=$status metode=$metode total=$total biaya=$biaya jenis=$jenis buktiLen=".strlen($buktiFilePath)."\n", FILE_APPEND);
        if (!$stmt->bind_param($types, $bookingId, $userId, $geraiId, $status, $metode, $total, $biaya, $jenis, $buktiFilePath)) {
            throw new Exception('bind_param gagal (no alamat): '.$stmt->error.' types='.$types);
        }
        if (strlen($types) !== 9) {
            throw new Exception('Type string length mismatch (no alamat) len='.strlen($types));
        }
        $insertBranch = 'no_alamat';
    }
    if (!$stmt->execute()) throw new Exception('Insert transaksi failed: '.$stmt->error);
    @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." INSERT_BRANCH={$insertBranch} booking_id={$bookingId} id_alamat={$idAlamat}\n", FILE_APPEND);
    // Double-check inserted row's id_alamat (defensive):
    if ($idAlamat > 0) {
        if ($resCheck = $mysqli->query('SELECT id_alamat FROM transaksi WHERE booking_id=\''.$mysqli->real_escape_string($bookingId).'\' LIMIT 1')) {
            if ($rowChk = $resCheck->fetch_assoc()) {
                $actualInsertedAlamat = intval($rowChk['id_alamat'] ?? 0);
                if ($actualInsertedAlamat !== $idAlamat) {
                    @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." WARNING_MISMATCH_EXPECTED_ID_ALAMAT={$idAlamat} GOT={$actualInsertedAlamat}\n", FILE_APPEND);
                } else {
                    @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." CONFIRM_ID_ALAMAT_INSERTED={$actualInsertedAlamat}\n", FILE_APPEND);
                }
            }
            $resCheck->free();
        }
    }
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
        @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." ITEM_PRE_INSERT menuId=$menuId jumlah=$jumlah harga_satuan=$hargaSatuan subtotal=$subtotal addons_count=".(is_array($it['addons']??null)?count($it['addons']):0)." note_len=".strlen($note)."\n", FILE_APPEND);
        $stmtItem->bind_param('iiiiis', $transaksiId, $menuId, $jumlah, $hargaSatuan, $subtotal, $note);
        if (!$stmtItem->execute()) throw new Exception('Insert transaksi_item failed: '.$stmtItem->error);
        $tid = $stmtItem->insert_id;
        $addons = is_array($it['addons'] ?? null) ? $it['addons'] : [];
        foreach ($addons as $ad) {
            $adId = intval($ad);
            if ($adId <= 0) continue;
            $stmtAddon->bind_param('ii', $tid, $adId);
            if (!$stmtAddon->execute()) throw new Exception('Insert transaksi_item_addon failed: '.$stmtAddon->error);
            @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." ADDON_INSERT item_id=$tid addon_id=$adId\n", FILE_APPEND);
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
        'id_alamat'=>$idAlamat > 0 ? $idAlamat : null,
        'bukti_pembayaran'=>$buktiFilePath,
    ],'debug'=>[
        'incoming_jenis'=>$rawJenis,
        'final_jenis'=>$jenis,
    'validated_id_alamat'=>$idAlamat > 0 ? 'valid' : 'none',
    'id_alamat_reason'=>$idAlamatReason,
    'incoming_id_alamat'=> $data['id_alamat'] ?? null,
    'script_version'=>'ct_v4',
    'insert_branch'=>$insertBranch
    ]]);
} catch (Exception $e) {
    $mysqli->rollback();
    @file_put_contents(__DIR__.'/create_transaction_debug.log', date('c')." ERROR=".$e->getMessage()."\n", FILE_APPEND);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
