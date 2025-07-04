<?php 
require_once 'koneksi.php';
require_once 'autentikasi.php';

// Mengambil total kelas
$sqlKelas = "SELECT COUNT(*) AS total FROM kelas";
$resultKelas = mysqli_query($conn, $sqlKelas);
$rowKelas = mysqli_fetch_assoc($resultKelas);
$totalKelas = $rowKelas['total'];

// Mengambil total siswa yang berstatus 'Aktif'
$sqlSiswa = "SELECT COUNT(*) AS total FROM siswa WHERE status = 'Aktif'";
$resultSiswa = mysqli_query($conn, $sqlSiswa);
$rowSiswa = mysqli_fetch_assoc($resultSiswa);
$totalSiswa = $rowSiswa['total'];

// Mengambil total guru
$sqlGuru = "SELECT COUNT(*) AS total FROM guru WHERE status = 'Aktif'";
$resultGuru = mysqli_query($conn, $sqlGuru);
$rowGuru = mysqli_fetch_assoc($resultGuru);
$totalGuru = $rowGuru['total'];

// Tentukan tanggal hari ini (format YYYY-MM-DD)
$tglHariIni = date("Y-m-d");

// Mengambil jumlah presensi 'Hadir' hari ini secara global hanya untuk siswa yang 'Aktif'
$sqlHadir = "SELECT COUNT(*) AS total
             FROM presensi p
             JOIN siswa s ON p.siswa_id = s.id
             WHERE p.tanggal = '$tglHariIni' 
               AND p.status = 'Hadir'
               AND s.status = 'Aktif'";
$resultHadir = mysqli_query($conn, $sqlHadir);
$rowHadir = mysqli_fetch_assoc($resultHadir);
$totalHadir = $rowHadir['total'];

// Menghitung jumlah siswa yang belum hadir hari ini dengan status 'Aktif'
$sqlBelumHadir = "SELECT COUNT(*) AS total FROM siswa 
    WHERE status = 'Aktif' AND id NOT IN (
        SELECT siswa_id FROM presensi WHERE tanggal = '$tglHariIni' AND status = 'Hadir'
    )";
$resultBelum = mysqli_query($conn, $sqlBelumHadir);
$rowBelum = mysqli_fetch_assoc($resultBelum);
$totalBelum = $rowBelum['total'];

// Menghitung persentase kehadiran global (hanya menghitung siswa aktif)
$persentase = $totalSiswa > 0 ? ($totalHadir / $totalSiswa) * 100 : 0;
$persentase = ($persentase == floor($persentase)) ? (int) $persentase : round($persentase, 2);

// Mengambil data presensi terbaru (10 data terakhir) hanya untuk siswa dengan status 'Aktif'
$sqlPresensi = "SELECT p.tanggal, s.nis, s.nama, k.nama_kelas, p.status 
                FROM presensi p 
                JOIN siswa s ON p.siswa_id = s.id 
                JOIN kelas k ON s.kelas_id = k.id 
                WHERE s.status = 'Aktif'
                ORDER BY p.tanggal DESC, p.waktu_masuk DESC 
                LIMIT 10";
$resultPresensi = mysqli_query($conn, $sqlPresensi);

// Persentase Kehadiran per Kelas
$sqlAllKelas = "SELECT id, nama_kelas FROM kelas";
$resultAllKelas = mysqli_query($conn, $sqlAllKelas);
$kelasAttendance = array();

while($kelas = mysqli_fetch_assoc($resultAllKelas)) {
    $idKelas = $kelas['id'];
    $namaKelas = $kelas['nama_kelas'];
    
    // Total siswa per kelas dengan status 'Aktif'
    $sqlTotalSiswaKelas = "SELECT COUNT(*) AS total FROM siswa WHERE kelas_id = '$idKelas' AND status = 'Aktif'";
    $resultTotalSiswaKelas = mysqli_query($conn, $sqlTotalSiswaKelas);
    $rowTotalSiswaKelas = mysqli_fetch_assoc($resultTotalSiswaKelas);
    $totalSiswaKelas = $rowTotalSiswaKelas['total'];
    
    // Total hadir per kelas hari ini untuk siswa yang 'Aktif'
    $sqlHadirKelas = "SELECT COUNT(*) AS Hadir 
                      FROM presensi p 
                      JOIN siswa s ON p.siswa_id = s.id 
                      WHERE s.kelas_id = '$idKelas' 
                        AND s.status = 'Aktif'
                        AND p.tanggal = '$tglHariIni' 
                        AND p.status = 'Hadir'";
    $resultHadirKelas = mysqli_query($conn, $sqlHadirKelas);
    $rowHadirKelas = mysqli_fetch_assoc($resultHadirKelas);
    $totalHadirKelas = $rowHadirKelas['Hadir'];
    
    // Hitung persentase kehadiran per kelas
    $persentaseKelas = $totalSiswaKelas > 0 ? ($totalHadirKelas / $totalSiswaKelas) * 100 : 0;
    $persentaseKelas = ($persentaseKelas == floor($persentaseKelas)) ? (int) $persentaseKelas : round($persentaseKelas, 2);
    
    $kelasAttendance[] = array(
        'nama_kelas' => $namaKelas,
        'persentase'  => $persentaseKelas
    );
}
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

  <!-- Content Row -->
  <div class="row">
    <!-- Card 1: Total Kelas -->
    <div class="col-xl-4 col-md-6 mb-4">
      <a href="kelas.php" class="card-hover">
        <div class="card border-left-primary h-100 py-2">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Kelas</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalKelas; ?></div>
              </div>
              <div class="col-auto">
                <i class="fas fa-chalkboard fa-2x text-gray-300"></i>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <!-- Card 2: Total Siswa -->
    <div class="col-xl-4 col-md-6 mb-4">
      <a href="siswa.php" class="card-hover">
        <div class="card border-left-info h-100 py-2">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Siswa</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalSiswa; ?></div>
              </div>
              <div class="col-auto">
                <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <!-- Card 3: Total Guru -->
    <div class="col-xl-4 col-md-6 mb-4">
      <a href="guru.php" class="card-hover">
        <div class="card border-left-warning h-100 py-2">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Guru</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalGuru; ?></div>
              </div>
              <div class="col-auto">
                <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <!-- Card 4: Hadir Hari Ini -->
    <div class="col-xl-4 col-md-6 mb-4">
      <a href="data_presensi.php?kelas=&tanggal=<?php echo $tglHariIni; ?>" class="card-hover">
        <div class="card border-left-success h-100 py-2">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Hadir Hari Ini</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalHadir; ?></div>
              </div>
              <div class="col-auto">
                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
              </div>
            </div>
          </div>
        </div>
      </a>
    </div>
    <!-- Card 5: Belum Hadir -->
    <div class="col-xl-4 col-md-6 mb-4">
      <div class="card-hover">
        <div class="card border-left-danger h-100 py-2">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Belum Hadir</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalBelum; ?></div>
              </div>
              <div class="col-auto">
                <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Card 6: Persentase Global -->
    <div class="col-xl-4 col-md-6 mb-4">
      <div class="card-hover">
        <div class="card border-left-secondary h-100 py-2">
          <div class="card-body">
            <div class="row no-gutters align-items-center">
              <div class="col mr-2">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Persentase Kehadiran</div>
                <div class="row no-gutters align-items-center">
                  <div class="col-auto">
                    <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $persentase; ?>%</div>
                  </div>
                  <div class="col">
                    <div class="progress progress-sm mr-2">
                      <div class="progress-bar bg-secondary" role="progressbar"
                           style="width: <?php echo $persentase; ?>%;" aria-valuenow="<?php echo $persentase; ?>"
                           aria-valuemin="0" aria-valuemax="100"></div>
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
  </div>
  <!-- End Content Row -->

  <!-- Row: Kehadiran per Kelas dan Chart Pie -->
  <div class="row">
    <!-- Kehadiran per Kelas -->
    <div class="col-xl-8 col-lg-7 d-flex align-items-stretch">
      <div class="card mb-4 w-100">
        <div class="card-header py-3">
          <h6 class="m-0 font-weight-bold text-primary">Kehadiran per Kelas</h6>
        </div>
        <div class="card-body">
          <?php 
          foreach($kelasAttendance as $data) {
              echo '<h4 class="small font-weight-bold">Kelas ' . $data['nama_kelas'] . ' <span class="float-right">' . $data['persentase'] . '%</span></h4>';
              echo '<div class="progress mb-4">';
              echo '<div class="progress-bar" role="progressbar" style="width: ' . $data['persentase'] . '%" aria-valuenow="' . $data['persentase'] . '" aria-valuemin="0" aria-valuemax="100"></div>';
              echo '</div>';
          }
          ?>
        </div>
      </div>
    </div>
    <!-- Pie Chart -->
    <div class="col-xl-4 col-lg-5 d-flex align-items-stretch">
      <div class="card mb-4 w-100">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
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

  <!-- Card Presensi Terbaru -->
  <div class="card mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
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
            <?php 
            if(mysqli_num_rows($resultPresensi) > 0) {
                $no = 1;
                while($row = mysqli_fetch_assoc($resultPresensi)) {
                    echo "<tr>";
                    echo "<td>".$no++."</td>";
                    echo "<td>".date("d/m/Y", strtotime($row['tanggal']))."</td>";
                    echo "<td>".$row['nis']."</td>";
                    echo "<td>".$row['nama']."</td>";
                    echo "<td>".$row['nama_kelas']."</td>";
                    echo "<td>".$row['status']."</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr>";
                echo "<td colspan='6'>Tidak ada data presensi yang tersedia</td>";
                echo "</tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- End of Page Content -->
</div>
<!-- End of Container Fluid -->

<?php include 'footer.php'; ?>

<script>
// Konfigurasi Chart.js untuk Pie Chart
Chart.defaults.global.defaultFontFamily = 'Nunito, -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
Chart.defaults.global.defaultFontColor = '#858796';

var ctx = document.getElementById("myPieChart");
var hadir = <?php echo $totalHadir; ?>;
var belumHadir = <?php echo $totalBelum; ?>;

var myPieChart = new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ["Hadir", "Belum Hadir"],
    datasets: [{
      data: [hadir, belumHadir],
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
