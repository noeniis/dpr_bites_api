<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/protected.php';
if (!isset($id_users) || !is_int($id_users) || $id_users <= 0) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

$raw=file_get_contents('php://input');
$payload=json_decode($raw,true);
if(!is_array($payload)){
  echo json_encode(['success'=>false,'message'=>'Payload tidak valid']);
  exit;
}
$id_transaksi = isset($payload['id_transaksi'])?(int)$payload['id_transaksi']:0;
$id_users = (int)$id_users; // from JWT
$rating = isset($payload['rating'])?(int)$payload['rating']:0;
$komentar = isset($payload['komentar'])?trim($payload['komentar']):'';
$anonymous = isset($payload['anonymous']) ? (int) (!!$payload['anonymous']) : 0; // 1 anonim, 0 tampilkan nama

if($id_transaksi<=0||$id_users<=0){echo json_encode(['success'=>false,'message'=>'id_transaksi wajib']);exit;}
if($rating<1||$rating>5){echo json_encode(['success'=>false,'message'=>'Rating 1..5']);exit;}

// Pastikan transaksi ada dan milik user tsb & status selesai
$sqlC = "SELECT id_gerai,status,id_users FROM transaksi WHERE id_transaksi=$id_transaksi LIMIT 1";
$resC=$mysqli->query($sqlC);
if(!$resC||$resC->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$tx=$resC->fetch_assoc();
$resC->free();
// Ownership check
if((int)$tx['id_users'] !== $id_users){
  http_response_code(403);
  echo json_encode(['success'=>false,'message'=>'Forbidden: transaksi bukan milik Anda']);
  exit;
}
if(strtolower($tx['status'])!=='selesai'){
  echo json_encode(['success'=>false,'message'=>'Transaksi belum selesai']);
  exit;
}
$id_gerai=(int)$tx['id_gerai'];

// Cek apakah sudah pernah ulasan (opsional: satu ulasan per transaksi per user)
$cek=$mysqli->query("SELECT id_ulasan FROM ulasan WHERE id_transaksi=$id_transaksi AND id_users=$id_users LIMIT 1");
if($cek && $cek->num_rows>0){
  echo json_encode(['success'=>false,'message'=>'Ulasan sudah ada']);
  exit;
}
if($cek) $cek->free();

$stmt=$mysqli->prepare('INSERT INTO ulasan (id_transaksi,id_users,rating,komentar,is_anonymous,created_at) VALUES (?,?,?,?,?,NOW())');
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Prep error: '.$mysqli->error]);exit;}
$stmt->bind_param('iiisi',$id_transaksi,$id_users,$rating,$komentar,$anonymous);
if(!$stmt->execute()){
  echo json_encode(['success'=>false,'message'=>'Insert gagal: '.$stmt->error]);
  $stmt->close();
  exit;
}
$id_ulasan=$stmt->insert_id;
$stmt->close();

echo json_encode(['success'=>true,'message'=>'Ulasan tersimpan','data'=>[
  'id_ulasan'=>$id_ulasan,
  'id_transaksi'=>$id_transaksi,
  'id_users'=>$id_users,
  'rating'=>$rating,
  'komentar'=>$komentar,
  'anonymous'=>$anonymous,
]]);
?>
