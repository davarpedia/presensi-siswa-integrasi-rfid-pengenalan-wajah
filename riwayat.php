<?php 
$pageTitle = "Riwayat Tap RFID";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Inisialisasi filter status (sesuaikan dengan logika Anda)
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Riwayat Tap RFID</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Riwayat Tap RFID</li>
        </ol>
    </nav>
  </div>

  <!-- Form Filter -->
  <form method="GET" action="">
    <div class="form-row">
      <!-- Filter Status -->
      <div class="col-12 col-lg-auto mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <label class="input-group-text" for="filterStatus">Status</label>
          </div>
          <select class="custom-select" id="filterStatus" name="status">
            <option value="" <?php echo ($statusFilter == '' ? 'selected' : ''); ?>>Semua Status</option>
            <option value="Aktif" <?php echo ($statusFilter == 'Aktif' ? 'selected' : ''); ?>>Terdaftar (Aktif)</option>
            <option value="Nonaktif" <?php echo ($statusFilter == 'Nonaktif' ? 'selected' : ''); ?>>Terdaftar (Nonaktif)</option>
            <option value="Tidak Terdaftar" <?php echo ($statusFilter == 'Tidak Terdaftar' ? 'selected' : ''); ?>>Tidak Terdaftar</option>
          </select>
        </div>
      </div>

      <!-- Tombol Tampilkan -->
      <div class="col-12 col-md-12 col-lg-auto mb-3">
        <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
      </div>
    </div>
  </form>

  <!-- Tabel Riwayat -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-bordered text-center table-hover" id="dataTable" width="100%" cellspacing="0">
          <thead>
            <tr>
              <th>No</th>
              <th>RFID</th>
              <th>Waktu</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php
            // Query dasar untuk mengambil data riwayat tap RFID
            $sql = "SELECT h.*, s.status AS siswa_status FROM history_rfid h
                    LEFT JOIN siswa s ON h.no_rfid = s.no_rfid";

            // Jika ada filter status, tambahkan kondisi WHERE
            if ($statusFilter !== '') {
              if ($statusFilter == 'Aktif') {
                $sql .= " WHERE s.status = 'Aktif'";
              } elseif ($statusFilter == 'Nonaktif') {
                $sql .= " WHERE s.status = 'Nonaktif'";
              } elseif ($statusFilter == 'Tidak Terdaftar') {
                $sql .= " WHERE s.status IS NULL";
              }
            }
            $sql .= " ORDER BY h.waktu DESC";
            $result = $conn->query($sql);
            $nomor = 1;

            if ($result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                $no_rfid = $row['no_rfid'];
                if (!empty($row['siswa_status'])) {
                  if ($row['siswa_status'] == 'Aktif') {
                    $status = "Terdaftar (Aktif)";
                  } else {
                    $status = "Terdaftar (Nonaktif)";
                  }
                  $aksi = "<a href='siswa.php?rfid={$no_rfid}' class='btn btn-secondary btn-sm' title='Lihat Data'>
                              <i class='bi bi-eye-fill'></i>
                          </a>";
                } else {
                  $status = "Tidak Terdaftar"; 
                  $aksi = "<a href='tambah_siswa.php?rfid={$no_rfid}' class='btn btn-primary btn-sm' title='Daftarkan'>
                              <i class='bi bi-person-plus-fill'></i>
                           </a>";
                }

                echo "<tr>
                        <td>" . htmlspecialchars($nomor) . "</td>
                        <td>" . htmlspecialchars($row['no_rfid']) . "</td>
                        <td>" . date("d/m/Y H:i:s", strtotime($row['waktu'])) . "</td>
                        <td>" . htmlspecialchars($status) . "</td>
                        <td>" . $aksi . "</td>
                      </tr>";
                $nomor++;
              }
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- End of Page Content -->
</div>

<?php include 'footer.php'; ?>

<!-- Inisialisasi Data Tables -->
<script>
  $(document).ready(function() {
    $('#dataTable').DataTable({
      "paging": true,
      "searching": true,
      "ordering": true,
      "info": true,
      "language": {
        "emptyTable": "Tidak ada data riwayat tap RFID yang tersedia"
      }
    });
  });
</script>
