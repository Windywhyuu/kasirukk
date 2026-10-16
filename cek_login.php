<?php
session_start();
include "koneksi.php";

// Pastikan diakses melalui metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Ambil input dari form login
$username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
$password = trim($_POST['password']); // Jangan di-escape agar karakter hash/simbol khusus tidak rusak

// Ambil data user berdasarkan username saja terlebih dahulu
$query = mysqli_query($koneksi, "
    SELECT * FROM users
    WHERE username = '$username'
    LIMIT 1
");

if ($query && mysqli_num_rows($query) > 0) {
    $data = mysqli_fetch_assoc($query);
    $db_password = $data['password'];

    // Cek apakah kolom status ada dan apakah statusnya aktif (opsional, sesuaikan dengan database Anda)
    if (isset($data['status']) && $data['status'] !== 'aktif') {
        echo "<script>
            alert('Akun Anda tidak aktif! Silakan hubungi supervisor.');
            window.location='login.php';
        </script>";
        exit;
    }

    $login_berhasil = false;

    // 1. Cek apakah password cocok dengan format hash (menggunakan password_verify)
    if (password_verify($password, $db_password)) {
        $login_berhasil = true;
    } 
    // 2. Cek cadangan jika password di database masih berupa teks biasa (plain text)
    elseif ($password === $db_password) {
        $login_berhasil = true;
    }

    // Jika verifikasi password berhasil
    if ($login_berhasil) {
        // Set Session Login
        $_SESSION['id_user']  = $data['id_user'];
        $_SESSION['nama']     = $data['nama'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['level']    = $data['level'];

        // Pengarahan halaman berdasarkan level/hak akses
        if ($data['level'] == 'Kasir I') {
            header("Location: kasir/index.php");
            exit;
        } elseif ($data['level'] == 'Kasir II') {
            header("Location: kasir2/index.php");
            exit;
        } elseif ($data['level'] == 'Supervisor') {
            header("Location: supervisor/index.php");
            exit;
        } else {
            // Default jika level tidak spesifik
            header("Location: index.php");
            exit;
        }

    } else {
        // Password salah
        echo "<script>
            alert('Password salah!');
            window.location='login.php';
        </script>";
        exit;
    }

} else {
    // Username tidak ditemukan
    echo "<script>
        alert('Username tidak terdaftar!');
        window.location='login.php';
    </script>";
    exit;
}
?>