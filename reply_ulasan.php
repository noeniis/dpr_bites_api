<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}

require_once 'db.php';

$raw = file_get_contents('php://input');
if(!$raw){
  echo json_encode(['success'=>false,'message'=>'Empty body']);
  exit;
}

$req = json_decode($raw, true);
if(!is_array($req)){
  echo json_encode(['success'=>false,'message'=>'Invalid JSON','debug_raw'=>$raw]);
  exit;
}

// debug log
error_log("RAW BODY: ".$raw);
error_log("PARSED: ".print_r($req,true));

$id_ulasan = intval($req['id_ulasan'] ?? 0);
$balasan   = trim($req['balasan'] ?? '');

if($id_ulasan <= 0 || $balasan === ''){
  echo json_encode(['success'=>false,'message'=>'id_ulasan dan balasan wajib diisi','debug'=>$req]);
  exit;
}

$stmt = $conn->prepare('UPDATE ulasan SET balasan=? WHERE id_ulasan=?');
if(!$stmt){
  echo json_encode(['success'=>false,'message'=>'Prepare failed: '.$conn->error]);
  exit;
}
$stmt->bind_param('si', $balasan, $id_ulasan);
if($stmt->execute()){
  echo json_encode(['success'=>true]);
}else{
  echo json_encode(['success'=>false,'message'=>$stmt->error]);
}
$stmt->close();
$conn->close();
