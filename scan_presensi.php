<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>Sistem Presensi Siswa SD N Gemawang</title>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">

  <!-- Font Awesome -->
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <!-- SB Admin 2 CSS -->
  <link href="css/sb-admin-2.min.css" rel="stylesheet">

  <!-- Custom Style CSS -->
  <style>
    /* Header Sticky: Meniru tampilan topbar SB Admin 2 */
    .sticky-header {
      position: sticky;
      top: 0;
      z-index: 1030;
      background-color: #fff;
    }
    .navbar-brand .brand-icon {
      font-size: 1.75rem;
      transform: rotate(-15deg);
      margin-right: 0.5rem;
      color: #4e73df;
    }
    .navbar-brand .brand-text {
      font-size: 1.25rem;
      font-weight: 700;
      color: #4e73df;
    }
  </style>
</head>

<body id="page-top">
  <!-- Header (Sticky) -->
  <nav class="navbar navbar-expand navbar-light shadow sticky-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
      <a class="navbar-brand d-flex align-items-center" href="index.php">
        <div class="brand-icon ml-2">
          <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="brand-text ml-2">SISTEM PRESENSI SISWA SD N GEMAWANG</div>
      </a>
      <div id="dateTimeDisplay" class="text-muted text-right text-sm-right small mr-2"></div>
    </div>
  </nav>

  <!-- Content Wrapper -->
  <div id="content-wrapper" class="d-flex flex-column pt-5">
    <!-- Main Content -->
    <div id="content" class="container-fluid">
      <!-- Begin Page Content -->

  <!-- Teks instruksi awal -->
  <h5 id="displayText" class="alert alert-secondary text-center font-weight-bold mb-0">
    Silahkan Tap Kartu RFID Untuk Melakukan Presensi
  </h5>
  
  <!-- Alert untuk presensi berhasil -->
  <h5 id="displayTextSuccess" class="alert alert-success text-center font-weight-bold mb-3" style="display: none;"></h5>
  
  <!-- Alert untuk presensi gagal atau kesalahan -->
  <h5 id="displayTextFailed" class="alert alert-danger text-center font-weight-bold mb-3" style="display: none;"></h5>
  
  <!-- Container hasil presensi (kotak detail) -->
  <div id="resultContainer" class="card mx-auto mt-4 mb-3 shadow-sm px-4 px-md-5 py-4 py-md-5" style="max-width: 800px; width: 100%; display: none; border-radius: 10px;">
    <div class="card-body p-0">
      <div id="presensiInfo" class="w-100"></div>
    </div>
  </div>
  
<!-- Import DotLottie Player -->
<script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>

<!-- Animasi Tap RFID -->
<div class="d-flex justify-content-center mb-3">
  <dotlottie-player
    id="animasiTapRFID"
    src="https://lottie.host/f45eae73-68e5-4893-a31c-696676f7c969/tTWY5dhRSh.lottie"
    background="transparent"
    speed="1"
    style="width: 350px; height: 350px;"
    loop
    autoplay>
  </dotlottie-player>
</div>

<!-- Animasi Failed -->
<div class="d-flex justify-content-center mb-3" style="display: none;">
  <dotlottie-player
    id="animasiFailed"
    src="https://lottie.host/81428de6-572c-497a-8286-6bab6cf71d47/IJxZlZwPOU.lottie"
    background="transparent"
    speed="1"
    style="width: 250px; height: 250px; display: none;"
    loop
    autoplay>
  </dotlottie-player>
</div>
  
  <!-- Stream Kamera ESP32-CAM -->
  <div id="streamContainer" class="mt-0" style="display: none;">
    <div class="rounded mx-auto" style="max-width: 500px;">
      <img id="cameraStream" class="img-fluid rounded" alt="Streaming ESP32-CAM">
    </div>
  </div>
</div>

    <!-- Footer -->
    <footer class="sticky-footer bg-white">
      <div class="container my-auto">
        <div class="copyright text-center my-auto">
          <span>Copyright &copy; David Ardianto 2025</span>
        </div>
      </div>
    </footer>
    <!-- End of Footer -->
  </div>
  <!-- End of Content Wrapper -->

  <!-- Scroll to Top Button -->
  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

  <!-- Bootstrap core JavaScript -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Core plugin JavaScript -->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- SB Admin 2 Custom scripts -->
  <script src="js/sb-admin-2.min.js"></script>

  <!-- Tanggal dan Waktu Realtime -->
  <script src="js/waktu-realtime.js"></script>

<!-- JavaScript untuk menangani status dan kontrol scan presensi -->
<script>
  const displayText         = document.getElementById("displayText");
  const animasiTapRFID      = document.getElementById("animasiTapRFID");
  const animasiFailed       = document.getElementById("animasiFailed");
  const streamContainer     = document.getElementById("streamContainer");
  const cameraStream        = document.getElementById("cameraStream");
  const displayTextSuccess  = document.getElementById("displayTextSuccess");
  const displayTextFailed   = document.getElementById("displayTextFailed");
  const resultContainer     = document.getElementById("resultContainer");
  const presensiInfo        = document.getElementById("presensiInfo");

  const videoURL = 'http://192.168.10.177:5000/video_feed';
  const statusURL = 'http://192.168.10.177:5000/get_status';

  function updateUI(status, data = {}) {
    // Sembunyikan semua elemen terlebih dahulu
    displayText.style.display = "none";
    displayTextSuccess.style.display = "none";
    displayTextFailed.style.display = "none";
    animasiTapRFID.style.display = "none";
    animasiFailed.style.display = "none";
    streamContainer.style.display = "none";
    resultContainer.style.display = "none";

    if (status === "waiting") {
      displayText.style.display = "block";
      displayText.innerHTML = "Silahkan Tap Kartu RFID Untuk Melakukan Presensi";
      animasiTapRFID.style.display = "block";
    } else if (status === "scanning") {
      displayText.style.display = "block";
      displayText.innerHTML = "Memverifikasi Wajah..";
      cameraStream.src = videoURL;
      streamContainer.style.display = "block";
    } else if (status === "rfid_not_registered") {
      displayTextFailed.style.display = "block";
      displayTextFailed.innerHTML = "Kartu RFID Tidak Terdaftar!";
      animasiFailed.style.display = "block";
    } else if (status === "already_checked_out") {
      displayTextFailed.style.display = "block";
      displayTextFailed.innerHTML = "Anda Sudah Melakukan Presensi Keluar Hari Ini!";
      animasiFailed.style.display = "block";
    } else if (status === "holiday") {
      displayTextFailed.style.display = "block";
      displayTextFailed.innerHTML = "Hari Ini Libur! Tidak Dapat Melakukan Presensi";
      animasiFailed.style.display = "block";
    } else if (status === "nonaktif") {
      displayTextFailed.style.display = "block";
      displayTextFailed.innerHTML = "Siswa Sudah Nonaktif! Tidak Dapat Melakukan Presensi";
      animasiFailed.style.display = "block";
    } else if (status === "success") {
      displayTextSuccess.style.display = "block";
      let message = data.jenisPresensi === "Presensi Masuk"
                    ? `Selamat Datang ${data.nama}`
                    : `Selamat Jalan ${data.nama}`;
      displayTextSuccess.innerHTML = "Presensi Berhasil! " + message;
      
      // Tentukan nama file foto berdasarkan jenis presensi
      let foto = "";
      if (data.jenisPresensi === "Presensi Masuk" && data.fotoMasuk) {
        foto = data.fotoMasuk;
      } else if (data.jenisPresensi === "Presensi Keluar" && data.fotoKeluar) {
        foto = data.fotoKeluar;
      }
      
      // Buat layout dua kolom yang responsif, dengan dua versi <img> di satu div
      let infoHTML = `
        <div class="row align-items-center" style="min-height: 200px;">
          <!-- FOTO: kotak untuk ≥md, lingkaran untuk <md -->
          <div class="col-12 col-md-5 d-flex align-items-center justify-content-center
                      order-1 order-md-2 mb-3 mb-md-0">
            <!-- Kotak untuk layar medium ke atas -->
            <img
              src="data/foto/foto_presensi_siswa/${foto}"
              alt="Bukti Presensi"
              class="img-fluid rounded d-none d-md-block"
              style="max-height: 200px; width: auto; object-fit: cover;"
            >
            <!-- Lingkaran untuk layar kecil -->
            <img
              src="data/foto/foto_presensi_siswa/${foto}"
              alt="Bukti Presensi"
              class="d-block d-md-none"
              style="
                width: 100px;
                height: 100px;
                object-fit: cover;
                border-radius: 50%;
              "
            >
          </div>

          <!-- INFO: full-width di ≤md, di ≥md pindah ke kiri -->
          <div class="col-12 col-md-7 order-2 order-md-1">
            <table class="mb-0" style="width: 100%; border-collapse: collapse;">
              <tr>
                <td class="font-weight-bold responsive-font" style="white-space: nowrap;">Jenis Presensi</td>
                <td class="responsive-font" style="width:10px;">:</td>
                <td class="responsive-font">${data.jenisPresensi}</td>
              </tr>
              <tr>
                <td class="font-weight-bold responsive-font">Nama</td>
                <td class="responsive-font">:</td>
                <td class="responsive-font">${data.nama}</td>
              </tr>
              <tr>
                <td class="font-weight-bold responsive-font">Kelas</td>
                <td class="responsive-font">:</td>
                <td class="responsive-font">${data.kelas}</td>
              </tr>
              <tr>
                <td class="font-weight-bold responsive-font">Waktu</td>
                <td class="responsive-font">:</td>
                <td class="responsive-font">${data.waktu}</td>
              </tr>
              <tr>
                <td class="font-weight-bold responsive-font">Keterangan</td>
                <td class="responsive-font">:</td>
                <td class="responsive-font">${data.keterangan}</td>
              </tr>
            </table>
          </div>
        </div>
      `;
      presensiInfo.innerHTML = infoHTML;
      resultContainer.style.display = "block";
    } else if (status === "failed_timeout") {
      displayTextFailed.style.display = "block";
      displayTextFailed.innerHTML = "Presensi Gagal! Tidak Ada Wajah yang Cocok. Silahkan Coba Lagi..";
      animasiFailed.style.display = "block";
    }
  }

  function fetchStatus() {
    fetch(statusURL)
      .then(response => response.json())
      .then(data => {
        console.log("Data diterima:", data);
        updateUI(data.status, data);
      })
      .catch(error => console.error("Error fetching status:", error));
  }

  // Polling status setiap 1 detik (1000ms)
  setInterval(fetchStatus, 1000);
</script>
<!-- End Konten Scan Presensi -->
  </div>
<!-- End of Container Fluid -->
</body>

</html>
