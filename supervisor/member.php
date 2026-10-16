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
   PROSES TAMBAH MEMBER
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "tambah") {

    $nama   = mysqli_real_escape_string($koneksi, trim($_POST["nama_pelanggan"] ?? ""));
    $no_hp  = mysqli_real_escape_string($koneksi, trim($_POST["no_hp"] ?? ""));
    $alamat = mysqli_real_escape_string($koneksi, trim($_POST["alamat"] ?? ""));

    if ($nama === "") {
        header("Location: member.php?error=kosong");
        exit;
    }

    mysqli_query($koneksi, "
        INSERT INTO pelanggan
            (nama_pelanggan, alamat, no_hp, status_member, tgl_daftar, poin, jumlah_kunjungan)
        VALUES
            ('$nama', '$alamat', '$no_hp', 'Member', CURDATE(), 0, 0)
    ");

    header("Location: member.php?info=tambah");
    exit;
}


/* =========================
   PROSES EDIT DATA MEMBER
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "edit") {

    $id     = intval($_POST["id_pelanggan"] ?? 0);
    $nama   = mysqli_real_escape_string($koneksi, trim($_POST["nama_pelanggan"] ?? ""));
    $no_hp  = mysqli_real_escape_string($koneksi, trim($_POST["no_hp"] ?? ""));
    $alamat = mysqli_real_escape_string($koneksi, trim($_POST["alamat"] ?? ""));

    if ($id <= 0 || $nama === "") {
        header("Location: member.php?error=kosong");
        exit;
    }

    mysqli_query($koneksi, "
        UPDATE pelanggan
        SET nama_pelanggan = '$nama',
            no_hp          = '$no_hp',
            alamat         = '$alamat'
        WHERE id_pelanggan = '$id'
        AND status_member = 'Member'
    ");

    header("Location: member.php?info=edit");
    exit;
}


/* =========================
   PROSES ATUR POIN MANUAL
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "atur_poin") {

    $id   = intval($_POST["id_pelanggan"] ?? 0);
    $poin = max(0, intval($_POST["poin"] ?? 0));

    if ($id > 0) {

        mysqli_query($koneksi, "
            UPDATE pelanggan
            SET poin = '$poin'
            WHERE id_pelanggan = '$id'
            AND status_member = 'Member'
        ");
    }

    header("Location: member.php?info=poin");
    exit;
}


/* =========================
   PROSES TURUNKAN JADI UMUM
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "jadi_umum") {

    $id = intval($_POST["id_pelanggan"] ?? 0);

    if ($id > 0) {

        mysqli_query($koneksi, "
            UPDATE pelanggan
            SET status_member = 'Umum'
            WHERE id_pelanggan = '$id'
        ");
    }

    header("Location: member.php?info=turun");
    exit;
}


/* =========================
   PROSES HAPUS MEMBER
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["aksi"] ?? "") === "hapus") {

    $id = intval($_POST["id_pelanggan"] ?? 0);

    if ($id > 0) {

        $cek = mysqli_query($koneksi, "
            SELECT COUNT(*) AS jml
            FROM penjualan
            WHERE id_pelanggan = '$id'
        ");

        $jml = intval(mysqli_fetch_assoc($cek)['jml']);

        if ($jml > 0) {
            header("Location: member.php?error=terpakai");
            exit;
        }

        mysqli_query($koneksi, "
            DELETE FROM pelanggan
            WHERE id_pelanggan = '$id'
        ");
    }

    header("Location: member.php?info=hapus");
    exit;
}


/* =========================
   PENCARIAN & URUTAN
========================= */

 $q_cari = trim($_GET["q"] ?? "");
 $q_urut = trim($_GET["urut"] ?? "nama");

 $cari_e = mysqli_real_escape_string($koneksi, $q_cari);

 $syarat = "";

if ($q_cari !== "") {
    $syarat .= " AND (pl.nama_pelanggan LIKE '%$cari_e%' OR pl.no_hp LIKE '%$cari_e%')";
}

 $urutan_sql = "pl.nama_pelanggan ASC";

if ($q_urut === "poin")      { $urutan_sql = "pl.poin DESC, pl.nama_pelanggan ASC"; }
if ($q_urut === "kunjungan") { $urutan_sql = "pl.jumlah_kunjungan DESC, pl.nama_pelanggan ASC"; }
if ($q_urut === "baru")      { $urutan_sql = "pl.tgl_daftar DESC, pl.nama_pelanggan ASC"; }


/* =========================
   STATISTIK MEMBER
========================= */

 $q_stat = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS total_member,
        COALESCE(SUM(poin), 0) AS total_poin,
        COALESCE(SUM(MONTH(tgl_daftar) = MONTH(CURDATE()) AND YEAR(tgl_daftar) = YEAR(CURDATE())), 0) AS member_baru
    FROM pelanggan
    WHERE status_member = 'Member'
");

 $stat = mysqli_fetch_assoc($q_stat);


/* =========================
   DAFTAR MEMBER + JML TRANSAKSI
========================= */

 $q_member = mysqli_query($koneksi, "
    SELECT
        pl.id_pelanggan,
        pl.nama_pelanggan,
        pl.alamat,
        pl.no_hp,
        pl.tgl_daftar,
        pl.poin,
        pl.jumlah_kunjungan,
        COUNT(p.id_penjualan) AS jml_trx
    FROM pelanggan pl
    LEFT JOIN penjualan p ON p.id_pelanggan = pl.id_pelanggan
    WHERE pl.status_member = 'Member' $syarat
    GROUP BY pl.id_pelanggan
    ORDER BY $urutan_sql
");

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Member | GELORA WYNE</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-light: #f8f9fa;
            --bg-card: #ffffff;
            --bg-hover: #f1f3f5;
            --border-color: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
            
            /* Warna Tema Asli (Cokelat Gelora Wyne) */
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

        /* LAYOUT UTAMA GRID (Sidebar Asli & Konten) */
        .admin-container {
            display: grid;
            grid-template-columns: 80px 1fr;
            min-height: 100vh;
            width: 100vw;
            transition: grid-template-columns 0.3s ease;
        }

        .admin-container.sidebar-open {
            grid-template-columns: 280px 1fr;
        }

        @media (max-width: 1200px) {
            .admin-container {
                grid-template-columns: 70px 1fr;
            }
            .admin-container.sidebar-open {
                grid-template-columns: 250px 1fr;
            }
        }

        /* 1. SIDEBAR KIRI (ASLI) */
        .pos-sidebar {
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            padding: 20px 14px;
            justify-content: space-between;
            overflow-y: auto;
            overflow-x: hidden;
            transition: width 0.3s ease;
            white-space: nowrap;
            z-index: 1050;
            height: 100vh;
            position: sticky;
            top: 0;
        }

        .pos-sidebar::-webkit-scrollbar { width: 4px; }
        .pos-sidebar::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 2px; }

        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 24px;
            gap: 10px;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
        }

        .sidebar-brand img {
            width: 40px; height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-brown);
            flex-shrink: 0;
        }

        .brand-text {
            font-weight: bold;
            font-size: 14px;
            color: var(--accent-brown-dark);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .admin-container.sidebar-open .brand-text {
            opacity: 1;
            visibility: visible;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        .toggle-btn:hover { background: var(--bg-hover); color: var(--accent-brown); }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
        }

        .sidebar-menu a {
            height: 44px;
            display: flex; 
            align-items: center; 
            gap: 14px;
            color: var(--text-muted);
            border-radius: 10px;
            padding: 0 12px;
            font-size: 13.5px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .sidebar-menu a i {
            font-size: 18px;
            flex-shrink: 0;
            width: 20px;
            text-align: center;
        }

        .menu-label {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
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
        }

        /* 2. AREA KONTEN UTAMA */
        .admin-main {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            background: var(--bg-light);
        }

        .main-sticky-header {
            padding: 16px 28px;
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
            font-size: 17px;
            font-weight: 800;
            color: var(--accent-brown-dark);
            margin: 0;
        }

        .profile-area {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-light);
            padding: 6px 14px;
            border-radius: 30px;
            border: 1px solid var(--border-color);
        }

        .profile-icon {
            width: 32px; height: 32px;
            background: var(--accent-brown-light);
            color: var(--accent-brown);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .content-body {
            padding: 24px 28px;
        }

        /* STATISTIK & HEADER RINGKAS (Dibuat Tidak Terlalu Besar / Compact) */
        .dashboard-top-grid {
            display: grid;
            grid-template-columns: 1.6fr 2.4fr;
            gap: 14px;
            margin-bottom: 20px;
        }

        @media (max-width: 992px) {
            .dashboard-top-grid { grid-template-columns: 1fr; }
        }

        .welcome-box-new {
            background: linear-gradient(135deg, var(--accent-brown-dark), var(--accent-brown));
            color: #ffffff;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(90, 67, 52, 0.12);
        }
        .welcome-box-new h2 {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 4px;
            color: #ffffff;
        }
        .welcome-box-new p {
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
            font-size: 11.5px;
            line-height: 1.4;
        }

        .stat-mini-new {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        @media (max-width: 576px) {
            .stat-mini-new { grid-template-columns: 1fr; }
        }

        .stat-box-new {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .stat-box-new .label {
            font-size: 10.5px;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-box-new .nilai {
            font-size: 18px;
            font-weight: 800;
            color: var(--accent-brown-dark);
            margin-top: 4px;
        }

        .stat-box-new.emas {
            background: var(--accent-brown-light);
            border-color: #e6d5cb;
        }
        .stat-box-new.emas .nilai {
            color: var(--accent-brown);
        }

        /* PANEL UTAMA TABEL */
        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .catatan-modal {
            margin-top: 12px;
            padding: 10px 14px;
            background: var(--accent-brown-light);
            border-left: 3px solid var(--accent-brown);
            border-radius: 6px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* TOOLBAR PENCARIAN & FILTER */
        .toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar-left {
            display: flex;
            gap: 10px;
            flex: 1;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .search-box input {
            width: 100%;
            height: 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0 14px 0 38px;
            background: var(--bg-card);
            outline: none;
            font-size: 13px;
        }
        .search-box input:focus {
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 3px rgba(140, 109, 88, 0.12);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 11px;
            color: var(--text-muted);
            font-size: 15px;
        }

        .filter-select {
            height: 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-card);
            padding: 0 12px;
            font-size: 13px;
            min-width: 165px;
            outline: none;
            color: var(--text-main);
        }
        .filter-select:focus { border-color: var(--accent-brown); }

        .btn-tambah {
            height: 40px;
            border: none;
            background: var(--accent-brown);
            color: white;
            border-radius: 8px;
            padding: 0 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .btn-tambah:hover { background: var(--accent-brown-dark); }

        /* TABEL CUSTOM */
        .table-wrap { overflow-x: auto; }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .table-custom th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 12px 10px;
            border-bottom: 2px solid var(--border-color);
            letter-spacing: .5px;
            white-space: nowrap;
        }
        .table-custom td {
            padding: 14px 10px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-main);
        }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tbody tr:hover { background-color: var(--bg-hover); }

        .nama-member { font-weight: 700; font-size: 13px; color: var(--text-main); }
        .alamat-member {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hp-member { white-space: nowrap; font-weight: 600; color: var(--text-main); }
        .tgl-daftar { font-size: 11px; color: var(--text-muted); white-space: nowrap; }

        .poin-teks {
            color: var(--accent-brown-dark);
            font-weight: 800;
            font-size: 13.5px;
            white-space: nowrap;
        }

        .kunjungan-teks { white-space: nowrap; }
        .kunjungan-sub { font-size: 10.5px; color: var(--text-muted); margin-top: 2px; }

        .progres {
            width: 80px;
            height: 5px;
            background: var(--border-color);
            border-radius: 5px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progres-isi {
            height: 100%;
            background: var(--accent-brown);
            border-radius: 5px;
        }

        .aksi-btn {
            width: 30px; height: 30px;
            border-radius: 7px;
            border: 1px solid var(--border-color);
            background: white;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            font-size: 13px;
        }
        .aksi-btn.poin { color: var(--accent-brown); }
        .aksi-btn.poin:hover { background: var(--accent-brown-light); border-color: var(--accent-brown); }

        .aksi-btn.edit { color: #0284c7; }
        .aksi-btn.edit:hover { background: #f0f9ff; border-color: #0284c7; }

        .aksi-btn.turun { color: var(--text-muted); }
        .aksi-btn.turun:hover { background: var(--bg-light); border-color: var(--text-muted); }

        .aksi-btn.hapus { color: #dc3545; }
        .aksi-btn.hapus:hover { background: #fff5f5; border-color: #dc3545; }

        .text-kosong {
            text-align: center;
            color: var(--text-muted);
            padding: 30px 10px !important;
        }

        /* ALERT CUSTOM */
        .alert-custom {
            display: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            margin-bottom: 16px;
            align-items: center;
            justify-content: space-between;
        }
        .alert-custom.sukses {
            background: #d1e7dd;
            border: 1px solid #badbcc;
            color: #0f5132;
        }
        .alert-custom.gagal {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            color: #842029;
        }
        .alert-custom.show { display: flex; }
        .alert-close {
            background: none;
            border: none;
            font-size: 15px;
            cursor: pointer;
            color: inherit;
        }

        /* MODAL CUSTOM */
        .modal-content {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .modal-header, .modal-footer {
            border-color: var(--border-color);
            padding: 16px 18px;
        }
        .modal-body { padding: 18px; }
        .modal-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--accent-brown-dark);
        }
        .form-label {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }
        .form-control {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            min-height: 38px;
            font-size: 13px;
            background: var(--bg-light);
        }
        .form-control:focus {
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 3px rgba(140, 109, 88, 0.12);
            background: #fff;
        }
        .btn-pay {
            background: var(--accent-brown);
            border: none;
            color: white;
            border-radius: 8px;
            height: 38px;
            font-size: 13px;
            font-weight: 700;
            padding: 0 16px;
        }
        .btn-pay:hover { background: var(--accent-brown-dark); color: white; }
        .btn-cancel {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            border-radius: 8px;
            height: 38px;
            font-size: 13px;
            font-weight: 600;
            padding: 0 16px;
        }
        .btn-cancel:hover { background: var(--bg-light); color: var(--text-main); }
    </style>
</head>
<body>

<div class="admin-container" id="adminContainer">
    
    <!-- SIDEBAR KIRI (ASLI) -->
    <aside class="pos-sidebar">
        <div>
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img src="../assets/img/logo.jpeg" alt="Logo">
                    <span class="brand-text">GELORA WYNE</span>
                </div>
                <button class="toggle-btn" onclick="toggleSidebar()" title="Buka/Tutup Sidebar">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <div class="sidebar-menu">
                <a href="index.php" title="Katalog Hijab">
                    <i class="bi bi-bag-heart-fill"></i>
                    <span class="menu-label">Katalog Hijab</span>
                </a>
                <a href="produk.php" title="Produk">
                    <i class="bi bi-box-seam-fill"></i>
                    <span class="menu-label">Produk</span>
                </a>
                <a href="member.php" class="active" title="Member">
                    <i class="bi bi-award-fill"></i>
                    <span class="menu-label">Member</span>
                </a>
                <a href="kas.php" title="Kas">
                    <i class="bi bi-wallet2"></i>
                    <span class="menu-label">Kas</span>
                </a>
                <a href="laporan.php" title="Laporan">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span class="menu-label">Laporan</span>
                </a>
                <a href="users.php" title="Pengguna"><i class="bi bi-person-badge-fill"></i><span class="menu-label">Pengguna</span></a>
            </div>
        </div>

        <div>
            <div class="sidebar-menu">
                <a href="../logout.php" title="Keluar" style="color: #DC3545;">
                    <i class="bi bi-box-arrow-left"></i>
                    <span class="menu-label">Keluar</span>
                </a>
            </div>
        </div>
    </aside>


    <!-- KONTEN UTAMA -->
    <main class="admin-main">
        <header class="main-sticky-header">
            <h1 class="page-title">Kelola Member</h1>
            <div class="profile-area">
                <div class="profile-icon">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 12.5px;"><?= htmlspecialchars($_SESSION['nama']); ?></div>
                    <div style="font-size: 10px; color: var(--text-muted);">Supervisor</div>
                </div>
            </div>
        </header>

        <div class="content-body">

            <!-- BAGIAN ATAS LEBIH KECIL / COMPACT -->
            <div class="dashboard-top-grid">
                <div class="welcome-box-new">
                    <h2>Kelola Member &amp; Poin Pelanggan</h2>
                    <p>Pantau data keanggotaan dan perolehan poin secara praktis dalam satu panel.</p>
                </div>
                <div class="stat-mini-new">
                    <div class="stat-box-new">
                        <div class="label">Total Member</div>
                        <div class="nilai"><?= $stat['total_member']; ?></div>
                    </div>
                    <div class="stat-box-new emas">
                        <div class="label">Total Poin</div>
                        <div class="nilai"><?= $stat['total_poin']; ?></div>
                    </div>
                    <div class="stat-box-new">
                        <div class="label">Baru Bulan Ini</div>
                        <div class="nilai"><?= $stat['member_baru']; ?></div>
                    </div>
                </div>
            </div>

            <!-- ALERT -->
            <div id="alertSukses" class="alert-custom sukses">
                <span id="alertSuksesTeks"></span>
                <button type="button" class="alert-close" onclick="tutupAlert('alertSukses')">&times;</button>
            </div>

            <div id="alertGagal" class="alert-custom gagal">
                <span id="alertGagalTeks"></span>
                <button type="button" class="alert-close" onclick="tutupAlert('alertGagal')">&times;</button>
            </div>

            <!-- PANEL UTAMA ISI TABEL & TOOLBAR -->
            <div class="panel">
                <!-- TOOLBAR -->
                <form method="GET" action="member.php" class="toolbar">
                    <div class="toolbar-left">
                        <div class="search-box">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($q_cari); ?>" placeholder="Cari nama atau no. HP..." autocomplete="off">
                        </div>

                        <select name="urut" class="filter-select" onchange="this.form.submit()">
                            <option value="nama" <?= ($q_urut === "nama") ? "selected" : ""; ?>>Urut: Nama A-Z</option>
                            <option value="poin" <?= ($q_urut === "poin") ? "selected" : ""; ?>>Urut: Poin Terbanyak</option>
                            <option value="kunjungan" <?= ($q_urut === "kunjungan") ? "selected" : ""; ?>>Urut: Kunjungan</option>
                            <option value="baru" <?= ($q_urut === "baru") ? "selected" : ""; ?>>Urut: Terbaru</option>
                        </select>
                    </div>

                    <button type="button" class="btn-tambah" onclick="bukaTambah()">
                        <i class="bi bi-plus-circle-fill"></i> Tambah Member
                    </button>
                </form>

                <!-- TABEL MEMBER -->
                <div class="table-wrap">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>No. HP</th>
                                <th>Tgl Daftar</th>
                                <th style="text-align:center;">Kunjungan</th>
                                <th style="text-align:center;">Poin</th>
                                <th style="text-align:center;">Transaksi</th>
                                <th style="text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($q_member) == 0) { ?>
                                <tr>
                                    <td colspan="7" class="text-kosong">
                                        <i class="bi bi-people" style="font-size: 28px; opacity: 0.4; display: block; margin-bottom: 6px;"></i>
                                        Belum ada member terdaftar.
                                    </td>
                                </tr>
                            <?php } else { ?>
                                <?php while ($m = mysqli_fetch_assoc($q_member)) {
                                    $kunjungan = intval($m['jumlah_kunjungan']);
                                    $poin      = intval($m['poin']);
                                    $sisa_kunjungan = $kunjungan % 5;
                                    $progres_persen = ($sisa_kunjungan / 5) * 100;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="nama-member"><?= htmlspecialchars($m['nama_pelanggan']); ?></div>
                                            <?php if (!empty($m['alamat'])) { ?>
                                                <div class="alamat-member" title="<?= htmlspecialchars($m['alamat']); ?>">
                                                    <?= htmlspecialchars($m['alamat']); ?>
                                                </div>
                                            <?php } ?>
                                        </td>
                                        <td class="hp-member"><?= htmlspecialchars($m['no_hp'] ?: '-'); ?></td>
                                        <td class="tgl-daftar"><?= !empty($m['tgl_daftar']) ? date('d/m/Y', strtotime($m['tgl_daftar'])) : '-'; ?></td>
                                        <td style="text-align:center;" class="kunjungan-teks">
                                            <strong><?= $kunjungan; ?>x</strong>
                                            <div class="kunjungan-sub">
                                                <?= ($sisa_kunjungan > 0) ? $sisa_kunjungan . " lagi → poin" : "poin siap!"; ?>
                                            </div>
                                            <div class="progres mx-auto">
                                                <div class="progres-isi" style="width: <?= $progres_persen; ?>%;"></div>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="poin-teks"><?= $poin; ?></span>
                                        </td>
                                        <td style="text-align:center;"><?= intval($m['jml_trx']); ?>x</td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <button type="button" class="aksi-btn poin" title="Atur poin" onclick='bukaPoin(<?= json_encode(["id" => $m['id_pelanggan'], "nama" => $m['nama_pelanggan'], "poin" => $poin]); ?>)'>
                                                <i class="bi bi-award-fill"></i>
                                            </button>
                                            <button type="button" class="aksi-btn edit" title="Edit data" onclick='bukaEdit(<?= json_encode(["id" => $m['id_pelanggan'], "nama" => $m['nama_pelanggan'], "hp" => $m['no_hp'], "alamat" => $m['alamat']]); ?>)'>
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <form method="POST" action="member.php" style="display:inline;" onsubmit="return confirmTurun('<?= htmlspecialchars($m['nama_pelanggan'], ENT_QUOTES); ?>')">
                                                <input type="hidden" name="aksi" value="jadi_umum">
                                                <input type="hidden" name="id_pelanggan" value="<?= $m['id_pelanggan']; ?>">
                                                <button type="submit" class="aksi-btn turun" title="Turunkan jadi pelanggan umum">
                                                    <i class="bi bi-arrow-down-circle"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="member.php" style="display:inline;" onsubmit="return confirmHapus('<?= htmlspecialchars($m['nama_pelanggan'], ENT_QUOTES); ?>', <?= intval($m['jml_trx']); ?>)">
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="id_pelanggan" value="<?= $m['id_pelanggan']; ?>">
                                                <button type="submit" class="aksi-btn hapus" title="Hapus">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
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

<!-- MODAL TAMBAH MEMBER -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="member.php">
                <input type="hidden" name="aksi" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Member Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama member <span class="text-danger">*</span></label>
                        <input type="text" name="nama_pelanggan" class="form-control" placeholder="Contoh: Budi Santoso" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="no_hp" class="form-control" placeholder="Contoh: 0812xxxxxxx" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat member (opsional)"></textarea>
                    </div>
                    <div class="catatan-modal">
                        Tanggal daftar otomatis tercatat hari ini. Poin dimulai dari 0.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-pay">Daftarkan Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT MEMBER -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="member.php">
                <input type="hidden" name="aksi" value="edit">
                <input type="hidden" name="id_pelanggan" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Data Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama member <span class="text-danger">*</span></label>
                        <input type="text" name="nama_pelanggan" id="editNama" class="form-control" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="no_hp" id="editHp" class="form-control" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea name="alamat" id="editAlamat" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="catatan-modal">
                        Untuk mengubah poin, gunakan tombol <strong>medali</strong> di tabel.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-pay">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ATUR POIN -->
<div class="modal fade" id="modalPoin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="member.php">
                <input type="hidden" name="aksi" value="atur_poin">
                <input type="hidden" name="id_pelanggan" id="poinId">
                <div class="modal-header">
                    <h5 class="modal-title">Atur Poin Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="poin-sekarang mb-2" id="poinNama" style="font-size: 13px;"></div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah poin <span class="text-danger">*</span></label>
                        <input type="number" name="poin" id="poinNilai" class="form-control" min="0" required>
                    </div>
                    <div class="catatan-modal">
                        Perbarui jumlah poin akumulasi member secara manual jika diperlukan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-pay">Simpan Poin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    const container = document.getElementById('adminContainer');
    container.classList.toggle('sidebar-open');
}

function bukaTambah() {
    new bootstrap.Modal(document.getElementById("modalTambah")).show();
}

function bukaEdit(data) {
    document.getElementById("editId").value = data.id;
    document.getElementById("editNama").value = data.nama;
    document.getElementById("editHp").value = data.hp;
    document.getElementById("editAlamat").value = data.alamat;
    new bootstrap.Modal(document.getElementById("modalEdit")).show();
}

function bukaPoin(data) {
    document.getElementById("poinId").value = data.id;
    document.getElementById("poinNilai").value = data.poin;
    document.getElementById("poinNama").innerHTML = "<strong>" + data.nama + "</strong> — poin saat ini: <strong>" + data.poin + "</strong>";
    new bootstrap.Modal(document.getElementById("modalPoin")).show();
}

function confirmTurun(nama) {
    return confirm('Turunkan "' + nama + '" dari Member menjadi Pelanggan Umum?\n\nDatanya tidak dihapus, tapi tidak lagi terhitung sebagai member.\nBisa dijadikan member lagi lewat menu Pelanggan.');
}

function confirmHapus(nama, jmlTrx) {
    if (jmlTrx > 0) {
        alert('"' + nama + '" memiliki ' + jmlTrx + ' riwayat transaksi.\n\nMember yang pernah bertransaksi tidak bisa dihapus.\nGunakan tombol turunkan untuk menonaktifkan keanggotaannya.');
        return false;
    }
    return confirm('Hapus data member "' + nama + '"?');
}

const pesanSukses = {
    "tambah": "Member baru berhasil didaftarkan.",
    "edit": "Data member berhasil diperbarui.",
    "poin": "Poin member berhasil diatur.",
    "turun": "Member berhasil diturunkan menjadi pelanggan umum.",
    "hapus": "Data member berhasil dihapus."
};

const pesanGagal = {
    "kosong": "Gagal: nama member wajib diisi.",
    "terpakai": "Gagal: member memiliki riwayat transaksi sehingga tidak bisa dihapus."
};

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

function tutupAlert(id) {
    document.getElementById(id).classList.remove("show");
}
</script>
</body>
</html>