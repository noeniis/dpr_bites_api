<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$mysqli = @new mysqli('localhost', 'root', '', 'dpr_bites');
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Use a derived table to aggregate ratings per gerai first. This prevents
// row multiplication when joining to menu (one row per menu) which would
// otherwise inflate COUNT/AVG results.
$sql = "SELECT g.id_gerai,
               g.nama_gerai,
               COALESCE(gp.listing_path, '') AS listing_path,
               COALESCE(gp.deskripsi_gerai, '') AS deskripsi,
               ROUND(COALESCE(r.avg_rating, 0), 1) AS avg_rating,
               COALESCE(r.rating_count, 0) AS rating_count,
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
        ORDER BY g.id_gerai DESC";

$restaurants = [];
if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
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
    $result->free();
}

if (empty($restaurants)) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

$ids = implode(',', array_map('intval', array_keys($restaurants)));

$etalaseSql = "SELECT e.id_etalase, e.id_gerai, e.nama_etalase
               FROM etalase e
               WHERE e.id_gerai IN ($ids)";
$etalaseMap = [];
if ($res = $mysqli->query($etalaseSql)) {
    while ($r = $res->fetch_assoc()) {
        $g = (int)$r['id_gerai'];
        if (!isset($restaurants[$g])) continue;
        $eid = (int)$r['id_etalase'];
        $etalaseMap[$eid] = $g;
        $restaurants[$g]['etalase'][] = [
            'id'    => $eid,
            'name'  => $r['nama_etalase'],
            'menus' => [],
        ];
    }
    $res->free();
}

if (!empty($etalaseMap)) {
    $etalaseIds = implode(',', array_map('intval', array_keys($etalaseMap)));
    $menuSql = "SELECT id_menu, id_etalase, nama_menu, harga, kategori
                FROM menu
                WHERE id_etalase IN ($etalaseIds)";
    if ($res = $mysqli->query($menuSql)) {
        while ($m = $res->fetch_assoc()) {
            $eid = (int)$m['id_etalase'];
            $gid = $etalaseMap[$eid] ?? null;
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
        $res->free();
    }
}

echo json_encode([
    'success' => true,
    'data'    => array_values($restaurants),
]);
exit;