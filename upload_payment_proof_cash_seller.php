<?php
// Endpoint: Seller upload bukti pembayaran untuk pesanan cash saat menyerahkan pesanan.
// Mengubah status ke 'selesai' jika saat ini 'diantar' atau 'pickup'.
// Body JSON: {"booking_id"|"id_transaksi", "bukti_base64"}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

$raw=file_get_contents('php://input');
if(!$raw){echo json_encode(['success'=>false,'message'=>'Empty body']);exit;}
$req=json_decode($raw,true);
if(!is_array($req)){echo json_encode(['success'=>false,'message'=>'Invalid JSON']);exit;}

$idTransaksi=intval($req['id_transaksi']??0);
$bookingId=trim($req['booking_id']??'');
$buktiBase64=$req['bukti_base64']??'';
if($idTransaksi<=0 && $bookingId===''){echo json_encode(['success'=>false,'message'=>'booking_id atau id_transaksi wajib']);exit;}
if(!$buktiBase64){echo json_encode(['success'=>false,'message'=>'bukti_base64 wajib']);exit;}

$where = $idTransaksi>0 ? 'id_transaksi='.$idTransaksi : "booking_id='".$mysqli->real_escape_string($bookingId)."'";
$res=$mysqli->query("SELECT id_transaksi, STATUS, metode_pembayaran FROM transaksi WHERE $where LIMIT 1");
if(!$res||$res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$row=$res->fetch_assoc();$res->free();
if($row['metode_pembayaran']!=='cash'){echo json_encode(['success'=>false,'message'=>'Metode bukan cash']);exit;}
if(!in_array($row['STATUS'],['diantar','pickup','selesai'],true)){
  echo json_encode(['success'=>false,'message'=>'Status tidak memungkinkan upload bukti (harus diantar/pickup/selesai)']);exit;
}

// Normalisasi base64
if(preg_match('/^data:(image\/(png|jpe?g));base64,(.+)$/i',$buktiBase64,$m)){
  $buktiBase64=$m[3];
}
$binary=base64_decode(preg_replace('/\s+/','',$buktiBase64),true);
if($binary===false){echo json_encode(['success'=>false,'message'=>'Base64 tidak valid']);exit;}

$cloudName='dip8i3f6x';
$uploadPreset='dpr_bites';
$dataUri='data:image/png;base64,'.base64_encode($binary);
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

$nextStatus = $row['STATUS']==='selesai' ? 'selesai' : 'selesai'; // ensure selesai
$stmt=$mysqli->prepare("UPDATE transaksi SET bukti_pembayaran=?, STATUS=? WHERE id_transaksi=?");
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Prepare fail: '.$mysqli->error]);exit;}
$stmt->bind_param('ssi',$url,$nextStatus,$row['id_transaksi']);
if(!$stmt->execute()){echo json_encode(['success'=>false,'message'=>'Update fail: '.$stmt->error]);exit;}
$stmt->close();

echo json_encode(['success'=>true,'data'=>[
  'id_transaksi'=>$row['id_transaksi'],
  'status'=>$nextStatus,
  'bukti_pembayaran'=>$url
]]);
