<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

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
$idT = intval($tx['id_transaksi']);

// Ambil alamat utama user jika pengantaran
$locationBuyer = '';
if($tx['jenis_pengantaran']==='pengantaran'){
  if(!empty($tx['id_alamat'])){
    $resA = $mysqli->query('SELECT nama_gedung, detail_pengantaran FROM alamat_pengantaran WHERE id_alamat='.(int)$tx['id_alamat'].' LIMIT 1');
    if($resA && $resA->num_rows>0){ $rowA=$resA->fetch_assoc(); $locationBuyer = $rowA['detail_pengantaran']; $buildingNameBuyer = $rowA['nama_gedung']; $resA->free(); }
  }
  if($locationBuyer===''){
    $resA = $mysqli->query('SELECT nama_gedung, detail_pengantaran FROM alamat_pengantaran WHERE id_users='.(int)$tx['id_users'].' AND alamat_utama=1 LIMIT 1');
    if($resA && $resA->num_rows>0){ $rowA=$resA->fetch_assoc(); $locationBuyer = $rowA['detail_pengantaran']; $buildingNameBuyer = $rowA['nama_gedung']; $resA->free(); }
  }
}

// Items
$items = [];
$resI = $mysqli->query("SELECT ti.id_transaksi_item,ti.id_menu,m.nama_menu,ti.jumlah,ti.harga_satuan,ti.subtotal,ti.note FROM transaksi_item ti JOIN menu m ON m.id_menu=ti.id_menu WHERE ti.id_transaksi=$idT");
if($resI){
  while($rowI=$resI->fetch_assoc()){
    $tid = (int)$rowI['id_transaksi_item'];
    $addons=[]; $addonsDetail=[];
    $resAd=$mysqli->query('SELECT tia.id_addon,a.nama_addon FROM transaksi_item_addon tia JOIN addon a ON a.id_addon=tia.id_addon WHERE tia.id_transaksi_item='.$tid);
    if($resAd){
      while($rA=$resAd->fetch_assoc()){
        $idA = (int)$rA['id_addon'];
        $addons[] = $idA; // list id (legacy)
        $addonsDetail[] = ['id_addon'=>$idA,'nama_addon'=>$rA['nama_addon']];
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
      'addons'=>$addons, // list id
      'addons_detail'=>$addonsDetail, // list objek {id_addon,nama_addon}
    ];
  }
  $resI->free();
}

echo json_encode(['success'=>true,'data'=>[
  'id_transaksi'=>$idT,
  'booking_id'=>$tx['booking_id'],
  'id_gerai'=>(int)$tx['id_gerai'],
  'status'=>$tx['STATUS'],
  'jenis_pengantaran'=>$tx['jenis_pengantaran'],
  'id_alamat'=>isset($tx['id_alamat']) ? (int)$tx['id_alamat'] : null,
  'restaurantName'=>$tx['nama_gerai'],
  'metode_pembayaran'=>$tx['metode_pembayaran'],
  'bukti_pembayaran'=>$tx['bukti_pembayaran'],
  'qris_path'=>$tx['qris_path'],
  'locationSeller'=>$tx['detail_alamat'],
  'listing_path'=>isset($tx['listing_path'])?$tx['listing_path']:null,
  'locationBuyer'=>$locationBuyer,
  'buildingNameBuyer'=>isset($buildingNameBuyer)?$buildingNameBuyer:'',
  'items'=>$items,
  'catatan_pembatalan'=>$tx['catatan_pembatalan'],
]]);
