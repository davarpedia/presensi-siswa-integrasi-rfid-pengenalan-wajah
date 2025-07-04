<?php 
require_once 'koneksi.php';
require_once 'autentikasi.php';

// Ambil ID guru dari session
$guru_id = $_SESSION['guru_id'];

// Total Kelas yang Diampu
$sqlKelas = "SELECT COUNT(*) AS total FROM kelas WHERE guru_id = '$guru_id'";
$resultKelas = mysqli_query($conn, $sqlKelas);
$rowKelas = mysqli_fetch_assoc($resultKelas);
$totalKelasDiampu = $rowKelas['total'];

// Total Siswa yang Diampu (status 'Aktif')
$sqlSiswa = "
  SELECT COUNT(*) AS total
  FROM siswa s
  JOIN kelas k ON s.kelas_id = k.id
  WHERE k.guru_id = '$guru_id'
    AND s.status = 'Aktif'
";
$resultSiswa = mysqli_query($conn, $sqlSiswa);
$rowSiswa = mysqli_fetch_assoc($resultSiswa);
$totalSiswaDiampu = $rowSiswa['total'];

// Tanggal hari ini
$tglHariIni = date("Y-m-d");

// Hadir Hari Ini untuk Kelas Diampu
$sqlHadir = "
  SELECT COUNT(*) AS total
  FROM presensi p
  JOIN siswa s ON p.siswa_id = s.id
  JOIN kelas k ON s.kelas_id = k.id
  WHERE k.guru_id = '$guru_id'
    AND p.tanggal = '$tglHariIni'
    AND p.status = 'Hadir'
    AND s.status = 'Aktif'
";
$resultHadir = mysqli_query($conn, $sqlHadir);
$rowHadir = mysqli_fetch_assoc($resultHadir);
$totalHadir = $rowHadir['total'];

// Belum Hadir Hari Ini (siswa aktif di kelas guru - yang sudah hadir)
$totalBelum = max(0, $totalSiswaDiampu - $totalHadir);

// Terlambat Hari Ini untuk Kelas Diampu
// Ambil jam masuk dari tabel pengaturan (id = 1)
$sqlPengaturan = "SELECT jam_masuk FROM pengaturan WHERE id = 1";
$resultPengaturan = mysqli_query($conn, $sqlPengaturan);
$rowPengaturan = mysqli_fetch_assoc($resultPengaturan);
$jamMasuk = $rowPengaturan['jam_masuk'];

$sqlTerlambat = "
  SELECT COUNT(*) AS total
  FROM presensi p
  JOIN siswa s ON p.siswa_id = s.id
  JOIN kelas k ON s.kelas_id = k.id
  WHERE k.guru_id = '$guru_id'
    AND p.tanggal = '$tglHariIni'
    AND p.waktu_masuk > '$jamMasuk'
    AND s.status = 'Aktif'
";
$resultTerlambat = mysqli_query($conn, $sqlTerlambat);
$rowTerlambat = mysqli_fetch_assoc($resultTerlambat);
$totalTerlambat = $rowTerlambat['total'];

// Persentase Global Kehadiran
$persentase = $totalSiswaDiampu > 0
    ? ($totalHadir / $totalSiswaDiampu) * 100
    : 0;
$persentase = ($persentase == floor($persentase))
    ? (int)$persentase
    : round($persentase, 2);

// Kehadiran per Kelas (progress bar per kelas yang diampu)
$sqlAllKelas = "
  SELECT id, nama_kelas 
  FROM kelas 
  WHERE guru_id = '$guru_id'
";
$resultAllKelas = mysqli_query($conn, $sqlAllKelas);
$kelasAttendance = [];
while($kelas = mysqli_fetch_assoc($resultAllKelas)) {
    $idKelas = $kelas['id'];
    $namaKelas = $kelas['nama_kelas'];
    // total siswa per kelas
    $sqlTot = "
      SELECT COUNT(*) AS total 
      FROM siswa 
      WHERE kelas_id = '$idKelas' 
        AND status = 'Aktif'
    ";
    $rTot = mysqli_query($conn, $sqlTot);
    $rowTot = mysqli_fetch_assoc($rTot);
    $totS = $rowTot['total'];
    // hadir hari ini per kelas
    $sqlHd = "
      SELECT COUNT(*) AS hadir
      FROM presensi p
      JOIN siswa s ON p.siswa_id = s.id
      WHERE s.kelas_id = '$idKelas'
        AND p.tanggal = '$tglHariIni'
        AND p.status = 'Hadir'
        AND s.status = 'Aktif'
    ";
    $rHd = mysqli_query($conn, $sqlHd);
    $rowHd = mysqli_fetch_assoc($rHd);
    $hadirK = $rowHd['hadir'];
    $pct = $totS > 0 ? ($hadirK/$totS)*100 : 0;
    $pct = ($pct == floor($pct)) ? (int)$pct : round($pct,2);
    $kelasAttendance[] = [
      'nama_kelas' => $namaKelas,
      'persentase' => $pct
    ];
}

// Presensi Terbaru (10 data terakhir hanya untuk kelas yang diampu)
$sqlPresensi = "
  SELECT p.tanggal, s.nis, s.nama, k.nama_kelas, p.status
  FROM presensi p
  JOIN siswa s ON p.siswa_id = s.id
  JOIN kelas k ON s.kelas_id = k.id
  WHERE k.guru_id = '$guru_id'
    AND s.status = 'Aktif'
  ORDER BY p.tanggal DESC, p.waktu_masuk DESC
  LIMIT 10
";
$resultPresensi = mysqli_query($conn, $sqlPresensi);
?>

<?php 
$pageTitle = "Dashboard";
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <!-- Content Row: Cards -->
  <div class="row">
    <!-- Total Kelas yang Diampu -->
    <div class="col-xl-4 col-md-6 mb-4">
    <div class="card-hover">
      <div class="card border-left-primary h-100 py-2">
        <div class="card-body">
          <div class="row no-gutters align-items-center">
            <div class="col mr-2">
              <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                Total Kelas yang Diampu
              </div>
              <div class="h5 mb-0 font-weight-bold text-gray-800">
                <?= $totalKelasDiampu; ?>
              </div>
            </div>
            <div class="col-auto">
              <i class="fas fa-chalkboard fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
    <!-- Total Siswa -->
    <div class="col-xl-4 col-md-6 mb-4">
    <a href="siswa.php" class="card-hover">
      <div class="card border-left-info h-100 py-2">
        <div class="card-body">
          <div class="row no-gutters align-items-center">
            <div class="col mr-2">
              <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                Total Siswa
              </div>
              <div class="h5 mb-0 font-weight-bold text-gray-800">
                <?= $totalSiswaDiampu; ?>
              </div>
            </div>
            <div class="col-auto">
              <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>
    </a>
    </div>
    <!-- Persentase Global Kehadiran -->
    <div class="col-xl-4 col-md-6 mb-4">
    <div class="card-hover">
      <div class="card border-left-secondary h-100 py-2">
        <div class="card-body">
          <div class="row no-gutters align-items-center">
            <div class="col mr-2">
              <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                Persentase Kehadiran
              </div>
              <div class="row no-gutters align-items-center">
                <div class="col-auto">
                  <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">
                    <?= $persentase; ?>%
                  </div>
                </div>
                <div class="col">
                  <div class="progress progress-sm mr-2">
                    <div class="progress-bar bg-secondary" role="progressbar"
                         style="width: <?= $persentase; ?>%;"
                         aria-valuenow="<?= $persentase; ?>" aria-valuemin="0" aria-valuemax="100">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-auto">
              <i class="fas fa-percentage fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
    <!-- Hadir Hari Ini -->
    <div class="col-xl-4 col-md-6 mb-4">
    <a href="data_presensi.php?kelas=&tanggal=<?php echo $tglHariIni; ?>" class="card-hover">
      <div class="card border-left-success h-100 py-2">
        <div class="card-body">
          <div class="row no-gutters align-items-center">
            <div class="col mr-2">
              <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                Hadir Hari Ini
              </div>
              <div class="h5 mb-0 font-weight-bold text-gray-800">
                <?= $totalHadir; ?>
              </div>
            </div>
            <div class="col-auto">
              <i class="fas fa-check-circle fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>
    </a>
    </div>
    <!-- Belum Hadir -->
    <div class="col-xl-4 col-md-6 mb-4">
    <div class="card-hover">
      <div class="card border-left-danger h-100 py-2">
        <div class="card-body">
          <div class="row no-gutters align-items-center">
            <div class="col mr-2">
              <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                Belum Hadir
              </div>
              <div class="h5 mb-0 font-weight-bold text-gray-800">
                <?= $totalBelum; ?>
              </div>
            </div>
            <div class="col-auto">
              <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    </div>
    <!-- Terlambat -->
    <div class="col-xl-4 col-md-6 mb-4">
    <a href="data_presensi.php?kelas=&tanggal=<?php echo $tglHariIni; ?>" class="card-hover">
      <div class="card border-left-warning h-100 py-2">
        <div class="card-body">
          <div class="row no-gutters align-items-center">
            <div class="col mr-2">
              <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                Terlambat
              </div>
              <div class="h5 mb-0 font-weight-bold text-gray-800">
                <?= $totalTerlambat; ?>
              </div>
            </div>
            <div class="col-auto">
              <i class="fas fa-clock fa-2x text-gray-300"></i>
            </div>
          </div>
        </div>
      </div>
    </a>
    </div>
  </div>

  <!-- Row: Kehadiran per Kelas & Pie Chart -->
  <div class="row">
    <!-- Kehadiran per Kelas -->
    <div class="col-xl-8 col-lg-7 d-flex align-items-stretch">
      <div class="card mb-4 w-100">
        <div class="card-header py-3">
          <h6 class="m-0 font-weight-bold text-primary">Kehadiran per Kelas</h6>
        </div>
        <div class="card-body">
          <?php foreach($kelasAttendance as $data): ?>
            <h4 class="small font-weight-bold">
              <?= $data['nama_kelas']; ?>
              <span class="float-right"><?= $data['persentase']; ?>%</span>
            </h4>
            <div class="progress mb-4">
              <div class="progress-bar" role="progressbar"
                   style="width: <?= $data['persentase']; ?>%;"
                   aria-valuenow="<?= $data['persentase']; ?>"
                   aria-valuemin="0" aria-valuemax="100">
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <!-- Pie Chart Kehadiran vs Belum -->
    <div class="col-xl-4 col-lg-5 d-flex align-items-stretch">
      <div class="card mb-4 w-100">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
          <h6 class="m-0 font-weight-bold text-primary">Perbandingan Kehadiran</h6>
        </div>
        <div class="card-body">
          <div class="chart-pie pt-4 pb-2">
            <canvas id="myPieChart"></canvas>
          </div>
          <div class="mt-4 text-center small">
            <span class="mr-2"><i class="fas fa-circle text-success"></i> Hadir</span>
            <span class="mr-2"><i class="fas fa-circle text-danger"></i> Belum Hadir</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Presensi Terbaru -->
  <div class="card mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
      <h6 class="m-0 font-weight-bold text-primary">Presensi Terbaru</h6>
      <a href="data_presensi.php" class="text-secondary text-decoration-none hover-effect d-flex align-items-center">
        <span class="mr-1 d-none d-sm-inline">Selengkapnya</span>
        <i class="fas fa-chevron-right fa-sm fa-fw"></i>
      </a>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover text-center" width="100%" cellspacing="0">
          <thead>
            <tr>
              <th>No</th>
              <th>Tanggal</th>
              <th>NIS</th>
              <th>Nama Siswa</th>
              <th>Kelas</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if(mysqli_num_rows($resultPresensi) > 0): ?>
              <?php $no=1; while($row = mysqli_fetch_assoc($resultPresensi)): ?>
                <tr>
                  <td><?= $no++; ?></td>
                  <td><?= date("d/m/Y", strtotime($row['tanggal'])); ?></td>
                  <td><?= $row['nis']; ?></td>
                  <td><?= $row['nama']; ?></td>
                  <td><?= $row['nama_kelas']; ?></td>
                  <td><?= $row['status']; ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6">Tidak ada data presensi yang tersedia</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
<!-- End of Page Content -->

</div>

<?php include 'footer.php'; ?>

<!-- Chart.js untuk Pie Chart -->
<script>
Chart.defaults.global.defaultFontFamily = 'Nunito, -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
Chart.defaults.global.defaultFontColor = '#858796';

var ctx = document.getElementById("myPieChart");
var hadir = <?= $totalHadir; ?>;
var belum = <?= $totalBelum; ?>;

var myPieChart = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ["Hadir", "Belum Hadir"],
    datasets: [{
      data: [hadir, belum],
      backgroundColor: ['#1cc88a', '#e74a3b'],
      hoverBackgroundColor: ['#17a673', '#c0392b'],
      hoverBorderColor: "rgba(234, 236, 244, 1)",
    }],
  },
  options: {
    maintainAspectRatio: false,
    tooltips: {
      backgroundColor: "rgb(255,255,255)",
      bodyFontColor: "#858796",
      borderColor: '#dddfeb',
      borderWidth: 1,
      xPadding: 15,
      yPadding: 15,
      displayColors: false,
      caretPadding: 10,
    },
    legend: { display: false },
    cutoutPercentage: 80,
  },
});
</script>
