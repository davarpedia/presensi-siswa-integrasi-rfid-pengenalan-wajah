<?php
require_once 'autentikasi.php';

$level = strtolower($_SESSION['session_level']);

// Pilih dashboard sesuai level
if ($level === 'admin') {
    hanyaAdmin();
    include 'dashboard_admin.php';
}
elseif ($level === 'guru') {
    include 'dashboard_guru.php';
}
