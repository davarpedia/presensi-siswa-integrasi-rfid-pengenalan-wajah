<?php 
$pageTitle = "Akses Ditolak";
require_once 'koneksi.php';
require_once 'autentikasi.php';
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">

  <!-- 403 Error Text -->
  <div class="text-center">
    <div class="error mx-auto" data-text="403">403</div>
    <p class="lead text-gray-800 mb-4">Akses Ditolak</p>
    <p class="text-gray-500 mb-4">Anda tidak memiliki hak akses untuk membuka halaman ini.</p>
    <a href="index.php">&larr; Kembali ke Dashboard</a>
  </div>

</div>
<!-- End of Main Content -->

</div>

<?php include 'footer.php'; ?>
