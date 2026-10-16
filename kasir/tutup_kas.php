<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['level'] != 'Kasir I') {
    header("Location: ../login.php");
    exit;
}

include "../koneksi.php";

$id_user = intval($_SESSION['id_user']);

/* =========================
   AMBIL KAS YANG TERBUKA
========================= */
$q_kas = mysqli_query($koneksi, "
    SELECT *
    FROM kas
    WHERE id_user = '$id_user'
    AND status = 'buka'
    LIMIT 1
");

$kas = mysqli_fetch_assoc($q_kas);

if (!$kas) {
    header("Location: buka_kas.php");
    exit;
}

/* =========================
   REKAP PENJUALAN SEJAK KAS DIBUKA
========================= */
$q_rekap = mysqli_query($koneksi, "
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(CASE WHEN metode_pembayaran = 'Tunai' THEN total_harga ELSE 0 END), 0) AS total_tunai,
        COALESCE(SUM(CASE WHEN metode_pembayaran = 'QRIS'  THEN total_harga ELSE 0 END), 0) AS total_qris,
        COALESCE(SUM(total_harga), 0) AS total_semua
    FROM penjualan
    WHERE id_user = '$id_user'
    AND tanggal_penjualan >= '" . $kas['waktu_buka'] . "'
");

$rekap = mysqli_fetch_assoc($q_rekap);

$saldo_awal  = intval($kas['saldo_awal']);
$total_tunai = intval($rekap['total_tunai']);
$total_qris  = intval($rekap['total_qris']);
$jumlah_trx  = intval($rekap['jumlah_transaksi']);
$uang_harus  = $saldo_awal + $total_tunai;

/* =========================
   SIMPAN PENUTUPAN KAS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $uang_fisik = intval($_POST["uang_fisik"] ?? 0);
    $catatan    = mysqli_real_escape_string($koneksi, trim($_POST["catatan"] ?? ''));
    $selisih    = $uang_fisik - $uang_harus;

    $update = mysqli_query($koneksi, "
        UPDATE kas
        SET saldo_akhir     = '$uang_harus',
            uang_fisik      = '$uang_fisik',
            selisih         = '$selisih',
            total_tunai     = '$total_tunai',
            total_qris      = '$total_qris',
            total_transaksi = '$jumlah_trx',
            catatan         = '$catatan',
            waktu_tutup     = NOW(),
            status          = 'tutup'
        WHERE id_kas = '" . intval($kas['id_kas']) . "'
    ");

    if ($update) {
        header("Location: tutup_kas.php?status=sukses&id=" . intval($kas['id_kas']));
        exit;
    }
}

/* =========================
   TAMPILAN RINGKASAN
========================= */
$kas_selesai = null;
if (($_GET['status'] ?? '') === 'sukses') {
    $id_kas = intval($_GET['id'] ?? 0);
    $q_selesai = mysqli_query($koneksi, "
        SELECT *
        FROM kas
        WHERE id_kas = '$id_kas'
        AND id_user = '$id_user'
        LIMIT 1
    ");
    $kas_selesai = mysqli_fetch_assoc($q_selesai);
}

/* =========================
   RIWAYAT TRANSAKSI SHIFT INI
========================= */
$q_trx = mysqli_query($koneksi, "
    SELECT kode_transaksi, total_harga, metode_pembayaran, tanggal_penjualan
    FROM penjualan
    WHERE id_user = '$id_user'
    AND tanggal_penjualan >= '" . $kas['waktu_buka'] . "'
    ORDER BY tanggal_penjualan DESC
    LIMIT 15
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tutup Kas - GELORA WYNE</title>

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
            /* Wallpaper Ikon Uang (Money) Miring di Lapisan Paling Belakang */
            background-color: var(--accent-brown-light);
            background-image: 
                linear-gradient(rgba(244, 239, 235, 0.88), rgba(244, 239, 235, 0.88)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 300 300' width='130' height='130'%3E%3Cg transform='rotate(25 150 150)' fill='%238C6D58' fill-opacity='0.22'%3E%3Cpath d='M250 100H50c-11 0-20 9-20 20v100c0 11 9 20 20 20h200c11 0 20-9 20-20V120c0-11-9-20-20-20zm0 120H50V120h200v100zM150 140c-19.3 0-35 15.7-35 35s15.7 35 35 35 35-15.7 35-35-15.7-35-35-35zm0 50c-8.3 0-15-6.7-15-15s6.7-15 15-15 15 6.7 15 15-6.7 15-15 15zM75 145h20v10H75v-10zm130 0h20v10h-20v-10z'/%3E%3C/g%3E%3C/svg%3E");
            background-repeat: repeat;
            color: var(--text-dark);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            font-size: 14px;
        }

        /* SIDEBAR UNIFORM */
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
            box-shadow: 4px 0 20px rgba(0,0,0,0.02);
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
            transition: all 0.2s ease;
        }

        .logout-link:hover { background: #FFF0F0; }

        /* LAYOUT */
        .main-wrapper {
            margin-left: 200px;
            min-height: 100vh;
            padding: 24px 28px;
        }

        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .card-modern {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(140, 109, 88, 0.08);
            position: relative;
            z-index: 2;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 18px;
            color: var(--text-dark);
        }

        .rekap-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .rekap-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 14px;
        }

        .rekap-label {
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
        }

        .rekap-nilai {
            color: var(--accent-brown-dark);
            font-size: 17px;
            font-weight: 800;
            margin-top: 2px;
        }

        .laci-box {
            margin-top: 14px;
            background: var(--accent-brown);
            color: #FFFFFF;
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .laci-label { font-size: 12.5px; opacity: .9; }
        .laci-nilai { font-size: 20px; font-weight: 800; }

        .riwayat {
            margin-top: 14px;
            max-height: 220px;
            overflow-y: auto;
        }

        .trx-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .trx-row:last-child { border-bottom: none; }

        .trx-kode { font-size: 12px; font-weight: 700; }
        .trx-waktu { font-size: 10.5px; color: var(--text-muted); }

        .badge-metode {
            font-size: 9px;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge-metode.tunai { background: var(--border-color); color: var(--text-dark); }
        .badge-metode.qris { background: var(--accent-brown-light); color: var(--accent-brown-dark); }

        .trx-total {
            margin-left: auto;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--accent-brown-dark);
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .input-money { position: relative; }

        .money-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 700;
        }

        .input-money input {
            padding-left: 42px;
            height: 44px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            font-size: 15px;
            font-weight: 600;
            background: var(--bg-canvas);
        }

        .input-money input:focus {
            background: #FFFFFF;
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 3px rgba(140, 109, 88, 0.15);
        }

        .selisih-box {
            margin-top: 14px;
            background: var(--bg-canvas);
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
        }

        .selisih-ket { font-size: 11.5px; color: var(--text-muted); }
        .selisih-nilai { font-size: 18px; font-weight: 800; color: var(--accent-brown-dark); margin-top: 2px; }

        .selisih-box.cocok  { background: #EAF5ED; border-color: #BFE3C9; }
        .selisih-box.kurang { background: #FDF2F2; border-color: #F8C4C4; }
        .selisih-box.lebih  { background: #FFF8E7; border-color: #FDE68A; }

        .selisih-box.cocok .selisih-nilai { color: #1E6B37; }
        .selisih-box.kurang .selisih-nilai { color: #9B1C1C; }
        .selisih-box.lebih .selisih-nilai { color: #B45309; }

        .btn-brown {
            background-color: var(--accent-brown);
            color: #FFFFFF;
            font-size: 13.5px;
            font-weight: 700;
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-brown:hover { background-color: var(--accent-brown-dark); color: #FFFFFF; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px dashed var(--border-color);
            font-size: 13px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); }
        .detail-nilai { font-weight: 700; color: var(--text-dark); text-align: right; }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        @media (max-width: 768px) {
            .main-wrapper { margin-left: 0; padding: 16px; }
            .sidebar { display: none; }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
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
        <a href="laporan.php">
            <i class="bi bi-bar-chart-line"></i>
            <span>Laporan</span>
        </a>
        <a href="tutup_kas.php" class="active">
            <i class="bi bi-lock"></i>
            <span>Tutup Kas</span>
        </a>
    </div>

    <div class="cashier-info-card">
        <div class="avatar-circle">
            <?php echo strtoupper(substr($_SESSION['nama'] ?? 'K', 0, 1)); ?>
        </div>
        <div>
            <div style="font-size: 12px; font-weight: 700;"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Kasir'); ?></div>
            <div style="font-size: 10px; color: var(--text-muted);">Kasir Hijab</div>
        </div>
    </div>

    <a href="../logout.php" class="logout-link">
        <i class="bi bi-box-arrow-left"></i>
        <span>Keluar</span>
    </a>
</aside>

<!-- MAIN CONTENT -->
<div class="main-wrapper">

    <div class="header-bar">
        <div>
            <h1 class="h4 fw-bold m-0 text-dark"><i class="bi bi-lock-fill me-2 text-warning"></i>Penutupan Kas Shift</h1>
        </div>
        <div style="font-weight: 700; font-size: 13px; color: var(--text-muted);">
            Kas Dibuka: <?= date('d/m/Y H:i', strtotime($kas['waktu_buka'])); ?>
        </div>
    </div>

    <?php if ($kas_selesai) : ?>
        <!-- RINGKASAN SUKSES TUTUP -->
        <div class="card-modern mx-auto" style="max-width: 500px;">
            <div class="text-center mb-3">
                <i class="bi bi-check-circle-fill text-success fs-1"></i>
                <h2 class="fw-bold fs-5 mt-2 mb-0">Kas Berhasil Ditutup</h2>
            </div>

            <div class="detail-row">
                <span class="detail-label">Tanggal</span>
                <span class="detail-nilai"><?= date('d/m/Y', strtotime($kas_selesai['tanggal'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Waktu Buka / Tutup</span>
                <span class="detail-nilai"><?= date('H:i', strtotime($kas_selesai['waktu_buka'])); ?> - <?= date('H:i', strtotime($kas_selesai['waktu_tutup'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Transaksi</span>
                <span class="detail-nilai"><?= intval($kas_selesai['total_transaksi']); ?> Transaksi</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Saldo Awal</span>
                <span class="detail-nilai">Rp <?= number_format($kas_selesai['saldo_awal'], 0, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Penjualan Tunai</span>
                <span class="detail-nilai">Rp <?= number_format($kas_selesai['total_tunai'], 0, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Penjualan QRIS</span>
                <span class="detail-nilai">Rp <?= number_format($kas_selesai['total_qris'], 0, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Uang Fisik Dihitung</span>
                <span class="detail-nilai">Rp <?= number_format($kas_selesai['uang_fisik'], 0, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Selisih Uang</span>
                <?php
                $sel = intval($kas_selesai['selisih']);
                if ($sel == 0) {
                    echo '<span class="detail-nilai text-success">Rp 0 (Cocok)</span>';
                } elseif ($sel < 0) {
                    echo '<span class="detail-nilai text-danger">Rp ' . number_format(abs($sel), 0, ',', '.') . ' (Kurang)</span>';
                } else {
                    echo '<span class="detail-nilai text-warning">Rp ' . number_format($sel, 0, ',', '.') . ' (Lebih)</span>';
                }
                ?>
            </div>

            <a href="index.php" class="btn-brown mt-4 text-decoration-none">
                <i class="bi bi-house-door-fill"></i> Kembali ke Katalog Kasir
            </a>
        </div>

    <?php else : ?>

        <div class="row g-4">
            <!-- REKAP KIRI -->
            <div class="col-lg-7">
                <div class="card-modern">
                    <div class="section-title">
                        <i class="bi bi-calculator-fill text-warning fs-5"></i> Rekap Perhitungan Shift Ini
                    </div>

                    <div class="rekap-grid">
                        <div class="rekap-card">
                            <div class="rekap-label">Saldo Awal Kas</div>
                            <div class="rekap-nilai">Rp <?= number_format($saldo_awal, 0, ',', '.'); ?></div>
                        </div>
                        <div class="rekap-card">
                            <div class="rekap-label">Penjualan Tunai</div>
                            <div class="rekap-nilai">Rp <?= number_format($total_tunai, 0, ',', '.'); ?></div>
                        </div>
                        <div class="rekap-card">
                            <div class="rekap-label">Penjualan QRIS</div>
                            <div class="rekap-nilai">Rp <?= number_format($total_qris, 0, ',', '.'); ?></div>
                        </div>
                        <div class="rekap-card">
                            <div class="rekap-label">Total Penjualan</div>
                            <div class="rekap-nilai">Rp <?= number_format($total_tunai + $total_qris, 0, ',', '.'); ?></div>
                        </div>
                    </div>

                    <div class="laci-box">
                        <div class="laci-label">
                            <strong>Uang Seharusnya di Laci</strong><br>
                            <small>(Saldo Awal + Penjualan Tunai)</small>
                        </div>
                        <div class="laci-nilai">
                            Rp <?= number_format($uang_harus, 0, ',', '.'); ?>
                        </div>
                    </div>

                    <div class="section-title mt-4 mb-2" style="font-size: 13.5px;">
                        <i class="bi bi-clock-history"></i> Transaksi Shift Ini (<?= $jumlah_trx; ?>)
                    </div>

                    <div class="riwayat">
                        <?php if (mysqli_num_rows($q_trx) == 0) : ?>
                            <div class="text-muted small py-3 text-center">Belum ada transaksi pada shift ini.</div>
                        <?php else : ?>
                            <?php while ($t = mysqli_fetch_assoc($q_trx)) : ?>
                                <div class="trx-row">
                                    <div>
                                        <div class="trx-kode"><?= htmlspecialchars($t['kode_transaksi']); ?></div>
                                        <div class="trx-waktu"><?= date('H:i', strtotime($t['tanggal_penjualan'])); ?></div>
                                    </div>
                                    <span class="badge-metode <?= ($t['metode_pembayaran'] == 'Tunai') ? 'tunai' : 'qris'; ?>">
                                        <?= $t['metode_pembayaran']; ?>
                                    </span>
                                    <span class="trx-total">
                                        Rp <?= number_format($t['total_harga'], 0, ',', '.'); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- FORM TUTUP KANAN -->
            <div class="col-lg-5">
                <div class="card-modern">
                    <div class="section-title">
                        <i class="bi bi-safe2-fill text-warning fs-5"></i> Hitung Uang Fisik
                    </div>

                    <form id="formTutupKas" method="POST" action="tutup_kas.php">
                        <div class="mb-3">
                            <label class="form-label">Total Uang Tunai di Laci</label>
                            <div class="input-money">
                                <span class="money-prefix">Rp</span>
                                <input type="number" id="uangFisik" name="uang_fisik" class="form-control" placeholder="Hitung lalu masukkan" min="0" autocomplete="off" oninput="hitungSelisih()">
                            </div>
                        </div>

                        <div id="boxSelisih" class="selisih-box">
                            <div id="ketSelisih" class="selisih-ket">Masukkan nilai uang fisik untuk cek selisih</div>
                            <div id="nilaiSelisih" class="selisih-nilai">Rp 0</div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label">Catatan Tambahan (Opsional)</label>
                            <textarea name="catatan" class="form-control form-control-sm" rows="2" placeholder="Contoh: Pengeluaran operasional Rp 10.000"></textarea>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <a href="index.php" class="btn btn-light border text-dark fw-bold w-50 py-2" style="border-radius: 10px; font-size: 13px;">Batal</a>
                            <button type="button" class="btn-brown w-50" onclick="prosesTutupKas()">
                                <i class="bi bi-lock-fill"></i> Tutup Kas
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
const uangHarus = <?= $uang_harus; ?>;

function hitungSelisih() {
    const raw = document.getElementById("uangFisik").value;
    const box = document.getElementById("boxSelisih");
    const nilai = document.getElementById("nilaiSelisih");
    const ket = document.getElementById("ketSelisih");

    box.classList.remove("cocok", "kurang", "lebih");

    if (raw === "") {
        nilai.innerText = "Rp 0";
        ket.innerText = "Masukkan nilai uang fisik untuk cek selisih";
        return;
    }

    const fisik = Number(raw) || 0;
    const selisih = fisik - uangHarus;

    if (selisih === 0) {
        box.classList.add("cocok");
        nilai.innerText = "Rp 0";
        ket.innerText = "Jumlah uang fisik pas ✔";
    } else if (selisih < 0) {
        box.classList.add("kurang");
        nilai.innerText = "Rp " + Math.abs(selisih).toLocaleString("id-ID");
        ket.innerText = "Uang fisik kurang dari sistem";
    } else {
        box.classList.add("lebih");
        nilai.innerText = "Rp " + selisih.toLocaleString("id-ID");
        ket.innerText = "Uang fisik lebih dari sistem";
    }
}

function prosesTutupKas() {
    const raw = document.getElementById("uangFisik").value;
    if (raw === "") {
        alert("Isi jumlah uang tunai fisik yang ada di laci terlebih dahulu.");
        return;
    }
    if (!confirm("Konfirmasi penutupan kas? Transaksi shift ini akan dikunci.")) {
        return;
    }
    document.getElementById("formTutupKas").submit();
}
</script>

</body>
</html>