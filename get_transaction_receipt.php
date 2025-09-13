<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

// Require JWT and get user id from token
require_once __DIR__ . '/protected.php';
$token_user_id = isset($id_users) ? (int)$id_users : 0;

$bookingId = isset($_GET['booking_id']) ? trim($_GET['booking_id']) : '';
$idTransaksi = isset($_GET['id_transaksi']) ? (int)$_GET['id_transaksi'] : 0;
if($bookingId==='' && $idTransaksi<=0){ echo json_encode(['success'=>false,'message'=>'booking_id atau id_transaksi wajib']); exit; }

$where = $bookingId!=='' ? "t.booking_id='".$mysqli->real_escape_string($bookingId)."'" : 't.id_transaksi='.(int)$idTransaksi;
$sql = "SELECT t.id_transaksi,t.booking_id,t.status,t.jenis_pengantaran,t.metode_pembayaran,t.total_harga,t.biaya_pengantaran,t.created_at,t.catatan_pembatalan,
    t.id_alamat, t.id_users, g.nama_gerai,g.detail_alamat AS seller_alamat, gp.listing_path,
    a.nama_gedung AS buyer_nama_gedung,a.detail_pengantaran AS buyer_detail
  FROM transaksi t
  JOIN gerai g ON g.id_gerai=t.id_gerai
  LEFT JOIN gerai_profil gp ON gp.id_gerai = g.id_gerai
  LEFT JOIN alamat_pengantaran a ON a.id_alamat=t.id_alamat
  WHERE $where LIMIT 1";
$res = $mysqli->query($sql);
if(!$res || $res->num_rows===0){ echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']); exit; }
$tx=$res->fetch_assoc();
$res->free();
$idT=(int)$tx['id_transaksi'];

// Enforce ownership: the transaksi must belong to token user
if ($token_user_id <= 0 || (int)$tx['id_users'] !== $token_user_id) {
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Access denied']);
  exit;
}

// Items + addons
$orderSummary=[]; $subtotal=0;
$resI=$mysqli->query("SELECT ti.id_transaksi_item,ti.id_menu,ti.jumlah,ti.harga_satuan,ti.subtotal,ti.note,m.nama_menu FROM transaksi_item ti JOIN menu m ON m.id_menu=ti.id_menu WHERE ti.id_transaksi=$idT");
if($resI){
  while($rowI=$resI->fetch_assoc()){
    $subtotal += (int)$rowI['subtotal'];
    $tidItem = (int)$rowI['id_transaksi_item'];
    $addonsDetail=[]; $addonNames=[];
    $resAd = $mysqli->query('SELECT a.nama_addon FROM transaksi_item_addon tia JOIN addon a ON a.id_addon=tia.id_addon WHERE tia.id_transaksi_item='.$tidItem);
    if($resAd){
      while($rA=$resAd->fetch_assoc()){ $nm=$rA['nama_addon']; if($nm!==''){ $addonNames[]=$nm; $addonsDetail[]=['nama_addon'=>$nm]; } }
      $resAd->free();
    }
    $orderSummary[] = [
      'qty'=>(int)$rowI['jumlah'],
      'menu'=>$rowI['nama_menu'],
      'price'=>(int)$rowI['subtotal'],
      'note'=>$rowI['note'],
      'addons_detail'=>$addonsDetail,
    ];
  }
  $resI->free();
}

// Format date display
$dateDisplay='';
if(!empty($tx['created_at'])){
  $ts=strtotime($tx['created_at']);
  if($ts!==false){ $dateDisplay = date('d M Y, H:i',$ts).' WIB'; }
}

// Compose buyer location string
$buyerBuilding = $tx['buyer_nama_gedung'] ?? '';
$buyerDetail   = $tx['buyer_detail'] ?? '';
$locationBuyer = $buyerBuilding!=='' ? ($buyerBuilding.($buyerDetail!==''? ' - '.$buyerDetail:'')) : $tx['seller_alamat'];
// Normalisasi metode pembayaran
$metode = '';
if(isset($tx['metode_pembayaran'])){
  $metode = strtolower(trim($tx['metode_pembayaran']));
  if($metode==='tunai') $metode='cash';
}
echo json_encode(['success'=>true,'data'=>[
  'id_transaksi'=>$idT,
  'booking_id'=>$tx['booking_id'],
  'restaurantName'=>$tx['nama_gerai'],
  'status'=>strtolower($tx['status']),
  // metode cash/qris normalized (empty string if not set)
  'metode_pembayaran'=>$metode,
  'jenis_pengantaran'=>$tx['jenis_pengantaran'],
  'delivery'=>($tx['jenis_pengantaran']!=='pickup'),
  'deliveryFee'=>(int)$tx['biaya_pengantaran'],
  'dateDisplay'=>$dateDisplay,
  'created_at'=>$tx['created_at'],
  'seller_alamat'=>$tx['seller_alamat'],
  'locationSeller'=>$tx['seller_alamat'],
  'locationBuyer'=>$locationBuyer,
  'listing_path'=>isset($tx['listing_path'])?$tx['listing_path']:null,
  'id_alamat'=> isset($tx['id_alamat']) ? (int)$tx['id_alamat'] : null,
  'buyer_building'=>$buyerBuilding,
  'buyer_detail'=>$buyerDetail,
  'orderSummary'=>$orderSummary,
  'subtotal'=>$subtotal,
  'total'=>(int)$tx['total_harga'],
  'catatan_pembatalan'=>$tx['catatan_pembatalan'],
]]);
?>
