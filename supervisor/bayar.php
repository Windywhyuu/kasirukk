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
   DATA MEMBER
========================= */

 $query_member = mysqli_query($koneksi, "
    SELECT id_pelanggan, nama_pelanggan, alamat, no_hp
    FROM pelanggan
    WHERE status_member = 'Member'
    ORDER BY nama_pelanggan ASC
");

?>


<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pembayaran Transaksi | GELORA WYNE</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">

    <style>

        body {
            background: #F3EEE6;
            color: #293331;
            font-family: Arial, sans-serif;
            margin: 0;
        }

        .navbar-custom {
            height: 64px;
            background: #FFFFFF;
            border-bottom: 1px solid #E4DED5;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .brand img {
            width: 36px; height: 36px;
            object-fit: contain;
            background: #F3EEE6;
            border-radius: 9px;
            padding: 3px;
        }

        .brand-text {
            color: #365C63;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .main {
            max-width: 1180px;
            margin: auto;
            padding: 30px 32px;
        }

        .page-header {
            margin-bottom: 26px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .page-title { font-size: 24px; font-weight: 700; margin: 0; }

        .page-subtitle {
            color: #777A76;
            font-size: 14px;
            margin-top: 5px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #365C63;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            background: #FFFFFF;
            border: 1px solid #E0DAD2;
            padding: 9px 15px;
            border-radius: 9px;
            transition: .2s;
        }

        .back-btn:hover { background: #F3EEE6; color: #293331; }

        .section {
            background: #FFFFFF;
            border: 1px solid #E2DCD4;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(70,55,45,.04);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .st-icon {
            width: 33px; height: 33px;
            border-radius: 9px;
            background: #F3EEE6;
            color: #365C63;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .st-icon svg { width: 17px; height: 17px; }

        .payment-col { position: sticky; top: 20px; }

        .produk {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 13px 0;
            border-bottom: 1px solid #EEEAE4;
        }

        .produk:first-child { padding-top: 0; }
        .produk:last-child { border-bottom: none; padding-bottom: 0; }

        .produk-qty {
            width: 27px; height: 27px; min-width: 27px;
            background: #F3EEE6;
            color: #594A3F;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
        }

        .produk-nama { font-size: 14px; font-weight: 600; }

        .produk-detail {
            color: #888A86;
            font-size: 12.5px;
            margin-top: 4px;
        }

        .subtotal {
            color: #365C63;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            margin-left: auto;
        }

        .total-area {
            background: #F7F4EE;
            border: 1px dashed #D9D2C7;
            border-radius: 12px;
            margin-top: 20px;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .total-label { color: #777A76; font-size: 13px; }

        .total {
            color: #365C63;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .form-select, .form-control {
            border: 1px solid #D9D4CC;
            border-radius: 8px;
            min-height: 43px;
            font-size: 14px;
            background: #FFFFFF;
        }

        .form-select:focus, .form-control:focus {
            border-color: #365C63;
            box-shadow: 0 0 0 3px rgba(54,92,99,.08);
        }

        .jenis-options, .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .jenis-option, .payment-option {
            border: 1.5px solid #DDD8D0;
            border-radius: 12px;
            padding: 13px 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 11px;
            transition: .2s;
        }

        .jenis-option:hover, .payment-option:hover { background: #FAF8F4; }

        .jenis-option input, .payment-option input { display: none; }

        .jenis-option.selected, .payment-option.selected {
            border-color: #365C63;
            background: #EEF3F3;
            box-shadow: 0 0 0 3px rgba(54,92,99,.08);
        }

        .jenis-icon, .pay-icon {
            width: 38px; height: 38px; min-width: 38px;
            background: #F3EEE6;
            border-radius: 10px;
            color: #365C63;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .jenis-icon svg, .pay-icon svg { width: 19px; height: 19px; }

        .jenis-nama, .pay-name { font-weight: 700; font-size: 14px; }

        .jenis-ket, .pay-desc {
            font-size: 11px;
            color: #8A8D89;
            margin-top: 2px;
        }

        .umum-note {
            padding: 11px 13px;
            background: #F7F4EE;
            border-left: 3px solid #B49A67;
            border-radius: 8px;
            font-size: 12px;
            color: #6D716D;
            line-height: 1.7;
        }

        .input-cari { position: relative; margin-bottom: 9px; }

        .input-cari svg {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px; height: 15px;
            color: #8A8D89;
            pointer-events: none;
        }

        .input-cari input { padding-left: 38px; }

        .badge-member {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .6px;
            padding: 3px 8px;
            border-radius: 20px;
            background: #F5EEDC;
            color: #8A6D2E;
            border: 1px solid #DDC98F;
        }

        .customer-info {
            display: none;
            margin-top: 9px;
            padding: 11px 13px;
            background: #F7F4EE;
            border-left: 3px solid #B49A67;
            border-radius: 8px;
            font-size: 12px;
            color: #6D716D;
            line-height: 1.8;
        }

        .info-atas {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 3px;
        }

        .input-money { position: relative; }

        .money-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8A8D89;
            font-size: 14px;
            font-weight: 600;
            pointer-events: none;
        }

        .input-money input { padding-left: 43px; }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] { -moz-appearance: textfield; }

        .qris-note {
            display: none;
            margin-top: 9px;
            padding: 11px 13px;
            background: #EEF3F3;
            border-left: 3px solid #365C63;
            border-radius: 8px;
            font-size: 12px;
            color: #4E6A6F;
            line-height: 1.7;
        }

        .change {
            margin-top: 18px;
            background: #F7F4EE;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .change-label { color: #777A76; font-size: 13px; }

        .change-value {
            color: #365C63;
            font-size: 18px;
            font-weight: 800;
        }

        .change-value.kurang {
            color: #C0504D;
            font-size: 14px;
            font-weight: 700;
        }

        .btn-pay {
            background: #365C63;
            border: none;
            color: white;
            border-radius: 9px;
            min-height: 45px;
            font-size: 14px;
            font-weight: 600;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s;
        }

        .btn-pay:hover { background: #2D4F54; color: white; }
        .btn-pay:disabled { opacity: .75; }

        .btn-cancel {
            background: white;
            border: 1px solid #D8D3CB;
            color: #555B58;
            border-radius: 9px;
            min-height: 45px;
            font-size: 14px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            transition: .2s;
        }

        .btn-cancel:hover { background: #F7F4EE; color: #293331; }

        .spinner {
            width: 15px; height: 15px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #FFFFFF;
            border-radius: 50%;
            animation: putar .7s linear infinite;
        }

        @keyframes putar { to { transform: rotate(360deg); } }

        .empty {
            text-align: center;
            color: #858783;
            font-size: 14px;
            padding: 38px 10px;
        }

        @media (max-width: 991px) {

            .payment-col { position: static; }

        }

        @media (max-width: 768px) {

            .main { padding: 22px 16px; }
            .section { padding: 20px; }
            .page-title { font-size: 21px; }

            .jenis-options, .payment-options { grid-template-columns: 1fr; }
            .total { font-size: 21px; }

        }

    </style>

</head>

<body>


<nav class="navbar-custom">

    <div class="brand">

        <img src="../assets/img/logo.jpeg" alt="GELORA WYNE">

        <span class="brand-text">GELORA WYNE</span>

    </div>

</nav>


<main class="main">

    <div class="page-header">

        <div>
            <h1 class="page-title">Pembayaran Transaksi</h1>

            <div class="page-subtitle">
                Periksa pesanan sebelum pembayaran disimpan
            </div>
        </div>

        <a href="kasir.php" class="back-btn">

            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>

            Kembali ke Produk

        </a>

    </div>


    <div class="row g-4">


        <!-- PESANAN -->

        <div class="col-lg-7">

            <div class="section">

                <div class="section-title">

                    <span class="st-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </span>

                    Rincian Pesanan

                </div>

                <div id="daftarPesanan">
                    <div class="empty">Memuat barang...</div>
                </div>


                <div class="total-area">

                    <div>

                        <div class="total-label">Total belanja</div>

                        <div id="jumlahItem" class="total-label mt-1">0 barang</div>

                    </div>

                    <div id="totalHarga" class="total">Rp 0</div>

                </div>

            </div>

        </div>


        <!-- PEMBAYARAN -->

        <div class="col-lg-5">

            <div class="payment-col">

                <div class="section">

                    <div class="section-title">

                        <span class="st-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </span>

                        Pembayaran

                    </div>


                    <!-- JENIS PELANGGAN -->

                    <div class="mb-4">

                        <label class="form-label">Jenis pelanggan</label>

                        <div class="jenis-options">


                            <label class="jenis-option selected" id="optUmum">

                                <input type="radio" name="jenisPelanggan" value="Umum" checked onchange="pilihJenis('Umum')">

                                <span class="jenis-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>

                                <span>
                                    <span class="jenis-nama d-block">Pelanggan Umum</span>
                                    <span class="jenis-ket d-block">Beli langsung, tanpa member</span>
                                </span>

                            </label>


                            <label class="jenis-option" id="optMember">

                                <input type="radio" name="jenisPelanggan" value="Member" onchange="pilihJenis('Member')">

                                <span class="jenis-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </span>

                                <span>
                                    <span class="jenis-nama d-block">Member</span>
                                    <span class="jenis-ket d-block">Terdaftar di database</span>
                                </span>

                            </label>


                        </div>

                    </div>


                    <!-- AREA UMUM -->

                    <div id="areaUmum" class="mb-4" style="display:block">

                        <div class="umum-note">
                            Transaksi akan disimpan sebagai <strong>Pelanggan Umum</strong>.
                            Isi nama di bawah jika ingin datanya tercatat.
                        </div>


                        <div style="margin-top:10px;">

                            <label class="form-label">Nama pembeli (opsional)</label>

                            <input
                                type="text"
                                id="namaUmum"
                                class="form-control"
                                placeholder="Kosongkan jika tidak perlu dicatat"
                                autocomplete="off">

                        </div>


                        <div style="margin-top:10px;">

                            <label class="form-label">No. HP (opsional)</label>

                            <input
                                type="text"
                                id="hpUmum"
                                class="form-control"
                                placeholder="Agar tercatat sebagai pelanggan"
                                autocomplete="off">

                        </div>

                    </div>


                    <!-- AREA MEMBER -->

                    <div id="areaMember" class="mb-4" style="display:none">

                        <label class="form-label">Data member</label>


                        <div class="input-cari">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>

                            <input
                                type="text"
                                id="cariPelanggan"
                                class="form-control"
                                placeholder="Cari nama member..."
                                autocomplete="off"
                                oninput="filterPelanggan()">

                        </div>


                        <select
                            id="idPelanggan"
                            class="form-select"
                            onchange="tampilkanPelanggan()">

                            <option value="">— Pilih member —</option>

                            <?php while ($m = mysqli_fetch_assoc($query_member)) { ?>

                                <option
                                    value="<?= $m['id_pelanggan']; ?>"
                                    data-nama="<?= htmlspecialchars($m['nama_pelanggan']); ?>"
                                    data-alamat="<?= htmlspecialchars($m['alamat']); ?>"
                                    data-hp="<?= htmlspecialchars($m['no_hp']); ?>">

                                    <?= htmlspecialchars($m['nama_pelanggan']); ?>

                                </option>

                            <?php } ?>

                        </select>


                        <div id="customerInfo" class="customer-info"></div>


                        <div class="umum-note" style="margin-top:9px;">
                            Member tidak ada di daftar? Daftarkan dulu lewat menu
                            <strong>Member → Tambah Member</strong>.
                        </div>

                    </div>


                    <!-- METODE -->

                    <div class="mb-4">

                        <label class="form-label">Metode pembayaran</label>


                        <div class="payment-options">


                            <label class="payment-option selected">

                                <input type="radio" name="metode" value="Tunai" checked onchange="ubahMetode()">

                                <span class="pay-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                                </span>

                                <span>
                                    <span class="pay-name d-block">Tunai</span>
                                    <span class="pay-desc d-block">Uang cash</span>
                                </span>

                            </label>


                            <label class="payment-option">

                                <input type="radio" name="metode" value="QRIS" onchange="ubahMetode()">

                                <span class="pay-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><line x1="14" y1="14" x2="14" y2="14.01"/><line x1="18" y1="14" x2="18" y2="14.01"/><line x1="21" y1="14" x2="21" y2="14.01"/><line x1="14" y1="18" x2="14" y2="18.01"/><line x1="18" y1="18" x2="18" y2="18.01"/><line x1="21" y1="18" x2="21" y2="18.01"/><line x1="14" y1="21" x2="14" y2="21.01"/><line x1="18" y1="21" x2="18" y2="21.01"/><line x1="21" y1="21" x2="21" y2="21.01"/></svg>
                                </span>

                                <span>
                                    <span class="pay-name d-block">QRIS</span>
                                    <span class="pay-desc d-block">Scan & bayar</span>
                                </span>

                            </label>


                        </div>


                        <div id="qrisNote" class="qris-note">
                            Pelanggan akan memindai kode QR.
                            Nominal otomatis sama dengan total belanja.
                        </div>

                    </div>


                    <!-- BAYAR -->

                    <div id="bagianTunai" class="mb-4">

                        <label class="form-label">Uang bayar</label>

                        <div class="input-money">

                            <span class="money-prefix">Rp</span>

                            <input
                                type="number"
                                id="uangBayar"
                                class="form-control"
                                placeholder="Masukkan nominal"
                                min="0"
                                oninput="hitungKembalian()">

                        </div>

                    </div>


                    <!-- KEMBALIAN -->

                    <div class="change">

                        <span class="change-label">Kembalian</span>

                        <span id="kembalian" class="change-value">Rp 0</span>

                    </div>


                    <!-- BUTTON -->

                    <div class="row g-2 mt-4">

                        <div class="col-5">

                            <button type="button" class="btn-cancel" onclick="window.location.href='kasir.php'">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>

                                Batal

                            </button>

                        </div>

                        <div class="col-7">

                            <button type="button" class="btn-pay" onclick="prosesPembayaran()">

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>

                                Bayar & Simpan

                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</main>


<script src="../assets/js/bootstrap.bundle.min.js"></script>

<script>

/* =========================
   DATA
========================= */

let keranjang = [];
let totalHarga = 0;
let totalItem = 0;
let jenisPelanggan = "Umum";


/* =========================
   LOAD KERANJANG
========================= */

document.addEventListener("DOMContentLoaded", function () {

    const data = sessionStorage.getItem("keranjangGeloraWyne");


    if (!data) {
        document.getElementById("daftarPesanan").innerHTML =
            '<div class="empty">Keranjang kosong. <br><a href="kasir.php">Pilih produk dulu →</a></div>';
        return;
    }


    try {
        keranjang = JSON.parse(data);
    } catch (e) {
        document.getElementById("daftarPesanan").innerHTML =
            '<div class="empty">Keranjang kosong. <br><a href="kasir.php">Pilih produk dulu →</a></div>';
        return;
    }


    if (!Array.isArray(keranjang) || keranjang.length === 0) {
        document.getElementById("daftarPesanan").innerHTML =
            '<div class="empty">Keranjang kosong. <br><a href="kasir.php">Pilih produk dulu →</a></div>';
        return;
    }


    tampilkanPesanan();

});


/* =========================
   TAMPILKAN PESANAN
========================= */

function tampilkanPesanan() {

    const container = document.getElementById("daftarPesanan");

    container.innerHTML = "";
    totalHarga = 0;
    totalItem = 0;


    keranjang.forEach(function (item) {

        const jumlah = Number(item.jumlah) || 0;
        const harga = Number(item.harga) || 0;
        const subtotal = jumlah * harga;

        totalHarga += subtotal;
        totalItem += jumlah;


        const div = document.createElement("div");
        div.className = "produk";


        div.innerHTML = `

            <div class="produk-qty">${jumlah}</div>


            <div class="flex-grow-1">

                <div class="produk-nama">${escapeHTML(item.nama)}</div>

                <div class="produk-detail">Rp ${harga.toLocaleString("id-ID")} / item</div>

            </div>


            <div class="subtotal">Rp ${subtotal.toLocaleString("id-ID")}</div>

        `;


        container.appendChild(div);

    });


    document.getElementById("totalHarga").innerText =
        "Rp " + totalHarga.toLocaleString("id-ID");


    document.getElementById("jumlahItem").innerText =
        totalItem + " barang";


    hitungKembalian();

}


/* =========================
   PILIH JENIS PELANGGAN
========================= */

function pilihJenis(jenis) {

    jenisPelanggan = jenis;


    document.getElementById("optUmum").classList.toggle("selected", jenis === "Umum");
    document.getElementById("optMember").classList.toggle("selected", jenis === "Member");


    document.getElementById("areaUmum").style.display =
        (jenis === "Umum") ? "block" : "none";


    document.getElementById("areaMember").style.display =
        (jenis === "Member") ? "block" : "none";

}


/* =========================
   CARI MEMBER
========================= */

function filterPelanggan() {

    const kata =
        document.getElementById("cariPelanggan").value.toLowerCase().trim();

    const select = document.getElementById("idPelanggan");

    let jumlahTampil = 0;


    select.querySelectorAll("option").forEach(function (opt) {

        if (!opt.value) return;

        const cocok =
            kata === "" ||
            opt.textContent.toLowerCase().includes(kata);

        opt.style.display = cocok ? "" : "none";

        if (cocok) jumlahTampil++;

    });

}


/* =========================
   INFO MEMBER
========================= */

function tampilkanPelanggan() {

    const select = document.getElementById("idPelanggan");

    const option = select.options[select.selectedIndex];

    const info = document.getElementById("customerInfo");


    if (!select.value) {

        info.style.display = "none";
        info.innerHTML = "";
        return;

    }


    info.innerHTML = `

        <div class="info-atas">

            <strong>${escapeHTML(option.dataset.nama)}</strong>

            <span class="badge-member">★ MEMBER</span>

        </div>


        <div>Alamat: ${escapeHTML(option.dataset.alamat || "belum diisi")}</div>


        <div>No. HP: ${escapeHTML(option.dataset.hp || "belum diisi")}</div>

    `;


    info.style.display = "block";

}


/* =========================
   METODE
========================= */

function ubahMetode() {

    const metode =
        document.querySelector('input[name="metode"]:checked').value;


    document.querySelectorAll(".payment-option").forEach(function (opt) {

        opt.classList.toggle(
            "selected",
            opt.querySelector("input").checked
        );

    });


    const bagian = document.getElementById("bagianTunai");
    const input = document.getElementById("uangBayar");
    const qrisNote = document.getElementById("qrisNote");


    if (metode === "QRIS") {

        bagian.style.display = "none";
        qrisNote.style.display = "block";

        input.value = totalHarga;

        document.getElementById("kembalian").innerText = "Rp 0";

    } else {

        bagian.style.display = "block";
        qrisNote.style.display = "none";

        input.value = "";

        hitungKembalian();

    }

}


/* =========================
   KEMBALIAN
========================= */

function hitungKembalian() {

    const metode =
        document.querySelector('input[name="metode"]:checked').value;


    if (metode === "QRIS") {

        document.getElementById("kembalian").innerText = "Rp 0";
        return;

    }


    const bayar =
        Number(document.getElementById("uangBayar").value) || 0;

    const kembalian = bayar - totalHarga;

    const el = document.getElementById("kembalian");


    if (bayar === 0) {

        el.innerText = "Rp 0";
        el.classList.remove("kurang");

    } else if (kembalian < 0) {

        el.innerText = "Uang kurang";
        el.classList.add("kurang");

    } else {

        el.innerText = "Rp " + kembalian.toLocaleString("id-ID");
        el.classList.remove("kurang");

    }

}


/* =========================
   BAYAR → SIMPAN
========================= */

function prosesPembayaran() {

    if (keranjang.length === 0) {

        alert("Keranjang masih kosong.");
        return;

    }


    let idPelanggan = 0;
    let jenisKirim = "Umum";


    if (jenisPelanggan === "Member") {

        idPelanggan =
            document.getElementById("idPelanggan").value;


        if (!idPelanggan) {

            alert("Silakan pilih member terlebih dahulu, atau ubah jenis pelanggan ke Pelanggan Umum.");
            return;

        }


        jenisKirim = "Member";

    }


    const metode =
        document.querySelector('input[name="metode"]:checked').value;


    let bayar = 0;
    let kembalian = 0;


    if (metode === "Tunai") {

        bayar =
            Number(document.getElementById("uangBayar").value) || 0;


        if (bayar <= 0) {

            alert("Masukkan uang pembayaran.");
            return;

        }


        if (bayar < totalHarga) {

            alert("Uang pembayaran masih kurang.");
            return;

        }


        kembalian = bayar - totalHarga;

    } else {

        bayar = totalHarga;
        kembalian = 0;

    }


    /* kirim ke simpan_transaksi.php */

    const form = document.createElement("form");

    form.method = "POST";
    form.action = "simpan_transaksi.php";


    function addField(nama, nilai) {

        const input = document.createElement("input");

        input.type = "hidden";
        input.name = nama;
        input.value = nilai;

        form.appendChild(input);

    }


    addField("id_pelanggan", idPelanggan);
    addField("jenis_pelanggan", jenisKirim);
    addField("nama_umum", document.getElementById("namaUmum").value.trim());
    addField("hp_umum", document.getElementById("hpUmum").value.trim());
    addField("metode", metode);
    addField("total", totalHarga);
    addField("bayar", bayar);
    addField("kembalian", kembalian);


    keranjang.forEach(function (item) {

        addField("id_produk[]", item.id);
        addField("jumlah[]", item.jumlah);
        addField("harga[]", item.harga);

    });


    document.body.appendChild(form);


    const btn = document.querySelector(".btn-pay");

    btn.disabled = true;

    btn.innerHTML =
        '<span class="spinner"></span> Memproses...';


    form.submit();

}


/* =========================
   ESCAPE HTML
========================= */

function escapeHTML(text) {

    const div = document.createElement("div");

    div.textContent = text ?? "";

    return div.innerHTML;

}

</script>


</body>
</html>