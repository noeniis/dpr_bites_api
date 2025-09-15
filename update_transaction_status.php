<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');
$raw=file_get_contents('php://input');
if(!$raw){echo json_encode(['success'=>false,'message'=>'Empty body']);exit;}
$req=json_decode($raw,true);
if(!is_array($req)){echo json_encode(['success'=>false,'message'=>'Invalid JSON']);exit;}

// Require JWT and get user id from token (use helper if available)
require_once __DIR__ . '/protected.php';
if (function_exists('getTokenUserId')) {
  $token_user_id = getTokenUserId();
} elseif (function_exists('requireAuth')) {
  $token_user_id = requireAuth();
} else {
  $token_user_id = isset($id_users) ? (int)$id_users : 0;
}

$idTransaksi = intval($req['id_transaksi'] ?? 0);
$bookingId = trim($req['booking_id'] ?? '');
$newStatus = trim(strtolower($req['new_status'] ?? ''));
$alasan = trim($req['alasan'] ?? '');

$allowed = ['konfirmasi_ketersediaan','konfirmasi_pembayaran','disiapkan','diantar','pickup','selesai','dibatalkan'];
if(!in_array($newStatus,$allowed,true)){
  echo json_encode(['success'=>false,'message'=>'Status tidak valid']);exit;
}

$where='';
if($idTransaksi>0){
  $where='id_transaksi='.$idTransaksi;
}else if($bookingId!==''){
  $where="booking_id='".$mysqli->real_escape_string($bookingId)."'";
}else{
  echo json_encode(['success'=>false,'message'=>'Harus sertakan id_transaksi atau booking_id']);exit;
}

$res=$mysqli->query("SELECT id_transaksi, id_users, id_gerai, STATUS, jenis_pengantaran, metode_pembayaran FROM transaksi WHERE $where LIMIT 1");
if(!$res || $res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$row=$res->fetch_assoc();
$res->free();

// Role-based permission checks: allow seller owner for seller actions, buyer for buyer actions
$current=$row['STATUS'];
$jenis=$row['jenis_pengantaran'];
$metode=$row['metode_pembayaran'];

$seller_actions = ['konfirmasi_ketersediaan','disiapkan','diantar','pickup','selesai'];
$buyer_actions = ['konfirmasi_pembayaran'];

// Fetch gerai owner id to allow role checks without helper
$geraiOwnerId = 0;
if (isset($row['id_gerai']) && (int)$row['id_gerai'] > 0) {
  $rG = $mysqli->query("SELECT id_users FROM gerai WHERE id_gerai = " . (int)$row['id_gerai'] . " LIMIT 1");
  if ($rG && $rG->num_rows > 0) {
    $gRow = $rG->fetch_assoc();
    $geraiOwnerId = (int)$gRow['id_users'];
    $rG->free();
  }
}

if (in_array($newStatus, $seller_actions, true)) {
  // require gerai owner for seller actions
  if ($geraiOwnerId !== $token_user_id) {
    echo json_encode(['success'=>false,'message'=>'Forbidden: seller only']);exit;
  }
} elseif (in_array($newStatus, $buyer_actions, true)) {
  // require buyer for buyer actions
  if ($token_user_id <= 0 || (int)$row['id_users'] !== $token_user_id) {
    echo json_encode(['success'=>false,'message'=>'Forbidden: buyer only']);exit;
  }
} elseif ($newStatus === 'dibatalkan') {
  // cancellation: allow buyer to cancel early, otherwise allow seller owner to cancel
  $canByBuyer = ($token_user_id > 0 && (int)$row['id_users'] === $token_user_id && in_array($current, ['konfirmasi_ketersediaan','konfirmasi_pembayaran'], true));
  $canBySeller = ($geraiOwnerId === $token_user_id);
  if (!($canByBuyer || $canBySeller)) {
    echo json_encode(['success'=>false,'message'=>'Forbidden: cannot cancel']);exit;
  }
} else {
  // default: allow if buyer or gerai owner
  $isOwner = ($geraiOwnerId === $token_user_id);
  if (!($token_user_id > 0 && (int)$row['id_users'] === $token_user_id) && !$isOwner) {
    echo json_encode(['success'=>false,'message'=>'Access denied']);exit;
  }
}

// Validasi transition
// Transition dasar
$validNext = [
  'konfirmasi_ketersediaan'=>['konfirmasi_pembayaran','disiapkan','dibatalkan'], // tambahkan disiapkan (cash skip pembayaran)
  'konfirmasi_pembayaran'=>['disiapkan','dibatalkan'],
  'disiapkan'=>['diantar','pickup','dibatalkan'],
  'diantar'=>['selesai','dibatalkan'],
  'pickup'=>['selesai','dibatalkan'],
  'selesai'=>[],
  'dibatalkan'=>[]
];

// Jika metode cash maka status konfirmasi_pembayaran seharusnya dilewati, cegah kembali ke konfirmasi_pembayaran
if ($metode==='cash') {
  // Tidak izinkan transisi ke konfirmasi_pembayaran dari manapun
  foreach($validNext as $k=>$arr){
    $validNext[$k] = array_values(array_filter($arr, fn($s)=>$s!=='konfirmasi_pembayaran'));
  }
}

if(!in_array($newStatus, $validNext[$current] ?? [], true)){
  echo json_encode(['success'=>false,'message'=>'Transition tidak diizinkan dari '.$current.' ke '.$newStatus]);exit;
}

// Aturan khusus: kalau jenis_pengantaran=pickup, tidak boleh ke diantar
if($jenis==='pickup' && $newStatus==='diantar'){
  echo json_encode(['success'=>false,'message'=>'Transaksi pickup tidak bisa diantar']);exit;
}

// Wajib alasan jika dibatalkan
if($newStatus==='dibatalkan' && $alasan===''){
  $alasan='Dibatalkan';
}

$stmt=$mysqli->prepare("UPDATE transaksi SET STATUS=?, catatan_pembatalan=IF(?='',catatan_pembatalan,?) WHERE id_transaksi=?");
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Prepare fail: '.$mysqli->error]);exit;}
$stmt->bind_param('sssi',$newStatus,$alasan,$alasan,$row['id_transaksi']);
if(!$stmt->execute()){echo json_encode(['success'=>false,'message'=>'Update fail: '.$stmt->error]);exit;}
$stmt->close();

echo json_encode(['success'=>true,'data'=>[
  'id_transaksi'=>$row['id_transaksi'],
  'from'=>$current,
  'to'=>$newStatus,
  'alasan'=>$alasan
]]);
