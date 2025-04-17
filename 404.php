<?php 
http_response_code(404);
$pageTitle = "Halaman Tidak Ditemukan";
require_once 'koneksi.php';
require_once 'autentikasi.php';
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">

  <!-- 404 Error Text -->
  <div class="text-center">
    <div class="error mx-auto" data-text="404">404</div>
    <p class="lead text-gray-800 mb-4">Halaman Tidak Ditemukan</p>
    <p class="text-gray-500 mb-4">Maaf, halaman ini tidak tersedia. Periksa kembali alamat URL Anda atau bisa pergi ke dashboard.</p>
    <a href="index.php">&larr; Kembali ke Dashboard</a>
  </div>

</div>
<!-- End of Main Content -->

</div>

<?php include 'footer.php'; ?>
