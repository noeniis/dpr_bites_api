<?php
// Endpoint: get_restaurants_by_rating.php
// Deskripsi: Mengambil daftar gerai (status approved) yang memenuhi minimal rating.
// Cara pakai:
//   Tanpa parameter (default 4.5):
//     http://localhost/dpr_bites_api/get_restaurants_by_rating.php
//   Dengan parameter GET:
//     http://localhost/dpr_bites_api/get_restaurants_by_rating.php?min_rating=4.0
//   Dengan POST JSON:
//     {"min_rating":4.2}
// Response JSON struktur mirip get_restaurants.php agar mudah reuse di Flutter.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$mysqli = @new mysqli('localhost', 'root', '', 'dpr_bites');
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Baca input JSON opsional
$raw = file_get_contents('php://input');
$jsonBody = null;
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $jsonBody = $tmp;
}

// Ambil min_rating (default 4.5 sesuai filter chip "Bintang 4.5+")
$minRating = 4.5;
if (isset($_GET['min_rating'])) {
    $minRating = floatval($_GET['min_rating']);
} elseif ($jsonBody && isset($jsonBody['min_rating'])) {
    $minRating = floatval($jsonBody['min_rating']);
}
if ($minRating < 0) $minRating = 0; // sanitasi ringan

// Query utama: agregasi rating & rentang harga
// Aggregate ratings in a derived subquery to avoid duplicates caused by
// joining menu rows to the transaksi/ulasan joins.
$sql = "SELECT g.id_gerai,
               g.nama_gerai,
               COALESCE(gp.listing_path, '') AS listing_path,
               COALESCE(gp.deskripsi_gerai, '') AS deskripsi,
               ROUND(COALESCE(r.avg_rating,0),1) AS avg_rating,
               COALESCE(r.rating_count,0) AS rating_count,
               MIN(m.harga) AS min_price,
               MAX(m.harga) AS max_price
        FROM gerai g
        LEFT JOIN gerai_profil gp ON gp.id_gerai = g.id_gerai
        LEFT JOIN (
            SELECT t.id_gerai AS id_gerai,
                   AVG(u.rating) AS avg_rating,
                   COUNT(u.id_ulasan) AS rating_count
            FROM transaksi t
            JOIN ulasan u ON u.id_transaksi = t.id_transaksi
            GROUP BY t.id_gerai
        ) r ON r.id_gerai = g.id_gerai
        LEFT JOIN menu m ON m.id_gerai = g.id_gerai
        WHERE g.status_pengajuan = 'approved'
        GROUP BY g.id_gerai
        HAVING avg_rating >= ?
        ORDER BY avg_rating DESC, g.id_gerai DESC";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Prepare failed']);
    exit;
}
$stmt->bind_param('d', $minRating);
$stmt->execute();
$res = $stmt->get_result();

$restaurants = [];
while ($row = $res->fetch_assoc()) {
    $restaurants[$row['id_gerai']] = [
        'id'         => (int)$row['id_gerai'],
        'name'       => $row['nama_gerai'],
        'profilePic' => $row['listing_path'],
        'rating'     => (float)$row['avg_rating'],
        'ratingCount'=> (int)$row['rating_count'],
        'desc'       => $row['deskripsi'],
        'minPrice'   => $row['min_price'] !== null ? (int)$row['min_price'] : null,
        'maxPrice'   => $row['max_price'] !== null ? (int)$row['max_price'] : null,
        'etalase'    => [],
    ];
}
$stmt->close();

if (empty($restaurants)) {
    echo json_encode([
        'success' => true,
        'filter'  => ['min_rating' => $minRating],
        'data'    => []
    ]);
    exit;
}

$ids = implode(',', array_map('intval', array_keys($restaurants)));

// Ambil etalase
$etalaseSql = "SELECT id_etalase, id_gerai, nama_etalase FROM etalase WHERE id_gerai IN ($ids)";
if ($etRes = $mysqli->query($etalaseSql)) {
    // Simpan relasi etalase->gerai
    $etalaseToGerai = [];
    while ($er = $etRes->fetch_assoc()) {
        $g = (int)$er['id_gerai'];
        $eid = (int)$er['id_etalase'];
        if (!isset($restaurants[$g])) continue;
        $etalaseToGerai[$eid] = $g;
        $restaurants[$g]['etalase'][] = [
            'id'    => $eid,
            'name'  => $er['nama_etalase'],
            'menus' => [],
        ];
    }
    $etRes->free();

    if (!empty($etalaseToGerai)) {
        $etalaseIds = implode(',', array_map('intval', array_keys($etalaseToGerai)));
        $menuSql = "SELECT id_menu, id_etalase, nama_menu, harga, kategori FROM menu WHERE id_etalase IN ($etalaseIds)";
        if ($mRes = $mysqli->query($menuSql)) {
            while ($m = $mRes->fetch_assoc()) {
                $eid = (int)$m['id_etalase'];
                $gid = $etalaseToGerai[$eid] ?? null;
                if ($gid === null) continue;
                foreach ($restaurants[$gid]['etalase'] as &$et) {
                    if ($et['id'] === $eid) {
                        $et['menus'][] = [
                            'id'       => (int)$m['id_menu'],
                            'name'     => $m['nama_menu'],
                            'price'    => (int)$m['harga'],
                            'category' => $m['kategori'],
                        ];
                        break;
                    }
                }
                unset($et);
            }
            $mRes->free();
        }
    }
}

echo json_encode([
    'success' => true,
    'filter'  => ['min_rating' => $minRating],
    'data'    => array_values($restaurants)
]);
exit;
?>