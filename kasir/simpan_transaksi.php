<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['level'] != 'Kasir I') {
    header("Location: ../login.php");
    exit;
}

include "../koneksi.php";

/* =========================
   VALIDASI DATA MASUK
========================= */
if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($_POST["id_produk"])) {
    header("Location: index.php");
    exit;
}

/* ⭐ PERUBAHAN 1: id_pelanggan = 0 artinya Pelanggan Umum */
$id_pelanggan = intval($_POST["id_pelanggan"] ?? 0);
$id_user      = intval($_SESSION["id_user"]);
$metode       = ($_POST["metode"] === "QRIS") ? "QRIS" : "Tunai";

$id_produk = $_POST["id_produk"];
$jumlah    = $_POST["jumlah"];

/* =========================
   ⭐ PERUBAHAN 2: VERIFIKASI MEMBER
   Kalau katanya member, pastikan ID-nya benar-benar ada di tabel pelanggan.
   Kalau tidak ada → anggap Pelanggan Umum.
========================= */
if ($id_pelanggan > 0) {
    $cek = mysqli_query($koneksi, "
        SELECT id_pelanggan
        FROM pelanggan
        WHERE id_pelanggan = '$id_pelanggan'
    ");

    if (mysqli_num_rows($cek) == 0) {
        $id_pelanggan = 0;
    }
}

/* ⭐ PERUBAHAN 3:
   Pelanggan Umum → kirim NULL ke database, bukan angka 0,
   supaya tidak bentrok dengan foreign key tabel pelanggan.
*/
$id_db = ($id_pelanggan > 0) ? $id_pelanggan : "NULL";

/* =========================
   VERIFIKASI ULANG PRODUK
   (harga & stok dari DB)
========================= */
$items = [];

for ($i = 0; $i < count($id_produk); $i++) {
    $pid = intval($id_produk[$i]);
    $qty = intval($jumlah[$i]);

    if ($pid <= 0 || $qty <= 0) {
        continue;
    }

    $q = mysqli_query($koneksi, "
        SELECT nama_produk, harga, stok
        FROM produk
        WHERE id_produk = '$pid'
    ");

    $p = mysqli_fetch_assoc($q);

    if (!$p || $p['stok'] < $qty) {
        $nama = $p ? $p['nama_produk'] : 'ID ' . $pid;

        echo "<script>
            alert('Stok \"{$nama}\" tidak mencukupi. Transaksi dibatalkan.');
            window.location.href = 'index.php';
        </script>";
        exit;
    }

    $items[] = [
        'id'       => $pid,
        'nama'     => $p['nama_produk'],
        'harga'    => intval($p['harga']),
        'jumlah'   => $qty,
        'subtotal' => intval($p['harga']) * $qty
    ];
}

if (count($items) === 0) {
    header("Location: index.php");
    exit;
}

$total = array_sum(array_column($items, 'subtotal'));

/* =========================
   GENERATE KODE TRANSAKSI
========================= */
$kode_transaksi = "TRX-" . date("Ymd") . "-" . date("His") . "-" . substr(md5(uniqid(mt_rand(), true)), 0, 4);

/* =========================
   HITUNG ULANG PEMBAYARAN
========================= */
if ($metode === "QRIS") {
    $bayar     = $total;
    $kembalian = 0;
} else {
    $bayar = intval($_POST["bayar"] ?? 0);

    if ($bayar < $total) {
        echo "<script>
            alert('Uang pembayaran kurang dari total belanja.');
            window.location.href = 'transaksi.php';
        </script>";
        exit;
    }

    $kembalian = $bayar - $total;
}

/* =========================
   SIMPAN KE TABEL
   penjualan + detailpenjualan + update pelanggan
========================= */
mysqli_begin_transaction($koneksi);

$berhasil = true;

do {
    /* --- 1. Header: Tabel Penjualan --- */
    $simpan = mysqli_query($koneksi, "
        INSERT INTO penjualan
            (kode_transaksi, id_pelanggan, id_user, total_harga, bayar, kembalian, metode_pembayaran)
        VALUES
            ('$kode_transaksi', $id_db, '$id_user', '$total', '$bayar', '$kembalian', '$metode')
    ");

    if (!$simpan) {
        $berhasil = false;
        break;
    }

    $id_penjualan = mysqli_insert_id($koneksi);

    /* --- 2. Detail: Tabel Detailpenjualan & Potong Stok --- */
    foreach ($items as $item) {
        $pid = $item['id'];
        $qty = $item['jumlah'];
        $hrg = $item['harga'];
        $sub = $item['subtotal'];

        $detail = mysqli_query($koneksi, "
            INSERT INTO detailpenjualan
                (id_penjualan, id_produk, jumlah, harga_satuan, subtotal)
            VALUES
                ('$id_penjualan', '$pid', '$qty', '$hrg', '$sub')
        ");

        if (!$detail) {
            $berhasil = false;
            break;
        }

        $update_stok = mysqli_query($koneksi, "
            UPDATE produk
            SET stok = stok - $qty
            WHERE id_produk = '$pid'
        ");

        if (!$update_stok) {
            $berhasil = false;
            break;
        }
    }

  /* --- 3. ⭐ UPDATE OTOMATIS KUNJUNGAN & POIN KELIPATAN 5 TRANSAKSI --- */
    if ($id_pelanggan > 0) {
        // Ambil jumlah kunjungan pelanggan saat ini dari database
        $q_kunjungan = mysqli_query($koneksi, "
            SELECT jumlah_kunjungan 
            FROM pelanggan 
            WHERE id_pelanggan = '$id_pelanggan'
        ");
        $data_p = mysqli_fetch_assoc($q_kunjungan);
        $kunjungan_lama = intval($data_p['jumlah_kunjungan'] ?? 0);
        
        // Hitung kunjungan baru (ditambah 1)
        $kunjungan_baru = $kunjungan_lama + 1;

        // Cek apakah kunjungan baru merupakan kelipatan 5 (transaksi ke-5, ke-10, ke-15, dst)
        $tambah_poin = ($kunjungan_baru % 5 === 0) ? 1 : 0;

        $update_pelanggan = mysqli_query($koneksi, "
            UPDATE pelanggan
            SET jumlah_kunjungan = '$kunjungan_baru',
                poin = poin + $tambah_poin
            WHERE id_pelanggan = '$id_pelanggan'
        ");

        if (!$update_pelanggan) {
            $berhasil = false;
            break;
        }
    }
    
} while (false);

/* =========================
   HASIL TRANSAKSI
========================= */
if ($berhasil) {
    mysqli_commit($koneksi);

    echo "<script>
        sessionStorage.removeItem('keranjangGeloraWyne');
        window.location.href = 'rincian.php?id_penjualan={$id_penjualan}&status=sukses';
    </script>";
} else {
    mysqli_rollback($koneksi);

    /* Hapus komentar baris di bawah ini jika ingin debugging error MySQL:
    die("MySQL Error: " . mysqli_error($koneksi));
    */

    echo "<script>
        alert('Gagal menyimpan transaksi. Silakan coba lagi.');
        window.location.href = 'transaksi.php';
    </script>";
}
?>