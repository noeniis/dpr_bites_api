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
$userId = isset($id_users) ? (int)$id_users : 0;
if($userId<=0){ echo json_encode(['success'=>false,'message'=>'user_id wajib']); exit; }

// Ambil semua transaksi user dengan join gerai
$sql = "SELECT t.id_transaksi,t.booking_id,t.status,t.jenis_pengantaran,t.metode_pembayaran,t.total_harga,t.created_at,t.id_gerai,t.id_alamat,
         g.nama_gerai,g.detail_alamat AS seller_alamat,
         a.nama_gedung AS buyer_nama_gedung,a.detail_pengantaran AS buyer_detail
  FROM transaksi t
  JOIN gerai g ON g.id_gerai=t.id_gerai
  LEFT JOIN alamat_pengantaran a ON a.id_alamat=t.id_alamat
  WHERE t.id_users=$userId ORDER BY t.created_at DESC LIMIT 200"; // batas 200 terbaru
$res = $mysqli->query($sql);
$list = [];
if($res){
  while($row=$res->fetch_assoc()){
    $status = strtolower($row['status']);
    // Map status ke kategori filter (berlangsung/selesai/dibatalkan) akan dilakukan di client; tetap kirim status asli
    $created = $row['created_at'];
    $dateDisplay = '';
    if(!empty($created)){
      // Normalisasi format jika perlu, lalu format ke "dd MMM yyyy, HH:mm WIB"
      $ts = strtotime($created);
      if($ts!==false){ $dateDisplay = date('d M Y, H:i', $ts).' WIB'; }
    }
    $buyerBuilding = $row['buyer_nama_gedung'] ?? '';
    $buyerDetail   = $row['buyer_detail'] ?? '';
    $locBuyer = $buyerBuilding!=='' ? ($buyerBuilding.($buyerDetail!==''? ' - '.$buyerDetail:'')) : $row['seller_alamat'];
    $list[] = [
      'id_transaksi'=>(int)$row['id_transaksi'],
      'booking_id'=>$row['booking_id'],
      'restaurantName'=>$row['nama_gerai'],
      'price'=>(int)$row['total_harga'],
      'date_raw'=>$row['created_at'],
      'dateDisplay'=>$dateDisplay,
      'status'=>$status, // kirim raw lowercase
      'icon'=>'lib/assets/images/spatulaknife.png',
      'delivery'=>$row['jenis_pengantaran']!=='pickup',
      'locationSeller'=>$row['nama_gerai'],
      'locationBuyer'=>$locBuyer,
      'id_alamat'=> isset($row['id_alamat']) ? (int)$row['id_alamat'] : null,
      'buyer_building'=>$buyerBuilding,
      'buyer_detail'=>$buyerDetail,
    ];
  }
  $res->free();
}

echo json_encode(['success'=>true,'data'=>$list]);
?>
