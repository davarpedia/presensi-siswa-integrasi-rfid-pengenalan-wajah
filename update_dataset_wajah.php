<?php
$pageTitle = "Update Dataset Wajah";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Inisialisasi variabel error
$error = "";

$id = $_GET['id'] ?? '';

if (empty($id)) {
    $error = "ID tidak valid.";
} else {
    // Query database berdasarkan id untuk mendapatkan detail siswa
    $query = "SELECT id, nis, nama, no_rfid FROM siswa WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();

    if ($siswa) {
        $id = $siswa['id'];
        $nis = $siswa['nis'];
        $student_name = $siswa['nama'];
        $rfid = $siswa['no_rfid'];
    } else {
        $error = "Siswa tidak ditemukan.";
    }
}

$flask_url = "http://192.168.121.177:5000"; // URL Flask server
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Update Dataset Wajah</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="siswa.php">Data Siswa</a></li>
        <li class="breadcrumb-item"><a href="kelola_dataset_wajah.php?id=<?= $siswa['id'] ?>">Kelola Dataset Wajah</a></li>
        <li class="breadcrumb-item active" aria-current="page">Update Dataset Wajah</li>
        </ol>
    </nav>
  </div>

  <?php if (!empty($error)) : ?>
    <!-- Tampilkan pesan error di dalam container -->
    <div class="alert alert-danger text-center">
      <?php echo $error; ?>
    </div>
  <?php else: ?>
    <!-- Konten pengambilan dataset -->
    <div id="instructions" class="alert alert-secondary text-center">
        Tekan tombol 'Mulai Capture' untuk memulai pengambilan dataset wajah.
    </div>

    <div id="message" class="alert alert-secondary text-center" style="display: none;"></div>

    <div class="row justify-content-center text-center mb-3">
          <div class="col-12 col-md-8 col-lg-5">
          <img id="videoFeed" src="<?= $flask_url ?>/video_feed_capture" class="img-fluid rounded" alt="Video Feed">
          </div>
    </div>

    <div class="text-center">
        <button id="startButton" class="btn btn-primary mb-4">Mulai Capture</button>
        <button id="retryButton" class="btn btn-danger mb-4 mr-2" style="display:none;">Ulangi Capture</button>
        <button id="embeddingButton" class="btn btn-success mb-4" style="display:none;">Lakukan Embedding</button>
    </div>

    <script>
          // Saat halaman dimuat, disable face recognition agar tidak mengganggu proses capture dataset
          fetch("<?= $flask_url ?>/disable_face_recognition", { method: 'POST' })
              .then(response => response.json())
              .then(data => console.log(data.message))
              .catch(error => console.error('Error disable face recognition:', error));

          // Saat halaman akan ditutup, aktifkan kembali face recognition
          window.addEventListener("beforeunload", function () {
              fetch("<?= $flask_url ?>/enable_face_recognition", { method: 'POST' })
                  .then(response => response.json())
                  .then(data => console.log(data.message))
                  .catch(error => console.error('Error enable face recognition:', error));
          });

          // Fungsi untuk mengubah tampilan alert message berdasarkan status
          function updateAlertMessage(elementId, message, type) {
              const msgElement = document.getElementById(elementId);
              msgElement.innerText = message;
              msgElement.style.display = "block";
              // Tentukan kelas alert berdasarkan jenis pesan
              if (type === 'error') {
                  msgElement.className = 'alert alert-danger text-center';
              } else if (type === 'success') {
                  msgElement.className = 'alert alert-success text-center';
              } else if (type === 'info') {
              msgElement.className = 'alert alert-secondary text-center';
            }
          }

          // Mulai Capture ketika tombol "Mulai Capture" diklik
          document.getElementById("startButton").addEventListener("click", function() {
              const nis = "<?= $nis ?>";
              const student_name = "<?= $student_name ?>";
              const rfid = "<?= $rfid ?>";

              // Sembunyikan instruksi dan tombol start
              document.getElementById("instructions").style.display = "none";
              document.getElementById("startButton").style.display = "none";

              fetch("<?= $flask_url ?>/start_capture", {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ nis: nis, student_name: student_name, rfid: rfid })
              })
              .then(response => response.json())
              .then(result => {
                  if(result.error) {
                      updateAlertMessage('message', result.error, 'error');
                  } else {
                      updateAlertMessage('message', result.message, 'info');
                  }
                  checkCaptureStatus();
              })
              .catch(error => {
                  updateAlertMessage('message', 'Terjadi kesalahan: ' + error, 'error');
              });
          });

          // Fungsi untuk mengecek status capture setiap detik
          function checkCaptureStatus() {
              let interval = setInterval(() => {
                  fetch("<?= $flask_url ?>/capture_status")
                  .then(response => response.json())
                  .then(result => {
                      if (result.message) {
                          clearInterval(interval);
                          updateAlertMessage('message', result.message, 'success');
                          // Hentikan kamera capture
                          stopCamera();
                          // Tampilkan tombol "Ulangi Capture" dan "Lakukan Embedding"
                          document.getElementById("retryButton").style.display = "inline";
                          document.getElementById("embeddingButton").style.display = "inline";
                      }
                  });
              }, 1000);
          }

          // Fungsi untuk mematikan kamera
          function stopCamera() {
              fetch("<?= $flask_url ?>/stop_camera", { method: 'POST' })
                  .then(response => response.json())
                  .then(data => console.log(data.message))
                  .catch(error => console.error('Error:', error));
          }

          // Tombol "Lakukan Embedding": ketika diklik, memicu endpoint /update_face_data dengan data baru, lalu redirect
          document.getElementById("embeddingButton").addEventListener("click", function() {
              const updateData = {
                  nis: "<?= $nis ?>",
                  name: "<?= $student_name ?>", 
                  rfid: "<?= $rfid ?>"   
              };

              // Tampilkan pesan status saat update dimulai
              updateAlertMessage('message', "Sedang melakukan embedding dataset wajah. Harap tunggu sebentar...", 'info');
          
              fetch("<?= $flask_url ?>/update_face_data", {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify(updateData)
              })
              .then(response => response.json())
              .then(data => {
                  if (data.error) {
                      updateAlertMessage('message', data.error, 'error');
                  } else {
                      updateAlertMessage('message', data.message, 'success');
                      // Setelah update selesai, redirect ke halaman tertentu
                      setTimeout(function(){
                          window.location.href = "kelola_dataset_wajah.php?id=<?= $siswa['id'] ?>";
                      }, 2000);
                  }
              })
              .catch(error => {
                  updateAlertMessage('message', 'Error: ' + error, 'error');
              });
          });

          // Tombol "Ulangi Capture": hapus dataset lama dan reload halaman capture
          document.getElementById("retryButton").addEventListener("click", function() {
              const nis = "<?= $nis ?>";
              const student_name = "<?= $student_name ?>";
              const rfid = "<?= $rfid ?>";

              fetch("<?= $flask_url ?>/delete_old_dataset", {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ nis: nis, student_name: student_name, rfid: rfid })
              })
              .then(response => response.json())
              .then(result => {
                  if (result.error) {
                      updateAlertMessage('message', result.error, 'error');
                  } else {
                      updateAlertMessage('message', result.message, 'success');
                  }
                  location.reload();
              })
              .catch(error => {
                  updateAlertMessage('message', 'Terjadi kesalahan: ' + error, 'error');
              });
          });

          // Jika halaman ditutup, pastikan kamera dimatikan
          window.addEventListener("beforeunload", function () {
              navigator.sendBeacon("<?= $flask_url ?>/stop_camera");
          });
      </script>

    <?php endif; ?>

</div>
<!-- End of Container Fluid -->

</div>
<!-- End of Page Content -->

<?php
include 'footer.php';
?>
