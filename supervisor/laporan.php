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
   ⚙️ KONFIGURASI
========================= */
$TABEL_USER = "users";     
$KOLOM_NAMA = "nama";      

function rp($n) {
    return "Rp " . number_format($n, 0, ',', '.');
}

/* =========================
   PROSES HAPUS TERPILIH
========================= */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_pilih') {
    if (!empty($_POST['pilih_trx']) && is_array($_POST['pilih_trx'])) {
        $ids_terpilih = [];
        foreach ($_POST['pilih_trx'] as $id_p) {
            $ids_terpilih[] = intval($id_p);
        }
        
        if (count($ids_terpilih) > 0) {
            $id_list = implode(',', $ids_terpilih);
            
            mysqli_query($koneksi, "DELETE FROM detailpenjualan WHERE id_penjualan IN ($id_list)");
            mysqli_query($koneksi, "DELETE FROM penjualan WHERE id_penjualan IN ($id_list)");
            
            $_SESSION['pesan_sukses'] = "Riwayat transaksi yang dipilih berhasil dihapus.";
        }
    } else {
        $_SESSION['pesan_error'] = "Tidak ada transaksi yang dipilih untuk dihapus.";
    }
    
    $redirect_url = "laporan.php";
    if (!empty($_POST['tgl_awal']) && !empty($_POST['tgl_akhir'])) {
        $redirect_url .= "?tgl_awal=" . urlencode($_POST['tgl_awal']) . "&tgl_akhir=" . urlencode($_POST['tgl_akhir']);
    }
    header("Location: " . $redirect_url);
    exit;
}

/* =========================
   FILTER TANGGAL
========================= */
$tgl_awal  = trim($_GET['tgl_awal']  ?? date('Y-m-d'));
$tgl_akhir = trim($_GET['tgl_akhir'] ?? date('Y-m-d'));

if (!strtotime($tgl_awal))  $tgl_awal  = date('Y-m-d');
if (!strtotime($tgl_akhir)) $tgl_akhir = date('Y-m-d');

if (strtotime($tgl_awal) > strtotime($tgl_akhir)) {
    $temp      = $tgl_awal;
    $tgl_awal  = $tgl_akhir;
    $tgl_akhir = $temp;
}

$awal_safe  = mysqli_real_escape_string($koneksi, $tgl_awal);
$akhir_safe = mysqli_real_escape_string($koneksi, $tgl_akhir);

$batas_dari   = $awal_safe  . " 00:00:00";
$batas_sampai = $akhir_safe . " 23:59:59";
$syarat_tanggal = "p.tanggal_penjualan >= '$batas_dari' AND p.tanggal_penjualan <= '$batas_sampai'";
$tampil_periode = date("d-m-Y", strtotime($tgl_awal)) . " s/d " . date("d-m-Y", strtotime($tgl_akhir));

/* =========================
   RINGKASAN PENJUALAN
========================= */
$q_ringkasan = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS jml_trx,
        COALESCE(SUM(p.total_harga), 0) AS omset,
        COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'Tunai' THEN p.total_harga ELSE 0 END), 0) AS tunai,
        COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'QRIS'  THEN p.total_harga ELSE 0 END), 0) AS qris
    FROM penjualan p
    WHERE $syarat_tanggal
");
$ringkasan = mysqli_fetch_assoc($q_ringkasan);
$jml_trx  = intval($ringkasan['jml_trx']);
$omset    = intval($ringkasan['omset']);

$q_item = mysqli_query($koneksi, "
    SELECT IFNULL(SUM(dp.jumlah), 0) AS total_barang
    FROM detailpenjualan dp
    JOIN penjualan p ON p.id_penjualan = dp.id_penjualan
    WHERE $syarat_tanggal
");
$total_barang = mysqli_fetch_assoc($q_item)['total_barang'];

$q_mut = mysqli_query($koneksi, "
    SELECT
        COALESCE(SUM(CASE WHEN jenis = 'Keluar' THEN jumlah ELSE 0 END), 0) AS keluar,
        COALESCE(SUM(CASE WHEN jenis = 'Masuk'  THEN jumlah ELSE 0 END), 0) AS masuk
    FROM kas_mutasi
    WHERE waktu >= '$batas_dari' AND waktu <= '$batas_sampai'
");
$mutasi = mysqli_fetch_assoc($q_mut);

$q_metode = mysqli_query($koneksi, "
    SELECT
        metode_pembayaran,
        COUNT(*) AS jml,
        IFNULL(SUM(total_harga), 0) AS omzet
    FROM penjualan p
    WHERE $syarat_tanggal
    GROUP BY metode_pembayaran
");
$metode_data = [];
while ($m = mysqli_fetch_assoc($q_metode)) {
    $metode_data[$m['metode_pembayaran']] = $m;
}

/* =========================
   DATA RIWAYAT TRANSAKSI LENGKAP
========================= */
$q_riwayat_trx = mysqli_query($koneksi, "
    SELECT 
        p.*, 
        pl.nama_pelanggan, 
        u.$KOLOM_NAMA AS nama_kasir,
        GROUP_CONCAT(pr.nama_produk SEPARATOR '<br>') AS daftar_barang,
        GROUP_CONCAT(dp.jumlah SEPARATOR '<br>') AS daftar_jumlah
    FROM penjualan p
    LEFT JOIN pelanggan pl ON pl.id_pelanggan = p.id_pelanggan
    LEFT JOIN $TABEL_USER u ON u.id_user = p.id_user
    LEFT JOIN detailpenjualan dp ON dp.id_penjualan = p.id_penjualan
    LEFT JOIN produk pr ON pr.id_produk = dp.id_produk
    WHERE $syarat_tanggal
    GROUP BY p.id_penjualan
    ORDER BY p.tanggal_penjualan DESC
    LIMIT 200
");

$q_barang = mysqli_query($koneksi, "
    SELECT
        DATE(p.tanggal_penjualan) AS tanggal,
        pr.nama_produk,
        pr.kode_produk,
        SUM(dp.jumlah)   AS qty,
        SUM(dp.subtotal) AS total
    FROM detailpenjualan dp
    JOIN penjualan p ON p.id_penjualan = dp.id_penjualan
    JOIN produk pr   ON pr.id_produk   = dp.id_produk
    WHERE $syarat_tanggal
    GROUP BY DATE(p.tanggal_penjualan), pr.id_produk
    ORDER BY tanggal DESC, total DESC
");

$q_kasir = mysqli_query($koneksi, "
    SELECT u.id_user, u.$KOLOM_NAMA AS nama_kasir, COUNT(p.id_penjualan) AS trx, COALESCE(SUM(p.total_harga), 0) AS omset
    FROM penjualan p
    LEFT JOIN $TABEL_USER u ON u.id_user = p.id_user
    WHERE $syarat_tanggal
    GROUP BY p.id_user, u.id_user, u.$KOLOM_NAMA
    ORDER BY omset DESC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan & Riwayat Transaksi - GELORA WYNE[cite: 1]</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-light: #f8f9fa;
            --bg-card: #ffffff;
            --bg-hover: #f1f3f5;
            --border-color: #cbd5e1;
            --text-main: #1e293b;
            --text-muted: #64748b;
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

        .pos-container {
            display: grid;
            grid-template-columns: 80px 1fr;
            min-height: 100vh;
            width: 100vw;
            transition: grid-template-columns 0.3s ease;
        }

        .pos-container.sidebar-open {
            grid-template-columns: 280px 1fr;
        }

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
            overflow-y: auto;
            z-index: 1050;
            white-space: nowrap;
        }

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

        .pos-container.sidebar-open .brand-text {
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

        .sidebar-menu a i { font-size: 18px; flex-shrink: 0; width: 20px; text-align: center; }

        .menu-label {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
        }

        .pos-container.sidebar-open .menu-label {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-menu a:hover { background: var(--accent-brown-light); color: var(--accent-brown-dark); }
        .sidebar-menu a.active { background: var(--accent-brown); color: #ffffff; }

        .pos-main {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: var(--bg-light);
            padding: 24px 32px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title h2 { font-size: 18px; font-weight: 800; color: var(--text-main); margin: 0 0 4px 0; }
        .page-title p { font-size: 12.5px; color: var(--text-muted); margin: 0; }

        .filter-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .filter-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-form input[type="date"] {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            height: 34px;
            font-size: 12.5px;
            padding: 0 10px;
            outline: none;
            background: var(--bg-card);
            color: var(--text-main);
        }
        .btn-filter {
            background: var(--accent-brown);
            border: none;
            color: white;
            border-radius: 6px;
            height: 34px;
            font-size: 12.5px;
            font-weight: 600;
            padding: 0 14px;
            cursor: pointer;
        }
        .btn-filter:hover { background: var(--accent-brown-dark); }
        .btn-cetak {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            border-radius: 6px;
            height: 34px;
            font-size: 12.5px;
            font-weight: 600;
            padding: 0 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-cetak:hover { background: var(--bg-hover); border-color: var(--accent-brown); }

        /* KARTU RINGKASAN RINGKAS */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 12px;
        }
        .metric-card .label {
            font-size: 10.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
        }
        .metric-card .value {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--text-main);
            font-family: 'JetBrains Mono', monospace;
            margin-top: 2px;
        }

        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .panel-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-custom th {
            text-align: left;
            font-size: 10.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 8px;
            border-bottom: 2px solid var(--border-color);
            font-weight: 700;
            background: var(--bg-light);
        }
        .table-custom td {
            padding: 8px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
        }
        .table-custom tr:hover td { background: var(--bg-hover); }

        .btn-detail {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            border: 1px solid var(--accent-brown);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .btn-detail:hover { background: var(--accent-brown); color: white; }

        .btn-hapus-terpilih {
            background: #dc3545;
            border: none;
            color: white;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-hapus-terpilih:hover { background: #bb2d3b; }

        .duo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 992px) {
            .duo-grid { grid-template-columns: 1fr; }
        }

        /* =========================================
           KOP & FORMAT CETAK HEMAT TEMPAT (PRINT)
           ========================================= */
        .print-header { display: none; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            .pos-sidebar, .filter-panel, .btn-cetak, .btn-detail, .btn-hapus-terpilih, .col-aksi-th, .col-aksi-td, .page-header { 
                display: none !important; 
            }
            body { 
                background: white !important; 
                color: #000 !important;
                font-size: 9pt;
            }
            .pos-container { display: block !important; }
            .pos-main { padding: 0 !important; background: white !important; width: 100% !important; }

            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 12px;
                border-bottom: 1.5px solid #000;
                padding-bottom: 6px;
            }
            .print-header h1 {
                font-size: 14pt;
                font-weight: bold;
                margin: 0;
                text-transform: uppercase;
            }
            .print-header p {
                font-size: 8.5pt;
                margin: 2px 0 0 0;
            }

            .panel {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin-bottom: 10px !important;
            }
            .metrics-grid {
                display: grid !important;
                grid-template-columns: repeat(6, 1fr) !important;
                gap: 5px !important;
                margin-bottom: 10px !important;
            }
            .metric-card {
                border: 1px solid #999 !important;
                background: #fff !important;
                padding: 6px !important;
            }
            .metric-card .label { font-size: 7.5pt !important; color: #333 !important; }
            .metric-card .value { font-size: 9pt !important; }

            .table-custom {
                font-size: 8.5pt !important;
                border: 1px solid #999 !important;
            }
            .table-custom th {
                background: #e2e2e2 !important;
                color: #000 !important;
                border-bottom: 1.5px solid #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                padding: 4px 6px !important;
            }
            .table-custom td, .table-custom th {
                border: 1px solid #bbb !important;
                padding: 4px 6px !important;
            }
            .duo-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
            }
        }
    </style>
</head>
<body>

<div class="pos-container" id="posContainer">
    
    <aside class="pos-sidebar">
        <div>
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img src="../assets/img/logo.jpeg" alt="Logo">
                    <span class="brand-text">GELORA WYNE[cite: 1]</span>
                </div>
                <button class="toggle-btn" onclick="toggleSidebar()" title="Buka/Tutup Sidebar">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <div class="sidebar-menu">
                <a href="index.php" title="Katalog Hijab"><i class="bi bi-bag-heart-fill"></i><span class="menu-label">Katalog Hijab</span></a>
                <a href="produk.php" title="Produk"><i class="bi bi-box-seam-fill"></i><span class="menu-label">Produk</span></a>
                <a href="member.php" title="Member"><i class="bi bi-award-fill"></i><span class="menu-label">Member</span></a>
                <a href="kas.php" title="Kas"><i class="bi bi-wallet2"></i><span class="menu-label">Kas</span></a>
                <a href="laporan.php" class="active" title="Laporan"><i class="bi bi-bar-chart-line-fill"></i><span class="menu-label">Laporan</span></a>
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

    <main class="pos-main">
        <!-- KOP LAPORAN KHUSUS CETAK -->
        <div class="print-header">
            <h1>GELORA WYNE[cite: 1]</h1>
            <p>Laporan Keuangan & Riwayat Transaksi</p>
            <p>Periode: <strong><?= $tampil_periode; ?></strong></p>
        </div>

        <div class="page-header">
            <div class="page-title">
                <h2>Analisis Laporan Penjualan</h2>
                <p>Periode aktif: <strong style="color: var(--accent-brown);"><?= $tampil_periode; ?></strong></p>
            </div>
        </div>

        <?php if (isset($_SESSION['pesan_sukses'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
                <?= $_SESSION['pesan_sukses']; unset($_SESSION['pesan_sukses']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['pesan_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                <?= $_SESSION['pesan_error']; unset($_SESSION['pesan_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="filter-panel">
            <form method="GET" action="laporan.php" class="filter-form">
                <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal); ?>" required>
                <span style="color: var(--text-muted);">s/d</span>
                <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir); ?>" required>
                <button type="submit" class="btn-filter">Terapkan</button>
            </form>

            <button type="button" class="btn-cetak" onclick="window.print()">
                <i class="bi bi-printer-fill"></i> Cetak Laporan
            </button>
        </div>

        <!-- METRIK RINGKAS -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="label">Total Pendapatan</div>
                <div class="value"><?= rp($omset); ?></div>
            </div>
            <div class="metric-card">
                <div class="label">Total Transaksi</div>
                <div class="value"><?= number_format($jml_trx, 0, ',', '.'); ?> <span style="font-size:10px; font-weight:normal; color:var(--text-muted);">Nota</span></div>
            </div>
            <div class="metric-card">
                <div class="label">Barang Keluar</div>
                <div class="value"><?= number_format($total_barang, 0, ',', '.'); ?> <span style="font-size:10px; font-weight:normal; color:var(--text-muted);">Pcs</span></div>
            </div>
            <div class="metric-card">
                <div class="label">Tunai</div>
                <div class="value"><?= rp($ringkasan['tunai']); ?></div>
            </div>
            <div class="metric-card">
                <div class="label">QRIS</div>
                <div class="value"><?= rp($ringkasan['qris']); ?></div>
            </div>
            <div class="metric-card">
                <div class="label">Tunai Laci</div>
                <div class="value" style="color: #198754;"><?= rp($ringkasan['tunai'] - intval($mutasi['keluar']) + intval($mutasi['masuk'])); ?></div>
            </div>
        </div>

        <div class="duo-grid">
            <div class="panel">
                <div class="panel-title">
                    <span>Metode Pembayaran</span>
                </div>
                <?php if (count($metode_data) === 0) : ?>
                    <div style="color: var(--text-muted); text-align: center; padding: 10px;">Tidak ada data</div>
                <?php else : ?>
                    <?php foreach ($metode_data as $m) : ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed var(--border-color);">
                            <div>
                                <span style="background: var(--accent-brown-light); color: var(--accent-brown-dark); padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 10.5px;"><?= htmlspecialchars($m['metode_pembayaran']); ?></span>
                                <span style="font-size: 11px; color: var(--text-muted); margin-left: 6px;"><?= $m['jml']; ?> Transaksi</span>
                            </div>
                            <strong style="font-size: 13px; font-family:'JetBrains Mono', monospace;"><?= rp($m['omzet']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-title">
                    <span>Kinerja Kasir</span>
                </div>
                <?php if (mysqli_num_rows($q_kasir) == 0): ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 10px;">Belum ada transaksi kasir.</div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Kasir</th>
                                    <th style="text-align:center;">Trx</th>
                                    <th style="text-align:right;">Omset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($u = mysqli_fetch_assoc($q_kasir)): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= htmlspecialchars($u['nama_kasir'] ?? 'Tanpa Nama'); ?></td>
                                        <td style="text-align:center; font-family:'JetBrains Mono', monospace;"><?= intval($u['trx']); ?>x</td>
                                        <td style="text-align:right; font-weight:700; font-family:'JetBrains Mono', monospace; color:var(--accent-brown-dark);"><?= rp($u['omset']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL BARANG KELUAR & RIWAYAT TRANSAKSI -->
        <div class="panel">
            <div class="panel-title">
                <span><i class="bi bi-box-seam"></i> Ringkasan Barang Keluar Harian</span>
            </div>
            <?php if (mysqli_num_rows($q_barang) === 0) : ?>
                <div style="text-align: center; color: var(--text-muted); padding: 15px;">Tidak ada data barang keluar.</div>
            <?php else : ?>
                <div style="overflow-x: auto; max-height: 180px; overflow-y: auto;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Barang</th>
                                <th style="text-align: center;">Qty</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tgl_sebelumnya = "";
                            while ($b = mysqli_fetch_assoc($q_barang)) :
                                $tampil_tgl = $b['tanggal'] !== $tgl_sebelumnya ? date("d-m-Y", strtotime($b['tanggal'])) : "";
                                $tgl_sebelumnya = $b['tanggal'];
                            ?>
                                <tr>
                                    <td style="color: var(--text-muted); font-size: 11px;"><?= $tampil_tgl; ?></td>
                                    <td><?= htmlspecialchars($b['nama_produk']); ?></td>
                                    <td style="text-align: center; font-weight: 700;"><?= $b['qty']; ?></td>
                                    <td style="text-align: right; font-weight: 700; font-family:'JetBrains Mono', monospace;"><?= rp($b['total']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-title">
                <span><i class="bi bi-receipt-cutoff"></i> Rincian Riwayat Transaksi (Hingga 200 Transaksi)</span>
                <?php if (mysqli_num_rows($q_riwayat_trx) > 0): ?>
                    <button type="submit" form="formHapusRiwayat" class="btn-hapus-terpilih" onclick="return konfirmasiHapus();">
                        <i class="bi bi-trash3-fill"></i> Hapus Terpilih
                    </button>
                <?php endif; ?>
            </div>
            
            <form id="formHapusRiwayat" method="POST" action="laporan.php">
                <input type="hidden" name="aksi" value="hapus_pilih">
                <input type="hidden" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal); ?>">
                <input type="hidden" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir); ?>">

                <div style="overflow-x: auto;">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 30px; text-align: center;" class="col-aksi-th">
                                    <input type="checkbox" id="pilihSemua" onclick="togglePilihSemua(this)" title="Pilih Semua">
                                </th>
                                <th>No</th>
                                <th>Kode / Waktu</th>
                                <th>Pelanggan</th>
                                <th>Kasir</th>
                                <th>Barang</th>
                                <th style="text-align:center;">Jumlah</th>
                                <th>Metode</th>
                                <th style="text-align:right;">Total</th>
                                <th style="text-align:center;" class="col-aksi-th">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($q_riwayat_trx) == 0): ?>
                                <tr><td colspan="10" class="text-center text-muted py-3">Belum ada transaksi pada periode ini.</td></tr>
                            <?php else: ?>
                                <?php $no = 1; while ($trx = mysqli_fetch_assoc($q_riwayat_trx)): ?>
                                    <tr>
                                        <td style="text-align: center;" class="col-aksi-td">
                                            <input type="checkbox" name="pilih_trx[]" value="<?= $trx['id_penjualan']; ?>" class="item-checkbox">
                                        </td>
                                        <td style="color: var(--text-muted);"><?= $no++; ?></td>
                                        <td>
                                            <b style="color: var(--accent-brown); font-family:'JetBrains Mono', monospace;"><?= htmlspecialchars($trx['kode_transaksi'] ?? $trx['id_penjualan']); ?></b>
                                            <div style="font-size:10.5px; color:var(--text-muted);"><?= date('d-m-Y H:i', strtotime($trx['tanggal_penjualan'])); ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($trx['nama_pelanggan'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($trx['nama_kasir'] ?? '-'); ?></td>
                                        <td><?= $trx['daftar_barang'] ?? '-'; ?></td>
                                        <td style="text-align:center; font-family:'JetBrains Mono', monospace; font-weight:700;"><?= $trx['daftar_jumlah'] ?? '-'; ?></td>
                                        <td>
                                            <span style="display:inline-block; padding: 2px 6px; border-radius: 4px; font-weight:700; font-size:10.5px; background: var(--accent-brown-light); color: var(--accent-brown-dark);">
                                                <?= htmlspecialchars($trx['metode_pembayaran']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right; font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--accent-brown-dark);">
                                            <?= rp($trx['total_harga']); ?>
                                        </td>
                                        <td style="text-align:center;" class="col-aksi-td">
                                            <a href="rincian.php?id_penjualan=<?= $trx['id_penjualan']; ?>" class="btn-detail">
                                                <i class="bi bi-eye-fill"></i> Rincian
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>

    </main>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('posContainer').classList.toggle('sidebar-open');
}

function togglePilihSemua(master) {
    let checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = master.checked;
    });
}

function konfirmasiHapus() {
    let checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Pilih setidaknya satu transaksi yang ingin dihapus!');
        return false;
    }
    return confirm('Yakin ingin menghapus ' + checkboxes.length + ' transaksi yang dipilih?');
}
</script>
</body>
</html>