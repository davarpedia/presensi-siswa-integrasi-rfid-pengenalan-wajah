<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';

// Endpoint update status via AJAX (tetap sama)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_update_status'])) {
    $no_rfid = $conn->real_escape_string($_POST['no_rfid']);
    $status  = $conn->real_escape_string($_POST['status']);
    $tanggal = $conn->real_escape_string($_POST['tanggal']);

    // Cek apakah data presensi untuk no_rfid dan tanggal tersebut sudah ada
    $checkQuery = "SELECT id, status, waktu_masuk, waktu_keluar FROM presensi WHERE no_rfid = '$no_rfid' AND tanggal = '$tanggal'";
    $resultCheck = $conn->query($checkQuery);

    if ($resultCheck->num_rows > 0) {
        $existing = $resultCheck->fetch_assoc();
        
        // Cek apakah sudah ada data waktu yang valid
        $hasValidMasuk = (!is_null($existing['waktu_masuk']) && $existing['waktu_masuk'] != '00:00:00');
        $hasValidKeluar = (!is_null($existing['waktu_keluar']) && $existing['waktu_keluar'] != '00:00:00');

        // Jika sudah ada data waktu yang valid, update hanya status saja
        if ($hasValidMasuk || $hasValidKeluar) {
            $updateSql = "UPDATE presensi SET status = '$status' WHERE no_rfid = '$no_rfid' AND tanggal = '$tanggal'";
        } else {
            // Jika data belum ada, update dengan reset untuk status manual
            if (in_array($status, ['Izin', 'Sakit', 'Alpa'])) {
                $updateSql = "UPDATE presensi 
                              SET status = '$status', 
                                  waktu_masuk = NULL, 
                                  foto_masuk = '', 
                                  waktu_keluar = NULL, 
                                  foto_keluar = '' 
                              WHERE no_rfid = '$no_rfid' AND tanggal = '$tanggal'";
            } else {
                $updateSql = "UPDATE presensi SET status = '$status' WHERE no_rfid = '$no_rfid' AND tanggal = '$tanggal'";
            }
        }
        
        if ($conn->query($updateSql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Status presensi berhasil diubah']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    } else {
        // Jika data belum ada, lakukan INSERT dengan menggunakan tanggal yang dikelola
        if (in_array($status, ['Izin', 'Sakit', 'Alpa'])) {
            $insertSql = "INSERT INTO presensi (no_rfid, tanggal, status, waktu_masuk, foto_masuk, waktu_keluar, foto_keluar) 
                          VALUES ('$no_rfid', '$tanggal', '$status', NULL, '', NULL, '')";
        } else {
            $insertSql = "INSERT INTO presensi (no_rfid, tanggal, status) 
                          VALUES ('$no_rfid', '$tanggal', '$status')";
        }
        if ($conn->query($insertSql) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'Status presensi berhasil disimpan']);
        } else {
            echo json_encode(['success' => false, 'message' => $conn->error]);
        }
        exit;
    }
}

// Tentukan level user. Jika bukan Admin, anggap Guru.
$isAdmin = (isset($_SESSION['session_level']) && strtolower($_SESSION['session_level']) === 'admin');

$pageTitle = "Kelola Presensi";
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Ambil parameter kelas dan tanggal dari URL
$kelasFilter   = $_GET['kelas'] ?? '';
$tanggalFilter = $_GET['tanggal'] ?? '';

// Pengaturan disableColumns (sesuai kebutuhan tampilan tabel)
if (empty($kelasFilter)) {
    $disableColumns = [3,6,8,9];
} elseif (!empty($kelasFilter)) {
    $disableColumns = [3,5,7,8];
}

// Cek apakah tanggal yang dipilih merupakan hari libur (operasional dan libur nasional)
$isHoliday = false;
if (!empty($tanggalFilter)) {
    $dt = new DateTime($tanggalFilter);

    // Ambil setting hari operasional dari database
    $sqlOperasional = "SELECT hari_operasional FROM pengaturan WHERE id = 1";
    $resultOperasional = $conn->query($sqlOperasional);
    $operational_days = [];
    if ($resultOperasional && $resultOperasional->num_rows > 0) {
        $rowOps = $resultOperasional->fetch_assoc();
        if (!empty($rowOps['hari_operasional'])) {
            $operational_days = array_map('trim', explode(',', $rowOps['hari_operasional']));
            $operational_days = array_map('intval', $operational_days);
        }
    }
    if (empty($operational_days)) {
        $isHoliday = true;
    } else {
        $day_number = (int)$dt->format('N');
        if (!in_array($day_number, $operational_days)) {
            $isHoliday = true;
        }
    }
    // Cek apakah tanggal tersebut termasuk hari libur nasional
    $sqlHolidayCheck = "SELECT id FROM hari_libur WHERE '$tanggalFilter' BETWEEN tanggal_mulai AND tanggal_selesai";
    $resultHolidayCheck = $conn->query($sqlHolidayCheck);
    if ($resultHolidayCheck && $resultHolidayCheck->num_rows > 0) {
        $isHoliday = true;
    }
}

// --- Ambil daftar kelas secara dinamis ---
// Admin: ambil semua kelas
// Guru: hanya ambil kelas yang diampu
$kelasList = [];
if ($isAdmin) {
    $sqlKelas = "SELECT * FROM kelas ORDER BY nama_kelas ASC";
    $resultKelas = $conn->query($sqlKelas);
    if ($resultKelas && $resultKelas->num_rows > 0) {
        while ($rowKelas = $resultKelas->fetch_assoc()) {
            $kelasList[] = $rowKelas;
        }
    }
} else {
    $guruId = $_SESSION['guru_id'] ?? null;
    $sqlKelas = "SELECT k.* FROM kelas k 
                 JOIN guru g ON k.id_guru = g.id 
                 WHERE g.id = ? AND g.status = 'Aktif'
                 ORDER BY k.nama_kelas ASC";
    $stmt = $conn->prepare($sqlKelas);
    $stmt->bind_param('i', $guruId);
    $stmt->execute();
    $resultKelas = $stmt->get_result();
    while ($rowKelas = $resultKelas->fetch_assoc()) {
        $kelasList[] = $rowKelas;
    }
    $stmt->close();
}

// --- Siapkan query untuk mengambil data presensi ---
// Query dieksekusi hanya jika tanggal dipilih.
// Bagi Guru, jika kelas belum dipilih, kunci query dengan kondisi agar tidak mengembalikan data.
if (!empty($tanggalFilter)) {
    $sql = "SELECT s.nis, s.no_rfid, s.nama, s.foto_siswa, s.id_kelas, k.nama_kelas, 
                p.tanggal, p.waktu_masuk, p.foto_masuk, p.waktu_keluar, p.foto_keluar, p.status 
            FROM siswa s
            LEFT JOIN presensi p ON s.no_rfid = p.no_rfid AND p.tanggal = '$tanggalFilter'
            LEFT JOIN kelas k ON s.id_kelas = k.id
            WHERE s.status = 'Aktif'";
    if (!empty($kelasFilter)) {
        $sql .= " AND k.nama_kelas = '$kelasFilter'";
    } else {
        if (!$isAdmin) {
            $sql .= " AND 1=0"; // Guru harus memilih kelas
        }
    }
    $sql .= " ORDER BY p.tanggal DESC, p.waktu_masuk DESC";
    $result = $conn->query($sql);
}
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Kelola Presensi</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Kelola Presensi</li>
        </ol>
    </nav>
  </div>

  <?php 
  // Jika level Guru dan (kelas atau tanggal belum dipilih), tampilkan instruksi
  if (!$isAdmin && (empty($kelasFilter) || empty($tanggalFilter))) { 
      echo '<p class="mb-4">Silahkan pilih kelas dan tanggal yang ingin ditampilkan.</p>';
  } elseif (empty($tanggalFilter)) {
      // Untuk Admin hanya tampilkan instruksi bila tanggal belum dipilih
      echo '<p class="mb-4">Silahkan pilih kelas dan tanggal yang ingin ditampilkan.</p>';
  }
  ?>

  <!-- Form Filter -->
  <form id="filterForm" method="GET" action="">
    <div class="form-row">

      <!-- Pilihan Kelas -->
      <div class="col-12 col-lg-auto mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <label class="input-group-text" for="filterKelas">Kelas</label>
          </div>
          <select class="custom-select" id="filterKelas" name="kelas">
            <?php if ($isAdmin): ?>
              <option value="" <?= ($kelasFilter == '' ? 'selected' : '') ?>>Semua Kelas</option>
            <?php else: ?>
              <option value="" <?= ($kelasFilter == '' ? 'selected' : '') ?>>Pilih Kelas</option>
            <?php endif; ?>
            <?php foreach ($kelasList as $kelasRow): 
                      $selected = ($kelasFilter == $kelasRow['nama_kelas']) ? 'selected' : ''; ?>
              <option value="<?= $kelasRow['nama_kelas'] ?>" <?= $selected ?>><?= $kelasRow['nama_kelas'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Pilihan Tanggal -->
      <div class="col-12 col-lg-auto mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <label class="input-group-text" for="filterTanggal">Tanggal</label>
          </div>
          <input type="date" id="filterTanggal" class="form-control" name="tanggal" value="<?= htmlspecialchars($tanggalFilter) ?>">
        </div>
      </div>

      <!-- Tombol Tampilkan -->
      <div class="col-12 col-lg-auto mb-3">
        <button class="btn btn-primary btn-block" type="submit">Tampilkan</button>
      </div>
    </div>
  </form>

  <?php if (!empty($tanggalFilter)) { ?>
    <!-- Card untuk membungkus tabel -->
    <div class="card">
      <div class="card-body">
        <div class="table-responsive">
          <table id="dataTable" class="table table-striped table-bordered table-hover">
            <thead class="bg-white">
              <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Foto Siswa</th>
                <?php if ($kelasFilter == ''): ?>
                  <th>Kelas</th>
                <?php endif; ?>
                <th>Waktu Masuk</th>
                <th>Foto Masuk</th>
                <th>Waktu Keluar</th>
                <th>Foto Keluar</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              if (!$isHoliday) {
                  $nomor = 1;
                  $foto_default = 'img/default_image.jpg';
                  if ($result && $result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                          $nis = $row['nis'];
                          $no_rfid = $row['no_rfid'];
                          $status = !empty($row['status']) ? $row['status'] : 'Alpa';
                          $waktuMasuk = $row['waktu_masuk'];
                          $waktuKeluar = $row['waktu_keluar'];
                          $fotoMasuk = $row['foto_masuk'];
                          $fotoKeluar = $row['foto_keluar'];

                          // Tampilkan foto siswa
                          $foto_siswa_path = "data/foto/foto_siswa/{$row['foto_siswa']}";
                          $foto_siswa = !empty($row['foto_siswa']) && file_exists($foto_siswa_path)
                              ? "<div class='img-circle-crop'><img src='{$foto_siswa_path}' alt='Foto Siswa' width='50' height='50'></div>"
                              : "<div class='img-circle-crop'><img src='{$foto_default}' alt='Foto Siswa' width='50' height='50'></div>";

                          // Tampilkan foto masuk
                          $foto_masuk_path = "data/foto/foto_presensi_siswa/{$fotoMasuk}";
                          $foto_masuk = (!empty($fotoMasuk) && file_exists($foto_masuk_path))
                              ? "<div class='img-circle-crop'><img src='{$foto_masuk_path}' alt='Foto Masuk' width='50' height='50'></div>"
                              : "";

                          // Tampilkan foto keluar
                          $foto_keluar_path = "data/foto/foto_presensi_siswa/{$fotoKeluar}";
                          $foto_keluar = (!empty($fotoKeluar) && file_exists($foto_keluar_path))
                              ? "<div class='img-circle-crop'><img src='{$foto_keluar_path}' alt='Foto Keluar' width='50' height='50'></div>"
                              : "";

                          echo "<tr>
                                  <td>{$nomor}</td>
                                  <td>{$nis}</td>
                                  <td>{$row['nama']}</td>
                                  <td>{$foto_siswa}</td>";
                          if ($kelasFilter == '') {
                              echo "<td>{$row['nama_kelas']}</td>";
                          }
                          echo "  <td>{$waktuMasuk}</td>
                                  <td>{$foto_masuk}</td>
                                  <td>{$waktuKeluar}</td>
                                  <td>{$foto_keluar}</td>
                                  <td>
                                    <input type='hidden' class='no_rfid-hidden' value='{$no_rfid}'>
                                    <select name='status' class='custom-select w-auto status-dropdown' data-no_rfid='{$no_rfid}'>
                                      <option value='Masuk' " . ($status == 'Masuk' ? 'selected' : '') . ">Masuk</option>
                                      <option value='Hadir' " . ($status == 'Hadir' ? 'selected' : '') . ">Hadir</option>
                                      <option value='Izin' " . ($status == 'Izin' ? 'selected' : '') . ">Izin</option>
                                      <option value='Sakit' " . ($status == 'Sakit' ? 'selected' : '') . ">Sakit</option>
                                      <option value='Alpa' " . ($status == 'Alpa' ? 'selected' : '') . ">Alpa</option>
                                    </select>
                                  </td>
                                </tr>";
                          $nomor++;
                      }
                  }
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php } ?>
</div>
<!-- End of Page Content -->

</div>

<?php
include 'footer.php';
?>

<!-- Script DataTables -->
<script>
var isHoliday = <?php echo $isHoliday ? 'true' : 'false'; ?>;
$(document).ready(function() {
    $('#dataTable').DataTable({
        "paging": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "language": {
            "emptyTable": isHoliday ? "Libur" : "Tidak ada data presensi untuk kelas dan tanggal yang dipilih"
        },
        columnDefs: [
            { orderable: false, targets: <?php echo json_encode($disableColumns); ?> }
        ]
    });
});
</script>

<!-- Script AJAX untuk update status -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusDropdowns = document.querySelectorAll('.status-dropdown');
    statusDropdowns.forEach(function (dropdown) {
        dropdown.addEventListener('change', function () {
            const no_rfid = this.getAttribute('data-no_rfid');
            const status = this.value;
            const tanggal = document.getElementById('filterTanggal').value;
            if (!tanggal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Tanggal harus diisi!',
                    showConfirmButton: false,
                    timer: 2000
                });
                return;
            }
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    ajax_update_status: true,
                    no_rfid: no_rfid,
                    status: status,
                    tanggal: tanggal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Terjadi kesalahan saat mengubah status presensi!',
                    showConfirmButton: false,
                    timer: 2000
                });
            });
        });
    });

    // Validasi form: 
    // - Di level Admin: tanggal tidak boleh kosong.
    // - Di level Guru: kelas dan tanggal tidak boleh kosong.
    $('#filterForm').on('submit', function(e) {
      let tanggal = $('#filterTanggal').val();
      <?php if (!$isAdmin): ?>
      let kelas = $('#filterKelas').val();
      if (!kelas || !tanggal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Peringatan',
          text: 'Silahkan pilih kelas dan tanggal yang ingin ditampilkan!',
          confirmButtonText: 'OK',
          customClass: {
            confirmButton: 'btn btn-primary'
          },
          buttonsStyling: false
        });
      }
      <?php else: ?>
      if (!tanggal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Peringatan',
          text: 'Silahkan pilih tanggal yang ingin ditampilkan!',
          confirmButtonText: 'OK',
          customClass: {
            confirmButton: 'btn btn-primary'
          },
          buttonsStyling: false
        });
      }
      <?php endif; ?>
    });
});
</script>
