<?php
session_start();

if (!isset($_SESSION['id_user']) || $_SESSION['level'] != 'Kasir I') {
    header("Location: ../login.php");
    exit;
}

include "../koneksi.php";

$id_user = intval($_SESSION['id_user']);

/* =========================
   CEK KAS TERBUKA
========================= */
$cek = mysqli_query($koneksi, "
    SELECT id_kas
    FROM kas
    WHERE id_user = '$id_user'
    AND status = 'buka'
    LIMIT 1
");

if (mysqli_num_rows($cek) > 0) {
    header("Location: tutup_kas.php");
    exit;
}

/* =========================
   SIMPAN KAS BUKA
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $saldo_awal = intval($_POST["saldo_awal"] ?? 0);

    if ($saldo_awal < 0) {
        $saldo_awal = 0;
    }

    $simpan = mysqli_query($koneksi, "
        INSERT INTO kas (id_user, tanggal, saldo_awal, waktu_buka, status)
        VALUES ('$id_user', CURDATE(), '$saldo_awal', NOW(), 'buka')
    ");

    if ($simpan) {
        header("Location: index.php?info=kas_dibuka");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buka Kas - GELORA WYNE</title>

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
            /* LAPISAN PALING BELAKANG: Wallpaper Ikon Uang (Money) Miring Estetis */
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

        /* MAIN CONTENT LAYOUT */
        .main-wrapper {
            margin-left: 200px;
            min-height: 100vh;
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
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
            padding: 32px 28px;
            max-width: 560px;
            margin: 0 auto;
            width: 100%;
            box-shadow: 0 10px 30px rgba(140, 109, 88, 0.08);
            position: relative;
            z-index: 2;
        }

        .icon-box-lg {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin: 0 auto 16px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
        }

        .input-money {
            position: relative;
        }

        .money-prefix {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 700;
            pointer-events: none;
        }

        .input-money input {
            padding-left: 46px;
            height: 48px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            font-size: 16px;
            font-weight: 700;
            background: var(--bg-canvas);
            color: var(--text-dark);
        }

        .input-money input:focus {
            background: #FFFFFF;
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 3px rgba(140, 109, 88, 0.15);
        }

        .nominal-cepat {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 10px;
        }

        .btn-cepat {
            background: var(--bg-canvas);
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            font-size: 12.5px;
            font-weight: 700;
            border-radius: 10px;
            padding: 9px 0;
            transition: all 0.2s;
        }

        .btn-cepat:hover {
            background: var(--accent-brown);
            color: #FFFFFF;
            border-color: var(--accent-brown);
        }

        .preview-saldo {
            text-align: center;
            color: var(--accent-brown-dark);
            font-size: 24px;
            font-weight: 800;
            margin-top: 18px;
            letter-spacing: -0.5px;
        }

        .note-kas {
            margin-top: 18px;
            padding: 14px 16px;
            background: var(--accent-brown-light);
            border-left: 3px solid var(--accent-brown);
            border-radius: 10px;
            font-size: 12.5px;
            color: var(--accent-brown-dark);
            line-height: 1.6;
        }

        .btn-brown {
            background-color: var(--accent-brown);
            color: #FFFFFF;
            font-size: 14.5px;
            font-weight: 700;
            padding: 14px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            margin-top: 24px;
            box-shadow: 0 4px 12px rgba(140, 109, 88, 0.25);
        }

        .btn-brown:hover {
            background-color: var(--accent-brown-dark);
            color: #FFFFFF;
            transform: translateY(-1px);
        }

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
        <a href="buka_kas.php" class="active">
            <i class="bi bi-unlock"></i>
            <span>Buka Kas</span>
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
            <h1 class="h4 fw-bold m-0 text-dark"><i class="bi bi-door-open-fill me-2 text-warning"></i>Mulai Shift Kasir</h1>
        </div>
        <div style="font-weight: 700; font-size: 13px; color: var(--text-muted);">
            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d M Y'); ?>
        </div>
    </div>

    <div class="card-modern">
        <div class="icon-box-lg">
            <i class="bi bi-cash-stack"></i>
        </div>

        <h2 class="text-center fw-bold fs-5 mb-1">Buka Kas Baru</h2>
        <p class="text-center text-muted small mb-4"><?= date('d/m/Y'); ?> · Petugas: <strong><?= htmlspecialchars($_SESSION['nama']); ?></strong></p>

        <form id="formBukaKas" method="POST" action="buka_kas.php">
            <label class="form-label">
                Saldo Awal Kas (Uang Fisik Laci)
            </label>

            <div class="input-money">
                <span class="money-prefix">Rp</span>
                <input type="number" id="saldoAwal" name="saldo_awal" class="form-control" placeholder="0" min="0" autocomplete="off" oninput="updatePreview()">
            </div>

            <div class="nominal-cepat">
                <button type="button" class="btn-cepat" onclick="isiCepat(50000)">50rb</button>
                <button type="button" class="btn-cepat" onclick="isiCepat(100000)">100rb</button>
                <button type="button" class="btn-cepat" onclick="isiCepat(150000)">150rb</button>
                <button type="button" class="btn-cepat" onclick="isiCepat(200000)">200rb</button>
            </div>

            <div id="previewSaldo" class="preview-saldo">
                Rp 0
            </div>

            <div class="note-kas">
                <i class="bi bi-info-circle-fill me-1"></i> Uang fisik awal yang berada di laci akan menjadi acuan dasar perhitungan uang kembalian dan rekapitilasi saldo saat tutup kas.
            </div>

            <button type="button" class="btn-brown" onclick="prosesBukaKas()">
                <i class="bi bi-check-circle-fill"></i> Buka Kas & Mulai Shift
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none small text-muted hover-dark"><i class="bi bi-arrow-left me-1"></i>Kembali ke Kasir</a>
        </div>
    </div>

</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function isiCepat(nilai) {
    document.getElementById("saldoAwal").value = nilai;
    updatePreview();
}

function updatePreview() {
    const nilai = Number(document.getElementById("saldoAwal").value) || 0;
    document.getElementById("previewSaldo").innerText = "Rp " + nilai.toLocaleString("id-ID");
}

function prosesBukaKas() {
    const raw = document.getElementById("saldoAwal").value;
    if (raw === "") {
        alert("Isi saldo awal kas terlebih dahulu.");
        return;
    }
    document.getElementById("formBukaKas").submit();
}
</script>

</body>
</html>