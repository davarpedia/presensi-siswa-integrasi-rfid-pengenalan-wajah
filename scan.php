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
<div class="container-fluid mb-5">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Scan Presensi</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <!-- <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Scan Presensi</li>
        </ol>
    </nav>
  </div> -->
  
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
<!-- End of Container Fluid -->

<!-- JavaScript untuk menangani status dan kontrol scan presensi -->
<script>
  const displayText             = document.getElementById("displayText");
  const animasiTapRFID   = document.getElementById("animasiTapRFID");
  const animasiFailed    = document.getElementById("animasiFailed");
  const streamContainer         = document.getElementById("streamContainer");
  const cameraStream            = document.getElementById("cameraStream");
  const displayTextSuccess      = document.getElementById("displayTextSuccess");
  const displayTextFailed       = document.getElementById("displayTextFailed");
  const resultContainer         = document.getElementById("resultContainer");
  const presensiInfo            = document.getElementById("presensiInfo");

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

<?php
include 'footer.php';
?>
