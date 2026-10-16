<?php
session_start();
include "../koneksi.php";

/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['id_user']) || $_SESSION['level'] != 'Kasir I') {
    header("Location: ../login.php");
    exit;
}

/* =========================
   CEK KAS
========================= */
$cek_kas = mysqli_query($koneksi, "
    SELECT id_kas
    FROM kas
    WHERE id_user = '" . intval($_SESSION['id_user']) . "'
    AND status = 'buka'
    LIMIT 1
");
$kas_aktif = mysqli_fetch_assoc($cek_kas);

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

$where = " AND DATE(pj.tanggal_penjualan) BETWEEN '$awal_safe' AND '$akhir_safe' ";
$tampil_periode = date("d-m-Y", strtotime($tgl_awal)) . " s/d " . date("d-m-Y", strtotime($tgl_akhir));

/* =========================
   RIWAYAT TRANSAKSI
========================= */
$q_transaksi = mysqli_query($koneksi, "
    SELECT
        pj.id_penjualan,
        pj.kode_transaksi,
        pj.tanggal_penjualan,
        pj.total_harga,
        pj.bayar,
        pj.kembalian,
        pj.metode_pembayaran,
        pl.nama_pelanggan,
        u.nama AS nama_kasir
    FROM penjualan pj
    LEFT JOIN pelanggan pl ON pl.id_pelanggan = pj.id_pelanggan
    LEFT JOIN users u      ON u.id_user = pj.id_user
    WHERE 1=1 $where
    ORDER BY pj.tanggal_penjualan DESC
    LIMIT 200
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - GELORA WYNE</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --bg-canvas: #F4F5F7;
            --card-bg: #FFFFFF;
            --text-dark: #121212;
            --text-muted: #6C757D;
            --border-color: #E2E4E8;
            --sidebar-bg: #FFFFFF;
            --accent-brown: #8C6D58;
            --accent-brown-dark: #5A4334;
            --accent-brown-light: #F4EFEB;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg-canvas);
            color: var(--text-dark);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            overflow-x: hidden;
        }

        /* SIDEBAR KIRI */
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 200px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            padding: 20px 14px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 8px 24px 8px;
        }

        .brand img {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-brown);
        }

        .brand-text {
            font-size: 15px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .menu { flex: 1; }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            text-decoration: none;
            padding: 11px 14px;
            border-radius: 12px;
            margin-bottom: 6px;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .menu a:hover {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
        }

        .menu a.active {
            background: var(--accent-brown);
            color: #FFFFFF;
        }

        .cashier-info-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            padding: 10px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar-circle {
            width: 32px; height: 32px;
            background: var(--text-dark);
            color: white;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 12px;
        }

        .logout-link {
            color: #DC3545;
            text-decoration: none;
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 13px;
            border-radius: 10px;
        }

        .logout-link:hover { background: #FFF0F0; }

        /* MAIN WRAPPER */
        .main-wrapper {
            margin-left: 200px;
            min-height: 100vh;
            padding: 24px 28px;
        }

        /* PANEL KONTROL FILTER TANGGAL */
        .control-panel {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .filter-form-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .filter-form-group input[type="date"] {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
        }

        .btn-action-primary {
            background: var(--accent-brown);
            color: white;
            border: none;
            padding: 9px 20px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-action-primary:hover {
            background: var(--accent-brown-dark);
        }

        .card-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
        }

        .card-box-title {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
            color: var(--text-dark);
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            text-align: left;
            padding: 12px 16px;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
        }

        .modern-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
        }

        .modern-table tr:hover td {
            background: var(--bg-canvas);
        }

        .badge-custom {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            padding: 5px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 11px;
        }

        .btn-view-detail {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-view-detail:hover {
            background: var(--accent-brown);
            color: white;
            border-color: var(--accent-brown);
        }

        @media (max-width: 1024px) {
            .main-wrapper { margin-left: 0; padding: 16px; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>

<!-- SIDEBAR KIRI -->
<aside class="sidebar">
    <div class="brand">
        <img src="../assets/img/logo.jpeg" alt="Logo">
        <span class="brand-text">GELORA WYNE</span>
    </div>

    <div class="menu">
        <a href="index.php">
            <i class="bi bi-bag-heart-fill"></i>
            <span>Katalog Hijab</span>
        </a>
        <a href="pelanggan.php">
            <i class="bi bi-people"></i>
            <span>Pelanggan</span>
        </a>
        <a href="laporan.php" class="active">
            <i class="bi bi-bar-chart-line"></i>
            <span>Riwayat Transaksi</span>
        </a>

        <?php if ($kas_aktif) : ?>
            <a href="tutup_kas.php">
                <i class="bi bi-lock"></i>
                <span>Tutup Kas</span>
            </a>
        <?php else : ?>
            <a href="buka_kas.php">
                <i class="bi bi-unlock"></i>
                <span>Buka Kas</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="cashier-info-card">
        <div class="avatar-circle">
            <?php echo strtoupper(substr($_SESSION['nama'], 0, 1)); ?>
        </div>
        <div>
            <div style="font-size: 12px; font-weight: 700;"><?php echo htmlspecialchars($_SESSION['nama']); ?></div>
            <div style="font-size: 10px; color: var(--text-muted);">Kasir Hijab</div>
        </div>
    </div>

    <a href="../logout.php" class="logout-link">
        <i class="bi bi-box-arrow-left"></i>
        <span>Keluar</span>
    </a>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-wrapper">

    <!-- PANEL KONTROL FILTER TANGGAL -->
    <div class="control-panel">
        <div>
            <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Riwayat Pesanan / Transaksi</h2>
            <p style="margin: 2px 0 0 0; color: var(--text-muted); font-size: 12.5px;">Periode aktif: <strong style="color: var(--accent-brown);"><?= $tampil_periode; ?></strong></p>
        </div>

        <div>
            <form method="GET" action="laporan.php" class="filter-form-group">
                <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal); ?>" required>
                <span style="color: var(--text-muted);">s/d</span>
                <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir); ?>" required>
                <button type="submit" class="btn-action-primary">Terapkan</button>
            </form>
        </div>
    </div>

    <!-- RIWAYAT TRANSAKSI TERAKHIR -->
    <div class="card-box">
        <div class="card-box-title">
            <span>Daftar Transaksi</span>
            <span style="font-size: 12px; color: var(--text-muted); font-weight: normal;">Menampilkan hingga 200 transaksi</span>
        </div>

        <?php if (mysqli_num_rows($q_transaksi) === 0) : ?>
            <div style="text-align: center; color: var(--text-muted); padding: 40px;">Belum ada riwayat transaksi pada periode ini.</div>
        <?php else : ?>
            <div style="overflow-x: auto;">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Transaksi</th>
                            <th>Tanggal & Waktu</th>
                            <th>Pelanggan</th>
                            <th>Kasir</th>
                            <th>Metode</th>
                            <th style="text-align: right;">Total Harga</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($t = mysqli_fetch_assoc($q_transaksi)) : ?>
                            <tr>
                                <td style="color: var(--text-muted);"><?= $no++; ?></td>
                                <td><b style="color: var(--accent-brown);"><?= htmlspecialchars($t['kode_transaksi']); ?></b></td>
                                <td><?= date("d-m-Y H:i", strtotime($t['tanggal_penjualan'])); ?></td>
                                <td><?= htmlspecialchars($t['nama_pelanggan'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($t['nama_kasir'] ?? '-'); ?></td>
                                <td><span class="badge-custom"><?= htmlspecialchars($t['metode_pembayaran']); ?></span></td>
                                <td style="text-align: right; font-weight: 700;">Rp <?= number_format($t['total_harga'], 0, ',', '.'); ?></td>
                                <td style="text-align: center;">
                                    <a href="rincian.php?id_penjualan=<?= $t['id_penjualan']; ?>" class="btn-view-detail">Lihat Rincian</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>