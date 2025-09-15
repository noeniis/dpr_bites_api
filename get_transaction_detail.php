<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
// Require JWT and determine token user id
require_once __DIR__ . '/protected.php';
// Prefer explicit token helper to read user id from Authorization header
if (function_exists('getTokenUserId')) {
  $token_user_id = getTokenUserId();
} elseif (function_exists('requireAuth')) {
  // requireAuth() exits with 401 on failure and returns user id on success
  $token_user_id = requireAuth();
} else {
  // Last resort: use $id_users if protected.php set it earlier
  $token_user_id = isset($id_users) ? (int)$id_users : 0;
}

date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

$bookingId = isset($_GET['booking_id']) ? trim($_GET['booking_id']) : '';
$idTransaksi = isset($_GET['id_transaksi']) ? intval($_GET['id_transaksi']) : 0;
if($bookingId==='' && $idTransaksi<=0){
  echo json_encode(['success'=>false,'message'=>'booking_id atau id_transaksi wajib']);exit;
}

$where = $bookingId!=='' ? "t.booking_id='".$mysqli->real_escape_string($bookingId)."'" : 't.id_transaksi='.$idTransaksi;
$sql = "SELECT t.id_transaksi,t.booking_id,t.STATUS,t.jenis_pengantaran,t.id_users,t.id_gerai,t.id_alamat,t.metode_pembayaran,t.bukti_pembayaran,t.catatan_pembatalan,g.nama_gerai,g.detail_alamat,g.qris_path, gp.listing_path FROM transaksi t JOIN gerai g ON g.id_gerai=t.id_gerai LEFT JOIN gerai_profil gp ON gp.id_gerai = g.id_gerai WHERE $where LIMIT 1";
$res = $mysqli->query($sql);
if(!$res || $res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$tx = $res->fetch_assoc();
$res->free();
// Ownership enforcement: allow access if token belongs to the buyer (tx.id_users)
// OR if token belongs to the owner of the gerai associated with the transaction.
if ($token_user_id <= 0) {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

$allowed = false;
if ((int)$tx['id_users'] === $token_user_id) {
  $allowed = true; // buyer
} else {
  // check gerai owner
  $idGerai = isset($tx['id_gerai']) ? (int)$tx['id_gerai'] : 0;
  $geraiOwnerId = 0;
  if ($idGerai > 0) {
    $resG = $mysqli->query("SELECT id_users FROM gerai WHERE id_gerai = " . $idGerai . " LIMIT 1");
    if ($resG && $resG->num_rows > 0) {
      $g = $resG->fetch_assoc();
      $resG->free();
      $geraiOwnerId = (int)$g['id_users'];
      if ($geraiOwnerId === $token_user_id) {
        $allowed = true; // seller (gerai owner)
      }
    }
  }
}

if (!$allowed) {
  http_response_code(403);
  // Include minimal debug details to help local diagnosis (safe for dev environment)
  echo json_encode([
    'success' => false,
    'message' => 'Access denied',
    'debug' => [
      'token_user_id' => $token_user_id,
      'transaction_user_id' => isset($tx['id_users']) ? (int)$tx['id_users'] : 0,
      'gerai_owner_id' => isset($geraiOwnerId) ? $geraiOwnerId : 0,
      'id_gerai' => isset($tx['id_gerai']) ? (int)$tx['id_gerai'] : 0,
    ]
  ]);
  exit;
}

$idT = intval($tx['id_transaksi']);
$idAlamat = isset($tx['id_alamat']) ? intval($tx['id_alamat']) : 0;

// Ambil detail alamat, lat, long jika ada id_alamat
$alamatDetail = '';
$alamatLat = null;
$alamatLng = null;
if ($idAlamat > 0) {
  $resAlamat = $mysqli->query("SELECT detail_pengantaran, latitude, longitude FROM alamat_pengantaran WHERE id_alamat = $idAlamat LIMIT 1");
  if ($resAlamat && $resAlamat->num_rows > 0) {
    $rowAlamat = $resAlamat->fetch_assoc();
    $alamatDetail = $rowAlamat['detail_pengantaran'];
    $alamatLat = $rowAlamat['latitude'];
    $alamatLng = $rowAlamat['longitude'];
    $resAlamat->free();
  }
}

// Ambil alamat utama user jika pengantaran
$locationBuyer = '';
if($tx['jenis_pengantaran']==='pengantaran'){
  $resA = $mysqli->query('SELECT nama_gedung, detail_pengantaran FROM alamat_pengantaran WHERE id_users='.(int)$tx['id_users'].' AND alamat_utama=1 LIMIT 1');
  if($resA && $resA->num_rows>0){ $rowA=$resA->fetch_assoc(); $locationBuyer = $rowA['detail_pengantaran']; $buildingNameBuyer = $rowA['nama_gedung']; $resA->free(); }
}

// Items
$items = [];
$resI = $mysqli->query("SELECT ti.id_transaksi_item,ti.id_menu,m.nama_menu,ti.jumlah,ti.harga_satuan,ti.subtotal,ti.note FROM transaksi_item ti JOIN menu m ON m.id_menu=ti.id_menu WHERE ti.id_transaksi=$idT");
if($resI){
  while($rowI=$resI->fetch_assoc()){
    $tid = (int)$rowI['id_transaksi_item'];
$addons = [];
$resAd = $mysqli->query('SELECT a.id_addon, a.nama_addon, a.harga FROM transaksi_item_addon tia JOIN addon a ON a.id_addon = tia.id_addon WHERE tia.id_transaksi_item='.$tid);
if($resAd){
  while($rA = $resAd->fetch_assoc()){
    $addons[] = [
      'id_addon' => (int)$rA['id_addon'],
      'nama_addon' => $rA['nama_addon'],
      'harga' => (int)$rA['harga'],
    ];
  }
  $resAd->free();
}
    $items[] = [
      'id_menu'=>(int)$rowI['id_menu'],
      'name'=>$rowI['nama_menu'],
      'qty'=>(int)$rowI['jumlah'],
      'harga_satuan'=>(int)$rowI['harga_satuan'],
      'subtotal'=>(int)$rowI['subtotal'],
      'note'=>$rowI['note'],
      'addons'=>$addons,
    ];
  }
  $resI->free();
}

echo json_encode(['success'=>true,'data'=>[
  'id_transaksi'=>$idT,
  'booking_id'=>$tx['booking_id'],
  'status'=>$tx['STATUS'],
  'jenis_pengantaran'=>$tx['jenis_pengantaran'],
  'restaurantName'=>$tx['nama_gerai'],
  'metode_pembayaran'=>$tx['metode_pembayaran'],
  'bukti_pembayaran'=>$tx['bukti_pembayaran'],
  'qris_path'=>$tx['qris_path'],
  'locationSeller'=>$tx['detail_alamat'],
  'listing_path'=>isset($tx['listing_path'])?$tx['listing_path']:null,
  'locationBuyer'=>$locationBuyer,
  'buildingNameBuyer'=>isset($buildingNameBuyer)?$buildingNameBuyer:'',
  'alamat_pengantaran'=>[
    'id_alamat'=>$idAlamat,
    'detail'=>$alamatDetail,
    'latitude'=>$alamatLat,
    'longitude'=>$alamatLng,
  ],
  'items'=>$items,
  'catatan_pembatalan'=>$tx['catatan_pembatalan'],
]]);
