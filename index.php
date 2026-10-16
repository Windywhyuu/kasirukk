<?php
include 'koneksi.php';

/* Menggunakan foto PNG milik sendiri sebagai background */
$hero_img = 'assets/produk/toko.jpeg';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GELORA WYNE | Official Store</title>

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Google Fonts: Outfit & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --font-heading: 'Outfit', sans-serif;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
            --accent-brown: #8D5B4C;
        }

        html, body {
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
            font-family: var(--font-sans);
            background-color: #1A1716;
        }

        /* --- BACKGROUND UTAMA (DASHBOARD) --- */
        .main-dashboard {
            position: relative;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 1.5s ease;
        }

        .main-dashboard.show {
            opacity: 1;
        }

        .bg-image {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
            object-position: center;
            filter: blur(5px) brightness(0.35);
            transform: scale(1.03);
            z-index: 1;
        }

        .dashboard-content {
            position: relative;
            z-index: 2;
            color: #FFFFFF;
            text-align: center;
            max-width: 650px;
            padding: 0 25px;
        }

        .dashboard-brand {
            font-family: var(--font-heading);
            font-size: clamp(34px, 5vw, 50px);
            font-weight: 800;
            letter-spacing: 3px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .dashboard-brand span {
            color: #D4A373;
        }

        .dashboard-desc {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 300;
            line-height: 1.7;
            margin-bottom: 35px;
        }

        .btn-login {
            background-color: var(--accent-brown);
            color: #FFFFFF;
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 14px 38px;
            border-radius: 50px;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background-color: #A36B5C;
            color: #FFFFFF;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
        }

        /* --- SPLASH SCREEN / INTRO "WELCOME" --- */
        #splashScreen {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #1A1716;
            transition: opacity 1s ease, visibility 1s ease;
        }

        #splashScreen.fade-out {
            opacity: 0;
            visibility: hidden;
        }

        .splash-bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
            object-position: center;
            filter: blur(12px) brightness(0.25); /* Blur tebal di awal */
            transform: scale(1.05);
            z-index: 1;
        }

        .splash-text {
            position: relative;
            z-index: 2;
            color: #FFFFFF;
            font-family: var(--font-heading);
            font-size: clamp(45px, 8vw, 85px);
            font-weight: 800;
            letter-spacing: 12px;
            text-transform: uppercase;
            text-align: center;
            animation: welcomeAnim 4.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        @keyframes welcomeAnim {
            0% {
                opacity: 0;
                transform: scale(0.85);
                letter-spacing: 5px;
            }
            30% {
                opacity: 1;
                transform: scale(1);
                letter-spacing: 12px;
            }
            75% {
                opacity: 1;
                transform: scale(1);
                letter-spacing: 12px;
            }
            100% {
                opacity: 0;
                transform: scale(1.1);
                filter: blur(8px);
            }
        }

        .footer-text {
            position: absolute;
            bottom: 20px;
            left: 0; width: 100%;
            text-align: center;
            z-index: 2;
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <!-- LAYAR PEMBUKA SPLASH SCREEN (WELCOME 5 DETIK) -->
    <div id="splashScreen">
        <img src="<?= htmlspecialchars($hero_img); ?>" alt="Splash BG" class="splash-bg">
        <div class="splash-text">
            WELCOME
        </div>
    </div>

    <!-- DASHBOARD UTAMA (MUNCUL SETELAH INTRO SELESAI) -->
    <div class="main-dashboard" id="mainDashboard">
        <img src="<?= htmlspecialchars($hero_img); ?>" alt="GELORA WYNE Store" class="bg-image">

        <div class="dashboard-content">
            <span class="text-uppercase fw-bold mb-3 d-block" style="letter-spacing: 4px; color: #D4A373; font-size: 12px;">
                <i class="bi bi-stars"></i> Official Management System
            </span>
            <h1 class="dashboard-brand">GELORA<span>WYNE</span></h1>
            <p class="dashboard-desc">
                Pusat kurasi fashion hijab dan aksesori eksklusif. Masuk ke panel untuk mengelola sistem kasir dan inventaris toko secara real-time.
            </p>
            <div>
                <a href="login.php" class="btn-login">
                    <i class="bi bi-lock-fill"></i> Masuk Panel Kasir
                </a>
            </div>
        </div>

        <div class="footer-text">
            © 2026 GELORA WYNE. All Rights Reserved.
        </div>
    </div>

    <!-- SCRIPT OTOMATIS PINDAH SETELAH 4.8 DETIK -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                const splash = document.getElementById('splashScreen');
                const dashboard = document.getElementById('mainDashboard');

                splash.classList.add('fade-out');
                dashboard.classList.add('show');
            }, 4600); // Durasi pas sekitar 4.6 - 5 detik
        });
    </script>
</body>
</html>