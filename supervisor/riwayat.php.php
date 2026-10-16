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


function rp($n) {
    return "Rp " . number_format($n, 0, ',', '.');
}


/* =========================
   FILTER
========================= */

 $q_cari    = trim($_GET["q"] ?? "");
 $q_metode  = trim($_GET["metode"] ?? "");
 $q_kasir   = intval($_GET["kasir"] ?? 0);
 $q_periode = trim($_GET["periode"] ?? "semua");

 $cari_e = mysqli_real_escape_string($koneksi, $q_cari);

 $syarat = " WHERE 1=1 ";

/* pencarian bebas: kode / kasir / pelanggan */
if ($q_cari !== "") {
    $syarat .= " AND (p.kode_transaksi LIKE '%$cari_e%'
                 OR u.$KOLOM_NAMA LIKE '%$cari_e%'
                 OR pl.nama_pelanggan LIKE '%$cari_e%') ";
}

/* metode */
if ($q_metode === "Tunai" || $q_metode === "QRIS") {
    $syarat .= " AND p.metode_pembayaran = '$q_metode' ";
}

/* kasir */
if ($q_kasir > 0) {
    $syarat .= " AND p.id_user = '$q_kasir' ";
}

/* periode */
 $label_periode = "Semua waktu";

switch ($q_periode) {

    case "hari_ini":
        $syarat .= " AND DATE(p.tanggal_penjualan) = CURDATE() ";
        $label_periode = "Hari ini";
        break;

    case "7hari":
        $syarat .= " AND p.tanggal_penjualan >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) ";
        $label_periode = "7 hari terakhir";
        break;

    case "30hari":
        $syarat .= " AND p.tanggal_penjualan >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) ";
        $label_periode = "30 hari terakhir";
        break;

    case "bulan_ini":
        $syarat .= " AND MONTH(p.tanggal_penjualan) = MONTH(CURDATE())
                     AND YEAR(p.tanggal_penjualan) = YEAR(CURDATE()) ";
        $label_periode = "Bulan ini";
        break;
}


/* =========================
   STATISTIK SESUAI FILTER
   Catatan: kalau fitur diskon poin aktif,
   ganti SUM(p.total_harga) → SUM(p.total_harga - p.diskon)
========================= */

 $q_stat = mysqli_query($koneksi, "
    SELECT
        COUNT(p.id_penjualan) AS jml,
        COALESCE(SUM(p.total_harga), 0) AS omset,
        COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'Tunai' THEN p.total_harga ELSE 0 END), 0) AS tunai,
        COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'QRIS'  THEN p.total_harga ELSE 0 END), 0) AS qris
    FROM penjualan p
    LEFT JOIN $TABEL_USER u ON u.id_user = p.id_user
    LEFT JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
    $syarat
");

 $stat = mysqli_fetch_assoc($q_stat);


/* =========================
   DAFTAR KASIR (untuk dropdown filter)
========================= */

 $q_daftar_kasir = mysqli_query($koneksi, "
    SELECT DISTINCT u.id_user, u.$KOLOM_NAMA AS nama_kasir
    FROM penjualan p
    INNER JOIN $TABEL_USER u ON u.id_user = p.id_user
    ORDER BY u.$KOLOM_NAMA ASC
");


/* =========================
   PAGINATION
========================= */

 $per_halaman = 25;

 $q_total = mysqli_query($koneksi, "
    SELECT COUNT(p.id_penjualan) AS jml
    FROM penjualan p
    LEFT JOIN $TABEL_USER u ON u.id_user = p.id_user
    LEFT JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
    $syarat
");

 $total_data = intval(mysqli_fetch_assoc($q_total)['jml']);
 $total_hal  = max(1, ceil($total_data / $per_halaman));
 $hal_skr    = max(1, intval($_GET["hal"] ?? 1));
if ($hal_skr > $total_hal) $hal_skr = $total_hal;

 $mulai = ($hal_skr - 1) * $per_halaman;


/* =========================
   DAFTAR TRANSAKSI
========================= */

 $q_trx = mysqli_query($koneksi, "
    SELECT
        p.id_penjualan,
        p.kode_transaksi,
        p.total_harga,
        p.metode_pembayaran,
        p.tanggal_penjualan,
        u.$KOLOM_NAMA AS nama_kasir,
        pl.nama_pelanggan
    FROM penjualan p
    LEFT JOIN $TABEL_USER u ON u.id_user = p.id_user
    LEFT JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
    $syarat
    ORDER BY p.tanggal_penjualan DESC
    LIMIT $mulai, $per_halaman
");


/* =========================
   DETAIL TRANSAKSI (?detail=id)
========================= */

 $detail = null;
 $detail_items = [];
 $detail_total = 0;

if (isset($_GET["detail"])) {

    $id_detail = intval($_GET["detail"]);

    $q_d = mysqli_query($koneksi, "
        SELECT
            p.*,
            u.$KOLOM_NAMA AS nama_kasir,
            pl.nama_pelanggan
        FROM penjualan p
        LEFT JOIN $TABEL_USER u ON u.id_user = p.id_user
        LEFT JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
        WHERE p.id_penjualan = '$id_detail'
        LIMIT 1
    ");

    $detail = mysqli_fetch_assoc($q_d);


    if ($detail) {

        $q_di = mysqli_query($koneksi, "
            SELECT
                d.jumlah,
                d.harga_satuan,
                d.subtotal,
                pr.kode_produk,
                pr.nama_produk
            FROM detailpenjualan d
            INNER JOIN produk pr ON pr.id_produk = d.id_produk
            WHERE d.id_penjualan = '$id_detail'
            ORDER BY d.id_detail ASC
        ");

        while ($it = mysqli_fetch_assoc($q_di)) {
            $detail_items[] = $it;
            $detail_total += intval($it['subtotal']);
        }
    }
}

?>


<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Transaksi | GELORA WYNE</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">

    <style>

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #F3EEE6;
            color: #293331;
            font-family: Arial, sans-serif;
            overflow-x: hidden;
        }

        .overlay-mobile {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(40,30,20,.45);
            z-index: 1040;
        }

        .overlay-mobile.show { display: block; }

        .menu-icon svg { width: 22px; height: 22px; }
        .toggle-btn svg { width: 22px; height: 22px; }
        .profile-icon svg { width: 20px; height: 20px; }
        .tutup-sidebar svg { width: 18px; height: 18px; }

        /* ===== SIDEBAR ===== */
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 245px;
            background: #594A3F;
            color: white;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            transition: transform .3s, width .3s;
        }

        .sidebar.collapsed { width: 76px; }

        .brand {
            height: 75px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 22px;
            border-bottom: 1px solid rgba(255,255,255,.1);
            position: relative;
        }

        .brand img {
            width: 40px; height: 40px;
            object-fit: contain;
            background: white;
            border-radius: 10px;
            padding: 4px;
            flex-shrink: 0;
        }

        .brand-text {
            font-size: 18px;
            font-weight: 700;
            white-space: nowrap;
            letter-spacing: .5px;
        }

        .sidebar.collapsed .brand-text { display: none; }

        .tutup-sidebar {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #D9CFC5;
            cursor: pointer;
            display: none;
            padding: 6px;
        }

        .menu { padding: 20px 12px; flex: 1; overflow-y: auto; }

        .menu-title {
            font-size: 11px;
            color: #D9CFC5;
            text-transform: uppercase;
            margin: 0 12px 10px;
            letter-spacing: 1px;
        }

        .sidebar.collapsed .menu-title { display: none; }

        .menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            color: #F5F1E8;
            text-decoration: none;
            padding: 12px 13px;
            border-radius: 10px;
            margin-bottom: 6px;
            font-size: 14px;
            transition: .2s;
            white-space: nowrap;
        }

        .menu a:hover { background: rgba(255,255,255,.08); }
        .menu a.active { background: #B49A67; }

        .menu-icon {
            width: 22px; min-width: 22px; height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-text { white-space: nowrap; }
        .sidebar.collapsed .menu a { justify-content: center; }
        .sidebar.collapsed .menu-text { display: none; }

        .logout {
            padding: 15px 12px;
            border-top: 1px solid rgba(255,255,255,.1);
        }

        .logout a {
            color: #F5F1E8;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 13px;
            border-radius: 10px;
            transition: .2s;
        }

        .logout a:hover { background: rgba(220,80,80,.25); }
        .sidebar.collapsed .logout .menu-text { display: none; }
        .sidebar.collapsed .logout a { justify-content: center; }

        /* ===== MAIN / NAVBAR ===== */
        .main {
            margin-left: 245px;
            min-height: 100vh;
            transition: .3s;
        }

        .main.expanded { margin-left: 76px; }

        .navbar-custom {
            height: 75px;
            background: white;
            border-bottom: 1px solid #E3DDD4;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-left { display: flex; align-items: center; gap: 16px; }

        .toggle-btn {
            width: 42px; height: 42px;
            border: none;
            background: #F3EEE6;
            color: #594A3F;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: .2s;
        }

        .toggle-btn:hover { background: #EADCCB; }

        .page-title { font-size: 20px; font-weight: 700; margin: 0; }

        .profile { display: flex; align-items: center; gap: 10px; }

        .profile-icon {
            width: 40px; height: 40px;
            background: #EADCCB;
            color: #594A3F;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-name { font-size: 14px; font-weight: 600; }
        .profile-role { font-size: 11px; color: #8A7B70; }

        /* ===== CONTENT ===== */
        .content { padding: 28px; }

        .welcome { margin-bottom: 20px; }
        .welcome h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .welcome p { margin: 0; color: #7A7068; font-size: 14px; }

        .periode-badge {
            display: inline-block;
            background: #F5EEDC;
            border: 1px solid #DDC98F;
            color: #8A6D2E;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            margin-top: 8px;
        }

        /* ===== STAT MINI ===== */
        .stat-mini {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            border: 1px solid #E4DED6;
            border-radius: 12px;
            padding: 14px 16px;
        }

        .stat-box .label { font-size: 11.5px; color: #8A7B70; }

        .stat-box .nilai {
            font-size: 19px;
            font-weight: 800;
            color: #365C63;
            margin-top: 2px;
        }

        .stat-box .sub { font-size: 10.5px; color: #9A8C81; margin-top: 2px; }

        .stat-box.utama { background: #365C63; border-color: #365C63; }
        .stat-box.utama .label { color: #BFD3D5; }
        .stat-box.utama .nilai { color: white; }
        .stat-box.utama .sub { color: #BFD3D5; }

        .stat-box.emas { background: #F5EEDC; border-color: #DDC98F; }
        .stat-box.emas .nilai { color: #8A6D2E; }

        /* ===== TOOLBAR ===== */
        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .search-box input {
            width: 100%;
            height: 46px;
            border: 1px solid #DED7CE;
            border-radius: 12px;
            padding: 0 16px 0 44px;
            background: white;
            outline: none;
            font-size: 14px;
        }

        .search-box input:focus {
            border-color: #B49A67;
            box-shadow: 0 0 0 3px rgba(180,154,103,.12);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 13px;
            color: #8A7B70;
        }

        .search-icon svg { width: 18px; height: 18px; }

        .filter-select {
            height: 46px;
            border: 1px solid #DED7CE;
            border-radius: 12px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            min-width: 140px;
            outline: none;
        }

        .filter-select:focus { border-color: #B49A67; }

        /* ===== PANEL ===== */
        .panel {
            background: white;
            border: 1px solid #E4DED6;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 22px;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .table-custom th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #9A8C81;
            padding: 10px;
            border-bottom: 2px solid #EEE9E2;
            letter-spacing: .5px;
            white-space: nowrap;
        }

        .table-custom td {
            padding: 12px 10px;
            border-bottom: 1px solid #EEE9E2;
            vertical-align: middle;
        }

        .table-custom tr:last-child td { border-bottom: none; }

        .kode-trx { font-weight: 700; }

        .waktu-sub { font-size: 11px; color: #9A8C81; margin-top: 2px; white-space: nowrap; }

        .badge-metode {
            font-size: 9.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
        }

        .badge-metode.tunai {
            background: #EEF3F3;
            color: #365C63;
            border: 1px solid #B9CDD0;
        }

        .badge-metode.qris {
            background: #F5EEDC;
            color: #8A6D2E;
            border: 1px solid #DDC98F;
        }

        .total-teks {
            color: #365C63;
            font-weight: 800;
            white-space: nowrap;
        }

        .redup { color: #9A8C81; }

        .btn-detail {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #F3EEE6;
            border: 1px solid #E0DAD2;
            color: #365C63;
            text-decoration: none;
            font-size: 11.5px;
            font-weight: 700;
            padding: 7px 12px;
            border-radius: 8px;
            transition: .2s;
            white-space: nowrap;
        }

        .btn-detail:hover { background: #365C63; color: white; }
        .btn-detail svg { width: 13px; height: 13px; }

        .btn-detail.aktif {
            background: #365C63;
            color: white;
        }

        .text-kosong {
            text-align: center;
            color: #9A8C81;
            padding: 30px 10px !important;
            font-size: 13px;
        }

        /* ===== DETAIL ===== */
        .detail-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .detail-judul { font-size: 17px; font-weight: 800; }
        .detail-sub { font-size: 12.5px; color: #8A7B70; margin-top: 3px; }

        .btn-tutup-detail {
            background: white;
            border: 1px solid #D8D3CB;
            color: #555B58;
            border-radius: 9px;
            font-size: 12.5px;
            font-weight: 600;
            padding: 8px 14px;
            text-decoration: none;
            transition: .2s;
        }

        .btn-tutup-detail:hover { background: #F7F4EE; color: #293331; }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 18px;
        }

        .detail-box {
            background: #F7F4EE;
            border: 1px solid #E7E1D8;
            border-radius: 10px;
            padding: 11px 13px;
        }

        .detail-box .lbl { font-size: 11px; color: #8A7B70; }

        .detail-box .val {
            font-size: 14.5px;
            font-weight: 800;
            color: #365C63;
            margin-top: 2px;
        }

        /* ===== PAGINATION ===== */
        .paginasi {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 18px;
        }

        .paginasi a,
        .paginasi span {
            min-width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid #DED7CE;
            background: white;
            color: #594A3F;
            padding: 0 10px;
        }

        .paginasi a:hover { background: #F3EEE6; }

        .paginasi span.aktif {
            background: #365C63;
            border-color: #365C63;
            color: white;
        }

        .paginasi span.nonaktif { opacity: .4; cursor: default; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) {

            .stat-mini { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }

        }

        @media (max-width: 850px) {

            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }

            .tutup-sidebar { display: block; }

            .main,
            .main.expanded { margin-left: 0; }

            .content { padding: 20px 15px; }
            .navbar-custom { padding: 0 16px; }
            .profile-name, .profile-role { display: none; }

            .table-wrap { overflow-x: auto; }
            .table-custom { min-width: 820px; }

        }

    </style>

</head>


<body>


<!-- OVERLAY MOBILE -->

<div class="overlay-mobile" id="overlayMobile"></div>


<!-- =========================
     SIDEBAR
========================= -->

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <img src="../assets/img/logo.jpeg" alt="GELORA WYNE">

        <span class="brand-text">GELORA WYNE</span>

        <button class="tutup-sidebar" id="tutupSidebar" type="button" aria-label="Tutup menu">

            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>

        </button>

    </div>


    <div class="menu">

        <div class="menu-title">Menu Supervisor</div>


        <a href="index.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>

            <span class="menu-text">Dashboard</span>

        </a>


        <a href="produk.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </span>

            <span class="menu-text">Produk</span>

        </a>


        <a href="kategori.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </span>

            <span class="menu-text">Kategori</span>

        </a>


        <a href="stok.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            </span>

            <span class="menu-text">Stok</span>

        </a>


        <a href="pelanggan.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>

            <span class="menu-text">Pelanggan</span>

        </a>


        <a href="member.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </span>

            <span class="menu-text">Member</span>

        </a>


        <a href="transaksi.php" class="active">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </span>

            <span class="menu-text">Transaksi</span>

        </a>


        <a href="kas.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>

            <span class="menu-text">Rekap Kas</span>

        </a>


        <a href="laporan.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
            </span>

            <span class="menu-text">Laporan</span>

        </a>


        <a href="pengguna.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>

            <span class="menu-text">Pengguna</span>

        </a>

    </div>


    <div class="logout">

        <a href="../logout.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </span>

            <span class="menu-text">Keluar</span>

        </a>

    </div>

</aside>


<!-- =========================
     MAIN
========================= -->

<main class="main" id="main">


    <nav class="navbar-custom">

        <div class="navbar-left">

            <button class="toggle-btn" id="toggleSidebar" type="button">

                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>

            </button>

            <h1 class="page-title">Transaksi</h1>

        </div>

        <div class="profile">

            <div class="profile-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>

            <div>
                <div class="profile-name"><?= htmlspecialchars($_SESSION['nama']); ?></div>
                <div class="profile-role">Supervisor</div>
            </div>

        </div>

    </nav>


    <div class="content">


        <!-- WELCOME -->

        <div class="welcome">

            <h2>Semua Transaksi</h2>

            <p>Seluruh penjualan dari semua kasir — cari, filter, dan lihat rinciannya.</p>

            <span class="periode-badge">
                📅 <?= htmlspecialchars($label_periode); ?> · <?= $total_data; ?> transaksi
            </span>

        </div>


        <!-- STAT MINI -->

        <div class="stat-mini">

            <div class="stat-box utama">

                <div class="label">Total Omset</div>
                <div class="nilai"><?= rp($stat['omset']); ?></div>
                <div class="sub"><?= intval($stat['jml']); ?> transaksi</div>

            </div>

            <div class="stat-box">

                <div class="label">Tunai</div>
                <div class="nilai"><?= rp($stat['tunai']); ?></div>

            </div>

            <div class="stat-box emas">

                <div class="label">QRIS</div>
                <div class="nilai"><?= rp($stat['qris']); ?></div>

            </div>

            <div class="stat-box">

                <div class="label">Rata-rata / Transaksi</div>
                <div class="nilai">
                    <?= rp(intval($stat['jml']) > 0 ? intval(floor($stat['omset'] / $stat['jml'])) : 0); ?>
                </div>

            </div>

        </div>


        <!-- =========================
             PANEL DETAIL TRANSAKSI
        ========================== -->

        <?php if ($detail) { ?>

            <div class="panel">

                <div class="detail-head">

                    <div>

                        <div class="detail-judul">
                            <?= htmlspecialchars($detail['kode_transaksi']); ?>
                        </div>

                        <div class="detail-sub">
                            <?= date('d/m/Y H:i', strtotime($detail['tanggal_penjualan'])); ?>
                            · kasir <?= htmlspecialchars($detail['nama_kasir'] ?? '-'); ?>
                            · pelanggan
                            <?= $detail['nama_pelanggan']
                                ? htmlspecialchars($detail['nama_pelanggan'])
                                : '<strong>Umum</strong>'; ?>
                        </div>

                    </div>

                    <a href="transaksi.php" class="btn-tutup-detail">✕ Tutup Detail</a>

                </div>


                <div class="detail-grid">

                    <div class="detail-box">
                        <div class="lbl">Metode pembayaran</div>
                        <div class="val">
                            <span class="badge-metode <?= ($detail['metode_pembayaran'] == 'Tunai') ? 'tunai' : 'qris'; ?>">
                                <?= $detail['metode_pembayaran']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="detail-box">
                        <div class="lbl">Total belanja</div>
                        <div class="val"><?= rp($detail['total_harga']); ?></div>
                    </div>

                    <div class="detail-box">
                        <div class="lbl">Dibayar</div>
                        <div class="val"><?= rp($detail['bayar']); ?></div>
                    </div>

                    <div class="detail-box">
                        <div class="lbl">Kembalian</div>
                        <div class="val"><?= rp($detail['kembalian']); ?></div>
                    </div>

                </div>


                <!-- ITEM PRODUK -->

                <div style="font-size:14px;font-weight:700;margin-bottom:10px;">
                    Item Transaksi
                </div>

                <div class="table-wrap">

                    <table class="table-custom" style="min-width:auto;">

                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th style="text-align:center;">Qty</th>
                                <th style="text-align:right;">Harga Satuan</th>
                                <th style="text-align:right;">Subtotal</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (count($detail_items) == 0) { ?>

                                <tr>
                                    <td colspan="4" class="text-kosong">
                                        Item tidak ditemukan.
                                    </td>
                                </tr>

                            <?php } else { ?>

                                <?php foreach ($detail_items as $it) { ?>

                                    <tr>

                                        <td>
                                            <div style="font-weight:700;">
                                                <?= htmlspecialchars($it['nama_produk']); ?>
                                            </div>
                                            <div class="waktu-sub"><?= htmlspecialchars($it['kode_produk']); ?></div>
                                        </td>

                                        <td style="text-align:center;">
                                            <?= intval($it['jumlah']); ?>
                                        </td>

                                        <td style="text-align:right;">
                                            <?= rp($it['harga_satuan']); ?>
                                        </td>

                                        <td style="text-align:right;font-weight:700;color:#365C63;">
                                            <?= rp($it['subtotal']); ?>
                                        </td>

                                    </tr>

                                <?php } ?>


                                <tr>

                                    <td colspan="3" style="text-align:right;font-weight:800;">
                                        Total
                                    </td>

                                    <td style="text-align:right;font-weight:800;color:#365C63;font-size:14.5px;">
                                        <?= rp($detail_total); ?>
                                    </td>

                                </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php } ?>


        <!-- TOOLBAR -->

        <form method="GET" action="transaksi.php" class="toolbar">

            <div class="search-box">

                <span class="search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>

                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($q_cari); ?>"
                    placeholder="Cari kode transaksi, kasir, atau pelanggan..."
                    autocomplete="off">

            </div>


            <select
                name="metode"
                class="filter-select"
                onchange="this.form.submit()">

                <option value="">Semua metode</option>
                <option value="Tunai" <?= ($q_metode === "Tunai") ? "selected" : ""; ?>>Tunai</option>
                <option value="QRIS"  <?= ($q_metode === "QRIS")  ? "selected" : ""; ?>>QRIS</option>

            </select>


            <select
                name="kasir"
                class="filter-select"
                onchange="this.form.submit()">

                <option value="0">Semua kasir</option>

                <?php while ($k = mysqli_fetch_assoc($q_daftar_kasir)) { ?>

                    <option value="<?= $k['id_user']; ?>" <?= ($q_kasir === intval($k['id_user'])) ? "selected" : ""; ?>>
                        <?= htmlspecialchars($k['nama_kasir']); ?>
                    </option>

                <?php } ?>

            </select>


            <select
                name="periode"
                class="filter-select"
                onchange="this.form.submit()">

                <option value="semua"    <?= ($q_periode === "semua")    ? "selected" : ""; ?>>Semua waktu</option>
                <option value="hari_ini" <?= ($q_periode === "hari_ini") ? "selected" : ""; ?>>Hari ini</option>
                <option value="7hari"    <?= ($q_periode === "7hari")    ? "selected" : ""; ?>>7 hari terakhir</option>
                <option value="30hari"   <?= ($q_periode === "30hari")   ? "selected" : ""; ?>>30 hari terakhir</option>
                <option value="bulan_ini" <?= ($q_periode === "bulan_ini") ? "selected" : ""; ?>>Bulan ini</option>

            </select>

        </form>


        <!-- TABEL TRANSAKSI -->

        <div class="panel">

            <div class="table-wrap">

                <table class="table-custom">

                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Waktu</th>
                            <th>Kasir</th>
                            <th>Pelanggan</th>
                            <th>Metode</th>
                            <th style="text-align:right;">Total</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (mysqli_num_rows($q_trx) == 0) { ?>

                            <tr>
                                <td colspan="7" class="text-kosong">
                                    Tidak ada transaksi yang cocok dengan filter.
                                </td>
                            </tr>

                        <?php } else { ?>

                            <?php while ($t = mysqli_fetch_assoc($q_trx)) { ?>

                                <tr>

                                    <td>
                                        <span class="kode-trx"><?= htmlspecialchars($t['kode_transaksi']); ?></span>
                                    </td>


                                    <td style="white-space:nowrap;">

                                        <?= date('d/m/Y', strtotime($t['tanggal_penjualan'])); ?>

                                        <div class="waktu-sub">
                                            <?= date('H:i', strtotime($t['tanggal_penjualan'])); ?>
                                        </div>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars($t['nama_kasir'] ?? '-'); ?>
                                    </td>


                                    <td>

                                        <?= ($t['nama_pelanggan'])
                                            ? htmlspecialchars($t['nama_pelanggan'])
                                            : '<span class="redup">Umum</span>'; ?>

                                    </td>


                                    <td>
                                        <span class="badge-metode <?= ($t['metode_pembayaran'] == 'Tunai') ? 'tunai' : 'qris'; ?>">
                                            <?= $t['metode_pembayaran']; ?>
                                        </span>
                                    </td>


                                    <td style="text-align:right;" class="total-teks">
                                        <?= rp($t['total_harga']); ?>
                                    </td>


                                    <td style="text-align:center;">


                                        <a
                                            href="transaksi.php?detail=<?= $t['id_penjualan']; ?>"
                                            class="btn-detail <?= (isset($_GET['detail']) && intval($_GET['detail']) === intval($t['id_penjualan'])) ? 'aktif' : ''; ?>">


                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>

                                            Detail

                                        </a>


                                    </td>

                                </tr>

                            <?php } ?>

                        <?php } ?>

                    </tbody>

                </table>

            </div>


            <!-- PAGINATION -->

            <?php if ($total_hal > 1) { ?>

                <div class="paginasi">

                    <?php

                    /* pertahankan filter saat pindah halaman */
                    $qs = $_GET;
                    unset($qs["hal"]);

                    function url_hal($h, $qs) {
                        $qs["hal"] = $h;
                        return "?" . http_build_query($qs);
                    }

                    ?>


                    <?php if ($hal_skr > 1) { ?>

                        <a href="<?= url_hal($hal_skr - 1, $qs); ?>">‹</a>

                    <?php } else { ?>

                        <span class="nonaktif">‹</span>

                    <?php } ?>


                    <?php

                    $mulai_n = max(1, $hal_skr - 2);
                    $akhir_n = min($total_hal, $hal_skr + 2);

                    for ($i = $mulai_n; $i <= $akhir_n; $i++) { ?>

                        <?php if ($i === $hal_skr) { ?>

                            <span class="aktif"><?= $i; ?></span>

                        <?php } else { ?>

                            <a href="<?= url_hal($i, $qs); ?>"><?= $i; ?></a>

                        <?php } ?>

                    <?php } ?>


                    <?php if ($hal_skr < $total_hal) { ?>

                        <a href="<?= url_hal($hal_skr + 1, $qs); ?>">›</a>

                    <?php } else { ?>

                        <span class="nonaktif">›</span>

                    <?php } ?>

                </div>

            <?php } ?>


            <div style="font-size:11px;color:#9A8C81;margin-top:12px;">
                Menampilkan <?= mysqli_num_rows($q_trx); ?> dari <?= $total_data; ?> transaksi.
            </div>

        </div>


    </div>

</main>


<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>

/* =========================
   SIDEBAR BUKA-TUTUP
========================= */

const sidebar   = document.getElementById("sidebar");
const main      = document.getElementById("main");
const toggleBtn = document.getElementById("toggleSidebar");
const tutupBtn  = document.getElementById("tutupSidebar");
const overlay   = document.getElementById("overlayMobile");

toggleBtn.addEventListener("click", function () {

    if (window.innerWidth <= 850) {

        sidebar.classList.add("mobile-open");
        overlay.classList.add("show");

    } else {

        sidebar.classList.toggle("collapsed");
        main.classList.toggle("expanded");

    }

});

tutupBtn.addEventListener("click", tutupMobile);

overlay.addEventListener("click", tutupMobile);

function tutupMobile() {

    sidebar.classList.remove("mobile-open");
    overlay.classList.remove("show");

}

window.addEventListener("resize", function () {

    if (window.innerWidth > 850) {

        tutupMobile();

    } else {

        sidebar.classList.remove("collapsed");
        main.classList.remove("expanded");

    }

});

</script>


</body>
</html>