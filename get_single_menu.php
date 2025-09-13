<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
require_once __DIR__ . '/protected.php';
$host='localhost';$user='root';$pass='';$db='dpr_bites';$port=3306;
$mysqli=@new mysqli($host,$user,$pass,$db,$port);
if($mysqli->connect_errno){echo json_encode(['success'=>false,'message'=>'DB error']);exit;}
$mysqli->set_charset('utf8mb4');
$id_menu = isset($_GET['id_menu']) ? (int)$_GET['id_menu'] : 0;
if($id_menu<=0){echo json_encode(['success'=>false,'message'=>'id_menu required']);exit;}
$sql = "SELECT m.id_menu,m.id_gerai,m.nama_menu,g.nama_gerai FROM menu m JOIN gerai g ON g.id_gerai=m.id_gerai WHERE m.id_menu=$id_menu LIMIT 1";
$res=$mysqli->query($sql);
if(!$res || $res->num_rows===0){echo json_encode(['success'=>false,'message'=>'Not found']);exit;}
$row=$res->fetch_assoc();
$res->free();
// Addons for this menu
$addons=[];
$ra=$mysqli->query('SELECT a.id_addon,a.nama_addon FROM menu_addon ma JOIN addon a ON a.id_addon=ma.id_addon WHERE ma.id_menu='.$id_menu);
if($ra){while($r=$ra->fetch_assoc()){ $addons[]=$r; } $ra->free();}
// Return
echo json_encode(['success'=>true,'data'=>[
  'id_menu'=>(int)$row['id_menu'],
  'id_gerai'=>(int)$row['id_gerai'],
  'nama_menu'=>$row['nama_menu'],
  'nama_gerai'=>$row['nama_gerai'],
  'addons'=>$addons,
]]);
