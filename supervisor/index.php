<?php
session_start();
include "../koneksi.php";

/* =========================
   CEK LOGIN ADMIN / KASIR
========================= */
if (!isset($_SESSION['id_user']) || !in_array($_SESSION['level'], ['Supervisor'])) {
    header("Location: ../login.php");
    exit;
}

/* =========================
   CEK KAS (OPSIONAL)
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
   DATA PRODUK HIJAB (SUMBER 1)
========================= */
$query_produk = mysqli_query($koneksi, "
    SELECT id_produk, kode_produk, nama_produk, kategori, harga, stok, status, foto
    FROM produk
    WHERE id_produk BETWEEN 1 AND 25
    AND stok > 0
    ORDER BY id_produk ASC
");

/* =========================
   AMBIL KATEGORI UNIK DARI PRODUK
========================= */
$kategori_query = mysqli_query($koneksi, "SELECT DISTINCT kategori FROM produk WHERE id_produk BETWEEN 1 AND 25 AND stok > 0");
$kategori_list = [];
while ($row = mysqli_fetch_assoc($kategori_query)) {
    if (!empty($row['kategori'])) {
        $kategori_list[] = $row['kategori'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin/POS Kasir Hijab - GELORA WYNE</title>
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
            
            --btn-iconic-bg: #D4A373;
            --btn-iconic-hover: #BC6C25;
            --btn-iconic-text: #FFFFFF;
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
            grid-template-columns: 80px 1fr 400px;
            height: 100vh;
            width: 100vw;
            transition: grid-template-columns 0.3s ease;
        }

        .pos-container.sidebar-open {
            grid-template-columns: 280px 1fr 400px;
        }

        @media (max-width: 1200px) {
            .pos-container {
                grid-template-columns: 70px 1fr 350px;
            }
            .pos-container.sidebar-open {
                grid-template-columns: 250px 1fr 350px;
            }
        }

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

        .pos-main {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            background: var(--bg-light);
        }

        .main-sticky-header {
            padding: 24px 28px 12px 28px;
            background: var(--bg-light);
            flex-shrink: 0;
            z-index: 10;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 480px;
        }

        .search-box input {
            width: 100%;
            height: 44px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0 16px 0 42px;
            color: var(--text-main);
            font-size: 13.5px;
            outline: none;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .search-box input:focus {
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 3px rgba(140, 109, 88, 0.15);
        }

        .search-box i {
            position: absolute;
            left: 15px; top: 13px;
            color: var(--text-muted);
            font-size: 16px;
        }

        .category-scroll {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
        }

        .category-scroll::-webkit-scrollbar { height: 4px; }
        .category-scroll::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 2px; }

        .cat-chip {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 7px 16px;
            border-radius: 20px;
            cursor: pointer;
            white-space: nowrap;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .cat-chip.active, .cat-chip:hover {
            background: var(--accent-brown);
            color: #fff;
            border-color: var(--accent-brown);
        }

        .products-scroll-area {
            flex: 1;
            overflow-y: auto;
            padding: 10px 28px 24px 28px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(175px, 1fr));
            gap: 16px;
        }

        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, border-color 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .product-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent-brown);
            box-shadow: 0 4px 12px rgba(140, 109, 88, 0.12);
        }

        .product-img-box {
            width: 100%;
            height: 120px;
            border-radius: 10px;
            overflow: hidden;
            background: #F8F9FA;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .product-img-box img { width: 100%; height: 100%; object-fit: cover; }

        .stock-badge {
            position: absolute;
            top: 6px; right: 6px;
            background: rgba(18, 18, 18, 0.75);
            color: #FFFFFF;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
        }

        .product-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--accent-brown-dark);
            margin-bottom: 8px;
        }

        .btn-add-item {
            width: 100%;
            background: var(--btn-iconic-bg);
            color: var(--btn-iconic-text);
            border: none;
            padding: 8px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            box-shadow: 0 3px 8px rgba(212, 163, 115, 0.3);
            transition: background 0.2s ease, transform 0.1s ease;
        }

        .btn-add-item:hover { background: var(--btn-iconic-hover); transform: translateY(-1px); }

        .cart-panel {
            background: var(--bg-card);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: sticky;
            top: 0;
            padding: 20px;
            box-shadow: -2px 0 8px rgba(0,0,0,0.02);
            z-index: 100;
        }

        .cart-title {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .cart-items-container {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
        }

        .cart-card-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .cart-card-info h6 { font-size: 12.5px; font-weight: 700; margin: 0 0 3px 0; }
        .cart-card-info span { font-size: 11.5px; color: var(--accent-brown-dark); font-weight: 600; }

        .cart-actions-qty { display: flex; align-items: center; gap: 6px; }

        .cart-actions-qty button {
            width: 24px; height: 24px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: white;
            font-weight: bold;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        }

        .cart-actions-qty button:hover { background: var(--accent-brown); color: white; border-color: var(--accent-brown); }

        .cart-summary-box {
            border-top: 1px solid var(--border-color);
            padding-top: 12px;
            margin-top: 10px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .summary-row.total {
            font-size: 15px;
            font-weight: 800;
            color: var(--accent-brown-dark);
            margin-top: 8px;
            border-top: 1px dashed var(--border-color);
            padding-top: 8px;
        }

        .btn-checkout {
            width: 100%;
            background: var(--accent-brown);
            color: #FFFFFF;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
            transition: background 0.2s;
        }

        .btn-checkout:hover { background: var(--accent-brown-dark); }
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
                <a href="index.php" class="active" title="Katalog Hijab">
                    <i class="bi bi-bag-heart-fill"></i>
                    <span class="menu-label">Katalog Hijab</span>
                </a>
                <a href="produk.php" title="Produk">
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

    <!-- MAIN CONTENT (PRODUK & KATALOG) -->
    <main class="pos-main">
        <div class="main-sticky-header">
            <div class="top-header">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchProduct" placeholder="Cari nama hijab, pashmina, kode..." autocomplete="off" onkeyup="filterProducts()">
                </div>
               <div style="font-weight: 700; font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 6px;">
    <i class="bi bi-calendar3"></i> 
    <span><?php echo date('d M Y'); ?></span>
    <span class="text-secondary mx-1">|</span>
    <i class="bi bi-clock"></i>
    <span id="liveClock"><?php echo date('H:i:s'); ?></span> WIB
</div>

<!-- Script agar jam berdetak secara real-time -->
<script>
function updateClock() {
    const now = new Date();
    let hours = String(now.getHours()).padStart(2, '0');
    let minutes = String(now.getMinutes()).padStart(2, '0');
    let seconds = String(now.getSeconds()).padStart(2, '0');
    
    document.getElementById('liveClock').textContent = `${hours}:${minutes}:${seconds}`;
}
setInterval(updateClock, 1000);
</script>
            </div>

            <!-- KATEGORI CHIPS -->
            <div class="category-scroll">
                <div class="cat-chip active" onclick="filterCategory('all', this)">Semua Produk</div>
                <?php foreach ($kategori_list as $kat): ?>
                    <div class="cat-chip" onclick="filterCategory('<?php echo strtolower($kat); ?>', this)"><?php echo $kat; ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="products-scroll-area">
            <div class="products-grid" id="productList">
                <?php while ($produk = mysqli_fetch_assoc($query_produk)) : ?>
                    <div class="product-card" 
                         data-name="<?php echo strtolower($produk['nama_produk']); ?>"
                         data-code="<?php echo strtolower($produk['kode_produk']); ?>"
                         data-category="<?php echo strtolower($produk['kategori']); ?>">
                        
                        <div class="product-img-box">
                            <span class="stock-badge">
                                <i class="bi bi-box-seam me-1"></i><?php echo $produk['stok']; ?>
                            </span>

                            <?php if (!empty($produk['foto']) && file_exists("../assets/produk/".$produk['foto'])) : ?>
                                <img src="../assets/produk/<?php echo htmlspecialchars($produk['foto']); ?>" alt="Foto">
                            <?php else : ?>
                                <i class="bi bi-bag-heart" style="font-size: 32px; color: var(--text-muted);"></i>
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
                            <i class="bi bi-plus-circle-fill"></i> Tambah
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <!-- RIGHT PANEL / KERANJANG -->
    <section class="cart-panel">
        <div class="cart-title">
            <span><i class="bi bi-cart3 me-2"></i>Keranjang Belanja</span>
            <span class="badge bg-secondary" id="cartCount">0 Item</span>
        </div>

        <div class="cart-items-container" id="cartItems">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bag-x" style="font-size: 36px; opacity: 0.3;"></i>
                <p class="mt-2 text-muted" style="font-size: 12px;">Belum ada item dipilih</p>
            </div>
        </div>

        <div class="cart-summary-box">
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subTotal" style="font-weight: 600;">Rp 0</span>
            </div>
            <div class="summary-row total">
                <span>Grand Total</span>
                <span id="grandTotal">Rp 0</span>
            </div>
            <button type="button" class="btn-checkout" onclick="prosesPembayaran()">
                <span>Proses Transaksi</span>
                <i class="bi bi-arrow-right-circle-fill"></i>
            </button>
        </div>
    </section>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
let keranjang = [];

function toggleSidebar() {
    const container = document.getElementById('posContainer');
    container.classList.toggle('sidebar-open');
}

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
    const subTotalElem = document.getElementById("subTotal");
    const grandTotalElem = document.getElementById("grandTotal");

    if (keranjang.length === 0) {
        cartItems.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bag-x" style="font-size: 36px; opacity: 0.3;"></i>
                <p class="mt-2 text-muted" style="font-size: 12px;">Belum ada item dipilih</p>
            </div>`;
        cartCount.innerText = "0 Item";
        subTotalElem.innerText = "Rp 0";
        grandTotalElem.innerText = "Rp 0";
        return;
    }

    let html = "";
    let totalQty = 0;
    let totalPrice = 0;

    keranjang.forEach((item, index) => {
        totalQty += item.jumlah;
        totalPrice += item.harga * item.jumlah;
        html += `
            <div class="cart-card-item">
                <div class="cart-card-info">
                    <h6>${item.nama}</h6>
                    <span>Rp ${formatRupiah(item.harga)}</span>
                </div>
                <div class="cart-actions-qty">
                    <button type="button" onclick="kurangiJumlah(${index})">-</button>
                    <span style="font-size: 12px; font-weight: bold; width: 18px; text-align: center;">${item.jumlah}</span>
                    <button type="button" onclick="tambahJumlah(${index})">+</button>
                </div>
            </div>`;
    });

    cartItems.innerHTML = html;
    cartCount.innerText = totalQty + " Item";
    subTotalElem.innerText = "Rp " + formatRupiah(totalPrice);
    grandTotalElem.innerText = "Rp " + formatRupiah(totalPrice);
}

function formatRupiah(angka) {
    return new Intl.NumberFormat("id-ID").format(angka);
}

function filterProducts() {
    const keyword = document.getElementById("searchProduct").value.toLowerCase();
    document.querySelectorAll(".product-card").forEach(product => {
        const nama = product.dataset.name;
        const kode = product.dataset.code;
        product.style.display = (nama.includes(keyword) || kode.includes(keyword)) ? "flex" : "none";
    });
}

function filterCategory(cat, el) {
    document.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
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