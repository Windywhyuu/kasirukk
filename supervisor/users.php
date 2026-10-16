<?php
session_start();
include "../koneksi.php";

/* =========================
   CEK LOGIN SUPERVISOR/ADMIN
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

$pesan = "";
$tipe_pesan = "";
$tab = $_GET['tab'] ?? 'list'; // Pilihan tab: list, tambah, edit
$edit_id = intval($_GET['id'] ?? 0);

/* =========================
   PROSES TAMBAH / EDIT / HAPUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi       = $_POST['aksi'] ?? '';
    $id_user    = intval($_POST['id_user'] ?? 0);
    $nama       = trim($_POST['nama'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $level      = $_POST['level'] ?? 'Kasir I';
    $status     = $_POST['status'] ?? 'aktif';

    if ($aksi === 'tambah') {
        if ($nama !== '' && $username !== '' && $password !== '') {
            $cek = mysqli_query($koneksi, "SELECT id_user FROM users WHERE username = '$username'");
            if (mysqli_num_rows($cek) > 0) {
                $pesan = "Username sudah digunakan oleh pengguna lain!";
                $tipe_pesan = "danger";
                $tab = 'tambah';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $q = "INSERT INTO users (nama, username, password, level, status) VALUES ('$nama', '$username', '$password_hash', '$level', '$status')";
                if (mysqli_query($koneksi, $q)) {
                    $pesan = "Pengguna berhasil ditambahkan.";
                    $tipe_pesan = "success";
                    $tab = 'list';
                } else {
                    $pesan = "Gagal menambahkan pengguna: " . mysqli_error($koneksi);
                    $tipe_pesan = "danger";
                    $tab = 'tambah';
                }
            }
        } else {
            $pesan = "Nama, Username, dan Password wajib diisi!";
            $tipe_pesan = "danger";
            $tab = 'tambah';
        }
    } elseif ($aksi === 'edit') {
        if ($id_user > 0 && $nama !== '' && $username !== '') {
            $cek = mysqli_query($koneksi, "SELECT id_user FROM users WHERE username = '$username' AND id_user != $id_user");
            if (mysqli_num_rows($cek) > 0) {
                $pesan = "Username sudah digunakan oleh pengguna lain!";
                $tipe_pesan = "danger";
                $tab = 'edit';
                $edit_id = $id_user;
            } else {
                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $q = "UPDATE users SET nama = '$nama', username = '$username', password = '$password_hash', level = '$level', status = '$status' WHERE id_user = $id_user";
                } else {
                    $q = "UPDATE users SET nama = '$nama', username = '$username', level = '$level', status = '$status' WHERE id_user = $id_user";
                }

                if (mysqli_query($koneksi, $q)) {
                    $pesan = "Data pengguna berhasil diperbarui.";
                    $tipe_pesan = "success";
                    $tab = 'list';
                } else {
                    $pesan = "Gagal memperbarui pengguna: " . mysqli_error($koneksi);
                    $tipe_pesan = "danger";
                    $tab = 'edit';
                    $edit_id = $id_user;
                }
            }
        } else {
            $pesan = "Data tidak lengkap!";
            $tipe_pesan = "danger";
            $tab = 'edit';
            $edit_id = $id_user;
        }
    }
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id_hapus = intval($_GET['hapus']);
    if ($id_hapus === intval($_SESSION['id_user'])) {
        $pesan = "Anda tidak dapat menghapus akun yang sedang digunakan sendiri!";
        $tipe_pesan = "danger";
    } else {
        mysqli_query($koneksi, "DELETE FROM users WHERE id_user = $id_hapus");
        header("Location: users.php?pesan=terhapus");
        exit;
    }
}

// Ambil data untuk mode edit
$data_edit = ['id_user' => '', 'nama' => '', 'username' => '', 'level' => 'Kasir I', 'status' => 'aktif'];
if ($tab === 'edit' && $edit_id > 0) {
    $q_edit = mysqli_query($koneksi, "SELECT * FROM users WHERE id_user = $edit_id");
    if (mysqli_num_rows($q_edit) > 0) {
        $data_edit = mysqli_fetch_assoc($q_edit);
    }
}

// Ambil daftar pengguna & statistik singkat
$q_users = mysqli_query($koneksi, "SELECT * FROM users ORDER BY id_user DESC");
$total_users = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_user FROM users"));
$total_aktif = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_user FROM users WHERE status='aktif'"));
$total_supervisor = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_user FROM users WHERE level='Supervisor'"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - GELORA WYNE</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-light: #f8fafc;
            --bg-card: #ffffff;
            --bg-hover: #f1f5f9;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-brown: #8C6D58;
            --accent-brown-dark: #5A4334;
            --accent-brown-light: #fdf8f5;
        }

        body {
            margin: 0;
            background: var(--bg-light);
            color: var(--text-main);
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 13.5px;
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

        /* SIDEBAR UTAMA (TIDAK DIRUBAH STRUKTURNYA)[cite: 1] */
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

        /* MAIN CONTENT DESIGN */
        .pos-main {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: var(--bg-light);
            padding: 32px 40px;
        }

        .page-top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .page-top-header h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 4px 0;
        }
        .page-top-header p {
            color: var(--text-muted);
            font-size: 13.5px;
            margin: 0;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            background: var(--accent-brown-light);
            color: var(--accent-brown);
        }
        .stat-info h4 {
            font-size: 18px;
            font-weight: 800;
            margin: 0 0 2px 0;
            color: var(--text-main);
        }
        .stat-info span {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* CONTAINER UTAMA KONTEN */
        .content-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }

        .content-card-body {
            padding: 24px;
        }

        /* NAVIGASI TAB */
        .nav-tabs-custom {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
            padding: 0 24px;
            background: #fafbfc;
        }
        .nav-tabs-custom a {
            padding: 14px 18px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-tabs-custom a:hover { color: var(--text-main); }
        .nav-tabs-custom a.active {
            color: var(--accent-brown);
            border-bottom-color: var(--accent-brown);
            background: var(--bg-card);
        }

        /* TABEL PENGGUNA */
        .table-custom {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .table-custom th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            background: #f8fafc;
        }
        .table-custom td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-main);
            vertical-align: middle;
        }
        .table-custom tbody tr:last-child td { border-bottom: none; }
        .table-custom tbody tr:hover td { background: var(--bg-hover); }

        .user-avatar-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-avatar-initial {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            border: 1px solid rgba(140, 109, 88, 0.2);
            flex-shrink: 0;
        }

        /* CARD GAYA LOGIN UNTUK FORM TAMBAH & EDIT (SATU KOLOM KE BAWAH) */
        .auth-form-card {
            max-width: 480px;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        .auth-form-header {
            text-align: center;
            margin-bottom: 24px;
        }
        .auth-form-header .icon-box {
            width: 50px; height: 50px;
            background: var(--accent-brown-light);
            color: var(--accent-brown);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px auto;
        }
        .auth-form-header h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 4px 0;
        }
        .auth-form-header p {
            font-size: 12.5px;
            color: var(--text-muted);
            margin: 0;
        }

        /* FORM STYLING */
        .form-label-custom {
            font-weight: 600;
            font-size: 12.5px;
            color: var(--text-main);
            margin-bottom: 6px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 10px 14px;
            font-size: 13.5px;
            color: var(--text-main);
            background: #fff;
            transition: all 0.2s;
            width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 3px rgba(140, 109, 88, 0.12);
        }

        /* BUTTONS */
        .btn-primary-custom {
            background: var(--accent-brown);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 13.5px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
        }
        .btn-primary-custom:hover { background: var(--accent-brown-dark); color: white; }

        .btn-light-custom {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 13.5px;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .btn-light-custom:hover { background: var(--bg-hover); color: var(--text-main); }
    </style>
</head>
<body>

<div class="pos-container" id="posContainer">
    
    <!-- SIDEBAR UTAMA APLIKASI (TIDAK DIRUBAH)[cite: 1] -->
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
                <a href="index.php" title="Katalog Hijab"><i class="bi bi-bag-heart-fill"></i><span class="menu-label">Katalog Hijab</span></a>
                <a href="produk.php" title="Produk"><i class="bi bi-box-seam-fill"></i><span class="menu-label">Produk</span></a>
                <a href="member.php" title="Member"><i class="bi bi-award-fill"></i><span class="menu-label">Member</span></a>
                <a href="kas.php" title="Kas"><i class="bi bi-wallet2"></i><span class="menu-label">Kas</span></a>
                <a href="laporan.php" title="Laporan"><i class="bi bi-bar-chart-line-fill"></i><span class="menu-label">Laporan</span></a>
                <a href="users.php" class="active" title="Pengguna"><i class="bi bi-person-badge-fill"></i><span class="menu-label">Pengguna</span></a>
            </div>
        </div>
        <div>
            <div class="sidebar-menu">
                <a href="../logout.php" title="Keluar" style="color: #DC3545;"><i class="bi bi-box-arrow-left"></i><span class="menu-label">Keluar</span></a>
            </div>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="pos-main">
        <div class="page-top-header">
            <div>
                <h2>Manajemen Pengguna</h2>
                <p>Kelola profil akun, kredensial login, dan tingkat hak akses sistem.</p>
            </div>
        </div>

        <!-- STATISTIK RINGKAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div class="stat-info">
                    <h4><?= $total_users; ?></h4>
                    <span>Total Pengguna</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #d1e7dd; color: #0f5132;"><i class="bi bi-check-circle"></i></div>
                <div class="stat-info">
                    <h4><?= $total_aktif; ?></h4>
                    <span>Akun Aktif</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;"><i class="bi bi-shield-check"></i></div>
                <div class="stat-info">
                    <h4><?= $total_supervisor; ?></h4>
                    <span>Supervisor</span>
                </div>
            </div>
        </div>

        <?php if (!empty($pesan)): ?>
            <div class="alert alert-<?= $tipe_pesan; ?> alert-dismissible fade show" role="alert" style="border-radius: 12px; margin-bottom: 20px;">
                <?= htmlspecialchars($pesan); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KONTEN UTAMA KARTU AKUN -->
        <div class="content-card">
            <!-- Navigasi Tab Modern -->
            <div class="nav-tabs-custom">
                <a href="users.php?tab=list" class="<?= ($tab === 'list') ? 'active' : ''; ?>">
                    <i class="bi bi-list-ul"></i> Daftar Pengguna
                </a>
                <a href="users.php?tab=tambah" class="<?= ($tab === 'tambah') ? 'active' : ''; ?>">
                    <i class="bi bi-person-plus"></i> Tambah Akun Baru
                </a>
                <?php if ($tab === 'edit'): ?>
                    <a href="users.php?tab=edit&id=<?= $edit_id; ?>" class="active">
                        <i class="bi bi-pencil-square"></i> Edit Akun
                    </a>
                <?php endif; ?>
            </div>

            <div class="content-card-body">
                <?php if ($tab === 'list'): ?>
                    <!-- TAB: DAFTAR PENGGUNA -->
                    <div style="overflow-x: auto;">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Pengguna</th>
                                    <th>Username</th>
                                    <th>Level Akses</th>
                                    <th>Status</th>
                                    <th style="text-align: right; width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($q_users) == 0): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-5">Belum ada data pengguna sistem.</td></tr>
                                <?php else: ?>
                                    <?php $no = 1; while ($u = mysqli_fetch_assoc($q_users)): 
                                        $is_aktif = ($u['status'] === 'aktif');
                                        $initial = strtoupper(substr($u['nama'], 0, 1));
                                    ?>
                                        <tr>
                                            <td style="font-family:'JetBrains Mono', monospace; color: var(--text-muted);"><?= $no++; ?></td>
                                            <td>
                                                <div class="user-avatar-cell">
                                                    <div class="user-avatar-initial"><?= $initial; ?></div>
                                                    <div>
                                                        <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($u['nama']); ?></div>
                                                        <div style="font-size: 11.5px; color: var(--text-muted);">ID: #<?= $u['id_user']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code style="font-family:'JetBrains Mono', monospace; background: var(--bg-hover); padding: 3px 8px; border-radius: 6px; color: var(--accent-brown-dark); font-size: 12px;"><?= htmlspecialchars($u['username']); ?></code>
                                            </td>
                                            <td>
                                                <span style="padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 11.5px; background: #f1f5f9; color: #334155; border: 1px solid var(--border-color);">
                                                    <?= htmlspecialchars($u['level']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 11.5px; background: <?= $is_aktif ? '#d1e7dd' : '#f8d7da'; ?>; color: <?= $is_aktif ? '#0f5132' : '#842029'; ?>;">
                                                    <i class="bi bi-circle-fill" style="font-size: 6px; vertical-align: middle; margin-right: 4px;"></i> <?= ucfirst($u['status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="users.php?tab=edit&id=<?= $u['id_user']; ?>" class="btn btn-sm btn-light" style="border-radius: 8px; border: 1px solid var(--border-color); padding: 5px 10px;" title="Ubah Akun">
                                                    <i class="bi bi-pencil text-primary"></i>
                                                </a>
                                                <a href="users.php?hapus=<?= $u['id_user']; ?>" class="btn btn-sm btn-light" style="border-radius: 8px; border: 1px solid var(--border-color); padding: 5px 10px;" onclick="return confirm('Yakin ingin menghapus pengguna ini?')" title="Hapus Akun">
                                                    <i class="bi bi-trash text-danger"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($tab === 'tambah'): ?>
                    <!-- TAB: TAMBAH PENGGUNA (GAYA KARTU LOGIN SATU KOLOM) -->
                    <div class="auth-form-card">
                        <div class="auth-form-header">
                            <div class="icon-box">
                                <i class="bi bi-person-plus-fill"></i>
                            </div>
                            <h3>Tambah Akun Baru</h3>
                            <p>Daftarkan akun kasir atau supervisor baru ke sistem.</p>
                        </div>

                        <form method="POST" action="users.php?tab=tambah">
                            <input type="hidden" name="aksi" value="tambah">
                            
                            <div class="mb-3">
                                <label class="form-label-custom">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama" required placeholder="Contoh: Siti Aminah">
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">Username</label>
                                <input type="text" class="form-control" name="username" required placeholder="Contoh: kasir1">
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">Password</label>
                                <input type="password" class="form-control" name="password" required placeholder="••••••••">
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">Level Hak Akses</label>
                                <select class="form-select" name="level">
                                    <option value="Kasir I">Kasir I</option>
                                    <option value="Kasir II">Kasir II</option>
                                    <option value="Supervisor">Supervisor</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label-custom">Status Akun</label>
                                <select class="form-select" name="status">
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </select>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <button type="submit" class="btn-primary-custom"><i class="bi bi-save"></i> Simpan Akun</button>
                                <a href="users.php?tab=list" class="btn-light-custom">Batal</a>
                            </div>
                        </form>
                    </div>

                <?php elseif ($tab === 'edit'): ?>
                    <!-- TAB: EDIT PENGGUNA (GAYA KARTU LOGIN SATU KOLOM) -->
                    <div class="auth-form-card">
                        <div class="auth-form-header">
                            <div class="icon-box">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <h3>Edit Akun Pengguna</h3>
                            <p>Perbarui informasi akun <strong><?= htmlspecialchars($data_edit['nama']); ?></strong>.</p>
                        </div>

                        <form method="POST" action="users.php?tab=edit&id=<?= $data_edit['id_user']; ?>">
                            <input type="hidden" name="aksi" value="edit">
                            <input type="hidden" name="id_user" value="<?= $data_edit['id_user']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label-custom">Nama Lengkap</label>
                                <input type="text" class="form-control" name="nama" value="<?= htmlspecialchars($data_edit['nama']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">Username</label>
                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($data_edit['username']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">Password Baru <span style="font-weight: normal; color: var(--text-muted); font-size: 11px;">(Opsional)</span></label>
                                <input type="password" class="form-control" name="password" placeholder="Biarkan kosong jika tetap">
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">Level Hak Akses</label>
                                <select class="form-select" name="level">
                                    <option value="Kasir I" <?= ($data_edit['level'] === 'Kasir I') ? 'selected' : ''; ?>>Kasir I</option>
                                    <option value="Kasir II" <?= ($data_edit['level'] === 'Kasir II') ? 'selected' : ''; ?>>Kasir II</option>
                                    <option value="Supervisor" <?= ($data_edit['level'] === 'Supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label-custom">Status Akun</label>
                                <select class="form-select" name="status">
                                    <option value="aktif" <?= ($data_edit['status'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="nonaktif" <?= ($data_edit['status'] === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                </select>
                            </div>

                            <div class="d-flex flex-column gap-2">
                                <button type="submit" class="btn-primary-custom"><i class="bi bi-check2-circle"></i> Simpan Perubahan</button>
                                <a href="users.php?tab=list" class="btn-light-custom">Batal</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('posContainer').classList.toggle('sidebar-open');
}
</script>
</body>
</html>