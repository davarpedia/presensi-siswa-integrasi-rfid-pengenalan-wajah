<?php 
$pageTitle = "Pengaturan";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Ambil data jam masuk dan hari operasional dari database
$sql = "SELECT jam_masuk, hari_operasional FROM pengaturan WHERE id = 1";
$result = $conn->query($sql);
$currentJamMasuk = "";
$currentHariOperasional = "";
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currentJamMasuk = $row['jam_masuk'];
    $currentHariOperasional = $row['hari_operasional'];
}
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Pengaturan</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Pengaturan</li>
        </ol>
    </nav>
  </div>

  <!-- Alert Message -->
  <div id="alert-message-container"></div>
  
  <!-- Cards -->
  <div class="row">
    <!-- Card: Mode Alat -->
    <div class="col-md-6 mb-4">
      <div class="card h-100 border">
        <div class="card-header text-primary font-weight-bold">
          Mode Alat
        </div>
        <div class="card-body d-flex justify-content-center align-items-center">
          <div class="text-center" style="font-size: 18px;">
            <label id="modeLabel" class="d-block mb-2">Mengecek mode alat..</label>
            <label class="switch">
              <input type="checkbox" id="modeSwitch" onclick="toggleMode()">
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Card: Mode Flash -->
    <div class="col-md-6 mb-4">
      <div class="card h-100 border">
        <div class="card-header text-primary font-weight-bold">
          Mode LED Flash
        </div>
        <div class="card-body d-flex justify-content-center align-items-center">
          <div class="text-center" style="font-size: 18px;">
            <label id="flashLabel" class="d-block mb-2">Mengecek mode flash..</label>
            <label class="switch">
              <input type="checkbox" id="flashSwitch" onclick="toggleFlashMode()">
              <span class="slider"></span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- Card: Jam Masuk -->
    <div class="col-12 col-md-12 col-lg-6 mb-4">
      <div class="card h-100 border">
        <div class="card-header text-primary font-weight-bold">
          Jam Masuk
        </div>
        <div class="card-body">
          <form id="jamMasukForm" action="update_jam_masuk.php" method="POST">
            <div class="form-group mb-3">
              <input type="time" class="form-control" id="jam_masuk" name="jam_masuk" value="<?= $currentJamMasuk; ?>">
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Card: Waktu RTC -->
    <div class="col-12 col-md-12 col-lg-6 mb-4">
      <div class="card h-100 border">
        <div class="card-header text-primary font-weight-bold">
          Waktu RTC
        </div>
        <div class="card-body">
          <form id="rtc-form" action="set_waktu_rtc.php" method="POST">
            <div class="form-group mb-3">
              <input type="datetime-local" step="1" class="form-control" id="waktu_rtc" name="waktu_rtc" required>
            </div>
            <div class="d-flex">
              <button type="submit" class="btn btn-primary mr-2">Simpan</button>
              <button type="button" class="btn btn-success" id="set-time">Set Waktu Otomatis</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Card: Hari Operasional -->
    <div class="col-12 mb-4">
      <div class="card h-100 border">
        <div class="card-header text-primary font-weight-bold">
          Hari Operasional
        </div>
        <div class="card-body">
          <form id="hariOperasionalForm" action="update_hari_operasional.php" method="POST">
            <div class="days-bar mb-3">
              <?php 
                // Mapping angka ke nama hari
                $daysMapping = [
                  1 => 'Senin',
                  2 => 'Selasa',
                  3 => 'Rabu',
                  4 => 'Kamis',
                  5 => 'Jumat',
                  6 => 'Sabtu',
                  7 => 'Minggu'
                ];
                $selectedDays = !empty($currentHariOperasional) ? explode(',', $currentHariOperasional) : [];
                foreach ($daysMapping as $num => $name) {
                    $activeClass = in_array($num, $selectedDays) ? ' active' : '';
                    echo "<div class='day-item{$activeClass}' data-value='{$num}'>{$name}</div>";
                }
              ?>
            </div>
            <!-- Hidden input untuk menyimpan nilai hari operasional -->
            <input type="hidden" name="hari_operasional" id="hari_operasional_input" value="<?= $currentHariOperasional; ?>">
            <button type="submit" class="btn btn-primary">Simpan</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Card: Pengelolaan Hari Libur -->
    <!-- <div class="col-lg-4 mb-4">
      <div class="card h-100 border">
        <div class="card-header text-primary font-weight-bold">
          Hari Libur
        </div>
        <div class="card-body d-flex justify-content-center align-items-center">
          <button type="button" class="btn btn-primary" onclick="window.location.href='hari_libur.php'">Kelola Hari Libur</button>
        </div>
      </div>
    </div> -->
  </div>

  <!-- Script JavaScript (AJAX & interaksi) -->
  <script>
    function showAlert(message, status) {
      const alertContainer = document.getElementById('alert-message-container');
      const alertDiv = document.createElement('div');
      alertDiv.className = 'alert alert-' + (status === 'success' ? 'success' : 'danger') + ' text-center';
      alertDiv.textContent = message;
      alertContainer.innerHTML = '';
      alertContainer.appendChild(alertDiv);
      setTimeout(() => {
        if (alertContainer.contains(alertDiv)) {
          alertContainer.removeChild(alertDiv);
        }
      }, 3000);
    }

    // Fungsi untuk mengubah mode alat (scan/add)
    function toggleMode() {
      let modeSwitch = document.getElementById("modeSwitch");
      let modeLabel = document.getElementById("modeLabel");
      let previousState = !modeSwitch.checked;
      let mode = modeSwitch.checked ? "scan" : "add";
      
      fetch("get_set_mode.php?mode=" + mode)
        .then(res => res.json())
        .then(response => {
          if (response.status === "success") {
            modeLabel.innerText = mode === "scan" ? "Scan Presensi" : "Tambah Kartu";
            document.querySelector('#modeSwitch + .slider').style.backgroundColor = "#4E73DF";
            showAlert(response.message, response.status);
          } else {
            modeSwitch.checked = previousState;
            modeLabel.innerText = "Gagal Terhubung!";
            document.querySelector('#modeSwitch + .slider').style.backgroundColor = "#ccc";
            showAlert(response.message, response.status);
          }
        })
        .catch(error => {
          modeSwitch.checked = previousState;
          modeLabel.innerText = "Gagal Terhubung!";
          document.querySelector('#modeSwitch + .slider').style.backgroundColor = "#ccc";
          showAlert("Terjadi kesalahan saat mengubah mode alat. Silakan coba lagi nanti!", "danger");
        });
    }

    // Fungsi untuk memuat mode alat saat ini
    function loadCurrentMode() {
      fetch("get_set_mode.php")
        .then(res => res.json())
        .then(response => {
          let mode = response.mode;
          let modeSwitch = document.getElementById("modeSwitch");
          let modeLabel = document.getElementById("modeLabel");
          const slider = document.querySelector('#modeSwitch + .slider');

          if (mode === "add") {
            modeLabel.innerText = "Tambah Kartu";
            modeSwitch.checked = false;
            slider.style.backgroundColor = "#4E73DF";
          } else if (mode === "scan") {
            modeLabel.innerText = "Scan Presensi";
            modeSwitch.checked = true;
            slider.style.backgroundColor = "#4E73DF";
          } else {
            modeLabel.innerText = "Gagal Terhubung!";
            modeSwitch.checked = false;
            slider.style.backgroundColor = "#ccc";
          }
          if (response.message) {
            showAlert(response.message, response.status);
          }
        });
    }

    // Fungsi untuk mengubah mode flash
    function toggleFlashMode() {
      let flashSwitch = document.getElementById("flashSwitch");
      let flashLabel = document.getElementById("flashLabel");
      let previousState = !flashSwitch.checked;
      let state = flashSwitch.checked ? "on" : "off";
      
      fetch("get_set_mode_flash.php?state=" + state)
        .then(response => response.json())
        .then(data => {
          if (data.status === "success") {
            flashLabel.innerText = state === "on" ? "Flash ON" : "Flash OFF";
            showAlert(data.message, data.status);
          } else {
            flashSwitch.checked = previousState;
            flashLabel.innerText = "Gagal Terhubung!";
            showAlert(data.message, data.status);
          }
        })
        .catch(error => {
          flashSwitch.checked = previousState;
          flashLabel.innerText = "Gagal Terhubung!";
          showAlert("Terjadi kesalahan saat mengubah mode flash. Silakan coba lagi nanti!", "danger");
        });
    }

    // Fungsi untuk memuat mode flash saat ini
    function loadCurrentFlashMode() {
      fetch("get_set_mode_flash.php")
        .then(response => response.json())
        .then(data => {
          let flashSwitch = document.getElementById("flashSwitch");
          let flashLabel = document.getElementById("flashLabel");
          let state = data.state;
          if (state === "on") {
            flashSwitch.checked = true;
            flashLabel.innerText = "Flash ON";
          } else if (state === "off") {
            flashSwitch.checked = false;
            flashLabel.innerText = "Flash OFF";
          } else {
            flashSwitch.checked = false;
            flashLabel.innerText = "Gagal Terhubung!";
          }
          if (data.message) {
            showAlert(data.message, data.status);
          }
        })
        .catch(error => {
          console.error("Error fetching flash mode:", error);
        });
    }

    window.addEventListener("load", function(){
      loadCurrentMode();
      loadCurrentFlashMode();
    });

    // Fungsi untuk mengelola interaksi bar hari operasional
    document.addEventListener('DOMContentLoaded', function(){
      const dayItems = document.querySelectorAll('.day-item');
      const hiddenInput = document.getElementById('hari_operasional_input');

      dayItems.forEach(item => {
        item.addEventListener('click', function(){
          this.classList.toggle('active');
          updateSelectedDays();
        });
      });

      function updateSelectedDays() {
        let selectedValues = [];
        document.querySelectorAll('.day-item.active').forEach(activeItem => {
          selectedValues.push(activeItem.getAttribute('data-value'));
        });
        hiddenInput.value = selectedValues.join(',');
      }
    });

    // Submit form Jam Masuk dengan AJAX
    document.getElementById('jamMasukForm').addEventListener('submit', function(event) {
      event.preventDefault();
      const url = this.getAttribute('action');
      const formData = new FormData(this);
      fetch(url, {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        showAlert(data.message, data.status);
      })
      .catch(error => {
        showAlert('Terjadi kesalahan saat mengupdate jam masuk. Silakan coba lagi nanti!', 'danger');
      });
    });

    // Set waktu otomatis untuk RTC
    document.getElementById('set-time').addEventListener('click', function () {
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      const day = String(now.getDate()).padStart(2, '0');
      const hours = String(now.getHours()).padStart(2, '0');
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const seconds = String(now.getSeconds()).padStart(2, '0');
      const datetime = `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
      document.getElementById('waktu_rtc').value = datetime;

      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'set_waktu_rtc.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        if (xhr.status === 200) {
          const response = JSON.parse(xhr.responseText);
          showAlert(response.message, response.status);
        } else {
          showAlert('Terjadi kesalahan saat mengatur waktu RTC. Silakan coba lagi nanti!', 'danger');
        }
      };
      xhr.send(`waktu_rtc=${datetime}`);
    });

    // Submit form Waktu RTC dengan AJAX
    document.getElementById('rtc-form').addEventListener('submit', function(event) {
      event.preventDefault();
      const waktu_rtc = document.getElementById('waktu_rtc').value;
      if (!waktu_rtc) {
        showAlert('Waktu RTC tidak boleh kosong!', 'danger');
        return;
      }
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'set_waktu_rtc.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        if (xhr.status === 200) {
          const response = JSON.parse(xhr.responseText);
          showAlert(response.message, response.status);
        } else {
          showAlert('Terjadi kesalahan saat mengatur waktu RTC. Silakan coba lagi nanti!', 'danger');
        }
      };
      xhr.send(`waktu_rtc=${waktu_rtc}`);
    });

    // Submit form Hari Operasional dengan AJAX
    document.getElementById('hariOperasionalForm').addEventListener('submit', function(event) {
      event.preventDefault();
      const formData = new FormData(this);
      fetch(this.getAttribute('action'), {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        showAlert(data.message, data.status);
      })
      .catch(error => {
        showAlert('Terjadi kesalahan saat mengupdate hari operasional. Silakan coba lagi nanti!', 'danger');
      });
    });
  </script>
</div>
<!-- End of Container Fluid -->

</div>
<!-- End of Page Content -->

<?php
include 'footer.php';
?>
