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
   DATA PRODUK
   (semua produk aktif yang ada stok)
========================= */

 $query_produk = mysqli_query($koneksi, "
    SELECT id_produk, kode_produk, nama_produk, kategori, harga, stok
    FROM produk
    WHERE stok > 0
    AND status = 'Aktif'
    ORDER BY nama_produk ASC
");

?>


<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mode Kasir | GELORA WYNE</title>

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

        .info-kas {
            padding: 11px 14px;
            background: #FBF3E8;
            border: 1px dashed #D9C9A8;
            border-radius: 10px;
            font-size: 12.5px;
            color: #8A6D3B;
            margin-bottom: 18px;
        }

        /* ===== SEARCH ===== */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            height: 48px;
            border: 1px solid #DED7CE;
            border-radius: 12px;
            padding: 0 18px 0 48px;
            background: white;
            outline: none;
        }

        .search-box input:focus {
            border-color: #B49A67;
            box-shadow: 0 0 0 3px rgba(180,154,103,.12);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 14px;
            color: #8A7B70;
        }

        .search-icon svg { width: 20px; height: 20px; }

        /* ===== PRODUK ===== */
        .product-wrapper {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 22px;
            align-items: start;
        }

        .products {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .product-card {
            background: white;
            border: 1px solid #E4DED6;
            border-radius: 14px;
            overflow: hidden;
            transition: .2s;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(70,55,45,.08);
        }

        .product-info { padding: 14px; }

        .product-code {
            font-size: 11px;
            color: #9A8C81;
            margin-bottom: 5px;
            font-family: monospace;
        }

        .product-name {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 5px;
            min-height: 34px;
        }

        .product-category {
            font-size: 11px;
            color: #6C827C;
            margin-bottom: 9px;
        }

        .product-price {
            color: #365C63;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .product-stock {
            color: #8A7B70;
            font-size: 11px;
            margin-bottom: 12px;
        }

        .btn-add {
            width: 100%;
            border: none;
            background: #365C63;
            color: white;
            border-radius: 9px;
            height: 37px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: .2s;
        }

        .btn-add svg { width: 14px; height: 14px; }
        .btn-add:hover { background: #2D4F54; }

        /* ===== KERANJANG ===== */
        .cart-box {
            background: white;
            border: 1px solid #E2DCD4;
            border-radius: 16px;
            padding: 20px;
            position: sticky;
            top: 98px;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #E8E2DA;
            margin-bottom: 15px;
        }

        .cart-title {
            font-size: 17px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cart-title svg { width: 18px; height: 18px; flex-shrink: 0; }

        .cart-count {
            background: #EADCCB;
            color: #594A3F;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: 700;
        }

        .cart-empty {
            text-align: center;
            color: #9A8C81;
            padding: 35px 10px;
            font-size: 13px;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 0;
            border-bottom: 1px solid #EEE9E2;
        }

        .cart-item-name { font-size: 13px; font-weight: 600; }

        .cart-item-price {
            font-size: 11px;
            color: #8A7B70;
            margin-top: 3px;
        }

        .qty-control {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 7px;
        }

        .qty-btn {
            width: 24px; height: 24px;
            border: 1px solid #DDD5CB;
            background: #F8F5F1;
            color: #594A3F;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s;
        }

        .qty-btn:hover { background: #EADCCB; }
        .qty-btn svg { width: 13px; height: 13px; }

        .qty-number { font-size: 12px; min-width: 15px; text-align: center; }

        .cart-subtotal {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 17px 0;
            margin-top: 5px;
            border-top: 1px solid #E8E2DA;
            font-size: 15px;
            font-weight: 700;
        }

        .total-price {
            color: #365C63;
            font-size: 19px;
        }

        .btn-payment {
            width: 100%;
            height: 45px;
            border: none;
            border-radius: 10px;
            background: #B49A67;
            color: white;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s;
        }

        .btn-payment svg { width: 17px; height: 17px; }
        .btn-payment:hover { background: #A68D5D; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1100px) {

            .products { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .product-wrapper { grid-template-columns: minmax(0, 1fr) 320px; }

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

            .product-wrapper { grid-template-columns: 1fr; }
            .cart-box { position: static; }

        }

        @media (max-width: 576px) {

            .products { grid-template-columns: 1fr 1fr; gap: 10px; }

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


        <a href="kasir.php" class="active">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </span>

            <span class="menu-text">Kasir</span>

        </a>


        <a href="produk.php">

            <span class="menu-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </span>

            <span class="menu-text">Produk</span>

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


        <a href="transaksi.php">

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

            <h1 class="page-title">Mode Kasir</h1>

        </div>

        <div class="profile">

            <div class="profile-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>

            <div>
                <div class="profile-name"><?= htmlspecialchars($_SESSION['nama']); ?></div>
                <div class="profile-role">Supervisor · Kasir</div>
            </div>

        </div>

    </nav>


    <div class="content">


        <!-- INFO KAS (opsional) -->

        <div class="info-kas">
            ℹ️ Transaksi yang Anda buat di sini langsung tersimpan & tampil di Laporan.
            Untuk pencatatan laci kas, buka sesi kas dulu lewat menu <strong>Rekap Kas → Buka Kas untuk Kasir</strong>.
        </div>


        <!-- SEARCH -->

        <div class="search-box">

            <span class="search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </span>

            <input
                type="text"
                id="searchProduct"
                placeholder="Cari nama atau kode produk..."
                autocomplete="off">

        </div>


        <div class="product-wrapper">


            <!-- PRODUK -->

            <div>

                <div class="products" id="productList">


                    <?php while ($produk = mysqli_fetch_assoc($query_produk)) : ?>


                        <div
                            class="product-card"
                            data-name="<?= strtolower(htmlspecialchars($produk['nama_produk'])); ?>"
                            data-code="<?= strtolower(htmlspecialchars($produk['kode_produk'])); ?>">


                            <div class="product-info">


                                <div class="product-code">
                                    <?= htmlspecialchars($produk['kode_produk']); ?>
                                </div>


                                <div class="product-name">
                                    <?= htmlspecialchars($produk['nama_produk']); ?>
                                </div>


                                <div class="product-category">
                                    <?= htmlspecialchars($produk['kategori'] ?: '-'); ?>
                                </div>


                                <div class="product-price">
                                    Rp <?= number_format($produk['harga'], 0, ',', '.'); ?>
                                </div>


                                <div class="product-stock">
                                    Stok: <?= $produk['stok']; ?>
                                </div>


                                <button
                                    type="button"
                                    class="btn-add"
                                    onclick="tambahKeranjang(
                                        <?= $produk['id_produk']; ?>,
                                        '<?= addslashes(htmlspecialchars($produk['nama_produk'])); ?>',
                                        <?= $produk['harga']; ?>,
                                        <?= $produk['stok']; ?>
                                    )">


                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>

                                    <span>Tambah</span>

                                </button>


                            </div>

                        </div>

                    <?php endwhile; ?>


                </div>

            </div>


            <!-- KERANJANG -->

            <div class="cart-box">


                <div class="cart-header">

                    <div class="cart-title">

                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#594A3F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>

                        <span>Keranjang</span>

                    </div>

                    <span class="cart-count" id="cartCount">0 item</span>

                </div>


                <div id="cartItems">

                    <div class="cart-empty">
                        Belum ada produk di keranjang.
                    </div>

                </div>


                <div class="cart-total">

                    <span>Total</span>

                    <span class="total-price" id="totalPrice">Rp 0</span>

                </div>


                <button type="button" class="btn-payment" onclick="prosesPembayaran()">

                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>

                    <span>Proses Pembayaran</span>

                </button>


            </div>


        </div>


    </div>

</main>


<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>

/* =========================
   SIDEBAR
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


/* =========================
   KERANJANG
========================= */

let keranjang = [];


function tambahKeranjang(id, nama, harga, stok) {

    let item = keranjang.find(function (p) {
        return p.id === id;
    });

    if (item) {

        if (item.jumlah >= stok) {
            alert("Jumlah melebihi stok yang tersedia.");
            return;
        }

        item.jumlah++;

    } else {

        keranjang.push({
            id: id,
            nama: nama,
            harga: harga,
            stok: stok,
            jumlah: 1
        });

    }

    tampilkanKeranjang();

}


function tambahJumlah(index) {

    if (keranjang[index].jumlah < keranjang[index].stok) {

        keranjang[index].jumlah++;
        tampilkanKeranjang();

    } else {

        alert("Jumlah melebihi stok yang tersedia.");

    }

}


function kurangiJumlah(index) {

    if (keranjang[index].jumlah > 1) {
        keranjang[index].jumlah--;
    } else {
        keranjang.splice(index, 1);
    }

    tampilkanKeranjang();

}


function tampilkanKeranjang() {

    const cartItems = document.getElementById("cartItems");
    const cartCount = document.getElementById("cartCount");
    const totalPrice = document.getElementById("totalPrice");


    if (keranjang.length === 0) {

        cartItems.innerHTML =
            '<div class="cart-empty">Belum ada produk di keranjang.</div>';

        cartCount.innerText = "0 item";
        totalPrice.innerText = "Rp 0";

        return;

    }


    let html = "";
    let total = 0;
    let jumlahItem = 0;


    keranjang.forEach(function (item, index) {

        let subtotal = item.harga * item.jumlah;

        total += subtotal;
        jumlahItem += item.jumlah;


        html += `

            <div class="cart-item">

                <div>

                    <div class="cart-item-name">${item.nama}</div>

                    <div class="cart-item-price">Rp ${formatRupiah(item.harga)}</div>


                    <div class="qty-control">

                        <button type="button" class="qty-btn" onclick="kurangiJumlah(${index})">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>

                        </button>


                        <span class="qty-number">${item.jumlah}</span>


                        <button type="button" class="qty-btn" onclick="tambahJumlah(${index})">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>

                        </button>

                    </div>

                </div>


                <div class="cart-subtotal">Rp ${formatRupiah(subtotal)}</div>

            </div>

        `;

    });


    cartItems.innerHTML = html;
    cartCount.innerText = jumlahItem + " item";
    totalPrice.innerText = "Rp " + formatRupiah(total);

}


function formatRupiah(angka) {
    return new Intl.NumberFormat("id-ID").format(angka);
}


/* =========================
   SEARCH
========================= */

document.getElementById("searchProduct")
    .addEventListener("input", function () {

        const keyword = this.value.toLowerCase();

        document.querySelectorAll(".product-card").forEach(function (p) {

            const cocok =
                p.dataset.name.includes(keyword) ||
                p.dataset.code.includes(keyword);

            p.style.display = cocok ? "" : "none";

        });

    });


/* =========================
   LANJUT KE PEMBAYARAN
========================= */

function prosesPembayaran() {

    if (keranjang.length === 0) {

        alert("Keranjang masih kosong.");
        return;

    }


    sessionStorage.setItem(
        "keranjangGeloraWyne",
        JSON.stringify(keranjang)
    );


    window.location.href = "bayar.php";

}

</script>


</body>
</html>