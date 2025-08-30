<?php
// Endpoint: get_restaurants_by_price.php
// Filter restoran (status approved) berdasarkan rentang harga menu.
// Parameter (GET atau POST JSON) "price" contoh:
//   "10000-25000" atau "15.000 – 25.000" (boleh titik & en dash)
//   ">30000" atau ">35.000"
//   "<15000" atau "<15.000"
// Tanpa parameter mengembalikan semua (approved).
// Logika disamakan dengan filter di aplikasi Flutter (iterasi etalase -> menus).

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$mysqli = @new mysqli('localhost', 'root', '', 'dpr_bites');
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Ambil input JSON (opsional)
$raw = file_get_contents('php://input');
$jsonBody = null;
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $jsonBody = $tmp;
}

// Ambil parameter price
$priceParam = null;
if (isset($_GET['price'])) {
    $priceParam = trim($_GET['price']);
} elseif ($jsonBody && isset($jsonBody['price'])) {
    $priceParam = trim((string)$jsonBody['price']);
}

$filterMode = null; // 'range' | 'gt' | 'lt'
$minPriceFilter = null;
$maxPriceFilter = null; // hanya dipakai untuk mode range

// Util parse angka dari string (hapus non digit)
function _parseInt($s) {
    $digits = preg_replace('/[^0-9]/', '', (string)$s);
    if ($digits === '' ) return null;
    return (int)$digits;
}

if ($priceParam !== null && $priceParam !== '') {
    // Normalisasi en dash / em dash menjadi minus
    $norm = str_replace(['–','—'], '-', $priceParam);
    if (strpos($norm, '-') !== false) { // rentang
        $parts = explode('-', $norm, 2);
        $minPriceFilter = _parseInt($parts[0]);
        $maxPriceFilter = _parseInt($parts[1]);
        if ($minPriceFilter !== null && $maxPriceFilter !== null) {
            $filterMode = 'range';
        }
    } elseif (strpos($norm, '>') !== false) { // lebih besar dari
        $val = _parseInt(str_replace('>', '', $norm));
        if ($val !== null) {
            $filterMode = 'gt';
            $minPriceFilter = $val; // gunakan sebagai ambang
        }
    } elseif (strpos($norm, '<') !== false) { // kurang dari
        $val = _parseInt(str_replace('<', '', $norm));
        if ($val !== null) {
            $filterMode = 'lt';
            $maxPriceFilter = $val; // gunakan sebagai batas atas
        }
    }
}

// Query utama (tanpa filter harga dulu)
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
        ORDER BY g.id_gerai DESC";

$restaurants = [];
if ($res = $mysqli->query($sql)) {
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
    $res->free();
}

if (empty($restaurants)) {
    echo json_encode([
        'success' => true,
        'filter'  => [
            'mode' => $filterMode,
            'min'  => $minPriceFilter,
            'max'  => $maxPriceFilter,
            'raw'  => $priceParam,
        ],
        'data'    => []
    ]);
    exit;
}

$ids = implode(',', array_map('intval', array_keys($restaurants)));

// Ambil etalase
$etalaseSql = "SELECT id_etalase, id_gerai, nama_etalase FROM etalase WHERE id_gerai IN ($ids)";
$etalaseToGerai = [];
if ($res = $mysqli->query($etalaseSql)) {
    while ($r = $res->fetch_assoc()) {
        $g = (int)$r['id_gerai'];
        if (!isset($restaurants[$g])) continue;
        $eid = (int)$r['id_etalase'];
        $etalaseToGerai[$eid] = $g;
        $restaurants[$g]['etalase'][] = [
            'id'    => $eid,
            'name'  => $r['nama_etalase'],
            'menus' => [],
        ];
    }
    $res->free();
}

// Ambil menu & tempel
if (!empty($etalaseToGerai)) {
    $etalaseIds = implode(',', array_map('intval', array_keys($etalaseToGerai)));
    $menuSql = "SELECT id_menu, id_etalase, nama_menu, harga, kategori FROM menu WHERE id_etalase IN ($etalaseIds)";
    if ($res = $mysqli->query($menuSql)) {
        while ($m = $res->fetch_assoc()) {
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
        $res->free();
    }
}

// Terapkan filter harga (algoritma sama dengan Flutter ditambah '<')
if ($filterMode !== null) {
    foreach ($restaurants as $rid => $resto) {
        $match = false;
        $etalase = $resto['etalase'];
        if (is_array($etalase)) {
            foreach ($etalase as $e) {
                if (!isset($e['menus']) || !is_array($e['menus'])) continue;
                foreach ($e['menus'] as $menu) {
                    $price = isset($menu['price']) ? (int)$menu['price'] : null;
                    if ($price === null) continue;
                    if ($filterMode === 'range' && $minPriceFilter !== null && $maxPriceFilter !== null) {
                        if ($price >= $minPriceFilter && $price <= $maxPriceFilter) { $match = true; break 2; }
                    } elseif ($filterMode === 'gt' && $minPriceFilter !== null) {
                        if ($price > $minPriceFilter) { $match = true; break 2; }
                    } elseif ($filterMode === 'lt' && $maxPriceFilter !== null) {
                        if ($price < $maxPriceFilter) { $match = true; break 2; }
                    }
                }
            }
        }
        if (!$match) {
            unset($restaurants[$rid]);
        }
    }
}

echo json_encode([
    'success' => true,
    'filter'  => [
        'mode' => $filterMode,
        'min'  => $minPriceFilter,
        'max'  => $maxPriceFilter,
        'raw'  => $priceParam,
    ],
    'data' => array_values($restaurants)
]);
exit;
?>