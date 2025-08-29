<?php
// Ambil status transaksi (by id_transaksi atau booking_id)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Asia/Jakarta');
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error: '.$mysqli->connect_error]);exit;}
$mysqli->set_charset('utf8mb4');

$idTransaksi = isset($_GET['id_transaksi']) ? intval($_GET['id_transaksi']) : 0;
$bookingId = isset($_GET['booking_id']) ? trim($_GET['booking_id']) : '';
if($idTransaksi<=0 && $bookingId===''){
  echo json_encode(['success'=>false,'message'=>'Parameter id_transaksi atau booking_id wajib']);exit;
}
$where = $idTransaksi>0 ? 'id_transaksi='.$idTransaksi : "booking_id='".$mysqli->real_escape_string($bookingId)."'";
$res=$mysqli->query("SELECT id_transaksi, booking_id, STATUS, jenis_pengantaran, catatan_pembatalan FROM transaksi WHERE $where LIMIT 1");
if(!$res || $res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Transaksi tidak ditemukan']);exit;}
$row=$res->fetch_assoc();
$res->free();

echo json_encode(['success'=>true,'data'=>$row]);
