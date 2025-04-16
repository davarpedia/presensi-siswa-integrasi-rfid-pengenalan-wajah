<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah pengguna sudah login
if (!isset($_SESSION['session_email'])) {
    header("location: login.php");
    exit();
}

// Fungsi untuk membatasi akses hanya untuk admin
function hanyaAdmin() {
    if (strtolower($_SESSION['session_level'] ?? '') !== 'admin') {
        header("location: akses_ditolak.php");
        exit();
    }
}