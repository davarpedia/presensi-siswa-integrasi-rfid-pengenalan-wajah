<?php  
$pageTitle = "Data Presensi";
require_once 'koneksi.php';
require_once 'autentikasi.php';
include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Tentukan level user. Jika level bukan Admin, anggap Guru.
$isAdmin = (isset($_SESSION['session_level']) && strtolower($_SESSION['session_level']) === 'admin');

// Ambil parameter filter dari URL
$kelasFilter   = $_GET['kelas']   ?? '';
$tanggalFilter = $_GET['tanggal'] ?? '';

// Ambil daftar kelas secara dinamis sesuai level user
$kelasList = [];
if ($isAdmin) {
    // Admin: ambil semua kelas
    $sqlKelas = "SELECT * FROM kelas ORDER BY nama_kelas ASC";
    $resultKelas = $conn->query($sqlKelas);
    if ($resultKelas && $resultKelas->num_rows > 0) {
        while ($rowKelas = $resultKelas->fetch_assoc()) {
            $kelasList[] = $rowKelas;
        }
    }
} else {
    // Guru: hanya ambil kelas yang diajarnya
    $guruId = $_SESSION['guru_id'] ?? null;
    $sqlKelas = "SELECT k.* 
                 FROM kelas k 
                 JOIN guru g ON k.guru_id = g.id 
                 WHERE g.id = ? AND g.status = 'Aktif'
                 ORDER BY k.nama_kelas ASC";
    $stmt = $conn->prepare($sqlKelas);
    $stmt->bind_param('i', $guruId);
    $stmt->execute();
    $resKelas = $stmt->get_result();
    while ($rowKelas = $resKelas->fetch_assoc()) {
        $kelasList[] = $rowKelas;
    }
    $stmt->close();
}

// === Filter untuk disable kolom ===
if(empty($kelasFilter) && empty($tanggalFilter)){
    $disableColumns = [3,7,8,10,11,12];
} elseif(!empty($kelasFilter) && empty($tanggalFilter)){
    $disableColumns = [3,6,7,9,10,11];
} elseif(empty($kelasFilter) && !empty($tanggalFilter)){
    $disableColumns = [3,6,7,9,10,11];
} elseif(!empty($kelasFilter) && !empty($tanggalFilter)){
    $disableColumns = [3,5,6,8,9,10];
}

// Query untuk mengambil data presensi
$sql = "SELECT p.id, 
               s.nis, 
               s.nama,
               s.foto_siswa, 
               s.kelas_id,
               k.nama_kelas,
               p.tanggal, 
               p.waktu_masuk, 
               p.waktu_keluar,
               p.foto_masuk,
               p.foto_keluar,
               p.status
        FROM presensi p 
        JOIN siswa s ON p.siswa_id = s.id
        LEFT JOIN kelas k ON s.kelas_id = k.id";

// Siapkan klausa WHERE
$whereClauses = [];
$whereClauses[] = "s.status = 'Aktif'";

// Untuk filter kelas
if (!empty($kelasFilter)) {
    $whereClauses[] = "k.nama_kelas = '$kelasFilter'";
} else {
    // Untuk Guru, data yang ditampilkan hanya dari kelas yang dia ampu
    if (!$isAdmin) {
        $guruId = $_SESSION['guru_id'] ?? null;
        $whereClauses[] = "k.id IN (SELECT id FROM kelas WHERE guru_id = $guruId)";
    }
}

// Untuk filter tanggal
if (!empty($tanggalFilter)) {
    $whereClauses[] = "p.tanggal = '$tanggalFilter'";
}

if (count($whereClauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}
$sql .= " ORDER BY p.tanggal DESC, p.waktu_masuk DESC";

$result = $conn->query($sql);

// Ambil jam batas masuk dari database
$jam_batas = "07:00"; // default
$queryJamMasuk = "SELECT jam_masuk FROM pengaturan WHERE id = 1";
$resultJamMasuk = $conn->query($queryJamMasuk);
if ($resultJamMasuk && $resultJamMasuk->num_rows > 0) {
    $rowJamMasuk = $resultJamMasuk->fetch_assoc();
    if (!empty($rowJamMasuk['jam_masuk'])) {
        $jam_batas = $rowJamMasuk['jam_masuk'];
    }
}

$tanggal_hari_ini = date("Y-m-d");
$foto_default = 'img/default_image.jpg';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Data Presensi</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Data Presensi</li>
        </ol>
    </nav>
  </div>

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
            <!-- Default untuk keduanya: Semua Kelas -->
            <option value="" <?php echo ($kelasFilter == '' ? 'selected' : ''); ?>>Semua Kelas</option>
            <?php foreach ($kelasList as $kelasRow): ?>
              <option value="<?php echo $kelasRow['nama_kelas']; ?>" <?php echo ($kelasFilter == $kelasRow['nama_kelas']) ? 'selected' : ''; ?>>
                <?php echo $kelasRow['nama_kelas']; ?>
              </option>
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
          <input type="date" id="filterTanggal" class="form-control" name="tanggal" value="<?php echo htmlspecialchars($tanggalFilter); ?>">
        </div>
      </div>

      <!-- Tombol Tampilkan -->
      <div class="col-12 col-lg-auto mb-2">
        <button type="submit" class="btn btn-primary btn-block">Tampilkan</button>
      </div>

      <!-- Tombol Reset -->
      <div class="col-12 col-lg-auto mb-3">
        <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="btn btn-secondary btn-block">Reset</a>
      </div>

    </div>
  </form>

  <!-- Card untuk Tabel Data Presensi -->
  <div class="card mb-4">
    <div class="card-body">
      <div class="table-responsive">
        <table id="dataTable" class="table table-striped table-bordered table-hover">
          <thead class="bg-white">
            <tr>
              <th>No</th>
              <th>NIS</th>
              <th>Nama</th>
              <th>Foto</th>
              <?php if ($kelasFilter == ''): ?>
                <th>Kelas</th>
              <?php endif; ?>
              <?php if (empty($tanggalFilter)): ?>
                <th>Tanggal</th>
              <?php endif; ?>
              <th>Waktu Masuk</th>
              <th>Foto Masuk</th>
              <th>Ket. Masuk</th>
              <th>Waktu Keluar</th>
              <th>Foto Keluar</th>
              <th>Ket. Keluar</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $nomor = 1;
            if ($result && $result->num_rows > 0):
              while ($row = $result->fetch_assoc()):
                $display_waktu_masuk = !empty($row['waktu_masuk']) ? $row['waktu_masuk'] : "-";
                $display_waktu_keluar = !empty($row['waktu_keluar']) ? $row['waktu_keluar'] : "-";
                
                // Keterangan Masuk
                if (!empty($row['waktu_masuk'])) {
                    $keterangan_masuk = ($row['waktu_masuk'] <= $jam_batas) ? 'Tepat waktu' : '<span class="text-danger">Terlambat</span>';
                } else {
                    $keterangan_masuk = "-";
                }
                
                // Keterangan Keluar
                if (!empty($row['waktu_keluar'])) {
                    $keterangan_keluar = "Sudah keluar";
                } else {
                    if (!empty($row['waktu_masuk'])) {
                        $presensi_timestamp = strtotime($row['tanggal']);
                        $current_timestamp = strtotime(date("Y-m-d"));
                        $keterangan_keluar = ($presensi_timestamp < $current_timestamp)
                                            ? "<span class='text-danger'>Siswa bolos</span>"
                                            : "Masih di sekolah";
                    } else {
                        $keterangan_keluar = "-";
                    }
                }
                
                // Foto Siswa
                $foto_siswa_path = !empty($row['foto_siswa']) ? "data/foto/foto_siswa/{$row['foto_siswa']}" : '';
                if (!empty($foto_siswa_path) && file_exists($foto_siswa_path)) {
                    $foto_siswa = "<div class='img-circle-crop'><img src='{$foto_siswa_path}' alt='Foto Siswa' width='50' height='50'></div>";
                } else {
                    $foto_siswa = "<div class='img-circle-crop'><img src='{$foto_default}' alt='Foto Siswa Default' width='50' height='50'></div>";
                }
                
                // Foto Masuk
                $foto_masuk = "-";
                if (!empty($row['foto_masuk'])) {
                    $foto_masuk_path = "data/foto/foto_presensi_siswa/{$row['foto_masuk']}";
                    $foto_masuk = (file_exists($foto_masuk_path))
                                  ? "<div class='img-circle-crop'><img src='{$foto_masuk_path}' alt='Foto Masuk' width='50' height='50'></div>"
                                  : "<div class='img-circle-crop'><img src='{$foto_default}' alt='Foto Masuk Default' width='50' height='50'></div>";
                }
                
                // Foto Keluar
                $foto_keluar = "-";
                if (!empty($row['foto_keluar'])) {
                    $foto_keluar_path = "data/foto/foto_presensi_siswa/{$row['foto_keluar']}";
                    $foto_keluar = (file_exists($foto_keluar_path))
                                  ? "<div class='img-circle-crop'><img src='{$foto_keluar_path}' alt='Foto Keluar' width='50' height='50'></div>"
                                  : "<div class='img-circle-crop'><img src='{$foto_default}' alt='Foto Keluar Default' width='50' height='50'></div>";
                }
            ?>
              <tr>
                <td><?php echo $nomor++; ?></td>
                <td><?php echo $row['nis']; ?></td>
                <td><?php echo $row['nama']; ?></td>
                <td><?php echo $foto_siswa; ?></td>
                <?php if ($kelasFilter == ''): ?>
                  <td><?php echo $row['nama_kelas']; ?></td>
                <?php endif; ?>
                <?php if (empty($tanggalFilter)): ?>
                  <td><?php echo date("d/m/Y", strtotime($row['tanggal'])); ?></td>
                <?php endif; ?>
                <td><?php echo $display_waktu_masuk; ?></td>
                <td><?php echo $foto_masuk; ?></td>
                <td><?php echo $keterangan_masuk; ?></td>
                <td><?php echo $display_waktu_keluar; ?></td>
                <td><?php echo $foto_keluar; ?></td>
                <td><?php echo $keterangan_keluar; ?></td>
                <td><?php echo $row['status']; ?></td>
              </tr>
            <?php 
              endwhile;
            endif;
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!-- End of Page Content -->

</div>

<?php
$conn->close();
include 'footer.php';
?>

<!-- Script untuk DataTable -->
<script>
$(document).ready(function() {
    $('#dataTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        info: true,
        language: {
            emptyTable: "Tidak ada data presensi yang tersedia"
        },
        columnDefs: [
            { orderable: false, targets: <?php echo json_encode($disableColumns); ?> }
        ]
    });
});
</script>
