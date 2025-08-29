<?php
// Endpoint untuk seller mengkonfirmasi ketersediaan otomatis ataupun menolak jika stok addon/menu kurang
// Body: {"booking_id"|"id_transaksi", "available":true/false, "alasan"?}
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
$available = isset($req['available']) ? (bool)$req['available'] : null;
$alasan = trim($req['alasan']??'');
if($available===null){echo json_encode(['success'=>false,'message'=>'available boolean wajib']);exit;}

$where='';
if($idTransaksi>0){$where='id_transaksi='.$idTransaksi;}elseif($bookingId!==''){$where="booking_id='".$mysqli->real_escape_string($bookingId)."'";}else{echo json_encode(['success'=>false,'message'=>'id_transaksi atau booking_id wajib']);exit;}

$res=$mysqli->query("SELECT id_transaksi, STATUS FROM transaksi WHERE $where LIMIT 1");
if(!$res||$res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$row=$res->fetch_assoc();
$res->free();
if($row['STATUS']!=='konfirmasi_ketersediaan'){echo json_encode(['success'=>false,'message'=>'Status sekarang bukan konfirmasi_ketersediaan']);exit;}

$newStatus = $available ? 'konfirmasi_pembayaran' : 'dibatalkan';
if(!$available && $alasan===''){ $alasan='Stok tidak tersedia'; }

$stmt=$mysqli->prepare("UPDATE transaksi SET STATUS=?, catatan_pembatalan=IF(?='',catatan_pembatalan,?) WHERE id_transaksi=?");
if(!$stmt){echo json_encode(['success'=>false,'message'=>'Prepare fail: '.$mysqli->error]);exit;}
$stmt->bind_param('sssi',$newStatus,$alasan,$alasan,$row['id_transaksi']);
if(!$stmt->execute()){echo json_encode(['success'=>false,'message'=>'Update fail: '.$stmt->error]);exit;}
$stmt->close();

echo json_encode(['success'=>true,'data'=>['id_transaksi'=>$row['id_transaksi'],'from'=>'konfirmasi_ketersediaan','to'=>$newStatus,'alasan'=>$alasan]]);
