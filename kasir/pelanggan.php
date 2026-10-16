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
   PROSES TAMBAH / EDIT / HAPUS
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST['aksi'] ?? '';

    /* ---------- TAMBAH ---------- */
    if ($aksi === 'tambah') {
        $nama      = mysqli_real_escape_string($koneksi, trim($_POST['nama_pelanggan'] ?? ''));
        $alamat    = mysqli_real_escape_string($koneksi, trim($_POST['alamat'] ?? ''));
        $no_hp     = mysqli_real_escape_string($koneksi, trim($_POST['no_hp'] ?? ''));
        $tipe      = mysqli_real_escape_string($koneksi, $_POST['tipe_pelanggan'] ?? 'Umum');
        $poin      = intval($_POST['poin'] ?? 0);
        $kunjungan = intval($_POST['jumlah_kunjungan'] ?? 0);
        $tgl_daftar= date('Y-m-d');

        if ($nama !== '') {
            mysqli_query($koneksi, "
                INSERT INTO pelanggan (nama_pelanggan, alamat, no_hp, tipe_pelanggan, tgl_daftar, poin, jumlah_kunjungan)
                VALUES ('$nama', '$alamat', '$no_hp', '$tipe', '$tgl_daftar', '$poin', '$kunjungan')
            ");
            header("Location: pelanggan.php?status=tambah");
            exit;
        }
    }

    /* ---------- EDIT ---------- */
    if ($aksi === 'edit') {
        $id        = intval($_POST['id_pelanggan'] ?? 0);
        $nama      = mysqli_real_escape_string($koneksi, trim($_POST['nama_pelanggan'] ?? ''));
        $alamat    = mysqli_real_escape_string($koneksi, trim($_POST['alamat'] ?? ''));
        $no_hp     = mysqli_real_escape_string($koneksi, trim($_POST['no_hp'] ?? ''));
        $tipe      = mysqli_real_escape_string($koneksi, $_POST['tipe_pelanggan'] ?? 'Umum');
        $poin      = intval($_POST['poin'] ?? 0);
        $kunjungan = intval($_POST['jumlah_kunjungan'] ?? 0);

        if ($id > 0 && $nama !== '') {
            mysqli_query($koneksi, "
                UPDATE pelanggan SET
                    nama_pelanggan   = '$nama',
                    alamat           = '$alamat',
                    no_hp            = '$no_hp',
                    tipe_pelanggan   = '$tipe',
                    poin             = '$poin',
                    jumlah_kunjungan = '$kunjungan'
                WHERE id_pelanggan = '$id'
            ");
            header("Location: pelanggan.php?status=edit");
            exit;
        }
    }

    /* ---------- HAPUS ---------- */
    if ($aksi === 'hapus') {
        $id = intval($_POST['id_pelanggan'] ?? 0);

        if ($id > 0) {
            $cek = mysqli_query($koneksi, "
                SELECT COUNT(*) AS jml
                FROM penjualan
                WHERE id_pelanggan = '$id'
            ");
            $data_cek = mysqli_fetch_assoc($cek);

            if ($data_cek['jml'] > 0) {
                header("Location: pelanggan.php?status=gagal_hapus");
                exit;
            }

            mysqli_query($koneksi, "
                DELETE FROM pelanggan
                WHERE id_pelanggan = '$id'
            ");
            header("Location: pelanggan.php?status=hapus");
            exit;
        }
    }
}

/* =========================
   PENCARIAN
========================= */
$cari      = trim($_GET['cari'] ?? '');
$cari_safe = mysqli_real_escape_string($koneksi, $cari);

$where = "";
if ($cari !== '') {
    $where = "
        WHERE nama_pelanggan LIKE '%$cari_safe%'
           OR no_hp          LIKE '%$cari_safe%'
           OR alamat         LIKE '%$cari_safe%'
           OR tipe_pelanggan LIKE '%$cari_safe%'
    ";
}

/* =========================
   STATISTIK
========================= */
$q_total = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pelanggan");
$total_pelanggan = mysqli_fetch_assoc($q_total)['total'] ?? 0;

$q_member = mysqli_query($koneksi, "SELECT COUNT(*) AS total_m FROM pelanggan WHERE tipe_pelanggan = 'Member'");
$total_member = mysqli_fetch_assoc($q_member)['total_m'] ?? 0;

/* =========================
   DAFTAR PELANGGAN
========================= */
$query_pelanggan = mysqli_query($koneksi, "
    SELECT id_pelanggan, nama_pelanggan, alamat, no_hp, tipe_pelanggan, tgl_daftar, poin, jumlah_kunjungan
    FROM pelanggan
    $where
    ORDER BY nama_pelanggan ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan - GELORA WYNE</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <!-- CDN Bootstrap Icons -->
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
            overflow-x: hidden;
            font-size: 14px;
        }

        /* SIDEBAR (Disamakan dengan index.php) */
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
            transition: all 0.2s ease;
        }

        .logout-link:hover { background: #FFF0F0; }

        /* CONTENT LAYOUT */
        .main-wrapper {
            margin-left: 200px;
            min-height: 100vh;
            padding: 24px 28px;
        }

        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .card-modern {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            padding: 20px;
            margin-bottom: 20px;
        }

        /* STAT CARDS */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.brown {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
        }

        .stat-icon.dark {
            background: var(--text-dark);
            color: #FFFFFF;
        }

        .stat-val {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1;
        }

        .stat-lbl {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 600;
        }

        .alert-modern {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .alert-success-custom {
            background: #EAF5ED;
            border: 1px solid #BFE3C9;
            color: #1E6B37;
        }

        .alert-danger-custom {
            background: #FDF2F2;
            border: 1px solid #F8C4C4;
            color: #9B1C1C;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 360px;
        }

        .search-box input {
            width: 100%;
            height: 40px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0 16px 0 38px;
            font-size: 13px;
            outline: none;
        }

        .search-box i {
            position: absolute; left: 13px; top: 11px;
            color: var(--text-muted); font-size: 15px;
        }

        .table-container {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .table-pos {
            width: 100%;
            border-collapse: collapse;
        }

        .table-pos th {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            letter-spacing: 0.5px;
        }

        .table-pos td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table-pos tr:last-child td { border-bottom: none; }

        .table-pos tr:hover { background-color: var(--bg-canvas); }

        .member-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent-brown);
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .badge-type {
            font-size: 10.5px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .badge-member {
            background-color: var(--text-dark);
            color: #FFFFFF;
        }

        .badge-umum {
            background-color: var(--border-color);
            color: var(--text-muted);
        }

        .badge-poin {
            background-color: #FFF8E7;
            color: #B45309;
            border: 1px solid #FDE68A;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .btn-brown {
            background-color: var(--accent-brown);
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            padding: 9px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-brown:hover {
            background-color: var(--accent-brown-dark);
            color: #FFFFFF;
        }

        .btn-action-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            color: var(--text-dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-action-icon:hover {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            border-color: var(--accent-brown);
        }

        .btn-action-icon.delete:hover {
            background: #FDF2F2;
            color: #9B1C1C;
            border-color: #F8C4C4;
        }

        .modal-content {
            border-radius: 16px;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            background: var(--accent-brown-light);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 18px;
        }

        .modal-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--accent-brown-dark);
        }
    </style>
</head>

<body>

<!-- SIDEBAR (Disamakan persis dengan index.php) -->
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
        <a href="pelanggan.php" class="active">
            <i class="bi bi-people"></i>
            <span>Pelanggan</span>
        </a>
        <a href="laporan.php">
            <i class="bi bi-bar-chart-line"></i>
            <span>Laporan</span>
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

    <!-- HEADER BAR -->
    <div class="header-bar">
        <div>
            <h1 class="h4 fw-bold m-0 text-dark"><i class="bi bi-people-fill me-2 text-warning"></i>Data Pelanggan & Loyalty</h1>
        </div>
        <div style="font-weight: 700; font-size: 13px; color: var(--text-muted);">
            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d M Y'); ?>
        </div>
    </div>

    <!-- NOTIFIKASI -->
    <?php $status_notif = $_GET['status'] ?? ''; ?>
    <?php if ($status_notif === 'tambah') : ?>
        <div class="alert-modern alert-success-custom">
            <i class="bi bi-check-circle-fill fs-6"></i> Pelanggan berhasil ditambahkan.
        </div>
    <?php elseif ($status_notif === 'edit') : ?>
        <div class="alert-modern alert-success-custom">
            <i class="bi bi-check-circle-fill fs-6"></i> Data pelanggan berhasil diperbarui.
        </div>
    <?php elseif ($status_notif === 'hapus') : ?>
        <div class="alert-modern alert-success-custom">
            <i class="bi bi-check-circle-fill fs-6"></i> Pelanggan berhasil dihapus.
        </div>
    <?php elseif ($status_notif === 'gagal_hapus') : ?>
        <div class="alert-modern alert-danger-custom">
            <i class="bi bi-exclamation-triangle-fill fs-6"></i> Pelanggan tidak dapat dihapus karena memiliki riwayat transaksi.
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon brown">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stat-val"><?= $total_pelanggan; ?></div>
                    <div class="stat-lbl">Total Pelanggan Terdaftar</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon dark">
                    <i class="bi bi-star-fill"></i>
                </div>
                <div>
                    <div class="stat-val"><?= $total_member; ?></div>
                    <div class="stat-lbl">Pelanggan Tipe Member</div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL CARD -->
    <div class="card-modern">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="cariPelanggan" placeholder="Cari nama, no. HP, atau tipe..." value="<?= htmlspecialchars($cari); ?>" autocomplete="off">
            </div>

            <button type="button" class="btn-brown" onclick="bukaTambah()">
                <i class="bi bi-person-plus-fill"></i> Tambah Pelanggan
            </button>
        </div>

        <!-- TABEL DATA -->
        <div class="table-container">
            <?php if (mysqli_num_rows($query_pelanggan) === 0) : ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary"></i>
                    <?php if ($cari !== '') : ?>
                        Tidak ada pelanggan yang cocok dengan "<strong><?= htmlspecialchars($cari); ?></strong>".<br>
                        <a href="pelanggan.php" class="btn btn-sm btn-link text-decoration-none mt-1">Reset Pencarian</a>
                    <?php else : ?>
                        Belum ada data pelanggan terdaftar.
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <table class="table-pos" id="tabelPelanggan">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th>Pelanggan</th>
                            <th style="width: 100px; text-align: center;">Tipe</th>
                            <th style="width: 140px;">No. HP</th>
                            <th style="width: 120px; text-align: center;">Transaksi</th>
                            <th style="width: 120px; text-align: center;">Poin</th>
                            <th>Alamat</th>
                            <th style="width: 90px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; while ($p = mysqli_fetch_assoc($query_pelanggan)) : ?>
                            <?php
                            $id_p        = $p['id_pelanggan'];
                            $nama_p      = $p['nama_pelanggan'];
                            $alamat_p    = $p['alamat'] ?? '';
                            $hp_p        = $p['no_hp'] ?? '';
                            $tipe_p      = $p['tipe_pelanggan'] ?? 'Umum';
                            $poin_p      = $p['poin'] ?? 0;
                            $kunjungan_p = $p['jumlah_kunjungan'] ?? 0;
                            $inisial     = strtoupper(mb_substr($nama_p, 0, 1));
                            ?>

                            <tr>
                                <td style="text-align: center;" class="text-muted"><?= $no++; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="member-avatar"><?= htmlspecialchars($inisial); ?></div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($nama_p); ?></div>
                                            <div class="text-muted" style="font-size: 11px;">MBR-<?= str_pad($id_p, 4, "0", STR_PAD_LEFT); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-type <?= ($tipe_p === 'Member') ? 'badge-member' : 'badge-umum'; ?>">
                                        <?= htmlspecialchars($tipe_p); ?>
                                    </span>
                                </td>
                                <td class="fw-medium"><?= htmlspecialchars($hp_p ?: '-'); ?></td>
                                <td style="text-align: center;">
                                    <span class="fw-bold text-dark"><i class="bi bi-arrow-repeat text-muted me-1"></i><?= number_format($kunjungan_p); ?>x</span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-poin"><i class="bi bi-award-fill me-1"></i><?= number_format($poin_p); ?> Poin</span>
                                   
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($alamat_p ?: '-'); ?></td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-action-icon me-1" title="Edit" onclick='bukaEdit(<?= $id_p; ?>, <?= json_encode($nama_p); ?>, <?= json_encode($alamat_p); ?>, <?= json_encode($hp_p); ?>, <?= json_encode($tipe_p); ?>, <?= $poin_p; ?>, <?= $kunjungan_p; ?>)'>
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <button type="button" class="btn-action-icon delete" title="Hapus" onclick='bukaHapus(<?= $id_p; ?>, <?= json_encode($nama_p); ?>)'>
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- MODAL TAMBAH / EDIT -->
<div class="modal fade" id="modalPelanggan" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="pelanggan.php" id="formPelanggan">
                <div class="modal-header">
                    <h5 class="modal-title" id="judulModal">
                        <i class="bi bi-person-lines-fill me-1"></i> <span id="judulModalText">Tambah Pelanggan</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" id="aksiForm" value="tambah">
                    <input type="hidden" name="id_pelanggan" id="idPelangganForm" value="">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Pelanggan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_pelanggan" id="namaPelangganForm" class="form-control form-control-sm" placeholder="Contoh: Alya" maxlength="100" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">No. HP</label>
                            <input type="text" name="no_hp" id="noHpForm" class="form-control form-control-sm" placeholder="Contoh: 081234567890" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tipe Pelanggan</label>
                            <select name="tipe_pelanggan" id="tipePelangganForm" class="form-select form-select-sm">
                                <option value="Umum">Umum</option>
                                <option value="Member">Member</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah Poin</label>
                            <input type="number" name="poin" id="poinForm" class="form-control form-control-sm" value="0" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Jumlah Transaksi (Kunjungan)</label>
                            <input type="number" name="jumlah_kunjungan" id="kunjunganForm" class="form-control form-control-sm" value="0" min="0">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Alamat</label>
                        <textarea name="alamat" id="alamatForm" class="form-control form-control-sm" rows="2" placeholder="Contoh: Kendal, Jawa Tengah"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-brown py-1 px-3" style="font-size: 12.5px;">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL HAPUS -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form method="POST" action="pelanggan.php">
                <div class="modal-header">
                    <h5 class="modal-title text-danger fw-bold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Hapus Pelanggan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_pelanggan" id="idHapusForm" value="">

                    <p class="mb-2" style="font-size: 13px;">
                        Apakah Anda yakin ingin menghapus pelanggan <strong id="namaHapusText"></strong>?
                    </p>
                    <div class="text-muted" style="font-size: 11.5px;">
                        <em>*Pelanggan dengan riwayat transaksi tidak dapat dihapus.</em>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-semibold">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>
const modalPelanggan = new bootstrap.Modal(document.getElementById("modalPelanggan"));
const modalHapus = new bootstrap.Modal(document.getElementById("modalHapus"));

function bukaTambah() {
    document.getElementById("aksiForm").value = "tambah";
    document.getElementById("idPelangganForm").value = "";
    document.getElementById("judulModalText").innerText = "Tambah Pelanggan";
    document.getElementById("namaPelangganForm").value = "";
    document.getElementById("noHpForm").value = "";
    document.getElementById("tipePelangganForm").value = "Umum";
    document.getElementById("poinForm").value = "0";
    document.getElementById("kunjunganForm").value = "0";
    document.getElementById("alamatForm").value = "";
    modalPelanggan.show();
}

function bukaEdit(id, nama, alamat, hp, tipe, poin, kunjungan) {
    document.getElementById("aksiForm").value = "edit";
    document.getElementById("idPelangganForm").value = id;
    document.getElementById("judulModalText").innerText = "Edit Pelanggan";
    document.getElementById("namaPelangganForm").value = nama;
    document.getElementById("noHpForm").value = hp;
    document.getElementById("tipePelangganForm").value = tipe;
    document.getElementById("poinForm").value = poin;
    document.getElementById("kunjunganForm").value = kunjungan;
    document.getElementById("alamatForm").value = alamat;
    modalPelanggan.show();
}

function bukaHapus(id, nama) {
    document.getElementById("idHapusForm").value = id;
    document.getElementById("namaHapusText").innerText = nama;
    modalHapus.show();
}

/* CARI LANGSUNG */
document.getElementById("cariPelanggan").addEventListener("keyup", function() {
    const keyword = this.value.toLowerCase();
    const rows = document.querySelectorAll("#tabelPelanggan tbody tr");

    rows.forEach(function(row) {
        const teks = row.innerText.toLowerCase();
        row.style.display = teks.includes(keyword) ? "" : "none";
    });
});

/* AUTO HIDE NOTIFIKASI */
const alertEl = document.querySelector(".alert-modern");
if (alertEl) {
    setTimeout(function() {
        alertEl.style.transition = ".5s";
        alertEl.style.opacity = "0";
        setTimeout(() => alertEl.remove(), 500);
    }, 4000);
}
</script>

</body>
</html>