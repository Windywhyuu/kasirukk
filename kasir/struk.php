<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['level'] != 'Kasir I') {
    header("Location: ../login.php");
    exit;
}

include "../koneksi.php";

/* =========================
   AMBIL ID DARI URL
========================= */

 $id_penjualan = intval($_GET['id_penjualan'] ?? 0);

if ($id_penjualan <= 0) {
    header("Location: index.php");
    exit;
}


/* =========================
   HEADER TRANSAKSI
   tabel: penjualan + pelanggan + users
========================= */

 $q_header = mysqli_query($koneksi, "
    SELECT
        pj.*,
        pl.nama_pelanggan,
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


/* =========================
   DETAIL PRODUK
   tabel: detailpenjualan + produk
========================= */

 $q_detail = mysqli_query($koneksi, "
    SELECT
        dp.jumlah,
        dp.harga_satuan,
        dp.subtotal,
        pr.nama_produk
    FROM detailpenjualan dp
    JOIN produk pr ON pr.id_produk = dp.id_produk
    WHERE dp.id_penjualan = '$id_penjualan'
    ORDER BY dp.id_detail ASC
");


/* =========================
   NOMOR STRUK
   pakai kode_transaksi dari DB
========================= */

 $no_struk = $header['kode_transaksi'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>Struk <?= htmlspecialchars($no_struk); ?> | GELORA WYNE</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: #E8E4DC;
            color: #000;
            padding: 20px 10px;
        }


        /* =========================
           STRUK (kertas thermal)
        ========================= */

        .struk {
            width: 300px;
            max-width: 100%;
            margin: auto;
            background: #fff;
            padding: 18px 16px;
            font-size: 12px;
            line-height: 1.55;
            box-shadow: 0 3px 14px rgba(0,0,0,.15);
        }


        /* HEADER TOKO */

        .toko {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
            margin-bottom: 10px;
        }

        .toko-nama {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 1.5px;
        }

        .toko-alamat {
            font-size: 10.5px;
        }


        /* INFO TRANSAKSI */

        .info {
            font-size: 11px;
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .info-row span:first-child {
            flex-shrink: 0;
        }

        .info-row span:last-child {
            text-align: right;
            word-break: break-word;
        }


        /* DAFTAR ITEM */

        .item {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 8px 0;
            margin-bottom: 8px;
        }

        .item-nama {
            font-weight: bold;
            font-size: 11.5px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }


        /* TOTAL */

        .total {
            margin-bottom: 10px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 1.5px 0;
        }

        .total-row.besar {
            font-size: 14px;
            font-weight: bold;
            border-top: 1px dashed #000;
            margin-top: 5px;
            padding-top: 6px;
        }


        /* FOOTER */

        .footer {
            text-align: center;
            font-size: 11px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }


        /* =========================
           TOOLBAR
           (tidak ikut tercetak)
        ========================= */

        .toolbar {
            width: 300px;
            max-width: 100%;
            margin: 0 auto 14px;
            display: flex;
            gap: 8px;
        }

        .toolbar button {
            flex: 1;
            padding: 10px;
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .toolbar button svg {
            width: 15px;
            height: 15px;
        }

        .btn-print {
            background: #365C63;
            color: #fff;
        }

        .btn-close {
            background: #fff;
            color: #555;
        }


        /* =========================
           MODE CETAK
           (hanya struk yang keluar)
        ========================= */

        @media print {

            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .struk {
                box-shadow: none;
                width: 100%;
                padding: 0;
            }

            @page {
                margin: 4mm;
            }

        }

    </style>
</head>

<body>

<!-- =========================
     TOOLBAR
     (hilang saat dicetak)
========================= -->

<div class="toolbar">

    <button class="btn-print" onclick="window.print()">

        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>

        Cetak

    </button>

    <button class="btn-close" onclick="window.close()">

        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>

        Tutup

    </button>

</div>


<!-- =========================
     STRUK
========================= -->

<div class="struk">

    <!-- HEADER TOKO -->

    <div class="toko">

        <div class="toko-nama">
            GELORA WYNE
        </div>

        <div class="toko-alamat">
            Jl. Contoh Alamat No. 123<br>
            Telp. 0812-3456-7890
        </div>

    </div>


    <!-- INFO TRANSAKSI -->

    <div class="info">

        <div class="info-row">
            <span>No. Struk</span>
            <span><?= htmlspecialchars($no_struk); ?></span>
        </div>

        <div class="info-row">
            <span>Tanggal</span>
            <span><?= date("d/m/Y H:i", strtotime($header['tanggal_penjualan'])); ?></span>
        </div>

        <div class="info-row">
            <span>Kasir</span>
            <span><?= htmlspecialchars($header['nama_kasir'] ?? '-'); ?></span>
        </div>

        <div class="info-row">
            <span>Pelanggan</span>
            <span><?= htmlspecialchars($header['nama_pelanggan'] ?? '-'); ?></span>
        </div>

        <div class="info-row">
            <span>Metode</span>
            <span><?= htmlspecialchars($header['metode_pembayaran']); ?></span>
        </div>

    </div>


    <!-- DAFTAR ITEM -->

    <div class="item">

        <?php while ($d = mysqli_fetch_assoc($q_detail)) : ?>

            <div class="item-nama">
                <?= htmlspecialchars($d['nama_produk']); ?>
            </div>

            <div class="item-row">
                <span>
                    <?= $d['jumlah']; ?> x
                    <?= number_format($d['harga_satuan'], 0, ',', '.'); ?>
                </span>
                <span>
                    <?= number_format($d['subtotal'], 0, ',', '.'); ?>
                </span>
            </div>

        <?php endwhile; ?>

    </div>


    <!-- TOTAL -->

    <div class="total">

        <div class="total-row besar">
            <span>TOTAL</span>
            <span>Rp <?= number_format($header['total_harga'], 0, ',', '.'); ?></span>
        </div>

        <div class="total-row">
            <span>Bayar (<?= htmlspecialchars($header['metode_pembayaran']); ?>)</span>
            <span>Rp <?= number_format($header['bayar'], 0, ',', '.'); ?></span>
        </div>

        <div class="total-row">
            <span>Kembalian</span>
            <span>Rp <?= number_format($header['kembalian'], 0, ',', '.'); ?></span>
        </div>

    </div>


    <!-- FOOTER -->

    <div class="footer">

        Terima kasih telah berbelanja<br>
        <strong>GELORA WYNE</strong><br><br>

        *** Barang yang sudah dibeli<br>
        tidak dapat dikembalikan ***

    </div>

</div>


<script>

/* =========================
   CETAK OTOMATIS
   saat halaman dibuka
========================= */

window.addEventListener("load", function () {

    setTimeout(function () {

        window.print();

    }, 400);

});

</script>

</body>
</html>