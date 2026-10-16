<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['level'] != 'Kasir I') {
    header("Location: ../login.php");
    exit;
}

include "../koneksi.php";

/* =========================
   AMBIL DATA PENJUALAN
========================= */
$id_penjualan  = intval($_GET['id_penjualan'] ?? 0);
$status_sukses = ($_GET['status'] ?? '') === 'sukses';

if ($id_penjualan <= 0) {
    header("Location: index.php");
    exit;
}

/* Header: tabel penjualan + pelanggan + users */
$q_header = mysqli_query($koneksi, "
    SELECT
        pj.*,
        pl.nama_pelanggan,
        pl.alamat,
        pl.no_hp,
        u.nama AS nama_kasir
    FROM penjualan pj
    LEFT JOIN pelanggan pl ON pl.id_pelanggan = pj.id_pelanggan
    LEFT JOIN users u      ON u.id_user = pj.id_user
    WHERE pj.id_penjualan = '$id_penjualan'
");

$header = mysqli_fetch_assoc($q_header);

if (!$header) {
    header("Location: index.php");
    exit;
}

/* Detail: tabel detailpenjualan */
$q_detail = mysqli_query($koneksi, "
    SELECT
        dp.jumlah,
        dp.harga_satuan,
        dp.subtotal,
        pr.nama_produk,
        pr.kode_produk
    FROM detailpenjualan dp
    JOIN produk pr ON pr.id_produk = dp.id_produk
    WHERE dp.id_penjualan = '$id_penjualan'
    ORDER BY dp.id_detail ASC
");

$no_invoice = $header['kode_transaksi'];
$total_item = 0;

// Deteksi halaman asal untuk tombol kembali
$redirect_back = "index.php";
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $redirect_back = $_SERVER['HTTP_REFERER'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rincian <?= htmlspecialchars($no_invoice); ?> — GELORA WYNE</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-body: #F4F1EA;
            --surface-card: #FFFFFF;
            --brand-dark: #231F20;
            --brand-brown: #7A5C4A;
            --brand-brown-light: #F0EADF;
            --brand-accent: #C59B7D;
            --text-main: #2C2A29;
            --text-muted: #78716C;
            --border-soft: #E7E0D6;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            font-size: 13px;
        }

        .navbar-pos {
            background: var(--brand-dark);
            color: #FFFFFF;
            padding: 10px 24px;
            border-bottom: 2px solid var(--brand-accent);
        }

        .navbar-brand-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #F7F4F0;
        }

        .navbar-info {
            font-size: 12.5px;
            color: #D6CEC7;
        }

        .main-wrapper {
            max-width: 1280px;
            margin: 18px auto;
            padding: 0 20px;
        }

        .card-modern {
            background: var(--surface-card);
            border-radius: 12px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            padding: 16px 18px;
            margin-bottom: 16px;
        }

        .alert-modern-success {
            background: #EAF5ED;
            border: 1px solid #BFE3C9;
            color: #1E6B37;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-soft);
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .receipt-title {
            font-size: 17px;
            font-weight: 700;
            margin: 0;
            color: var(--brand-dark);
        }

        .invoice-badge {
            background: var(--brand-brown);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 6px;
            letter-spacing: 0.5px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--brand-dark);
            margin-bottom: 12px;
        }

        .section-title i {
            color: var(--brand-brown);
            font-size: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px 16px;
        }

        .info-label {
            font-size: 10.5px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-main);
        }

        .badge-status {
            background: #EAF5ED;
            color: #1E6B37;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid #BFE3C9;
        }

        .badge-metode {
            background: var(--brand-brown-light);
            color: var(--brand-brown);
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid var(--border-soft);
        }

        .table-container {
            border: 1px solid var(--border-soft);
            border-radius: 8px;
            overflow: hidden;
            max-height: 280px;
            overflow-y: auto;
        }

        .table-pos {
            width: 100%;
            border-collapse: collapse;
        }

        .table-pos th {
            background: var(--brand-brown-light);
            color: var(--brand-dark);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 8px 12px;
            border-bottom: 1px solid var(--border-soft);
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .table-pos td {
            padding: 8px 12px;
            border-bottom: 1px solid #F3EFEA;
            font-size: 12.5px;
        }

        .table-pos tr:last-child td {
            border-bottom: none;
        }

        .qty-badge {
            background: #FAFAFA;
            border: 1px solid var(--border-soft);
            color: var(--text-main);
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .board-total {
            background: linear-gradient(135deg, #2B2623 0%, #1A1715 100%);
            border-radius: 10px;
            padding: 14px;
            color: #FFF;
            border: 1px solid #443C37;
            margin-bottom: 14px;
            text-align: center;
        }

        .board-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #E5C378;
            letter-spacing: 0.5px;
        }

        .board-val {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
            font-size: 26px;
            color: #E5C378;
            margin-top: 2px;
        }

        .ringkasan-box {
            background: var(--brand-brown-light);
            border-radius: 8px;
            padding: 12px 14px;
            border: 1px solid var(--border-soft);
            margin-bottom: 16px;
        }

        .ringkasan-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .ringkasan-row strong {
            color: var(--text-main);
        }

        .btn-modern {
            font-size: 12.5px;
            font-weight: 600;
            padding: 9px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            text-decoration: none;
            width: 100%;
        }

        .btn-brown {
            background-color: var(--brand-brown);
            color: #FFFFFF;
        }

        .btn-brown:hover {
            background-color: #634839;
            color: #FFFFFF;
        }

        .btn-dark-modern {
            background-color: var(--brand-dark);
            color: #FFFFFF;
        }

        .btn-dark-modern:hover {
            background-color: #000000;
            color: #FFFFFF;
        }

        .sticky-right {
            position: sticky;
            top: 18px;
        }

        @media (max-width: 991px) {
            .info-grid { grid-template-columns: repeat(2, 1fr); }
            .sticky-right { position: static; }
        }
    </style>
</head>

<body>

<!-- NAVBAR HEADER -->
<div class="navbar-pos d-flex justify-content-between align-items-center">
    <div class="navbar-brand-title">
        <i class="bi bi-bag-heart-fill text-warning me-2"></i>GELORA WYNE POS
    </div>
    <div class="navbar-info">
        <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['nama']); ?> &bull; Kasir I
    </div>
</div>

<!-- MAIN CONTAINER LAYOUT 2 KOLOM -->
<main class="main-wrapper">

    <!-- NOTIFIKASI SUKSES -->
    <?php if ($status_sukses) : ?>
        <div class="alert-modern-success">
            <i class="bi bi-check-circle-fill fs-6"></i>
            <span>Transaksi berhasil disimpan ke database!</span>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        
        <!-- KOLOM KIRI (INFORMASI & TABEL PRODUK) -->
        <div class="col-lg-8">
            
            <!-- HEADER & INFORMASI TRANSAKSI -->
            <div class="card-modern">
                <div class="receipt-header">
                    <div>
                        <h1 class="receipt-title">Rincian Transaksi</h1>
                        <div class="text-muted" style="font-size: 11.5px;">Ringkasan bukti pembayaran pelanggan</div>
                    </div>
                    <span class="invoice-badge">
                        <i class="bi bi-receipt me-1"></i> <?= htmlspecialchars($no_invoice); ?>
                    </span>
                </div>

                <div class="section-title">
                    <i class="bi bi-info-circle-fill"></i> Informasi Transaksi
                </div>

                <div class="info-grid">
                    <div>
                        <div class="info-label">Tanggal</div>
                        <div class="info-value">
                            <?= date("d-m-Y H:i", strtotime($header['tanggal_penjualan'])); ?> WIB
                        </div>
                    </div>

                    <div>
                        <div class="info-label">Kasir</div>
                        <div class="info-value">
                            <?= htmlspecialchars($header['nama_kasir'] ?? '-'); ?>
                        </div>
                    </div>

                    <div>
                        <div class="info-label">Pelanggan</div>
                        <div class="info-value">
                            <?= htmlspecialchars($header['nama_pelanggan'] ?? 'Pelanggan Umum'); ?>
                        </div>
                    </div>

                    <div>
                        <div class="info-label">No. HP</div>
                        <div class="info-value">
                            <?= htmlspecialchars($header['no_hp'] ?? '-'); ?>
                        </div>
                    </div>

                    <div>
                        <div class="info-label">Metode Pembayaran</div>
                        <div class="info-value">
                            <span class="badge-metode">
                                <?php if ($header['metode_pembayaran'] === 'QRIS') : ?>
                                    <i class="bi bi-qr-code-scan me-1"></i> QRIS
                                <?php else : ?>
                                    <i class="bi bi-cash-stack me-1"></i> Tunai
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <div>
                        <div class="info-label">Status Transaksi</div>
                        <div class="info-value">
                            <span class="badge-status"><i class="bi bi-check-lg me-1"></i>LUNAS</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DAFTAR PRODUK -->
            <div class="card-modern mb-0">
                <div class="section-title">
                    <i class="bi bi-box-seam-fill"></i> Daftar Produk
                </div>

                <div class="table-container">
                    <table class="table-pos">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">#</th>
                                <th>Produk</th>
                                <th style="width: 80px; text-align: center;">Qty</th>
                                <th style="width: 120px; text-align: right;">Harga</th>
                                <th style="width: 130px; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while ($d = mysqli_fetch_assoc($q_detail)) : ?>
                                <?php $total_item += $d['jumlah']; ?>
                                <tr>
                                    <td style="text-align: center;" class="text-muted"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($d['nama_produk']); ?></div>
                                        <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($d['kode_produk']); ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="qty-badge"><?= $d['jumlah']; ?> Pcs</span>
                                    </td>
                                    <td style="text-align: right;">
                                        Rp <?= number_format($d['harga_satuan'], 0, ',', '.'); ?>
                                    </td>
                                    <td style="text-align: right;" class="fw-bold">
                                        Rp <?= number_format($d['subtotal'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- KOLOM KANAN (RINGKASAN & ACTION BUTTONS) -->
        <div class="col-lg-4">
            <div class="sticky-right">
                
                <div class="card-modern">
                    <!-- DISPLAY TOTAL HASIL PENJUALAN -->
                    <div class="board-total">
                        <div class="board-label">Total Belanja</div>
                        <div class="board-val">Rp <?= number_format($header['total_harga'], 0, ',', '.'); ?></div>
                    </div>

                    <div class="section-title">
                        <i class="bi bi-credit-card-2-front-fill"></i> Rincian Pembayaran
                    </div>

                    <div class="ringkasan-box">
                        <div class="ringkasan-row">
                            <span>Total Barang</span>
                            <strong><?= $total_item; ?> Item</strong>
                        </div>
                        <div class="ringkasan-row">
                            <span>Metode Pembayaran</span>
                            <strong><?= htmlspecialchars($header['metode_pembayaran']); ?></strong>
                        </div>
                        <div class="ringkasan-row">
                            <span>Uang Bayar</span>
                            <strong>Rp <?= number_format($header['bayar'], 0, ',', '.'); ?></strong>
                        </div>
                        <div class="ringkasan-row">
                            <span>Kembalian</span>
                            <strong class="text-success">Rp <?= number_format($header['kembalian'], 0, ',', '.'); ?></strong>
                        </div>
                    </div>

                    <!-- TOMBOL AKSI -->
                    <div class="d-flex flex-column gap-2">
                        <!-- Tombol Kembali Dinamis -->
                        <a href="<?= htmlspecialchars($redirect_back); ?>" class="btn-modern" style="background-color: #78716C; color: #FFFFFF;">
                            <i class="bi bi-arrow-left-circle-fill"></i> Kembali
                        </a>
                        
                        <a href="struk.php?id_penjualan=<?= $id_penjualan; ?>" target="_blank" class="btn-modern btn-brown">
                            <i class="bi bi-printer-fill"></i> Cetak Struk
                        </a>
                        <a href="index.php" class="btn-modern btn-dark-modern">
                            <i class="bi bi-plus-circle-fill"></i> Transaksi Baru
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>

</main>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>