<?php
// Endpoint: Seller upload bukti pembayaran untuk pesanan cash saat menyerahkan pesanan.
// Mengubah status ke 'selesai' jika saat ini 'diantar'.
// Form-data: id_transaksi, bukti (file image)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

// Validate JWT
require_once __DIR__ . '/protected.php';
if (!isset($id_users) || $id_users <= 0) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

$idTransaksi=intval($_POST['id_transaksi']??0);
if($idTransaksi<=0){echo json_encode(['success'=>false,'message'=>'id_transaksi wajib']);exit;}
if(!isset($_FILES['bukti'])||$_FILES['bukti']['error']!==UPLOAD_ERR_OK){echo json_encode(['success'=>false,'message'=>'File bukti wajib dan harus valid']);exit;}

$where = 'id_transaksi='.$idTransaksi;
$res=$mysqli->query("SELECT id_transaksi, STATUS, metode_pembayaran FROM transaksi WHERE $where LIMIT 1");
if(!$res||$res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$row=$res->fetch_assoc();$res->free();
if($row['metode_pembayaran']!=='cash'){echo json_encode(['success'=>false,'message'=>'Metode bukan cash']);exit;}
if($row['STATUS']!=='diantar'){
  echo json_encode(['success'=>false,'message'=>'Status tidak memungkinkan upload bukti (harus diantar)']);exit;
}

// Upload ke Cloudinary
$cloudName='dip8i3f6x';
$uploadPreset='dpr_bites';
$tmpPath=$_FILES['bukti']['tmp_name'];
$type=mime_content_type($tmpPath);
$dataUri='data:'.$type.';base64,'.base64_encode(file_get_contents($tmpPath));
$ch=curl_init();
curl_setopt($ch,CURLOPT_URL,"https://api.cloudinary.com/v1_1/$cloudName/image/upload");
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_POSTFIELDS,[
  'file'=>$dataUri,
  'upload_preset'=>$uploadPreset,
  'folder'=>'bukti_pembayaran_cash'
]);
$resp=curl_exec($ch);$err=curl_error($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if($err||$code!==200){echo json_encode(['success'=>false,'message'=>'Upload gagal','error'=>$err,'http'=>$code]);exit;}
$json=json_decode($resp,true);
if(!is_array($json)||empty($json['secure_url'])){echo json_encode(['success'=>false,'message'=>'Respon upload tidak valid']);exit;}
$url=$json['secure_url'];

// Update transaksi
$stmt=$mysqli->prepare("UPDATE transaksi SET bukti_pembayaran=?, STATUS='selesai' WHERE id_transaksi=?");
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Prepare fail: '.$mysqli->error]);exit;}
$stmt->bind_param('si',$url,$row['id_transaksi']);
if(!$stmt->execute()){echo json_encode(['success'=>false,'message'=>'Update fail: '.$stmt->error]);exit;}
$stmt->close();

echo json_encode(['success'=>true,'data'=>[
  'id_transaksi'=>$row['id_transaksi'],
  'status'=>'selesai',
  'bukti_pembayaran'=>$url
]]);
