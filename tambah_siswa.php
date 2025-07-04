<?php
$pageTitle = "Tambah Siswa";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

// Kosongkan tabel tmp_rfid_tambah setiap kali halaman di-refresh
mysqli_query($conn, "DELETE FROM tmp_rfid_tambah");

// Inisialisasi variabel menggunakan null coalescing
$no_rfid       = $_POST['no_rfid'] ?? '';
$nis           = $_POST['nis'] ?? '';
$nama          = $_POST['nama'] ?? '';
$kelas_id      = $_POST['kelas_id'] ?? '';
$alamat        = $_POST['alamat'] ?? '';
$jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
$token         = $_POST['token'] ?? '';
$id_chat       = $_POST['id_chat'] ?? '';
$foto_siswa    = null;

// Variabel untuk menampung script alert (dibungkus <script>…</script>)
$alertScript = '';

// Proses penyimpanan data jika metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Validasi: pastikan no_rfid tidak kosong
    if (empty($no_rfid)) {
        $alertScript = "
        <script>
          document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
              title: 'Peringatan',
              text: 'Nomor RFID tidak boleh kosong! Silakan tempelkan kartu RFID yang ingin didaftarkan pada alat!',
              icon: 'warning',
              customClass: {
                confirmButton: 'btn btn-primary'
              },
              buttonsStyling: false
            });
          });
        </script>";
    } else {
        // 2. Cek apakah no_rfid sudah terdaftar
        $cekRfidQuery = "SELECT * FROM siswa WHERE no_rfid = ?";
        $stmtRfid = $conn->prepare($cekRfidQuery);
        $stmtRfid->bind_param("s", $no_rfid);
        $stmtRfid->execute();
        $resultRfid = $stmtRfid->get_result();

        // 3. Cek apakah NIS sudah terdaftar
        $cekNisQuery = "SELECT * FROM siswa WHERE nis = ?";
        $stmtNis = $conn->prepare($cekNisQuery);
        $stmtNis->bind_param("s", $nis);
        $stmtNis->execute();
        $resultNis = $stmtNis->get_result();

        // 4. Siapkan pesan peringatan jika ada duplikasi
        $peringatan = "";
        if ($resultRfid->num_rows > 0 && $resultNis->num_rows > 0) {
            $peringatan = "Nomor RFID dan NIS sudah terdaftar! Silakan gunakan yang berbeda!";
        } elseif ($resultRfid->num_rows > 0) {
            $peringatan = "Nomor RFID sudah terdaftar! Silakan gunakan kartu RFID yang berbeda!";
        } elseif ($resultNis->num_rows > 0) {
            $peringatan = "NIS sudah terdaftar! Silakan gunakan NIS yang berbeda!";
        }

        if (!empty($peringatan)) {
            $alertScript = "
            <script>
              document.addEventListener('DOMContentLoaded', function(){
                Swal.fire({
                  title: 'Peringatan',
                  text: '$peringatan',
                  icon: 'warning',
                  customClass: {
                    confirmButton: 'btn btn-primary'
                  },
                  buttonsStyling: false
                });
              });
            </script>";
        } else {
            // 5. Proses upload foto jika ada file
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $imageFileType = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $foto_siswa    = $nis . "_" . preg_replace('/\s+/', '_', $nama) . "_" . $no_rfid . "." . $imageFileType;
                $targetDir     = "data/foto/foto_siswa/";

                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $targetFile = $targetDir . $foto_siswa;

                // Cek apakah file benar‐benar gambar
                $check = getimagesize($_FILES['foto']['tmp_name']);
                if ($check === false) {
                    $alertScript = "
                    <script>
                      document.addEventListener('DOMContentLoaded', function(){
                        Swal.fire({
                          title: 'Peringatan',
                          text: 'File yang diupload bukan gambar!',
                          icon: 'warning',
                          customClass: {
                            confirmButton: 'btn btn-primary'
                          },
                          buttonsStyling: false
                        }).then(() => {
                          window.history.back();
                        });
                      });
                    </script>";
                }
                // Cek ukuran file maksimum 5MB
                elseif ($_FILES['foto']['size'] > 5000000) {
                    $alertScript = "
                    <script>
                      document.addEventListener('DOMContentLoaded', function(){
                        Swal.fire({
                          title: 'Peringatan',
                          text: 'Ukuran file terlalu besar! Maksimal 5MB.',
                          icon: 'warning',
                          customClass: {
                            confirmButton: 'btn btn-primary'
                          },
                          buttonsStyling: false
                        }).then(() => {
                          window.history.back();
                        });
                      });
                    </script>";
                }
                // Jika semua validasi di atas lolos, pindahkan file
                elseif (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
                    $alertScript = "
                    <script>
                      document.addEventListener('DOMContentLoaded', function(){
                        Swal.fire({
                          title: 'Error',
                          text: 'Maaf, terjadi kesalahan saat mengupload file.',
                          icon: 'error',
                          customClass: {
                            confirmButton: 'btn btn-primary'
                          },
                          buttonsStyling: false
                        }).then(() => {
                          window.history.back();
                        });
                      });
                    </script>";
                }
            }

            // 6. Jika tidak ada alertScript (semua validasi lolos), simpan ke database
            if (empty($alertScript)) {
                $insertQuery = "INSERT INTO siswa 
                    (no_rfid, nis, nama, jenis_kelamin, kelas_id, alamat, token, id_chat, foto_siswa)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtInsert = $conn->prepare($insertQuery);
                $stmtInsert->bind_param(
                    "sssssssss",
                    $no_rfid,
                    $nis,
                    $nama,
                    $jenis_kelamin,
                    $kelas_id,
                    $alamat,
                    $token,
                    $id_chat,
                    $foto_siswa
                );

                if ($stmtInsert->execute()) {
                    $id = $conn->insert_id;
                    header("Location: tambah_dataset_wajah.php?id=$id");
                    exit();
                } else {
                    // Jika query gagal
                    $errorDB = addslashes($stmtInsert->error);
                    $alertScript = "
                    <script>
                      document.addEventListener('DOMContentLoaded', function(){
                        Swal.fire({
                          title: 'Error',
                          text: 'Gagal menyimpan data ke database: $errorDB',
                          icon: 'error',
                          customClass: {
                            confirmButton: 'btn btn-primary'
                          },
                          buttonsStyling: false
                        }).then(() => {
                          window.history.back();
                        });
                      });
                    </script>";
                }
                $stmtInsert->close();
            }
        }

        $stmtRfid->close();
        $stmtNis->close();
    }
}

// Ambil data kelas untuk dropdown
$queryKelas   = "SELECT * FROM kelas";
$resultKelas  = mysqli_query($conn, $queryKelas);
$kelasOptions = [];
while ($rowKelas = mysqli_fetch_assoc($resultKelas)) {
    $kelasOptions[] = $rowKelas;
}

$conn->close();

// ====== Mulai bagian tampilan (HTML + PHP includes) ======
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Tambah Siswa</h1>
    <div id="dateTimeDisplay" class="text-muted text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="siswa.php">Data Siswa</a></li>
        <li class="breadcrumb-item active" aria-current="page">Tambah Siswa</li>
      </ol>
    </nav>
  </div>

  <!-- RFID Section -->
  <div class="card border-primary mb-4" style="background-color: rgba(0, 122, 204, 0.1);">
    <div class="card-body p-4">
      <div class="text-center">
        <label for="rfid" class="form-label font-weight-bold">No. RFID</label>
        <div class="row justify-content-center">
          <div class="col-12 col-md-6">
            <input id="rfid" class="form-control text-center" value="Tempelkan Kartu" disabled style="background-color: #f8f9fa;">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Formulir Section -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="POST" action="" enctype="multipart/form-data">
        <!-- Hidden input untuk menyimpan no_rfid yang didapat dari AJAX atau URL -->
        <input type="hidden" name="no_rfid" id="no_rfid" value="<?php echo htmlspecialchars($no_rfid); ?>">

        <div class="form-group">
          <label for="nis">NIS</label>
          <input
            type="text"
            id="nis"
            name="nis"
            class="form-control"
            value="<?php echo htmlspecialchars($nis); ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="nama">Nama</label>
          <input
            type="text"
            id="nama"
            name="nama"
            class="form-control"
            value="<?php echo htmlspecialchars($nama); ?>"
            required
          >
        </div>

        <div class="form-group">
          <label>Jenis Kelamin</label><br>
          <div class="custom-control custom-radio custom-control-inline">
            <input
              type="radio"
              id="jenis_kelamin_L"
              name="jenis_kelamin"
              value="L"
              class="custom-control-input"
              <?php if ($jenis_kelamin === 'L') echo 'checked'; ?>
            >
            <label class="custom-control-label" for="jenis_kelamin_L">Laki-laki</label>
          </div>
          <div class="custom-control custom-radio custom-control-inline">
            <input
              type="radio"
              id="jenis_kelamin_P"
              name="jenis_kelamin"
              value="P"
              class="custom-control-input"
              <?php if ($jenis_kelamin === 'P') echo 'checked'; ?>
            >
            <label class="custom-control-label" for="jenis_kelamin_P">Perempuan</label>
          </div>
        </div>

        <div class="form-group">
          <label for="kelas_id">Kelas</label>
          <select id="kelas_id" name="kelas_id" class="custom-select" required>
            <option value="">Pilih Kelas</option>
            <?php foreach ($kelasOptions as $kelasRow): ?>
              <option
                value="<?php echo $kelasRow['id']; ?>"
                <?php echo ($kelas_id == $kelasRow['id']) ? 'selected' : ''; ?>
              >
                <?php echo htmlspecialchars($kelasRow['nama_kelas']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="alamat">Alamat</label>
          <textarea
            id="alamat"
            name="alamat"
            class="form-control"
            rows="4"
            required
          ><?php echo htmlspecialchars($alamat); ?></textarea>
        </div>

        <div class="form-group">
          <label for="token">Token</label>
          <input
            type="text"
            id="token"
            name="token"
            class="form-control"
            value="<?php echo htmlspecialchars($token); ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="id_chat">ID Chat</label>
          <input
            type="text"
            id="id_chat"
            name="id_chat"
            class="form-control"
            value="<?php echo htmlspecialchars($id_chat); ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="foto">Foto Siswa</label>
          <div class="custom-file">
            <input
              type="file"
              class="custom-file-input"
              id="foto"
              name="foto"
              accept="image/*"
              required
            >
            <label class="custom-file-label" for="foto">Pilih File</label>
          </div>
          <small class="form-text text-danger mt-1">* Maksimal ukuran foto 5MB.</small>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Tambah Siswa</button>
      </form>
    </div>
  </div>
</div>
<!-- End Page Content -->

<?php include 'footer.php'; ?>

<!-- Jika ada alert, cetak script-nya di sini -->
<?php
if (!empty($alertScript)) {
    echo $alertScript;
}
?>

<!-- Script untuk menampilkan nama file di custom-file-label -->
<script>
  $('#foto').on('change', function(){
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').html(fileName);
  });
</script>

<!-- Script untuk mengambil data RFID secara AJAX -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var urlParams   = new URLSearchParams(window.location.search);
    var rfidFromUrl = urlParams.get('rfid');
    if (rfidFromUrl) {
      document.getElementById('rfid').value   = rfidFromUrl;
      document.getElementById('no_rfid').value = rfidFromUrl;
      var newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
      window.history.replaceState({}, document.title, newUrl);
    }
    fetchData();
  });

  function fetchData() {
    $.ajax({
      url: "./get_rfid.php",
      type: "GET",
      dataType: "json",
      success: function (data) {
        if (data.id && data.id !== "") {
          if (document.getElementById('no_rfid').value !== data.id) {
            document.getElementById('rfid').value   = data.id;
            document.getElementById('no_rfid').value = data.id;
          }
        } else {
          if (document.getElementById('no_rfid').value === "") {
            document.getElementById('rfid').value = "Tempelkan Kartu";
          }
        }
        setTimeout(fetchData, 1000);
      },
      error: function (xhr, status, error) {
        console.log("Terjadi kesalahan: " + error);
        setTimeout(fetchData, 1000);
      }
    });
  }
</script>
