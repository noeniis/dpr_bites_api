<?php
// get_restaurant_ratings.php
// Param: id (id_gerai)
// Return JSON:
// {
//   success: true/false,
//   data: {
//     rating: float,
//     ratingCount: int,
//     breakdown: [ {star:5,count:..},...,{star:1,count:..} ],
//     reviews: [ {id_ulasan:1, name:"Pengguna", pesanan:"Nasi Goreng", rating:5, komentar:"...", balasan:"..."}, ... ]
//   }
// }

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/protected.php';
if (!isset($id_users) || !is_int($id_users) || $id_users <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

$mysqli = new mysqli('localhost', 'root', '', 'dpr_bites');
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Ambil nama gerai
$geraiName = '';
if ($stmtG = $mysqli->prepare('SELECT nama_gerai FROM gerai WHERE id_gerai = ? LIMIT 1')) {
    $stmtG->bind_param('i', $id);
    if ($stmtG->execute()) {
        $resG = $stmtG->get_result();
        if ($rowG = $resG->fetch_assoc()) {
            $geraiName = $rowG['nama_gerai'] ?? '';
        }
    }
    $stmtG->close();
}

// Ambil daftar ulasan + pesanan + nama & foto user + balasan
$ulasanSql = "SELECT u.id_ulasan, u.rating, u.komentar, u.balasan, u.is_anonymous, t.id_transaksi,
    GROUP_CONCAT(DISTINCT m.nama_menu ORDER BY m.nama_menu SEPARATOR ', ') AS pesanan,
    us.nama_lengkap, us.photo_path, u.created_at
FROM transaksi t
JOIN ulasan u ON u.id_transaksi = t.id_transaksi
JOIN users us ON us.id_users = u.id_users
LEFT JOIN transaksi_item ti ON ti.id_transaksi = t.id_transaksi
LEFT JOIN menu m ON m.id_menu = ti.id_menu
WHERE t.id_gerai = ?
GROUP BY u.id_ulasan, u.rating, u.komentar, u.balasan, u.is_anonymous, t.id_transaksi, us.nama_lengkap, us.photo_path, u.created_at
ORDER BY u.id_ulasan DESC
LIMIT 500";

$reviews = [];
if ($stmt = $mysqli->prepare($ulasanSql)) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = $row['nama_lengkap'] ?: 'Pengguna';
        $isAnon = (int)$row['is_anonymous'] === 1;
        if ($isAnon) {
            // Masking nama
            $len = mb_strlen($name);
            if ($len <= 2) {
                $name = mb_substr($name,0,1) . str_repeat('*', $len-1);
            } else {
                $name = mb_substr($name,0,1) . str_repeat('*', $len-2) . mb_substr($name,-1);
            }
        }
        $reviews[] = [

            'id_ulasan' => (int)$row['id_ulasan'],
            'name'      => $name,
            'photo'     => $isAnon ? null : ($row['photo_path'] ?: null),
            'pesanan'   => $row['pesanan'] ?: '',
            'rating'    => (int)$row['rating'],
            'komentar'  => $row['komentar'] ?: '',
            'balasan'   => $row['balasan'] ?: '',
            'tanggal' => $row['created_at'] ?? '',
        ];
    }
    $stmt->close();
}

// Hitung breakdown rating
$ratingCount = count($reviews);
$sum = 0;
$breakdownCount = [1=>0,2=>0,3=>0,4=>0,5=>0];
foreach ($reviews as $r) {
    $star = (int)$r['rating'];
    if ($star >=1 && $star <=5) {
        $breakdownCount[$star]++;
        $sum += $star;
    }
}
$avg = $ratingCount > 0 ? round($sum / $ratingCount, 2) : 0;

// Bentuk breakdown urutan 5->1
$breakdown = [];
for ($s=5; $s>=1; $s--) {
    $breakdown[] = ['star'=>$s, 'count'=>$breakdownCount[$s]];
}

$data = [
    'rating' => $avg,
    'ratingCount' => $ratingCount,
    'breakdown' => $breakdown,
    'reviews' => $reviews,
    'gerai_name' => $geraiName,
];

$debug = trim(ob_get_clean());
echo json_encode([
  'success' => true,
  'data' => $data,
  'debug' => $debug !== '' ? $debug : null,
], JSON_UNESCAPED_UNICODE);

?>
