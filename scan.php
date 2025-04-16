<?php
$pageTitle = "Scan Presensi";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Scan Presensi</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Scan Presensi</li>
        </ol>
    </nav>
  </div>
  
  <!-- Teks instruksi awal -->
  <h5 id="displayText" class="alert alert-secondary text-center mb-3">
    Silahkan Tap Kartu RFID Untuk Melakukan Presensi
  </h5>
  
  <!-- Alert untuk presensi berhasil -->
  <h5 id="displayTextSuccess" class="alert alert-success text-center mb-3" style="display: none;"></h5>
  
  <!-- Alert untuk presensi gagal atau kesalahan -->
  <h5 id="displayTextFailed" class="alert alert-danger text-center mb-3" style="display: none;"></h5>
  
  <!-- Container hasil presensi (kotak detail) -->
  <div id="resultContainer" class="card mx-auto mt-5 mb-3 py-3" style="max-width: 800px; width: 100%; display: none;">
    <div class="card-body">
      <p id="presensiInfo" class="mb-0"></p>
    </div>
  </div>
  
  <!-- Animasi Tap RFID -->
  <div class="d-flex justify-content-center mb-4">
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <dotlottie-player 
        id="animasiTapRFID"
        src="https://lottie.host/f45eae73-68e5-4893-a31c-696676f7c969/tTWY5dhRSh.lottie" 
        background="transparent" 
        speed="1" 
        style="width: 350px; height: 350px" 
        loop 
        autoplay>
    </dotlottie-player>
  </div>
  
  <!-- Animasi Failed -->
  <div class="d-flex justify-content-center mb-4">
    <img id="animasiFailed" src="img/failed.gif" alt="Animasi Failed" class="img-fluid" style="max-width: 400px; display: none;">
  </div>
  
  <!-- Stream Kamera ESP32-CAM -->
  <div id="streamContainer" class="row" style="display: none;">
    <div class="col-12 text-center">
      <div class="stream-container">
        <img id="cameraStream" alt="Streaming ESP32-CAM">
      </div>
    </div>
  </div>
</div>
<!-- End of Container Fluid -->

<!-- JavaScript untuk menangani status dan kontrol scan presensi -->
<script>
  const displayText     = document.getElementById("displayText");
  const animasiTapRFID  = document.getElementById("animasiTapRFID");
  const animasiFailed   = document.getElementById("animasiFailed");
  const streamContainer = document.getElementById("streamContainer");
  const cameraStream    = document.getElementById("cameraStream");
  const displayTextSuccess = document.getElementById("displayTextSuccess");
  const displayTextFailed  = document.getElementById("displayTextFailed");
  const resultContainer = document.getElementById("resultContainer");
  const presensiInfo    = document.getElementById("presensiInfo");

  const videoURL = 'http://localhost:5000/video_feed';
  const statusURL = 'http://localhost:5000/get_status';

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
      
      // Buat layout dua kolom: kiri untuk info, kanan untuk foto
      let infoHTML = `
        <div class="row" style="align-items: stretch;">
          <div class="col-7" style="padding-left: 30px;">
            <table class="table table-borderless mb-0">
              <tr>
                <th style="width: 35%; font-size: 1.2rem; text-align: left; white-space: nowrap;">Jenis Presensi</th>
                <td style="width: 5%; font-size: 1.2rem; text-align: center;">:</td>
                <td style="font-size: 1.2rem; text-align: left; white-space: nowrap;">${data.jenisPresensi}</td>
              </tr>
              <tr>
                <th style="font-size: 1.2rem; text-align: left; white-space: nowrap;">Nama</th>
                <td style="font-size: 1.2rem; text-align: center;">:</td>
                <td style="font-size: 1.2rem; text-align: left; white-space: nowrap;">${data.nama}</td>
              </tr>
              <tr>
                <th style="font-size: 1.2rem; text-align: left; white-space: nowrap;">Kelas</th>
                <td style="font-size: 1.2rem; text-align: center;">:</td>
                <td style="font-size: 1.2rem; text-align: left; white-space: nowrap;">${data.kelas}</td>
              </tr>
              <tr>
                <th style="font-size: 1.2rem; text-align: left; white-space: nowrap;">Waktu</th>
                <td style="font-size: 1.2rem; text-align: center;">:</td>
                <td style="font-size: 1.2rem; text-align: left; white-space: nowrap;">${data.waktu}</td>
              </tr>
              <tr>
                <th style="font-size: 1.2rem; text-align: left; white-space: nowrap;">Keterangan</th>
                <td style="font-size: 1.2rem; text-align: center;">:</td>
                <td style="font-size: 1.2rem; text-align: left; white-space: nowrap;">${data.keterangan}</td>
              </tr>
            </table>
          </div>
          <div class="col-5 d-flex align-items-center justify-content-end" style="padding-right: 30px;">
            <img src="data/foto/foto_presensi_siswa/${foto}" alt="Bukti Presensi" class="img-fluid" style="max-height: 200px; width: auto; object-fit: contain;">
          </div>
        </div>`;
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

<?php
include 'footer.php';
?>
