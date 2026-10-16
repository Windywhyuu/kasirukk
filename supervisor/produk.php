<?php
session_start();
include "../koneksi.php";

/* =========================
   CEK LOGIN ADMIN / KASIR
========================= */
if (!isset($_SESSION['id_user']) || !in_array($_SESSION['level'], ['Kasir I', 'Kasir II', 'Supervisor'])) {
    header("Location: ../login.php");
    exit;
}

$pesan = "";
$error = "";
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : 'tampil';

/* =========================
   PROSES SIMPAN / TAMBAH & EDIT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_produk'])) {
    $id_produk    = isset($_POST['id_produk']) ? intval($_POST['id_produk']) : 0;
    $kode_produk  = mysqli_real_escape_string($koneksi, $_POST['kode_produk']);
    $nama_produk  = mysqli_real_escape_string($koneksi, $_POST['nama_produk']);
    $kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori']);
    $harga        = floatval($_POST['harga']);
    $stok         = intval($_POST['stok']);
    
    // Handle Upload Foto
    $nama_foto = $_POST['foto_lama'] ?? '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['foto']['tmp_name'];
        $nama_file = time() . '_' . preg_replace("/\s+/", "_", $_FILES['foto']['name']);
        $tujuan    = "../assets/produk/" . $nama_file;
        
        if (move_uploaded_file($file_tmp, $tujuan)) {
            if (!empty($_POST['foto_lama']) && file_exists("../assets/produk/" . $_POST['foto_lama'])) {
                unlink("../assets/produk/" . $_POST['foto_lama']);
            }
            $nama_foto = $nama_file;
        }
    }

    if ($id_produk > 0) {
        // PROSES UPDATE
        $query_update = "UPDATE produk SET 
            kode_produk = '$kode_produk', 
            nama_produk = '$nama_produk', 
            kategori = '$kategori', 
            harga = '$harga', 
            stok = '$stok', 
            foto = '$nama_foto' 
            WHERE id_produk = $id_produk";
        
        if (mysqli_query($koneksi, $query_update)) {
            header("Location: produk.php?pesan=sukses_edit");
            exit;
        } else {
            $error = "Gagal memperbarui produk: " . mysqli_error($koneksi);
        }
    } else {
        // PROSES TAMBAH BARU
        $query_insert = "INSERT INTO produk (kode_produk, nama_produk, kategori, harga, stok, foto) 
                         VALUES ('$kode_produk', '$nama_produk', '$kategori', '$harga', '$stok', '$nama_foto')";
        
        if (mysqli_query($koneksi, $query_insert)) {
            header("Location: produk.php?pesan=sukses_tambah");
            exit;
        } else {
            $error = "Gagal menambah produk: " . mysqli_error($koneksi);
        }
    }
}

/* =========================
   PROSES HAPUS MASSAL (BULK DELETE)
========================= */
if (isset($_POST['hapus_pilih']) && isset($_POST['id_pilih'])) {
    $ids = $_POST['id_pilih'];
    foreach ($ids as $id_pilih) {
        $id_pilih = intval($id_pilih);
        $q_foto = mysqli_query($koneksi, "SELECT foto FROM produk WHERE id_produk = $id_pilih");
        $data_f = mysqli_fetch_assoc($q_foto);
        if ($data_f && !empty($data_f['foto']) && file_exists("../assets/produk/" . $data_f['foto'])) {
            unlink("../assets/produk/" . $data_f['foto']);
        }
        mysqli_query($koneksi, "DELETE FROM produk WHERE id_produk = $id_pilih");
    }
    header("Location: produk.php?pesan=sukses_hapus_banyak");
    exit;
}

/* =========================
   PROSES EDIT MASSAL (BULK EDIT SIMPAN)
========================= */
if (isset($_POST['simpan_edit_pilih']) && isset($_POST['edit_data'])) {
    foreach ($_POST['edit_data'] as $id => $val) {
        $id       = intval($id);
        $harga    = floatval($val['harga']);
        $stok     = intval($val['stok']);
        mysqli_query($koneksi, "UPDATE produk SET harga = '$harga', stok = '$stok' WHERE id_produk = $id");
    }
    header("Location: produk.php?pesan=sukses_edit_banyak");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk - GELORA WYNE</title>
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
            height: 100vh;
            width: 100vw;
            transition: grid-template-columns 0.3s ease;
        }

        .pos-container.sidebar-open {
            grid-template-columns: 280px 1fr;
        }

        @media (max-width: 1200px) {
            .pos-container {
                grid-template-columns: 70px 1fr;
            }
            .pos-container.sidebar-open {
                grid-template-columns: 250px 1fr;
            }
        }

        /* SIDEBAR KIRI (DESIGN ASLI) */
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

        .pos-container.sidebar-open .menu-label {
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

        /* AREA UTAMA KONTEN */
        .pos-main {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: var(--bg-light);
            padding: 24px 28px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: var(--bg-card);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .page-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--accent-brown-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-custom {
            background: var(--accent-brown);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-custom:hover { background: var(--accent-brown-dark); color: #fff; }

        .card-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        /* PRODUCT GRID STYLING */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .product-card-img-wrap {
            width: 100%;
            height: 180px;
            background: #f1f3f5;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-card-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Angka Urutan di Pojok Kiri Atas Foto */
        .product-badge-number {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0, 0, 0, 0.65);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            z-index: 5;
            backdrop-filter: blur(2px);
        }

        .product-card-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex-grow: 1;
        }

        .product-card-title {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--text-main);
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card-category {
            font-size: 11px;
            color: var(--text-muted);
        }

        .product-card-footer {
            padding: 10px 12px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
        }

        .product-price {
            font-weight: 800;
            color: var(--accent-brown-dark);
            font-size: 13px;
        }

        /* Checkbox Seleksi Tersembunyi */
        .select-checkbox-wrap {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 10;
            display: none;
        }
        .select-checkbox-wrap input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .mode-select-active .select-checkbox-wrap {
            display: block;
        }
    </style>
</head>
<body>

<div class="pos-container" id="posContainer">
    
    <!-- SIDEBAR KIRI -->
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
                <a href="produk.php" class="active" title="Produk">
                    <i class="bi bi-box-seam-fill"></i>
                    <span class="menu-label">Produk</span>
                </a>
                <a href="member.php" title="Member">
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

    <!-- MAIN CONTENT -->
    <main class="pos-main">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['pesan'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    if ($_GET['pesan'] == 'sukses_tambah') echo "Produk berhasil ditambahkan!";
                    elseif ($_GET['pesan'] == 'sukses_edit') echo "Produk berhasil diperbarui!";
                    elseif ($_GET['pesan'] == 'sukses_hapus_banyak') echo "Produk terpilih berhasil dihapus!";
                    elseif ($_GET['pesan'] == 'sukses_edit_banyak') echo "Perubahan data produk terpilih berhasil disimpan!";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- KONDISI TAMPILAN: FORM TAMBAH / EDIT ATAU GRID UTAMA -->
        <?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>
            <?php 
                $id_produk = ""; $kode_produk = ""; $nama_produk = ""; $kategori = ""; $harga = ""; $stok = ""; $foto = "";
                if ($aksi === 'edit' && isset($_GET['id'])) {
                    $id_edit = intval($_GET['id']);
                    $q_isi = mysqli_query($koneksi, "SELECT * FROM produk WHERE id_produk = $id_edit");
                    $d_isi = mysqli_fetch_assoc($q_isi);
                    if ($d_isi) {
                        $id_produk   = $d_isi['id_produk'];
                        $kode_produk = $d_isi['kode_produk'];
                        $nama_produk = $d_isi['nama_produk'];
                        $kategori    = $d_isi['kategori'];
                        $harga       = $d_isi['harga'];
                        $stok        = $d_isi['stok'];
                        $foto        = $d_isi['foto'];
                    }
                }
            ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="bi bi-<?= $aksi === 'edit' ? 'pencil-square' : 'plus-circle-fill'; ?>"></i> 
                    <?= $aksi === 'edit' ? 'Edit Data Produk' : 'Tambah Produk Baru'; ?>
                </h1>
                <a href="produk.php" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
            </div>

            <div class="card-content">
                <form action="produk.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_produk" value="<?= $id_produk; ?>">
                    <input type="hidden" name="foto_lama" value="<?= $foto; ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold">Kode Produk</label>
                            <input type="text" class="form-control" name="kode_produk" value="<?= htmlspecialchars($kode_produk); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label font-weight-bold">Nama Produk</label>
                            <input type="text" class="form-control" name="nama_produk" value="<?= htmlspecialchars($nama_produk); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold">Kategori</label>
                            <input type="text" class="form-control" name="kategori" value="<?= htmlspecialchars($kategori); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold">Harga (Rp)</label>
                            <input type="number" class="form-control" name="harga" value="<?= $harga; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label font-weight-bold">Stok</label>
                            <input type="number" class="form-control" name="stok" value="<?= $stok; ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Foto Produk</label>
                        <?php if (!empty($foto)): ?>
                            <div class="mb-2">
                                <img src="../assets/produk/<?= $foto; ?>" alt="Foto" width="80" class="img-thumbnail rounded">
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" name="foto">
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengubah foto.</small>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" name="simpan_produk" class="btn-custom border-0"><i class="bi bi-save me-1"></i> Simpan Data</button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            
            <?php
            // Hitung total data produk untuk keterangan jumlah barang di paling atas
            $q_hitung = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM produk");
            $d_hitung = mysqli_fetch_assoc($q_hitung);
            $total_barang = $d_hitung['total'];
            ?>

            <!-- TAMPILAN UTAMA PRODUCT GRID & FITUR PENCARIAN -->
          <!-- PAGE HEADER DENGAN PENCARIAN DI TENGAH -->
<div class="page-header d-flex align-items-center justify-content-between gap-3">
    <!-- Bagian Kiri: Judul & Total Barang -->
    <div style="min-width: max-content;">
        <h1 class="page-title mb-0">
            <i class="bi bi-grid-fill"></i> Product Grid 
            <span class="badge bg-light text-dark border ms-2" style="font-size: 11px; font-weight: 600;">Total: <?= $total_barang; ?> Barang</span>
        </h1>
        <small class="text-muted">Kelola, cari, dan edit produk dengan cepat.</small>
    </div>

    <!-- Bagian Tengah: Kotak Pencarian Barang (Terletak di Tengah) -->
    <div class="mx-auto" style="width: 100%; max-width: 400px;">
        <div class="input-group input-group-sm shadow-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="searchInput" class="form-control" placeholder="Cari" onkeyup="filterProduk()">
        </div>
    </div>

    <!-- Bagian Kanan: Tombol-tombol Aksi -->
    <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end" style="min-width: max-content;">
        <button type="button" class="btn btn-outline-warning btn-sm fw-bold px-3 text-dark border" id="btnToggleEdit" onclick="toggleModeEdit()">
            <i class="bi bi-pencil-square me-1"></i> Edit Pilih
        </button>
        <button type="button" class="btn btn-outline-danger btn-sm fw-bold px-3" id="btnToggleHapus" onclick="toggleModeHapus()">
            <i class="bi bi-trash3 me-1"></i> Hapus Pilih
        </button>
        <a href="produk.php?aksi=tambah" class="btn-custom">
            <i class="bi bi-plus-circle-fill"></i> Tambah
        </a>
    </div>
</div>

            <!-- FORM CONTAINER UNTUK AKSI MASSAL -->
            <form action="produk.php" method="POST" id="formBulkAction">
                
                <!-- Panel Aksi Hapus Massal -->
                <div id="panelAksiHapus" class="mb-3 p-3 bg-white border rounded d-none justify-content-between align-items-center shadow-sm">
                    <span class="text-danger fw-bold" style="font-size: 12.5px;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Centang produk yang ingin dihapus massal.
                    </span>
                    <button type="submit" name="hapus_pilih" onclick="return confirm('Yakin ingin menghapus produk yang dicentang?')" class="btn btn-danger btn-sm px-3 fw-bold">
                        <i class="bi bi-trash3-fill me-1"></i> Hapus yang Dipilih
                    </button>
                </div>

                <!-- Panel Aksi Edit Massal -->
                <div id="panelAksiEdit" class="mb-3 p-3 bg-white border rounded d-none justify-content-between align-items-center shadow-sm">
                    <span class="text-dark fw-bold" style="font-size: 12.5px;">
                        <i class="bi bi-info-circle-fill me-1 text-warning"></i> Ubah harga atau stok pada produk yang dicentang, lalu klik Simpan.
                    </span>
                    <button type="submit" name="simpan_edit_pilih" class="btn btn-warning text-white btn-sm px-3 fw-bold">
                        <i class="bi bi-save-fill me-1"></i> Simpan Perubahan Terpilih
                    </button>
                </div>

                <!-- GRID PRODUK -->
                <div class="product-grid" id="productGridContainer">
                    <?php 
                    $no = 1;
                    $query_produk = mysqli_query($koneksi, "SELECT * FROM produk ORDER BY kode_produk ASC");
                    while ($row = mysqli_fetch_assoc($query_produk)) : 
                    ?>
                    <div class="product-card item-card" id="card-<?= $row['id_produk']; ?>" data-nama="<?= strtolower(htmlspecialchars($row['nama_produk'])); ?>" data-kode="<?= strtolower(htmlspecialchars($row['kode_produk'])); ?>" data-kategori="<?= strtolower(htmlspecialchars($row['kategori'])); ?>">
                        
                        <!-- Nomor Urut di Pojok Kiri Atas Foto -->
                        <span class="product-badge-number">#<?= $no++; ?></span>

                        <!-- Checkbox untuk pilihan massal di pojok kanan atas -->
                        <div class="select-checkbox-wrap">
                            <input type="checkbox" name="id_pilih[]" value="<?= $row['id_produk']; ?>" class="item-checkbox" onchange="handleCheckboxChange(this, <?= $row['id_produk']; ?>)">
                        </div>

                        <div class="product-card-img-wrap">
                            <?php if (!empty($row['foto']) && file_exists("../assets/produk/".$row['foto'])) : ?>
                                <img src="../assets/produk/<?= htmlspecialchars($row['foto']); ?>" alt="Foto Produk">
                            <?php else : ?>
                                <div class="text-muted d-flex flex-column align-items-center justify-content-center w-100 h-100 bg-light">
                                    <i class="bi bi-image fs-2"></i>
                                    <small style="font-size: 10px;">Tidak ada foto</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="product-card-body">
                            <span class="product-card-category text-uppercase"><?= htmlspecialchars($row['kategori']); ?></span>
                            <h6 class="product-card-title" title="<?= htmlspecialchars($row['nama_produk']); ?>"><?= htmlspecialchars($row['nama_produk']); ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Kode: <?= htmlspecialchars($row['kode_produk']); ?></small>
                            
                            <!-- Input Edit Masal -->
                            <div class="edit-inputs-group mt-2 d-none" id="edit-inputs-<?= $row['id_produk']; ?>">
                                <div class="input-group input-group-sm mb-1">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" name="edit_data[<?= $row['id_produk']; ?>][harga]" value="<?= $row['harga']; ?>" class="form-control" placeholder="Harga">
                                </div>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Stok</span>
                                    <input type="number" name="edit_data[<?= $row['id_produk']; ?>][stok]" value="<?= $row['stok']; ?>" class="form-control" placeholder="Stok">
                                </div>
                            </div>
                        </div>

                        <div class="product-card-footer">
                            <div class="normal-price-display">
                                <div class="product-price">Rp <?= number_format($row['harga'], 0, ',', '.'); ?></div>
                                <small class="text-muted" style="font-size: 11px;">Stok: <?= $row['stok']; ?></small>
                            </div>
                            <div>
                                <a href="produk.php?aksi=edit&id=<?= $row['id_produk']; ?>" class="btn btn-sm btn-light border text-muted" title="Edit Detail">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

            </form>

        <?php endif; ?>
    </main>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    const container = document.getElementById('posContainer');
    container.classList.toggle('sidebar-open');
}

// Fitur Pencarian / Filter Produk Secara Instan
function filterProduk() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('item-card');

    for (let i = 0; i < cards.length; i++) {
        let nama = cards[i].getAttribute('data-nama');
        let kode = cards[i].getAttribute('data-kode');
        let kategori = cards[i].getAttribute('data-kategori');

        if (nama.includes(input) || kode.includes(input) || kategori.includes(input)) {
            cards[i].style.display = "";
        } else {
            cards[i].style.display = "none";
        }
    }
}

// Fitur Mode Hapus Pilih
function toggleModeHapus() {
    const container = document.getElementById('productGridContainer');
    const panelAksi = document.getElementById('panelAksiHapus');
    const btnToggle = document.getElementById('btnToggleHapus');
    
    // Tutup mode edit jika sedang aktif
    document.getElementById('panelAksiEdit').classList.add('d-none');
    document.getElementById('panelAksiEdit').classList.remove('d-flex');
    const btnEdit = document.getElementById('btnToggleEdit');
    btnEdit.classList.remove('btn-warning', 'text-white');
    btnEdit.classList.add('btn-outline-warning', 'text-dark');
    document.querySelectorAll('.edit-inputs-group').forEach(el => el.classList.add('d-none'));

    container.classList.toggle('mode-select-active');

    if (panelAksi.classList.contains('d-none')) {
        panelAksi.classList.remove('d-none');
        panelAksi.classList.add('d-flex');
        btnToggle.classList.remove('btn-outline-danger');
        btnToggle.classList.add('btn-secondary');
        btnToggle.innerHTML = '<i class="bi bi-x-circle me-1"></i> Batal Hapus';
    } else {
        panelAksi.classList.remove('d-flex');
        panelAksi.classList.add('d-none');
        btnToggle.classList.remove('btn-secondary');
        btnToggle.classList.add('btn-outline-danger');
        btnToggle.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Pilih';
        document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.product-card').forEach(c => c.style.background = 'var(--bg-card)');
    }
}

// Fitur Mode Edit Pilih Massal
function toggleModeEdit() {
    const container = document.getElementById('productGridContainer');
    const panelAksi = document.getElementById('panelAksiEdit');
    const btnToggle = document.getElementById('btnToggleEdit');

    // Tutup mode hapus jika sedang aktif
    document.getElementById('panelAksiHapus').classList.add('d-none');
    document.getElementById('panelAksiHapus').classList.remove('d-flex');
    const btnHapus = document.getElementById('btnToggleHapus');
    btnHapus.classList.remove('btn-secondary');
    btnHapus.classList.add('btn-outline-danger');
    btnHapus.innerHTML = '<i class="bi bi-trash3 me-1"></i> Hapus Pilih';

    container.classList.toggle('mode-select-active');

    if (panelAksi.classList.contains('d-none')) {
        panelAksi.classList.remove('d-none');
        panelAksi.classList.add('d-flex');
        btnToggle.classList.remove('btn-outline-warning', 'text-dark');
        btnToggle.classList.add('btn-warning', 'text-white');
        btnToggle.innerHTML = '<i class="bi bi-x-circle me-1"></i> Batal Edit';
    } else {
        panelAksi.classList.remove('d-flex');
        panelAksi.classList.add('d-none');
        btnToggle.classList.remove('btn-warning', 'text-white');
        btnToggle.classList.add('btn-outline-warning', 'text-dark');
        btnToggle.innerHTML = '<i class="bi bi-pencil-square me-1"></i> Edit Pilih';
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            cb.checked = false;
            handleCheckboxChange(cb, cb.value);
        });
    }
}

// Menampilkan input edit inline saat card produk dicentang pada mode edit
function handleCheckboxChange(checkbox, id) {
    const editInputs = document.getElementById('edit-inputs-' + id);
    const card = document.getElementById('card-' + id);
    if (!editInputs) return;

    if (checkbox.checked) {
        editInputs.classList.remove('d-none');
        card.style.background = '#fdf8f5';
    } else {
        editInputs.classList.add('d-none');
        card.style.background = 'var(--bg-card)';
    }
}
</script>
</body>
</html>