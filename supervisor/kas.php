<?php

session_start();
include "../koneksi.php";

/* =========================
   CEK LOGIN SUPERVISOR
========================= */

if (!isset($_SESSION['id_user'])) {
    header("Location: ../login.php");
    exit;
}

 $level_boleh = ['Supervisor'];

if (!in_array($_SESSION['level'], $level_boleh)) {
    header("Location: ../login.php");
    exit;
}


/* =========================
   ⚙️ KONFIGURASI (GANTI INI!)
========================= */

 $TABEL_USER = "users";     /* ← nama tabel akun Anda */
 $KOLOM_NAMA = "nama";      /* ← kolom nama user */


/* helper format uang */
function rp($n) {
    return "Rp " . number_format($n, 0, ',', '.');
}


/* =========================
   ⭐ PROSES BUKA KAS
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "buka") {

    $id_kasir   = intval($_POST["id_kasir"] ?? 0);
    $saldo_awal = max(0, intval($_POST["saldo_awal"] ?? 0));

    if ($id_kasir <= 0) {
        header("Location: kas.php?error=kasir_kosong");
        exit;
    }

    $q_cek_u = mysqli_query($koneksi, "
        SELECT id_user
        FROM $TABEL_USER
        WHERE id_user = '$id_kasir'
        LIMIT 1
    ");

    if (mysqli_num_rows($q_cek_u) == 0) {
        header("Location: kas.php?error=kasir_kosong");
        exit;
    }

    $q_cek_buka = mysqli_query($koneksi, "
        SELECT id_kas
        FROM kas
        WHERE id_user = '$id_kasir'
        AND status = 'buka'
        LIMIT 1
    ");

    if (mysqli_num_rows($q_cek_buka) > 0) {
        header("Location: kas.php?error=sudah_buka");
        exit;
    }

    mysqli_query($koneksi, "
        INSERT INTO kas (id_user, tanggal, saldo_awal, waktu_buka, status)
        VALUES ('$id_kasir', CURDATE(), '$saldo_awal', NOW(), 'buka')
    ");

    header("Location: kas.php?info=buka");
    exit;
}


/* =========================
   ⭐ PROSES TUTUP KAS
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "tutup") {

    $id_kas     = intval($_POST["id_kas"] ?? 0);
    $uang_fisik = max(0, intval($_POST["uang_fisik"] ?? 0));
    $catatan    = mysqli_real_escape_string($koneksi, trim($_POST["catatan"] ?? ""));

    $q_k = mysqli_query($koneksi, "
        SELECT *
        FROM kas
        WHERE id_kas = '$id_kas'
        AND status = 'buka'
        LIMIT 1
    ");

    $k = mysqli_fetch_assoc($q_k);

    if (!$k) {
        header("Location: kas.php?error=sudah_tutup");
        exit;
    }

    $id_kasir = intval($k['id_user']);

    $q_tunai = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(total_harga), 0) AS t
        FROM penjualan
        WHERE id_user = '$id_kasir'
        AND tanggal_penjualan >= '" . $k['waktu_buka'] . "'
        AND metode_pembayaran = 'Tunai'
    ");
    $total_tunai = intval(mysqli_fetch_assoc($q_tunai)['t']);

    $q_qris = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(total_harga), 0) AS t
        FROM penjualan
        WHERE id_user = '$id_kasir'
        AND tanggal_penjualan >= '" . $k['waktu_buka'] . "'
        AND metode_pembayaran = 'QRIS'
    ");
    $total_qris = intval(mysqli_fetch_assoc($q_qris)['t']);

    $q_trx = mysqli_query($koneksi, "
        SELECT COUNT(*) AS j
        FROM penjualan
        WHERE id_user = '$id_kasir'
        AND tanggal_penjualan >= '" . $k['waktu_buka'] . "'
    ");
    $jml_trx = intval(mysqli_fetch_assoc($q_trx)['j']);

    $saldo_akhir = intval($k['saldo_awal']) + $total_tunai;
    $selisih     = $uang_fisik - $saldo_akhir;

    mysqli_query($koneksi, "
        UPDATE kas
        SET saldo_akhir     = '$saldo_akhir',
            uang_fisik      = '$uang_fisik',
            selisih         = '$selisih',
            total_tunai     = '$total_tunai',
            total_qris      = '$total_qris',
            total_transaksi = '$jml_trx',
            catatan         = '$catatan',
            waktu_tutup     = NOW(),
            status          = 'tutup'
        WHERE id_kas = '$id_kas'
    ");

    header("Location: kas.php?info=tutup&detail=$id_kas");
    exit;
}


/* =========================
   FILTER & PENCARIAN
========================= */

 $q_cari     = trim($_GET["q"] ?? "");
 $q_status   = trim($_GET["status"] ?? "");
 $q_tanggal  = trim($_GET["tanggal"] ?? "");
 $q_bulan    = trim($_GET["bulan"] ?? "");

 $cari_e = mysqli_real_escape_string($koneksi, $q_cari);
 $syarat = "";

if ($q_cari !== "") {
    $syarat .= " AND u.$KOLOM_NAMA LIKE '%$cari_e%'";
}

if ($q_status === "buka" || $q_status === "tutup") {
    $syarat .= " AND k.status = '$q_status'";
}

if ($q_tanggal !== "") {
    $tgl_e = mysqli_real_escape_string($koneksi, $q_tanggal);
    $syarat .= " AND k.tanggal = '$tgl_e'";
}

if ($q_bulan !== "") {
    $bln_e = mysqli_real_escape_string($koneksi, $q_bulan);
    $syarat .= " AND k.tanggal LIKE '$bln_e%'";
}


/* =========================
   STATISTIK
========================= */

 $q_stat = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total_sesi,
        COALESCE(SUM(status = 'buka'), 0) AS sedang_buka,
        COALESCE(SUM(CASE WHEN status = 'tutup' AND selisih < 0 THEN -selisih ELSE 0 END), 0) AS total_kurang,
        COALESCE(SUM(CASE WHEN status = 'tutup' AND selisih > 0 THEN selisih ELSE 0 END), 0) AS total_lebih
    FROM kas
");

 $stat = mysqli_fetch_assoc($q_stat);


/* =========================
   DAFTAR KASIR
========================= */

 $q_kasir_list = mysqli_query($koneksi, "
    SELECT id_user, $KOLOM_NAMA AS nama_kasir
    FROM $TABEL_USER
    WHERE level = 'Kasir I'
    ORDER BY $KOLOM_NAMA ASC
");


/* =========================
   DAFTAR SESI KAS
========================= */

 $q_kas = mysqli_query($koneksi, "
    SELECT
        k.*,
        u.$KOLOM_NAMA AS nama_kasir,
        (SELECT COALESCE(SUM(total_harga), 0) FROM penjualan p
         WHERE p.id_user = k.id_user
         AND p.tanggal_penjualan >= k.waktu_buka
         AND p.metode_pembayaran = 'Tunai') AS live_tunai,
        (SELECT COALESCE(SUM(total_harga), 0) FROM penjualan p
         WHERE p.id_user = k.id_user
         AND p.tanggal_penjualan >= k.waktu_buka
         AND p.metode_pembayaran = 'QRIS') AS live_qris,
        (SELECT COUNT(*) FROM penjualan p
         WHERE p.id_user = k.id_user
         AND p.tanggal_penjualan >= k.waktu_buka) AS live_trx
    FROM kas k
    LEFT JOIN $TABEL_USER u ON u.id_user = k.id_user
    WHERE 1=1 $syarat
    ORDER BY k.waktu_buka DESC
    LIMIT 100
");


/* =========================
   DETAIL SHIFT
========================= */

 $detail = null;
 $detail_trx = [];
 $detail_rekap = ["jml" => 0, "tunai" => 0, "qris" => 0];

if (isset($_GET["detail"])) {

    $id_detail = intval($_GET["detail"]);

    $q_d = mysqli_query($koneksi, "
        SELECT k.*, u.$KOLOM_NAMA AS nama_kasir
        FROM kas k
        LEFT JOIN $TABEL_USER u ON u.id_user = k.id_user
        WHERE k.id_kas = '$id_detail'
        LIMIT 1
    ");

    $detail = mysqli_fetch_assoc($q_d);

    if ($detail) {

        $batas_akhir = ($detail['status'] === 'tutup' && !empty($detail['waktu_tutup']))
            ? " AND tanggal_penjualan <= '" . $detail['waktu_tutup'] . "'"
            : "";

        $q_dt = mysqli_query($koneksi, "
            SELECT kode_transaksi, total_harga, metode_pembayaran, tanggal_penjualan
            FROM penjualan
            WHERE id_user = '" . intval($detail['id_user']) . "'
            AND tanggal_penjualan >= '" . $detail['waktu_buka'] . "'
            $batas_akhir
            ORDER BY tanggal_penjualan ASC
        ");

        while ($t = mysqli_fetch_assoc($q_dt)) {
            $detail_trx[] = $t;
            $detail_rekap['jml']++;

            if ($t['metode_pembayaran'] === 'Tunai') {
                $detail_rekap['tunai'] += intval($t['total_harga']);
            } else {
                $detail_rekap['qris'] += intval($t['total_harga']);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Kas | GELORA WYNE</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-light: #f4f6f9;
            --bg-card: #ffffff;
            --bg-hover: #f8fafc;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            
            /* Warna Tema Cokelat Gelora Wyne Profesional */
            --accent-brown: #8C6D58;
            --accent-brown-dark: #5A4334;
            --accent-brown-light: #fdf8f5;
        }

        body {
            margin: 0;
            background: var(--bg-light);
            color: var(--text-main);
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 13px;
            overflow-x: hidden;
        }

        /* LAYOUT UTAMA */
        .admin-container {
            display: grid;
            grid-template-columns: 80px 1fr;
            min-height: 100vh;
            width: 100vw;
            transition: grid-template-columns 0.3s ease;
        }

        .admin-container.sidebar-open {
            grid-template-columns: 260px 1fr;
        }

        /* SIDEBAR */
        .pos-sidebar {
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 20px 14px;
            justify-content: space-between;
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 1050;
            white-space: nowrap;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 24px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
        }

        .sidebar-brand img {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-brown);
            flex-shrink: 0;
        }

        .brand-text {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--accent-brown-dark);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease;
        }

        .admin-container.sidebar-open .brand-text {
            opacity: 1;
            visibility: visible;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-btn:hover { background: var(--bg-hover); color: var(--accent-brown); }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sidebar-menu a {
            height: 42px;
            display: flex; 
            align-items: center; 
            gap: 14px;
            color: var(--text-muted);
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .sidebar-menu a i { font-size: 16px; width: 20px; text-align: center; }

        .menu-label {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s;
        }

        .admin-container.sidebar-open .menu-label {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-menu a:hover {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
        }

        .sidebar-menu a.active {
            background: var(--accent-brown);
            color: #ffffff;
            font-weight: 600;
        }

        /* KONTEN UTAMA */
        .admin-main {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: var(--bg-light);
        }

        .main-sticky-header {
            padding: 14px 28px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-brown-dark);
            margin: 0;
        }

        .profile-area {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg-light);
            padding: 5px 12px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
        }

        .profile-icon {
            width: 28px; height: 28px;
            background: var(--accent-brown-light);
            color: var(--accent-brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .content-body {
            padding: 24px 28px;
        }

        /* STATISTIK BAR */
        .stat-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.01);
        }

        @media (max-width: 992px) {
            .stat-bar { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-item {
            padding: 14px 18px;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .stat-item:last-child { border-right: none; }
        
        .stat-item .label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.4px;
        }
        .stat-item .val {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent-brown-dark);
            margin-top: 3px;
        }
        .stat-item.merah .val { color: #dc3545; }
        .stat-item.kuning .val { color: #d97706; }

        /* KARTU KONTROL / UTAMA */
        .card-clean {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.01);
        }

        /* FILTER BAR */
        .filter-clean-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }

        .input-search-clean {
            position: relative;
            min-width: 200px;
            flex: 1;
        }
        .input-search-clean input {
            width: 100%;
            height: 36px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0 12px 0 34px;
            font-size: 12.5px;
            background: var(--bg-card);
            outline: none;
        }
        .input-search-clean input:focus { border-color: var(--accent-brown); }
        .input-search-clean i {
            position: absolute;
            left: 10px;
            top: 10px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .control-input {
            height: 36px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-card);
            padding: 0 10px;
            font-size: 12.5px;
            color: var(--text-main);
            outline: none;
        }
        .control-input:focus { border-color: var(--accent-brown); }

        .btn-action-primary {
            height: 36px;
            background: var(--accent-brown);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0 14px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: background 0.2s;
        }
        .btn-action-primary:hover { background: var(--accent-brown-dark); color: white; }

        /* TABEL ELEGAN */
        .table-clean {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        .table-clean th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            letter-spacing: .4px;
            background: var(--bg-hover);
        }
        .table-clean td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-main);
        }
        .table-clean tbody tr:hover { background-color: var(--bg-hover); }
        .table-clean tr:last-child td { border-bottom: none; }

        /* BADGES */
        .badge-chip {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            letter-spacing: .3px;
            text-transform: uppercase;
        }
        .badge-chip.buka { background: #d1e7dd; color: #0f5132; }
        .badge-chip.tutup { background: #f1f3f5; color: var(--text-muted); }

        .selisih-tag { font-weight: 600; }
        .selisih-tag.cocok { color: #0f5132; }
        .selisih-tag.kurang { color: #dc3545; }
        .selisih-tag.lebih { color: #d97706; }

        /* TOMBOL AKSI DALAM TABEL */
        .btn-row {
            padding: 4px 10px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--accent-brown-dark);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .btn-row:hover, .btn-row.aktif {
            background: var(--accent-brown);
            color: white;
            border-color: var(--accent-brown);
        }
        .btn-row.tutup-aksi {
            background: #fffbeb;
            border-color: #fde68a;
            color: #d97706;
            cursor: pointer;
        }
        .btn-row.tutup-aksi:hover {
            background: #d97706;
            color: white;
        }

        /* =========================================
           DETAIL PANEL TERSTRUKTUR KE BAWAH & BERWARNA
        ========================================= */
        .detail-wrapper {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-left: 5px solid var(--accent-brown);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .detail-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 14px;
        }

        /* List Berbaris Kebawah dengan Warna Kontras */
        .detail-stack-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-row-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 14px;
            border-radius: 6px;
            font-size: 13px;
        }
        .detail-row-item .row-label {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-row-item .row-val {
            font-weight: 700;
            font-size: 14px;
        }

        /* Pembeda Warna untuk Membantu Penglihatan/Fokus */
        .item-awal     { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .item-tunai    { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; } /* Hijau Muda: Uang Tunai Masuk */
        .item-qris     { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; } /* Biru Muda: QRIS Masuk */
        .item-totaltrx { background: #faf5ff; color: #6b21a8; border: 1px solid #e9d5ff; } /* Ungu: Jumlah Transaksi */
        .item-seharus  { background: #fdf8f5; color: var(--accent-brown-dark); border: 1px solid #f3e8df; } /* Cokelat Gelora: Seharusnya */
        .item-fisik    { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; } /* Oranye: Fisik Dihitung */
        .item-selisih  { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; } /* Merah Muda: Selisih */

        /* ALERT */
        .alert-clean {
            display: none;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 12px;
            margin-bottom: 16px;
            align-items: center;
            justify-content: space-between;
        }
        .alert-clean.sukses { background: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .alert-clean.gagal { background: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .alert-clean.show { display: flex; }

        /* MODAL */
        .modal-content {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .modal-header, .modal-footer { border-color: var(--border-color); padding: 14px 18px; }
        .modal-body { padding: 18px; }
        .form-label { font-weight: 600; font-size: 12px; margin-bottom: 4px; }
        .input-money { position: relative; }
        .input-money span { position: absolute; left: 10px; top: 9px; color: var(--text-muted); font-weight: 600; font-size: 12px; }
        .input-money input { padding-left: 32px; }
    </style>
</head>
<body>

<div class="admin-container" id="adminContainer">
    
    <!-- SIDEBAR -->
    <aside class="pos-sidebar">
        <div>
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img src="../assets/img/logo.jpeg" alt="Logo">
                    <span class="brand-text">GELORA WYNE</span>
                </div>
                <button class="toggle-btn" onclick="toggleSidebar()" title="Toggle Sidebar">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <div class="sidebar-menu">
                <a href="index.php"><i class="bi bi-bag-heart-fill"></i><span class="menu-label">Katalog Hijab</span></a>
                <a href="produk.php"><i class="bi bi-box-seam-fill"></i><span class="menu-label">Produk</span></a>
                <a href="member.php"><i class="bi bi-award-fill"></i><span class="menu-label">Member</span></a>
                <a href="kas.php" class="active"><i class="bi bi-wallet2"></i><span class="menu-label">Kas</span></a>
                <a href="laporan.php"><i class="bi bi-bar-chart-line-fill"></i><span class="menu-label">Laporan</span></a>
                <a href="users.php"><i class="bi bi-person-badge-fill"></i><span class="menu-label">Pengguna</span></a>
            </div>
        </div>
        <div class="sidebar-menu">
            <a href="../logout.php" style="color: #dc3545;"><i class="bi bi-box-arrow-left"></i><span class="menu-label">Keluar</span></a>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="admin-main">
        <header class="main-sticky-header">
            <h1 class="page-title">Manajemen &amp; Rekap Kas</h1>
            <div class="profile-area">
                <div class="profile-icon"><i class="bi bi-person-fill"></i></div>
                <div>
                    <div style="font-weight: 600; font-size: 12px;"><?= htmlspecialchars($_SESSION['nama']); ?></div>
                    <div style="font-size: 10px; color: var(--text-muted);">Supervisor</div>
                </div>
            </div>
        </header>

        <div class="content-body">

            <!-- ALERT NOTIFIKASI -->
            <div id="alertSukses" class="alert-clean sukses">
                <span id="alertSuksesTeks"></span>
                <button type="button" class="btn-close" style="font-size: 11px;" onclick="tutupAlert('alertSukses')"></button>
            </div>
            <div id="alertGagal" class="alert-clean gagal">
                <span id="alertGagalTeks"></span>
                <button type="button" class="btn-close" style="font-size: 11px;" onclick="tutupAlert('alertGagal')"></button>
            </div>

            <!-- STATISTIK BAR -->
            <div class="stat-bar">
                <div class="stat-item">
                    <div class="label">Total Sesi Kas</div>
                    <div class="val"><?= $stat['total_sesi']; ?></div>
                </div>
                <div class="stat-item">
                    <div class="label">Laci Sedang Buka</div>
                    <div class="val" style="color: #0f5132;"><?= $stat['sedang_buka']; ?> Sesi</div>
                </div>
                <div class="stat-item merah">
                    <div class="label">Akumulasi Selisih Kurang</div>
                    <div class="val" style="font-size: 14px;">Rp <?= number_format($stat['total_kurang'], 0, ',', '.'); ?></div>
                </div>
                <div class="stat-item kuning">
                    <div class="label">Akumulasi Selisih Lebih</div>
                    <div class="val" style="font-size: 14px;">Rp <?= number_format($stat['total_lebih'], 0, ',', '.'); ?></div>
                </div>
            </div>

            <!-- DETAIL SHIFT: TERSTRUKTUR KE BAWAH DENGAN WARNA BERBEDA -->
            <?php if ($detail) { ?>
                <div class="detail-wrapper">
                    <div class="detail-header-flex">
                        <div>
                            <div style="font-size: 15px; font-weight: 700; color: var(--accent-brown-dark);">
                                <i class="bi bi-receipt"></i> Rincian Shift Kasir: <?= htmlspecialchars($detail['nama_kasir'] ?? 'Kasir'); ?>
                            </div>
                            <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 2px;">
                                Tanggal: <?= date('d/m/Y', strtotime($detail['tanggal'])); ?> &bull; Waktu Buka: <?= date('H:i', strtotime($detail['waktu_buka'])); ?>
                                <?php if($detail['status'] === 'tutup') { echo ' &bull; Waktu Tutup: '.date('H:i', strtotime($detail['waktu_tutup'])); } ?>
                            </div>
                        </div>
                        <a href="kas.php" class="btn-row"><i class="bi bi-x-lg"></i> Tutup Rincian</a>
                    </div>

                    <!-- List ke bawah dengan warna kontras agar tidak membingungkan -->
                    <div class="detail-stack-list">
                        
                        <div class="detail-row-item item-awal">
                            <span class="row-label"><i class="bi bi-wallet"></i> Saldo Awal Laci</span>
                            <span class="row-val"><?= rp($detail['saldo_awal']); ?></span>
                        </div>

                        <div class="detail-row-item item-tunai">
                            <span class="row-label"><i class="bi bi-cash-stack"></i> Total Penjualan Tunai (Cash)</span>
                            <span class="row-val">+ <?= rp($detail_rekap['tunai']); ?></span>
                        </div>

                        <div class="detail-row-item item-qris">
                            <span class="row-label"><i class="bi bi-qr-code"></i> Total Penjualan QRIS (Non-Tunai)</span>
                            <span class="row-val">+ <?= rp($detail_rekap['qris']); ?></span>
                        </div>

                        <div class="detail-row-item item-totaltrx">
                            <span class="row-label"><i class="bi bi-bag-check"></i> Jumlah Transaksi Masuk</span>
                            <span class="row-val"><?= $detail_rekap['jml']; ?> Transaksi</span>
                        </div>

                        <?php if ($detail['status'] === 'tutup') { 
                            $sel = intval($detail['selisih']);
                        ?>
                            <div class="detail-row-item item-seharus">
                                <span class="row-label"><i class="bi bi-calculator"></i> Total Uang Seharusnya di Laci (Saldo Awal + Tunai)</span>
                                <span class="row-val"><?= rp($detail['saldo_akhir']); ?></span>
                            </div>

                            <div class="detail-row-item item-fisik">
                                <span class="row-label"><i class="bi bi-cash"></i> Uang Fisik Dihitung Kasir</span>
                                <span class="row-val"><?= rp($detail['uang_fisik']); ?></span>
                            </div>

                            <div class="detail-row-item item-selisih">
                                <span class="row-label"><i class="bi bi-exclamation-circle"></i> Status Selisih Akhir</span>
                                <span class="row-val">
                                    <?php 
                                    if($sel == 0) echo '<span style="color:#166534;">Pas &amp; Cocok ✔</span>';
                                    elseif($sel < 0) echo '<span style="color:#b91c1c;">Kurang — Rp '.number_format(abs($sel),0,',','.').'</span>';
                                    else echo '<span style="color:#c2410c;">Lebih + Rp '.number_format($sel,0,',','.').'</span>';
                                    ?>
                                </span>
                            </div>

                            <?php if(!empty($detail['catatan'])) { ?>
                                <div class="detail-row-item" style="background:#f8fafc; border:1px solid #e2e8f0; color:#475569;">
                                    <span class="row-label"><i class="bi bi-chat-left-text"></i> Catatan Penutupan</span>
                                    <span style="font-size: 12px; font-weight: normal;"><?= htmlspecialchars($detail['catatan']); ?></span>
                                </div>
                            <?php } ?>

                        <?php } else { ?>
                            <div class="detail-row-item" style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;">
                                <span class="row-label"><i class="bi bi-clock-history"></i> Status Laci Saat Ini</span>
                                <span style="display:flex; align-items:center; gap:10px;">
                                    <span class="badge-chip buka">MASIH AKTIF</span>
                                    <button type="button" class="btn-row tutup-aksi" style="padding: 6px 12px;" onclick='bukaModalTutup(<?= json_encode(["id" => $detail['id_kas'], "nama" => $detail['nama_kasir'] ?? "Kasir", "seharusnya" => intval($detail['saldo_awal']) + intval($detail_rekap['tunai'])], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        Tutup Kas Sekarang
                                    </button>
                                </span>
                            </div>
                        <?php } ?>

                    </div>
                </div>
            <?php } ?>

            <!-- KARTU UTAMA DAFTAR KAS -->
            <div class="card-clean">
                
                <!-- FILTER & TOOLBAR -->
                <form method="GET" action="kas.php" class="filter-clean-bar">
                    <div class="filter-group">
                        <div class="input-search-clean">
                            <i class="bi bi-search"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($q_cari); ?>" placeholder="Cari nama kasir..." autocomplete="off">
                        </div>

                        <input type="date" name="tanggal" value="<?= htmlspecialchars($q_tanggal); ?>" class="control-input" title="Filter Tanggal" onchange="this.form.submit()">

                        <input type="month" name="bulan" value="<?= htmlspecialchars($q_bulan); ?>" class="control-input" title="Filter Bulan" onchange="this.form.submit()">

                        <select name="status" class="control-input" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="buka" <?= ($q_status === "buka") ? "selected" : ""; ?>>Masih Buka</option>
                            <option value="tutup" <?= ($q_status === "tutup") ? "selected" : ""; ?>>Sudah Tutup</option>
                        </select>

                        <?php if ($q_cari !== "" || $q_tanggal !== "" || $q_bulan !== "" || $q_status !== "") { ?>
                            <a href="kas.php" class="btn-row" style="height: 36px;" title="Reset Filter"><i class="bi bi-arrow-counterclockwise"></i></a>
                        <?php } ?>
                    </div>

                    <button type="button" class="btn-action-primary" onclick="bukaModalBuka()">
                        <i class="bi bi-plus-circle-fill"></i> Buka Kas Baru
                    </button>
                </form>

                <!-- TABEL DATA -->
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Waktu Shift</th>
                                <th>Kasir</th>
                                <th style="text-align:right;">Saldo Awal</th>
                                <th style="text-align:right;">Tunai</th>
                                <th style="text-align:right;">QRIS</th>
                                <th style="text-align:right;">Seharusnya</th>
                                <th style="text-align:center;">Selisih</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($q_kas) == 0) { ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        Tidak ada riwayat sesi kas yang cocok dengan filter.
                                    </td>
                                </tr>
                            <?php } else { ?>
                                <?php while ($k = mysqli_fetch_assoc($q_kas)) {
                                    $masih_buka = ($k['status'] === 'buka');

                                    if ($masih_buka) {
                                        $tunai = intval($k['live_tunai']);
                                        $qris = intval($k['live_qris']);
                                        $seharusnya = intval($k['saldo_awal']) + $tunai;
                                        $selisih_html = '<span style="color:var(--text-muted);">—</span>';
                                    } else {
                                        $tunai = intval($k['total_tunai']);
                                        $qris = intval($k['total_qris']);
                                        $seharusnya = intval($k['saldo_akhir']);
                                        $sel = intval($k['selisih']);

                                        if ($sel == 0) $selisih_html = '<span class="selisih-tag cocok">Cocok ✔</span>';
                                        elseif ($sel < 0) $selisih_html = '<span class="selisih-tag kurang">-Rp ' . number_format(abs($sel), 0, ',', '.') . '</span>';
                                        else $selisih_html = '<span class="selisih-tag lebih">+Rp ' . number_format($sel, 0, ',', '.') . '</span>';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600;"><?= date('d/m/Y', strtotime($k['tanggal'])); ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?= date('H:i', strtotime($k['waktu_buka'])); ?></div>
                                        </td>
                                        <td style="font-weight: 600;"><?= htmlspecialchars($k['nama_kasir'] ?? 'Kasir'); ?></td>
                                        <td style="text-align:right;"><?= rp($k['saldo_awal']); ?></td>
                                        <td style="text-align:right;"><?= rp($tunai); ?></td>
                                        <td style="text-align:right;"><?= rp($qris); ?></td>
                                        <td style="text-align:right; font-weight:700; color:var(--accent-brown-dark);"><?= rp($seharusnya); ?></td>
                                        <td style="text-align:center;"><?= $selisih_html; ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($masih_buka) { ?>
                                                <span class="badge-chip buka">BUKA</span>
                                            <?php } else { ?>
                                                <span class="badge-chip tutup">TUTUP</span>
                                            <?php } ?>
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <a href="kas.php?detail=<?= $k['id_kas']; ?>" class="btn-row <?= (isset($_GET['detail']) && intval($_GET['detail']) === intval($k['id_kas'])) ? 'aktif' : ''; ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                            <?php if ($masih_buka) { ?>
                                                <button type="button" class="btn-row tutup-aksi" onclick='bukaModalTutup(<?= json_encode(["id" => $k['id_kas'], "nama" => $k['nama_kasir'] ?? "Kasir", "seharusnya" => $seharusnya], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                    Tutup
                                                </button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </main>

</div>

<!-- MODAL BUKA KAS -->
<div class="modal fade" id="modalBukaKas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="kas.php">
                <input type="hidden" name="aksi" value="buka">
                <div class="modal-header">
                    <h5 style="font-size: 14px; font-weight: 700; color: var(--accent-brown-dark); margin:0;">Buka Sesi Kasir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pilih Kasir</label>
                        <select name="id_kasir" class="control-input w-100" required>
                            <option value="">— Pilih Kasir —</option>
                            <?php while ($kr = mysqli_fetch_assoc($q_kasir_list)) { ?>
                                <option value="<?= $kr['id_user']; ?>"><?= htmlspecialchars($kr['nama_kasir']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Saldo Awal di Laci</label>
                        <div class="input-money">
                            <span>Rp</span>
                            <input type="number" name="saldo_awal" class="control-input w-100" min="0" required placeholder="0" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-row" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-action-primary">Buka Kas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL TUTUP KAS -->
<div class="modal fade" id="modalTutupKas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="kas.php" id="formTutupKas">
                <input type="hidden" name="aksi" value="tutup">
                <input type="hidden" name="id_kas" id="tutupId">
                <div class="modal-header">
                    <h5 style="font-size: 14px; font-weight: 700; color: var(--accent-brown-dark); margin:0;">Tutup Sesi Kas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div id="tutupInfo" style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;"></div>

                    <div class="mb-3">
                        <label class="form-label">Total Uang Fisik Dihitung</label>
                        <div class="input-money">
                            <span>Rp</span>
                            <input type="number" name="uang_fisik" id="tutupUang" class="control-input w-100" min="0" required placeholder="0" autocomplete="off" oninput="hitungSelisihTutup()">
                        </div>
                    </div>

                    <div id="boxSelisihTutup" style="padding: 8px 12px; border-radius: 6px; font-size: 12px; background: var(--bg-light); border: 1px dashed var(--border-color); margin-bottom: 12px;">
                        Masukkan uang fisik untuk melihat selisih.
                    </div>

                    <div>
                        <label class="form-label">Catatan (Opsional)</label>
                        <textarea name="catatan" class="control-input w-100" style="height: auto;" rows="2" placeholder="Keterangan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-row" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn-action-primary" onclick="submitTutup()">Konfirmasi Tutup Kas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('adminContainer').classList.toggle('sidebar-open');
}

function bukaModalBuka() {
    new bootstrap.Modal(document.getElementById("modalBukaKas")).show();
}

let seharusnyaSaatIni = 0;
function bukaModalTutup(data) {
    seharusnyaSaatIni = data.seharusnya;
    document.getElementById("tutupId").value = data.id;
    document.getElementById("tutupInfo").innerHTML = "Kasir: <strong>" + data.nama + "</strong> · Seharusnya di laci: <strong>Rp " + data.seharusnya.toLocaleString("id-ID") + "</strong>";
    document.getElementById("tutupUang").value = "";
    hitungSelisihTutup();
    new bootstrap.Modal(document.getElementById("modalTutupKas")).show();
}

function hitungSelisihTutup() {
    const raw = document.getElementById("tutupUang").value;
    const box = document.getElementById("boxSelisihTutup");
    if (raw === "") {
        box.innerText = "Masukkan uang fisik untuk melihat selisih.";
        box.style.color = "var(--text-muted)";
        return;
    }
    const fisik = Number(raw) || 0;
    const sel = fisik - seharusnyaSaatIni;

    if (sel === 0) {
        box.innerText = "Uang fisik pas dan cocok ✔";
        box.style.color = "#0f5132";
    } else if (sel < 0) {
        box.innerText = "Selisih Kurang: Rp " + Math.abs(sel).toLocaleString("id-ID");
        box.style.color = "#dc3545";
    } else {
        box.innerText = "Selisih Lebih: Rp " + sel.toLocaleString("id-ID");
        box.style.color = "#d97706";
    }
}

function submitTutup() {
    if (document.getElementById("tutupUang").value === "") {
        alert("Harap isi jumlah uang fisik.");
        return;
    }
    if (confirm("Tutup sesi kas ini? Data tidak dapat diubah setelah ditutup.")) {
        document.getElementById("formTutupKas").submit();
    }
}

// Notifikasi Alert
const pesanSukses = { "buka": "Kas berhasil dibuka.", "tutup": "Kas berhasil ditutup." };
const pesanGagal = { "kasir_kosong": "Kasir tidak valid.", "sudah_buka": "Kasir masih memiliki sesi aktif.", "sudah_tutup": "Sesi sudah ditutup." };

const infoGet = "<?= htmlspecialchars($_GET['info'] ?? ''); ?>";
const errorGet = "<?= htmlspecialchars($_GET['error'] ?? ''); ?>";

if (pesanSukses[infoGet]) {
    document.getElementById("alertSuksesTeks").innerText = pesanSukses[infoGet];
    document.getElementById("alertSukses").classList.add("show");
}
if (pesanGagal[errorGet]) {
    document.getElementById("alertGagalTeks").innerText = pesanGagal[errorGet];
    document.getElementById("alertGagal").classList.add("show");
}
function tutupAlert(id) { document.getElementById(id).classList.remove("show"); }
</script>
</body>
</html>