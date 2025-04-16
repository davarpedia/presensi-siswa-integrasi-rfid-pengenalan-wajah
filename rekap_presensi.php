<?php 
$pageTitle = "Rekap Presensi";
require_once 'koneksi.php';
require_once 'autentikasi.php';

// Tentukan level user. Jika bukan Admin, anggap Guru.
$isAdmin = (isset($_SESSION['session_level']) && strtolower($_SESSION['session_level']) === 'admin');

include 'header.php';
include 'sidebar.php';
include 'topbar.php';

// Ambil filter dari URL
$kelasFilter  = $_GET['kelas'] ?? '';
$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

// Fungsi ambil daftar tanggal libur dan non-operasional
function getHolidayDates($conn, $start, $end, $operationalDays) {
    $dates = [];
    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);
    $endDate->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period   = new DatePeriod($startDate, $interval, $endDate);

    foreach ($period as $dt) {
        $tanggal = $dt->format('Y-m-d');
        $dayNum  = $dt->format('N');
        if (!in_array($dayNum, $operationalDays)) {
            $dates[] = $tanggal;
        }
    }

    $query = "SELECT tanggal_mulai, tanggal_selesai 
              FROM hari_libur 
              WHERE tanggal_mulai <= '$end' AND tanggal_selesai >= '$start'";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $mulai = new DateTime($row['tanggal_mulai']);
            $selesai = new DateTime($row['tanggal_selesai']);
            while ($mulai <= $selesai) {
                $dates[] = $mulai->format('Y-m-d');
                $mulai->modify('+1 day');
            }
        }
    }

    return array_unique($dates);
}

// Pengaturan default dan ambil pengaturan dari database
$jam_batas_default = "07:00";
$jam_batas         = $jam_batas_default;
$operationalDays   = [];

$queryPengaturan = "SELECT jam_masuk, hari_operasional FROM pengaturan WHERE id = 1";
$resultPengaturan = $conn->query($queryPengaturan);
if ($resultPengaturan && $resultPengaturan->num_rows > 0) {
    $row = $resultPengaturan->fetch_assoc();
    if (!empty($row['jam_masuk'])) {
        $jam_batas = $row['jam_masuk'];
    }
    if (!empty($row['hari_operasional'])) {
        $operationalDays = array_map('trim', explode(',', $row['hari_operasional']));
    }
}

// Inisialisasi variabel default
$totalDays = 0;
$totalHolidayUnique = 0;
$totalEffective = 0;
$totalEffectiveAlpa = 0;
$today = date('Y-m-d');
$allHolidayDates = [];

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    // Hitung jumlah total hari
    $totalDays = (strtotime($tanggalAkhir) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;

    // Ambil semua tanggal libur dan non-operasional
    $allHolidayDates = getHolidayDates($conn, $tanggalMulai, $tanggalAkhir, $operationalDays);
    $totalHolidayUnique = count($allHolidayDates);

    // Total hari efektif (bukan hari libur)
    $totalEffective = $totalDays - $totalHolidayUnique;

    // Perhitungan khusus untuk status Alpa (hanya sampai hari ini)
    $tanggalAkhirAlpa = (strtotime($tanggalAkhir) > strtotime($today)) ? $today : $tanggalAkhir;

    if (strtotime($tanggalAkhirAlpa) >= strtotime($tanggalMulai)) {
        $totalDaysAlpa = (strtotime($tanggalAkhirAlpa) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;
        $holidayAlpa = getHolidayDates($conn, $tanggalMulai, $tanggalAkhirAlpa, $operationalDays);
        $totalEffectiveAlpa = $totalDaysAlpa - count($holidayAlpa);
    } else {
        $totalEffectiveAlpa = 0;
    }
}

// Ambil data kelas
$kelasList = [];
if ($isAdmin) {
    // Untuk Admin, ambil semua kelas
    $sqlKelas = "SELECT * FROM kelas ORDER BY nama_kelas ASC";
    $resultKelas = $conn->query($sqlKelas);
    if ($resultKelas && $resultKelas->num_rows > 0) {
        while ($rowKelas = $resultKelas->fetch_assoc()) {
            $kelasList[] = $rowKelas;
        }
    }
} else {
    // Untuk Guru, hanya ambil kelas yang diajarnya
    $guruId = $_SESSION['guru_id'] ?? null;
    $sqlKelas = "SELECT k.* 
                 FROM kelas k 
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

// Siapkan klausa untuk filter kelas berdasarkan level:
// Admin: jika $kelasFilter kosong, tampilkan semua. 
// Guru: jika $kelasFilter kosong, paksa query mengembalikan hasil kosong.
if ($isAdmin) {
    $kelasClause = "('$kelasFilter' = '' OR s.id_kelas = '$kelasFilter')";
} else {
    $kelasClause = !empty($kelasFilter) ? "s.id_kelas = '$kelasFilter'" : "1=0";
}

// Query rekap ringkasan jika tanggal sudah diisi
if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    $sqlRekap = "
        SELECT  
            s.nis, 
            s.nama, 
            s.id_kelas,
            k.nama_kelas,
            COUNT(DISTINCT CASE WHEN p.status = 'Hadir' AND p.tanggal <= CURDATE() THEN p.tanggal END) AS total_hadir,
            COUNT(DISTINCT CASE WHEN p.status = 'Izin' AND p.tanggal <= CURDATE() THEN p.tanggal END) AS total_izin,
            COUNT(DISTINCT CASE WHEN p.status = 'Sakit' AND p.tanggal <= CURDATE() THEN p.tanggal END) AS total_sakit,
            COUNT(DISTINCT CASE 
                WHEN p.status = 'Masuk' 
                     AND (p.waktu_keluar IS NULL OR p.waktu_keluar = '') 
                     AND p.tanggal < CURDATE() 
                THEN p.tanggal 
            END) AS total_bolos,
            (
              $totalEffectiveAlpa
              - (
                  COUNT(DISTINCT CASE WHEN p.status IN ('Hadir','Izin','Sakit') AND p.tanggal <= CURDATE() THEN p.tanggal END)
                  +
                  COUNT(DISTINCT CASE 
                      WHEN p.status = 'Masuk' 
                           AND (p.waktu_keluar IS NULL OR p.waktu_keluar = '') 
                           AND p.tanggal < CURDATE() 
                      THEN p.tanggal 
                  END)
              )
            ) AS total_alpa,
            COUNT(DISTINCT CASE WHEN p.waktu_masuk > '$jam_batas' AND p.tanggal <= CURDATE() THEN p.tanggal END) AS total_terlambat,
            (
                COUNT(DISTINCT CASE WHEN p.status = 'Hadir' AND p.tanggal <= CURDATE() THEN p.tanggal END)
                / 
                CASE WHEN $totalEffectiveAlpa > 0 THEN $totalEffectiveAlpa ELSE 1 END
            ) * 100 AS persentase_kehadiran
        FROM siswa s
        LEFT JOIN presensi p 
            ON s.no_rfid = p.no_rfid 
            AND (p.tanggal BETWEEN '$tanggalMulai' AND '$tanggalAkhir')
        LEFT JOIN kelas k ON s.id_kelas = k.id
        WHERE 
            s.status = 'Aktif'
            AND $kelasClause
        GROUP BY 
            s.nis
        ORDER BY 
            s.nis ASC
    ";
    $resultRekap = $conn->query($sqlRekap);
}

// Siapkan data untuk detail presensi (seluruh tanggal dalam rentang)
$allDates = [];
if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    $startD = new DateTime($tanggalMulai);
    $endD   = new DateTime($tanggalAkhir);
    $endD->modify('+1 day');
    $periodD = new DatePeriod($startD, new DateInterval('P1D'), $endD);
    foreach ($periodD as $dt) {
        $allDates[] = $dt->format('Y-m-d');
    }
}
// Ambil data siswa untuk detail presensi
$sqlSiswa = "SELECT s.nis, s.nama, k.nama_kelas 
             FROM siswa s 
             LEFT JOIN kelas k ON s.id_kelas = k.id
             WHERE s.status = 'Aktif'
               AND $kelasClause
             ORDER BY s.nis ASC";
$resultSiswa = $conn->query($sqlSiswa);
?>
<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Rekap Presensi</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Rekap Presensi</li>
        </ol>
    </nav>
  </div>

  <!-- Instruksi di bawah judul (muncul 1x saja) -->
  <?php if (empty($tanggalMulai) || empty($tanggalAkhir)): ?>
    <p class="mb-4">Silahkan pilih kelas dan tanggal yang ingin ditampilkan.</p>
  <?php endif; ?>

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
            <?php foreach ($kelasList as $kelasRow): ?>
              <option value="<?= $kelasRow['id'] ?>" <?= ($kelasFilter == $kelasRow['id'] ? 'selected' : '') ?>>
                <?= $kelasRow['nama_kelas'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Pilihan Tanggal Mulai -->
      <div class="col-12 col-lg-auto mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <label class="input-group-text" for="tanggalMulai">Tanggal Mulai</label>
          </div>
          <input type="date"
                 id="tanggalMulai"
                 name="tanggal_mulai"
                 class="form-control"
                 value="<?= htmlspecialchars($tanggalMulai) ?>">
        </div>
      </div>

      <!-- Pilihan Tanggal Akhir -->
      <div class="col-12 col-lg-auto mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <label class="input-group-text" for="tanggalAkhir">Tanggal Akhir</label>
          </div>
          <input type="date"
                 id="tanggalAkhir"
                 name="tanggal_akhir"
                 class="form-control"
                 value="<?= htmlspecialchars($tanggalAkhir) ?>">
        </div>
      </div>

      <!-- Tombol Tampilkan -->
      <div class="col-12 col-lg-auto mb-4">
        <button type="submit" class="btn btn-primary btn-block">Tampilkan</button>
      </div>
    </div>
  </form>

  <?php if (!empty($tanggalMulai) && !empty($tanggalAkhir)): ?>
  <!-- Badge Total Hari -->
  <div class="form-row mb-3 text-center text-lg-left">
    <div class="col-12 col-sm-6 col-md-4 col-lg-auto mb-2">
      <span class="badge badge-primary d-inline-block w-100 text-wrap" style="padding: 0.6rem 1rem; font-size: 0.9rem;">Total Hari: <?= $totalDays ?> hari</span>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-auto mb-2">
      <span class="badge badge-dark d-inline-block w-100 text-wrap" style="padding: 0.6rem 1rem; font-size: 0.9rem;">Total Hari Libur: <?= $totalHolidayUnique ?> hari</span>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-auto mb-2">
      <span class="badge badge-secondary d-inline-block w-100 text-wrap" style="padding: 0.6rem 1rem; font-size: 0.9rem;">Total Hari Masuk: <?= $totalEffective ?> hari</span>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-lg-auto mb-2">
      <span class="badge badge-success d-inline-block w-100 text-wrap" style="padding: 0.6rem 1rem; font-size: 0.9rem;">Total Hari Efektif: <?= $totalEffectiveAlpa ?> hari</span>
    </div>
  </div>

  <!-- Card Ringkasan Rekap -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="card-title mb-0 font-weight-bold text-primary">Ringkasan</h6>
      <div>
        <form method="GET" action="download_pdf.php" class="d-inline">
          <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelasFilter) ?>">
          <input type="hidden" name="tanggal_mulai" value="<?= htmlspecialchars($tanggalMulai) ?>">
          <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($tanggalAkhir) ?>">
          <button type="submit" class="btn btn-danger btn-sm">
            <i class="fas fa-file-pdf mr-1"></i> 
            <span class="d-none d-lg-inline">Download </span>PDF
          </button>
        </form>
        <form method="GET" action="download_excel_ringkasan.php" class="d-inline">
          <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelasFilter) ?>">
          <input type="hidden" name="tanggal_mulai" value="<?= htmlspecialchars($tanggalMulai) ?>">
          <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($tanggalAkhir) ?>">
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel mr-1"></i> 
            <span class="d-none d-lg-inline">Download </span>Excel
          </button>
        </form>
      </div>
    </div>
    <div class="card-body">
      <table id="rekapTable" class="table table-striped table-bordered table-hover" style="width:100%;">
        <thead>
          <tr>
            <th>No</th>
            <th>NIS</th>
            <th>Nama</th>
            <?php if ($kelasFilter == ''): ?>
              <th>Kelas</th>
            <?php endif; ?>
            <th>Hadir</th>
            <th>Izin</th>
            <th>Sakit</th>
            <th>Alpa</th>
            <th>Bolos</th>
            <th>Terlambat</th>
            <th>Persentase</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if (isset($resultRekap) && $resultRekap && $resultRekap->num_rows > 0) {
              $nomor = 1;
              while ($row = $resultRekap->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td>{$nomor}</td>";
                  echo "<td>{$row['nis']}</td>";
                  echo "<td>{$row['nama']}</td>";
                  if ($kelasFilter == '') {
                      echo "<td>{$row['nama_kelas']}</td>";
                  }
                  echo "<td>{$row['total_hadir']}</td>
                        <td>{$row['total_izin']}</td>
                        <td>{$row['total_sakit']}</td>
                        <td>{$row['total_alpa']}</td>
                        <td>{$row['total_bolos']}</td>
                        <td>{$row['total_terlambat']}</td>
                        <td>" . 
                            (($row['persentase_kehadiran'] == floor($row['persentase_kehadiran'])) 
                                ? number_format($row['persentase_kehadiran'], 0) 
                                : number_format($row['persentase_kehadiran'], 2)) . 
                        "%</td>";
                  echo "</tr>";
                  $nomor++;
              }
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Card Detail Presensi Harian -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="card-title mb-0 font-weight-bold text-primary">Detail Presensi Harian</h6>
      <div>
        <form method="GET" action="download_excel_detail.php" class="d-inline">
          <input type="hidden" name="kelas" value="<?= htmlspecialchars($kelasFilter) ?>">
          <input type="hidden" name="tanggal_mulai" value="<?= htmlspecialchars($tanggalMulai) ?>">
          <input type="hidden" name="tanggal_akhir" value="<?= htmlspecialchars($tanggalAkhir) ?>">
          <button type="submit" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel mr-1"></i> 
            <span class="d-none d-lg-inline">Download </span>Excel
          </button>
        </form>
      </div>
    </div>
    <div class="card-body">
      <table id="dailyTable" class="table table-striped table-bordered table-hover" style="width:100%;">
        <thead>
          <tr>
            <th rowspan="2">No</th>
            <th rowspan="2">NIS</th>
            <th rowspan="2">Nama</th>
            <?php if ($kelasFilter == ''): ?>
              <th rowspan="2">Kelas</th>
            <?php endif; ?>
            <th colspan="<?= count($allDates) ?>" class="text-center">Tanggal</th>
          </tr>
          <tr>
            <?php foreach ($allDates as $tgl): ?>
              <th><?= date('d/m', strtotime($tgl)) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
          $badgeColors = [
              'H' => 'green',
              'B' => 'black',
              'I' => 'brown',
              'S' => 'orange',
              'A' => 'red',
              '-' => 'gray',
              'L' => '#5A7D9A'
          ];
          $badgeStyle = "padding: 0.3rem; width: 1.75em; height: 1.75em; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 12px;";
          
          if ($resultSiswa && $resultSiswa->num_rows > 0) {
              $no = 1;
              while ($rowS = $resultSiswa->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td>{$no}</td>";
                  echo "<td>{$rowS['nis']}</td>";
                  echo "<td>{$rowS['nama']}</td>";
                  if ($kelasFilter == '') {
                      echo "<td>{$rowS['nama_kelas']}</td>";
                  }
                  foreach ($allDates as $tgl) {
                      if (in_array($tgl, $allHolidayDates)) {
                          echo "<td><span class='badge' style='background-color: {$badgeColors['L']}; color: #fff; $badgeStyle'>L</span></td>";
                      } else {
                          $queryPresensi = "
                              SELECT p.*, 
                                  CASE 
                                      WHEN p.status = 'Masuk'
                                          AND (p.waktu_keluar IS NULL OR p.waktu_keluar = '')
                                          AND p.tanggal < CURDATE() THEN 'B'
                                      WHEN p.status IN ('Hadir','Masuk') THEN 'H'
                                      WHEN p.status = 'Izin'  THEN 'I'
                                      WHEN p.status = 'Sakit' THEN 'S'
                                      ELSE p.status
                                  END AS status_singkat
                              FROM presensi p
                              JOIN siswa s2 ON s2.no_rfid = p.no_rfid
                              WHERE s2.nis = '{$rowS['nis']}'
                              AND p.tanggal = '$tgl'
                              LIMIT 1
                          ";
                          $resP = $conn->query($queryPresensi);
                          if ($resP && $resP->num_rows > 0) {
                              $dataP = $resP->fetch_assoc();
                              $status = strtoupper($dataP['status_singkat']);
                              if ($status === 'ALPA') {
                                  $status = 'A';
                              }
                              $badgeColor = isset($badgeColors[$status]) ? $badgeColors[$status] : 'gray';
                              echo "<td><span class='badge' style='background-color: $badgeColor; color: #fff; $badgeStyle'>$status</span></td>";
                          } else {
                              if (strtotime($tgl) <= strtotime($today)) {
                                  echo "<td><span class='badge' style='background-color: {$badgeColors['A']}; color: #fff; $badgeStyle'>A</span></td>";
                              } else {
                                  echo "<td><span class='badge' style='background-color: {$badgeColors['-']}; color: #fff; $badgeStyle'>-</span></td>";
                              }
                          }
                      }
                  }
                  echo "</tr>";
                  $no++;
              }
          }
          ?>
        </tbody>
      </table>
      <!-- Legend Badge -->
      <div class="mt-3">
        <strong class="d-block mb-2">Keterangan:</strong>
        <div class="d-flex flex-wrap">
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:green; color:#fff; <?= $badgeStyle ?>">H</span>&nbsp;Hadir
          </span>
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:black; color:#fff; <?= $badgeStyle ?>">B</span>&nbsp;Bolos
          </span>
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:brown; color:#fff; <?= $badgeStyle ?>">I</span>&nbsp;Izin
          </span>
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:orange; color:#fff; <?= $badgeStyle ?>">S</span>&nbsp;Sakit
          </span>
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:red; color:#fff; <?= $badgeStyle ?>">A</span>&nbsp;Alpa
          </span>
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:gray; color:#fff; <?= $badgeStyle ?>">-</span>&nbsp;Belum Ada
          </span>
          <span class="d-flex align-items-center mr-3 mb-2">
            <span class="badge" style="background-color:#5A7D9A; color:#fff; <?= $badgeStyle ?>">L</span>&nbsp;Libur
          </span>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<!-- End of Container Fluid -->

</div>
<!-- End of Page Content -->

<?php
$conn->close();
include 'footer.php';
?>

<!-- Inisialisasi DataTables & Validasi Form -->
<script>
let rekapTable, dailyTable;

$(document).ready(function() {

  // Inisialisasi DataTables untuk rekapTable
  <?php if ($kelasFilter == ''): ?>
    var rekapNonOrderable = [4,5,6,7,8,9,10];
  <?php else: ?>
    var rekapNonOrderable = [3,4,5,6,7,8,9];
  <?php endif; ?>

  rekapTable = $('#rekapTable').DataTable({
    "scrollX": true,
    "autoWidth": false,
    "paging": true,
    "searching": true,
    "ordering": true,
    "info": true,
    "language": {
      "emptyTable": "Tidak ada data presensi untuk kelas dan tanggal yang dipilih"
    },
    columnDefs: [
      { orderable: false, targets: rekapNonOrderable }
    ]
  });

  // Inisialisasi DataTables untuk dailyTable
  <?php if ($kelasFilter == ''): ?>
    var dailyStart = 4;
    var dailyTotalColumns = 4 + <?= count($allDates) ?>;
  <?php else: ?>
    var dailyStart = 3;
    var dailyTotalColumns = 3 + <?= count($allDates) ?>;
  <?php endif; ?>

  var dailyNonOrderable = [];
  for (var i = dailyStart; i < dailyTotalColumns; i++) {
    dailyNonOrderable.push(i);
  }

  dailyTable = $('#dailyTable').DataTable({
    scrollX: true,
    autoWidth: false,
    paging: true,
    searching: true,
    ordering: true,
    info: true,
    language: {
      emptyTable: "Tidak ada data presensi untuk kelas dan tanggal yang dipilih"
    },
    columnDefs: [
      { orderable: false, targets: dailyNonOrderable }
    ]
  });

  // Adjust kolom saat window di-resize
  $(window).on('resize', function() {
    rekapTable.columns.adjust();
    dailyTable.columns.adjust();
  });

  // Adjust kolom setelah sidebar toggle
  $('#sidebarToggle, #sidebarToggleTop').on('click', function() {
    setTimeout(function() {
      rekapTable.columns.adjust().draw();
      dailyTable.columns.adjust().draw();
    }, 0);
  });

  // Validasi form filter:
  // - Di level Guru: pastikan kelas, tanggal mulai, dan tanggal akhir tidak boleh kosong.
  // - Di level Admin: minimal tanggal mulai dan tanggal akhir tidak boleh kosong.
  $('#filterForm').on('submit', function(e) {
    let start = $('#tanggalMulai').val();
    let end   = $('#tanggalAkhir').val();
    <?php if (!$isAdmin): ?>
      let kelas = $('#filterKelas').val();
      if (!kelas || !start || !end) {
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
      } else if (end < start) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Peringatan',
          text: 'Tanggal akhir tidak boleh kurang dari tanggal mulai!',
          confirmButtonText: 'OK',
          customClass: {
            confirmButton: 'btn btn-primary'
          },
          buttonsStyling: false
        });
      }
    <?php else: ?>
      if (!start || !end) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Peringatan',
          text: 'Tanggal mulai dan tanggal akhir wajib diisi!',
          confirmButtonText: 'OK',
          customClass: {
            confirmButton: 'btn btn-primary'
          },
          buttonsStyling: false
        });
      } else if (end < start) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Peringatan',
          text: 'Tanggal akhir tidak boleh kurang dari tanggal mulai!',
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
