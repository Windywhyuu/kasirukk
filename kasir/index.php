<?php
session_start();
include "../koneksi.php";

/* =========================
   CEK LOGIN
========================= */
if (!isset($_SESSION['id_user'])||$_SESSION['level'] != 'Kasir I') {
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
   DATA PRODUK HIJAB
========================= */
$query_produk = mysqli_query($koneksi, "
    SELECT id_produk, kode_produk, nama_produk, kategori, harga, stok, status, foto
    FROM produk
    WHERE id_produk BETWEEN 1 AND 25
    AND stok > 0
    ORDER BY id_produk ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Hijab - GELORA WYNE</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <!-- CDN Bootstrap Icons (Mengatasi Ikon Hilang) -->
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
            
            /* Warna ikonik khusus untuk tombol tambah barang */
            --btn-iconic-bg: #D4A373;
            --btn-iconic-hover: #BC6C25;
            --btn-iconic-text: #FFFFFF;
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

        /* SIDEBAR */
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

        /* MAIN LAYOUT */
        .main-wrapper {
            margin-left: 200px;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 340px;
        }

        .content-area { padding: 24px 28px; }

        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 480px;
        }

        .search-box input {
            width: 100%;
            height: 44px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0 16px 0 42px;
            font-size: 13.5px;
            outline: none;
        }

        .search-box i {
            position: absolute; left: 15px; top: 13px;
            color: var(--text-muted); font-size: 16px;
        }

        /* CATEGORY FILTER */
        .category-filter {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .cat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .cat-card i { font-size: 18px; color: var(--text-muted); }

        .cat-card .cat-name {
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
        }

        .cat-card .cat-count {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
        }

        .cat-card.active {
            background: var(--accent-brown-light);
            border-color: var(--accent-brown);
        }

        .cat-card.active i, .cat-card.active .cat-name {
            color: var(--accent-brown-dark);
        }

        /* PRODUCT GRID */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
            gap: 18px;
        }

        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .product-img-box {
            width: 100%;
            height: 130px;
            border-radius: 12px;
            overflow: hidden;
            background: #F8F9FA;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-img-box img {
            width: 100%; height: 100%; object-fit: cover;
        }

        .stock-badge {
            position: absolute;
            top: 8px; right: 8px;
            background: rgba(18, 18, 18, 0.75);
            color: #FFFFFF;
            font-size: 10.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
        }

        .product-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .product-price {
            font-size: 14px;
            font-weight: 800;
            color: var(--accent-brown-dark);
            margin-bottom: 8px;
        }

        /* TOMBOL TAMBAH DENGAN WARNA IKONIK / BERBEDA */
        .btn-add-item {
            width: 100%;
            background: var(--btn-iconic-bg);
            color: var(--btn-iconic-text);
            border: none;
            padding: 9px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 10px rgba(212, 163, 115, 0.3);
            transition: background 0.2s ease, transform 0.1s ease;
        }

        .btn-add-item:hover {
            background: var(--btn-iconic-hover);
            transform: translateY(-1px);
        }

        /* RIGHT PANEL */
        .cart-panel {
            background: var(--card-bg);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: sticky;
            top: 0;
            padding: 24px 20px;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-header h3 {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .item-count-badge {
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .cart-items-list {
            flex: 1;
            overflow-y: auto;
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .cart-item-title { font-size: 13px; font-weight: 700; }

        .cart-item-price { font-size: 12px; color: var(--text-muted); }

        .qty-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-canvas);
            padding: 4px 8px;
            border-radius: 8px;
        }

        .qty-btn {
            border: none;
            background: transparent;
            font-weight: 800;
            cursor: pointer;
        }

        .btn-lanjut-transaksi {
            width: 100%;
            background: var(--accent-brown);
            color: #FFFFFF;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }

        .btn-lanjut-transaksi:hover { background: var(--accent-brown-dark); }
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
        <a href="index.php" class="active">
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

<!-- MAIN CONTENT -->
<div class="main-wrapper">
    
    <div class="content-area">
        <div class="header-bar">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchProduct" placeholder="Cari nama hijab, pashmina, kode..." autocomplete="off">
            </div>
            <div style="font-weight: 700; font-size: 13px; color: var(--text-muted);">
                <i class="bi bi-calendar-check me-1"></i> <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- PRODUCT GRID -->
        <div class="products-grid" id="productList">
            <?php while ($produk = mysqli_fetch_assoc($query_produk)) : ?>
                <div class="product-card" 
                     data-name="<?php echo strtolower($produk['nama_produk']); ?>"
                     data-code="<?php echo strtolower($produk['kode_produk']); ?>"
                     data-category="<?php echo strtolower($produk['kategori']); ?>">
                    
                    <div class="product-img-box">
                        <span class="stock-badge">
                            <i class="bi bi-box-seam me-1"></i>Stok: <?php echo $produk['stok']; ?>
                        </span>

                        <?php if (!empty($produk['foto'])) : ?>
                            <img src="../assets/produk/<?php echo htmlspecialchars($produk['foto']); ?>" alt="Foto">
                        <?php else : ?>
                            <i class="bi bi-bag-heart" style="font-size: 36px; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="product-title"><?php echo htmlspecialchars($produk['nama_produk']); ?></div>
                        <div class="product-price">Rp <?php echo number_format($produk['harga'], 0, ',', '.'); ?></div>
                    </div>

                    <button type="button" class="btn-add-item" 
                            onclick="tambahKeranjang(
                                <?php echo $produk['id_produk']; ?>,
                                '<?php echo addslashes($produk['nama_produk']); ?>',
                                <?php echo $produk['harga']; ?>,
                                <?php echo $produk['stok']; ?>
                            )">
                        <i class="bi bi-plus-circle-fill"></i> Tambah Barang
                    </button>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="cart-panel">
        <div class="cart-header">
            <h3>
                <i class="bi bi-bag-check"></i>
                Barang Dipilih
            </h3>
            <span class="item-count-badge" id="cartCount">0 Item</span>
        </div>

        <div class="cart-items-list" id="cartItems">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-cart-x" style="font-size: 38px; opacity: 0.3;"></i>
                <p class="mt-2" style="font-size: 12.5px;">Belum ada hijab/item dipilih</p>
            </div>
        </div>

        <button type="button" class="btn-lanjut-transaksi" onclick="prosesPembayaran()">
            <span>Lanjut Transaksi</span>
            <i class="bi bi-arrow-right-circle-fill"></i>
        </button>
    </div>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
let keranjang = [];

function tambahKeranjang(id, nama, harga, stok) {
    let item = keranjang.find(p => p.id === id);
    if (item) {
        if (item.jumlah >= stok) {
            alert("Stok produk tidak mencukupi!");
            return;
        }
        item.jumlah++;
    } else {
        keranjang.push({ id, nama, harga, stok, jumlah: 1 });
    }
    tampilkanKeranjang();
}

function tambahJumlah(index) {
    if (keranjang[index].jumlah < keranjang[index].stok) {
        keranjang[index].jumlah++;
        tampilkanKeranjang();
    } else {
        alert("Stok terbatas!");
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

    if (keranjang.length === 0) {
        cartItems.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-cart-x" style="font-size: 38px; opacity: 0.3;"></i>
                <p class="mt-2" style="font-size: 12.5px;">Belum ada hijab/item dipilih</p>
            </div>`;
        cartCount.innerText = "0 Item";
        return;
    }

    let html = "";
    let totalQty = 0;

    keranjang.forEach((item, index) => {
        totalQty += item.jumlah;
        html += `
            <div class="cart-item">
                <div>
                    <div class="cart-item-title">${item.nama}</div>
                    <div class="cart-item-price">Rp ${formatRupiah(item.harga)}</div>
                </div>
                <div class="qty-controls">
                    <button class="qty-btn" onclick="kurangiJumlah(${index})">-</button>
                    <span class="qty-val">${item.jumlah}</span>
                    <button class="qty-btn" onclick="tambahJumlah(${index})">+</button>
                </div>
            </div>`;
    });

    cartItems.innerHTML = html;
    cartCount.innerText = totalQty + " Item";
}

function formatRupiah(angka) {
    return new Intl.NumberFormat("id-ID").format(angka);
}

document.getElementById("searchProduct").addEventListener("input", function() {
    const keyword = this.value.toLowerCase();
    document.querySelectorAll(".product-card").forEach(product => {
        const nama = product.dataset.name;
        const kode = product.dataset.code;
        product.style.display = (nama.includes(keyword) || kode.includes(keyword)) ? "flex" : "none";
    });
});

function filterCategory(cat, el) {
    document.querySelectorAll('.cat-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');

    document.querySelectorAll(".product-card").forEach(product => {
        if (cat === 'all' || product.dataset.category === cat) {
            product.style.display = "flex";
        } else {
            product.style.display = "none";
        }
    });
}

function prosesPembayaran() {
    if (keranjang.length === 0) {
        alert("Pilih minimal 1 item hijab untuk diproses.");
        return;
    }
    sessionStorage.setItem("keranjangGeloraWyne", JSON.stringify(keranjang));
    window.location.href = "transaksi.php";
}
</script>
</body>
</html>