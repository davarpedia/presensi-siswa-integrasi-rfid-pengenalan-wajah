<?php
// Set zona waktu
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi koneksi database
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "db_sistem_presensi_siswa_tugas_akhir";

// Buat koneksi ke database
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Cek koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
