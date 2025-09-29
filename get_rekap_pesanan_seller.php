
<?php
header('Content-Type: application/json');
require_once __DIR__.'/protected.php'; 
require_once 'db.php'; 

// ambil param
$id_gerai = $_GET['id_gerai'] ?? $_POST['id_gerai'] ?? null;
$tanggal  = $_GET['tanggal']  ?? $_POST['tanggal']  ?? date('Y-m-d');


// Deteksi mode filter: harian, bulanan, atau all
$isAll = empty($tanggal) || strtolower($tanggal) === 'all';
$isMonth = false;
$isDay = false;
if (!$isAll) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $isDay = true;
    } elseif (preg_match('/^\d{4}-\d{2}$/', $tanggal)) {
        $isMonth = true;
    }
}

if (!$id_gerai) {
    echo json_encode(['success' => false, 'message' => 'id_gerai required']);
    exit;
}

try {
    $sqlRekap = "
        SELECT REPLACE(LOWER(status), ' ', '_') AS st, COUNT(*) AS cnt
        FROM transaksi
        WHERE id_gerai = ? ";
    if ($isDay) {
        $sqlRekap .= " AND DATE(created_at) = ? ";
    } elseif ($isMonth) {
        $sqlRekap .= " AND DATE_FORMAT(created_at, '%Y-%m') = ? ";
    }
    $sqlRekap .= " GROUP BY REPLACE(LOWER(status), ' ', '_') ";
    $stmt = $conn->prepare($sqlRekap);
    if ($isDay || $isMonth) {
        $stmt->bind_param("ss", $id_gerai, $tanggal);
    } else {
        $stmt->bind_param("s", $id_gerai);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $counts = [
        'konfirmasi_ketersediaan' => 0,
        'konfirmasi_pembayaran'   => 0,
        'disiapkan'               => 0,
        'diantar'                 => 0,
        'pickup'                  => 0,
        'selesai'                 => 0,
        'dibatalkan'              => 0,
    ];
    $status_breakdown = [];

    while ($row = $result->fetch_assoc()) {
        $st  = $row['st'];
        $cnt = (int)$row['cnt'];
        $status_breakdown[$st] = $cnt;
        if (array_key_exists($st, $counts)) {
            $counts[$st] = $cnt;
        }
    }
    $stmt->close();

    $pesanan_baru     = $counts['konfirmasi_ketersediaan'] + $counts['konfirmasi_pembayaran'];
    $sedang_disiapkan = $counts['disiapkan'];
    $diantar          = $counts['diantar'];
    $pickup           = $counts['pickup'];

	
    // --------- Total saldo (status selesai) ----------

    $sqlSaldo = "
        SELECT SUM(
            COALESCE((
                SELECT SUM(ti.subtotal)
                FROM transaksi_item ti
                WHERE ti.id_transaksi = t.id_transaksi
            ),0)
            +
            COALESCE((
                SELECT SUM(a.harga * ti2.jumlah)
                FROM transaksi_item ti2
                LEFT JOIN transaksi_item_Addon tia
                  ON tia.id_transaksi_item = ti2.id_transaksi_item
                LEFT JOIN addon a
                  ON a.id_addon = tia.id_addon
                WHERE ti2.id_transaksi = t.id_transaksi
            ),0)
        ) AS total_saldo
        FROM transaksi t
        WHERE t.id_gerai = ?
          AND REPLACE(LOWER(t.status),' ','_') = 'selesai' ";
    if ($isDay) {
        $sqlSaldo .= " AND DATE(t.created_at) = ? ";
    } elseif ($isMonth) {
        $sqlSaldo .= " AND DATE_FORMAT(t.created_at, '%Y-%m') = ? ";
    }

    $stmt2 = $conn->prepare($sqlSaldo);
    if ($isDay || $isMonth) {
        $stmt2->bind_param("ss", $id_gerai, $tanggal);
    } else {
        $stmt2->bind_param("s", $id_gerai);
    }
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row2 = $result2->fetch_assoc();
    $total_saldo = (int)($row2['total_saldo'] ?? 0);
    $stmt2->close();

    // --------- Rekap Menu Utama ----------
    $menu_rekap = [];
    $sqlMenu = "
        SELECT m.id_menu, m.nama_menu, COALESCE(m.harga,0) AS harga_satuan, SUM(ti.jumlah) as total_terjual, SUM(ti.subtotal) as total_pendapatan
        FROM transaksi_item ti
        JOIN menu m ON ti.id_menu = m.id_menu
        JOIN transaksi t ON ti.id_transaksi = t.id_transaksi
        WHERE t.id_gerai = ? ";
    if ($isDay) {
        $sqlMenu .= " AND DATE(t.created_at) = ? ";
    } elseif ($isMonth) {
        $sqlMenu .= " AND DATE_FORMAT(t.created_at, '%Y-%m') = ? ";
    }
    $sqlMenu .= " GROUP BY m.id_menu, m.nama_menu ";
    $stmtMenu = $conn->prepare($sqlMenu);
    if ($isDay || $isMonth) {
        $stmtMenu->bind_param("ss", $id_gerai, $tanggal);
    } else {
        $stmtMenu->bind_param("s", $id_gerai);
    }
    $stmtMenu->execute();
    $resultMenu = $stmtMenu->get_result();
    while ($row = $resultMenu->fetch_assoc()) {
        $menu_rekap[] = [
            'id_menu' => $row['id_menu'],
            'nama_menu' => $row['nama_menu'],
            'harga_satuan' => isset($row['harga_satuan']) ? (int)$row['harga_satuan'] : 0,
            'total_terjual' => (int)$row['total_terjual'],
            'total_pendapatan' => isset($row['total_pendapatan']) ? (int)$row['total_pendapatan'] : 0,
        ];
    }
    $stmtMenu->close();

    // --------- Rekap Add-on ----------
    $addon_rekap = [];
    $sqlAddon = "
        SELECT a.id_addon, a.nama_addon, COALESCE(a.harga,0) AS harga, COUNT(*) as total_terjual, SUM(a.harga) as total_pendapatan
        FROM transaksi_item_addon tia
        JOIN addon a ON tia.id_addon = a.id_addon
        JOIN transaksi_item ti ON tia.id_transaksi_item = ti.id_transaksi_item
        JOIN transaksi t ON ti.id_transaksi = t.id_transaksi
        WHERE t.id_gerai = ? ";
    if ($isDay) {
        $sqlAddon .= " AND DATE(t.created_at) = ? ";
    } elseif ($isMonth) {
        $sqlAddon .= " AND DATE_FORMAT(t.created_at, '%Y-%m') = ? ";
    }
    $sqlAddon .= " GROUP BY a.id_addon, a.nama_addon ";
    $stmtAddon = $conn->prepare($sqlAddon);
    if ($isDay || $isMonth) {
        $stmtAddon->bind_param("ss", $id_gerai, $tanggal);
    } else {
        $stmtAddon->bind_param("s", $id_gerai);
    }
    $stmtAddon->execute();
    $resultAddon = $stmtAddon->get_result();
    while ($row = $resultAddon->fetch_assoc()) {
        $addon_rekap[] = [
            'id_addon' => $row['id_addon'],
            'nama_addon' => $row['nama_addon'],
            'harga' => isset($row['harga']) ? (int)$row['harga'] : 0,
            'total_terjual' => (int)$row['total_terjual'],
            'total_pendapatan' => isset($row['total_pendapatan']) ? (int)$row['total_pendapatan'] : 0,
        ];
    }
    $stmtAddon->close();

    echo json_encode([
        'success'           => true,
        'pesanan_baru'      => $pesanan_baru,
        'sedang_disiapkan'  => $sedang_disiapkan,
        'diantar'           => $diantar,
        'pickup'            => $pickup,
        'total_saldo'       => $total_saldo,
        'debug_status_breakdown' => $status_breakdown,
        'debug_date_used'        => $isAll ? 'all' : ($isMonth ? 'month' : 'day'),
        'menu_rekap'        => $menu_rekap,
        'addon_rekap'       => $addon_rekap,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'server error', 'error' => $e->getMessage()]);
}
