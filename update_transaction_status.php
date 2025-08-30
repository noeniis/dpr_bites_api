<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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

$res=$mysqli->query("SELECT id_transaksi, STATUS, jenis_pengantaran, metode_pembayaran FROM transaksi WHERE $where LIMIT 1");
if(!$res || $res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$row=$res->fetch_assoc();
$current=$row['STATUS'];
$jenis=$row['jenis_pengantaran'];
$metode=$row['metode_pembayaran'];
$res->free();

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
