<?php
session_start();

// Hapus semua sesi
$_SESSION = array();
session_destroy();

// Hapus cookie jika ada
setcookie("cookie_email", "", time() - 3600, "/");
setcookie("cookie_token", "", time() - 3600, "/");

// Redirect ke halaman login
header("location: login.php");
exit();
