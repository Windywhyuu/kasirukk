<?php
session_start();

// Menangkap pesan error dari URL (jika ada)
$error = isset($_GET['error']) ? $_GET['error'] : '';
$pesan_error = '';

if ($error === 'password_salah') {
    $pesan_error = 'Password yang Anda masukkan salah!';
} elseif ($error === 'username_tidak_ditemukan') {
    $pesan_error = 'Username tidak terdaftar dalam sistem.';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Kasir - GELORA WYNE</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Font Modern -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #F8F9FA;            
            --card-bg: #FFFFFF;             
            --text-dark: #111827;           
            --text-muted: #6B7280;          
            --border-light: #E5E7EB;        
            
            --accent-brown: #8C6D58;        
            --accent-brown-dark: #5A4334;   
            --accent-brown-light: #F4EFEB;  
            --accent-glow: rgba(140, 109, 88, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: var(--bg-color);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Container Fullscreen Split */
        .login-wrapper {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            min-height: 100vh;
            width: 100vw;
        }

        /* ---------------------------------- */
        /* SISI KIRI: Visual & Branding       */
        /* ---------------------------------- */
        .brand-side {
            position: relative;
            background: linear-gradient(135deg, #2A1F18 0%, #1A1A1A 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 50px 60px;
            color: #FFFFFF;
            overflow: hidden;
        }

        /* Background Image overlay estetik */
        .brand-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('assets/img/logo.jpeg') center/cover no-repeat;
            opacity: 0.18;
            filter: blur(4px) grayscale(20%);
            transform: scale(1.05);
            z-index: 0;
        }

        /* Lingkaran Akses Cahaya Halus */
        .brand-side::after {
            content: '';
            position: absolute;
            width: 450px;
            height: 450px;
            background: var(--accent-brown);
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.25;
            top: -100px;
            left: -100px;
            z-index: 0;
        }

        .brand-header, .brand-body, .brand-footer {
            position: relative;
            z-index: 1;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .brand-title {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
            margin-bottom: 16px;
            background: linear-gradient(180deg, #FFFFFF 0%, #E2D9D3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-desc {
            font-size: 15px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.75);
            max-width: 460px;
            margin-bottom: 35px;
        }

        /* Feature List di Sisi Kiri */
        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 420px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 13.5px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(6px);
        }

        .feature-item i {
            font-size: 18px;
            color: var(--accent-brown-light);
        }

        .brand-footer {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.45);
        }


        /* ---------------------------------- */
        /* SISI KANAN: Form Login Area       */
        /* ---------------------------------- */
        .form-side {
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
        }

        .form-container {
            width: 100%;
            max-width: 380px;
        }

        /* Logo Circle Box */
        .logo-box {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #FFFFFF;
            padding: 3px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            border: 2px solid var(--accent-brown);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .form-header h3 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.3px;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-bottom: 0;
        }

        .role-tag {
            display: inline-block;
            background: var(--accent-brown-light);
            color: var(--accent-brown-dark);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 14px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            margin-top: 10px;
            border: 1px solid rgba(140, 109, 88, 0.2);
        }

        /* Custom Input Field Style */
        .form-group-custom {
            margin-bottom: 20px;
        }

        .form-group-custom label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .input-box {
            display: flex;
            align-items: center;
            background: #F9FAFB;
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            padding: 0 14px;
            transition: all 0.25s ease;
        }

        .input-box:focus-within {
            background: #FFFFFF;
            border-color: var(--accent-brown);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }

        .input-box i {
            font-size: 17px;
            color: var(--text-muted);
            transition: color 0.25s ease;
            margin-right: 10px;
        }

        .input-box:focus-within i {
            color: var(--accent-brown-dark);
        }

        .input-box input {
            width: 100%;
            border: none;
            background: transparent;
            padding: 13px 0;
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 500;
            outline: none;
        }

        .input-box input::placeholder {
            color: #9CA3AF;
        }

        /* Tombol Login */
        .btn-submit {
            width: 100%;
            background: var(--text-dark);
            color: #FFFFFF;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
        }

        .btn-submit:hover {
            background: var(--accent-brown-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(90, 67, 52, 0.25);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Alert Error */
        .alert-error {
            background-color: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        /* Link Kembali */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-top: 22px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: var(--accent-brown-dark);
            background: var(--accent-brown-light);
        }

        /* ---------------------------------- */
        /* RESPONSIVE DESIGN (Mobile & Tablet)*/
        /* ---------------------------------- */
        @media (max-width: 992px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .brand-side {
                display: none; /* Sembunyikan visual kiri di HP agar fokus ke login */
            }

            .form-side {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <!-- SISI KIRI: BRANDING & VISUAL -->
    <div class="brand-side">
        <div class="brand-header">
            <div class="brand-badge">
                <i class="bi bi-shop"></i> GELORA WYNE POS SYSTEM
            </div>
        </div>

        <div class="brand-body">
            <h1 class="brand-title">Kelola Toko Lebih Rapi & Cepat</h1>
            <p class="brand-desc">
                Sistem Point of Sale terintegrasi untuk memudahkan pengelolaan transaksi kasir, inventori produk, serta laporan penjualan secara akurat.
            </p>

            <div class="feature-list">
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Pencatatan transaksi kasir secara real-time</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Manajemen stok & inventori terstruktur</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Keamanan akses khusus kasir & supervisor</span>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            &copy; <?= date('Y'); ?> GELORA WYNE. Hak Cipta Dilindungi.
        </div>
    </div>

    <!-- SISI KANAN: FORM LOGIN -->
    <div class="form-side">
        <div class="form-container">

            <!-- Logo -->
            <div class="logo-box">
                <img src="assets/img/logo.jpeg" alt="Logo GELORA WYNE">
            </div>

            <!-- Header Form -->
            <div class="text-center form-header mb-4">
                <h3>Selamat Datang</h3>
                <p>Silakan masuk ke akun Anda</p>
                <span class="role-tag">
                    <i class="bi bi-shield-lock me-1"></i> ACCESS POINT KASIR
                </span>
            </div>

            <!-- Pesan Error Login (Jika Ada) -->
            <?php if (!empty($pesan_error)) { ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle-fill fs-5"></i>
                    <div><?= htmlspecialchars($pesan_error); ?></div>
                </div>
            <?php } ?>

            <!-- Form Utama -->
            <form action="cek_login.php" method="POST">

                <div class="form-group-custom">
                    <label for="username">Username</label>
                    <div class="input-box">
                        <i class="bi bi-person"></i>
                        <input type="text"
                               id="username"
                               name="username"
                               placeholder="Masukkan username Anda"
                               autocomplete="off"
                               required>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="password">Password</label>
                    <div class="input-box">
                        <i class="bi bi-lock"></i>
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="Masukkan password Anda"
                               required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Masuk ke Sistem
                </button>

            </form>

            <div class="text-center">
                <a href="index.php" class="back-link">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Halaman Utama
                </a>
            </div>

        </div>
    </div>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>