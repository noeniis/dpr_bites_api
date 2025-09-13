<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
$requireAuth = true;
if ($requireAuth) {
  require_once __DIR__ . '/protected.php';
  $token_user_id = isset($id_users) ? (int)$id_users : 0;
}

date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

$idTransaksi = isset($_GET['id_transaksi']) ? (int)$_GET['id_transaksi'] : 0;
$idUsers = $requireAuth ? $token_user_id : (isset($_GET['id_users']) ? (int)$_GET['id_users'] : 0);
if($idTransaksi<=0 || $idUsers<=0){
  echo json_encode(['success'=>false,'message'=>'id_transaksi & id_users wajib']);
  exit;
}

$sql = "SELECT id_ulasan,id_transaksi,id_users,rating,komentar,is_anonymous,created_at FROM ulasan WHERE id_transaksi=$idTransaksi AND id_users=$idUsers LIMIT 1";
$res = $mysqli->query($sql);
if(!$res){
  echo json_encode(['success'=>false,'message'=>'Query error']);
  exit;
}
if($res->num_rows===0){
  echo json_encode(['success'=>true,'data'=>null]);
  $res->free();
  exit;
}
$row=$res->fetch_assoc();
$res->free();

echo json_encode(['success'=>true,'data'=>[
  'id_ulasan'=>(int)$row['id_ulasan'],
  'id_transaksi'=>(int)$row['id_transaksi'],
  'id_users'=>(int)$row['id_users'],
  'rating'=>(int)$row['rating'],
  'komentar'=>$row['komentar'],
  'created_at'=>$row['created_at'],
  'anonymous'=> isset($row['is_anonymous']) ? (int)$row['is_anonymous'] : 0,
]]);
?>
