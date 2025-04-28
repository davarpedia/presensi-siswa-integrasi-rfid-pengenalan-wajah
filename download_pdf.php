<?php
require 'vendor/autoload.php'; 
require_once 'koneksi.php';

use Dompdf\Dompdf;

// Ambil filter dari URL/form
$kelasFilter  = $_GET['kelas']      ?? '';
$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

// Pengaturan default dan ambil pengaturan dari database (jam masuk dan hari operasional)
$jam_batas_default = "07:00";
$jam_batas         = $jam_batas_default;
$operationalDays   = [];

$queryPengaturan  = "SELECT jam_masuk, hari_operasional FROM pengaturan WHERE id = 1";
$resultPengaturan = $conn->query($queryPengaturan);
if ($resultPengaturan && $resultPengaturan->num_rows > 0) {
    $rowPengaturan = $resultPengaturan->fetch_assoc();
    if (!empty($rowPengaturan['jam_masuk'])) {
        $jam_batas = $rowPengaturan['jam_masuk'];
    }
    if (!empty($rowPengaturan['hari_operasional'])) {
        // Contoh: "1,2,3,4,5" untuk Senin-Jumat
        $operationalDays = array_map('trim', explode(',', $rowPengaturan['hari_operasional']));
    }
}

// Inisialisasi variabel default
$totalDays          = 0;
$totalHolidayUnique = 0;
$totalEffective     = 0;
$totalEffectiveAlpa = 0;
$today              = date('Y-m-d');
$allHolidayDates    = [];

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    // --- Hitung total hari dalam rentang ---
    $totalDays = (strtotime($tanggalAkhir) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;

    // 1) Kumpulkan seluruh tanggal dalam rentang
    $allDatesRaw = [];
    $startDate   = new DateTime($tanggalMulai);
    $endDate     = new DateTime($tanggalAkhir);
    $endDate->modify('+1 day'); // inklusif
    $interval    = new DateInterval('P1D');
    $period      = new DatePeriod($startDate, $interval, $endDate);
    foreach ($period as $dt) {
        $allDatesRaw[] = $dt->format('Y-m-d');
    }

    // 2) Tandai hari libur mingguan (non-operasional)
    $nonOperationalDates = [];
    foreach ($allDatesRaw as $tgl) {
        $dayNumber = date('N', strtotime($tgl)); // 1=Senin … 7=Minggu
        if (!in_array($dayNumber, $operationalDays)) {
            $nonOperationalDates[] = $tgl;
        }
    }

    // 3) Ambil libur nasional/dll dari tabel hari_libur
    $queryHolidayDates  = "
        SELECT tanggal_mulai, tanggal_selesai 
        FROM hari_libur 
        WHERE tanggal_mulai <= '$tanggalAkhir' 
          AND tanggal_selesai >= '$tanggalMulai'
    ";
    $resultHolidayDates = $conn->query($queryHolidayDates);
    $holidayDates       = [];
    if ($resultHolidayDates) {
        while ($row = $resultHolidayDates->fetch_assoc()) {
            $liburMulai   = max($tanggalMulai,   $row['tanggal_mulai']);
            $liburSelesai = min($tanggalAkhir,   $row['tanggal_selesai']);
            $p = new DatePeriod(
                new DateTime($liburMulai),
                new DateInterval('P1D'),
                (new DateTime($liburSelesai))->modify('+1 day')
            );
            foreach ($p as $dt) {
                $holidayDates[] = $dt->format('Y-m-d');
            }
        }
    }

    // 4) Gabungkan dan unikkan
    $allHolidayDates    = array_unique(array_merge($nonOperationalDates, $holidayDates));
    $totalHolidayUnique = count($allHolidayDates);

    // 5) Bangun string SQL untuk exclude
    if ($totalHolidayUnique > 0) {
        $holidayIn = "'" . implode("','", $allHolidayDates) . "'";
    } else {
        $holidayIn = "''";
    }

    // 6) Hitung total hari operasional
    $totalEffective = $totalDays - $totalHolidayUnique;

    // --- Hitung Alpa sampai hari ini ---
    $tanggalAkhirAlpa = (strtotime($tanggalAkhir) > strtotime($today)) ? $today : $tanggalAkhir;
    if (strtotime($tanggalAkhirAlpa) >= strtotime($tanggalMulai)) {
        $totalDaysAlpa = (strtotime($tanggalAkhirAlpa) - strtotime($tanggalMulai)) / (60*60*24) + 1;

        // hari non-op mingguan untuk Alpa
        $allDatesAlpa  = [];
        $startAlpa     = new DateTime($tanggalMulai);
        $endAlpa       = new DateTime($tanggalAkhirAlpa);
        $endAlpa->modify('+1 day');
        $periodAlpa    = new DatePeriod($startAlpa, $interval, $endAlpa);
        $nonOpAlpa     = [];
        foreach ($periodAlpa as $dtAlpa) {
            $tglA = $dtAlpa->format('Y-m-d');
            if (!in_array(date('N', strtotime($tglA)), $operationalDays)) {
                $nonOpAlpa[] = $tglA;
            }
        }

        // libur untuk Alpa
        $queryHolidayAlpa = "
            SELECT tanggal_mulai, tanggal_selesai 
            FROM hari_libur 
            WHERE tanggal_mulai <= '$tanggalAkhirAlpa'
              AND tanggal_selesai >= '$tanggalMulai'
        ";
        $resHolAlpa = $conn->query($queryHolidayAlpa);
        $holidayAlpa = [];
        if ($resHolAlpa) {
            while ($rowH = $resHolAlpa->fetch_assoc()) {
                $lMulai   = max($tanggalMulai,   $rowH['tanggal_mulai']);
                $lSelesai = min($tanggalAkhirAlpa, $rowH['tanggal_selesai']);
                $p2 = new DatePeriod(
                    new DateTime($lMulai),
                    new DateInterval('P1D'),
                    (new DateTime($lSelesai))->modify('+1 day')
                );
                foreach ($p2 as $dtHol) {
                    $holidayAlpa[] = $dtHol->format('Y-m-d');
                }
            }
        }

        $allHolAlpa         = array_unique(array_merge($nonOpAlpa, $holidayAlpa));
        $totalEffectiveAlpa = $totalDaysAlpa - count($allHolAlpa);
    } else {
        $totalEffectiveAlpa = 0;
    }
}

// Helper: format tanggal ke Bahasa Indonesia
function formatTanggalDisplay($tanggal) {
    if (!$tanggal) return '';
    $d = new DateTime($tanggal);
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    return $d->format('j') . ' ' . $bulan[$d->format('n')-1] . ' ' . $d->format('Y');
}

// Query rekap presensi dengan pengecualian hari libur/non-operasional
$sql = "
    SELECT  
        s.nis, 
        s.nama, 
        s.id_kelas,
        k.nama_kelas,
        COUNT(DISTINCT CASE WHEN p.status='Hadir'  AND p.tanggal<=CURDATE() THEN p.tanggal END) AS total_hadir,
        COUNT(DISTINCT CASE WHEN p.status='Izin'   AND p.tanggal<=CURDATE() THEN p.tanggal END) AS total_izin,
        COUNT(DISTINCT CASE WHEN p.status='Sakit'  AND p.tanggal<=CURDATE() THEN p.tanggal END) AS total_sakit,
        COUNT(DISTINCT CASE 
            WHEN p.status='Masuk'
                 AND (p.waktu_keluar='' OR p.waktu_keluar IS NULL)
                 AND p.tanggal<CURDATE()
            THEN p.tanggal END) AS total_bolos,
        (
          $totalEffectiveAlpa
          - (
              COUNT(DISTINCT CASE WHEN p.status IN ('Hadir','Izin','Sakit') AND p.tanggal<=CURDATE() THEN p.tanggal END)
              +
              COUNT(DISTINCT CASE 
                  WHEN p.status='Masuk'
                       AND (p.waktu_keluar='' OR p.waktu_keluar IS NULL)
                       AND p.tanggal<CURDATE()
                  THEN p.tanggal END)
          )
        ) AS total_alpa,
        COUNT(DISTINCT CASE WHEN p.waktu_masuk>'$jam_batas' AND p.tanggal<=CURDATE() THEN p.tanggal END) AS total_terlambat,
        CASE WHEN $totalEffectiveAlpa>0 THEN
          (COUNT(DISTINCT CASE WHEN p.status='Hadir' AND p.tanggal<=CURDATE() THEN p.tanggal END)
           / $totalEffectiveAlpa
          )*100
        ELSE 0 END AS persentase_kehadiran
    FROM siswa s
    LEFT JOIN presensi p 
      ON s.no_rfid = p.no_rfid
     AND (p.tanggal BETWEEN '$tanggalMulai' AND '$tanggalAkhir')
     AND p.tanggal NOT IN ($holidayIn)
    LEFT JOIN kelas k 
      ON s.id_kelas = k.id
    WHERE 
        s.status = 'Aktif'
        AND ('$kelasFilter' = '' OR s.id_kelas = '$kelasFilter')
    GROUP BY s.nis
    ORDER BY s.nis ASC
";

$result = $conn->query($sql);

// Mulai output buffering untuk HTML PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi Siswa SD Negeri Gemawang</title>
    <style>
        body {
            font-family: 'Calibri', sans-serif;
            font-size: 12px;
            margin: 20px;
            padding: 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 16px;
        }
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .header-table td {
            padding: 4px;
            vertical-align: top;
        }
        .header-table .label {
            width: 120px;
            font-weight: bold;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        .data-table th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <h2>Rekap Presensi Siswa SD Negeri Gemawang</h2>
    <!-- Tabel Header Informasi -->
    <table class="header-table">
        <tr>
            <td class="label">Kelas</td>
            <td>: <?= htmlspecialchars($kelasFilter ? getKelasName($conn, $kelasFilter) : 'Semua Kelas') ?></td>
        </tr>
        <tr>
            <td class="label">Periode</td>
            <td>: <?= htmlspecialchars(formatTanggalDisplay($tanggalMulai)) ?> - <?= htmlspecialchars(formatTanggalDisplay($tanggalAkhir)) ?></td>
        </tr>
        <tr>
            <td class="label">Total Hari</td>
            <td>: <?= $totalDays ?> hari</td>
        </tr>
        <tr>
            <td class="label">Total Hari Libur</td>
            <td>: <?= $totalHolidayUnique ?> hari</td>
        </tr>
        <tr>
            <td class="label">Total Hari Masuk</td>
            <td>: <?= $totalEffective ?> hari</td>
        </tr>
        <tr>
            <td class="label">Total Hari Efektif</td>
            <td>: <?= $totalEffectiveAlpa ?> hari</td>
        </tr>
    </table>
    
    <!-- Tabel Rekap Presensi -->
    <table class="data-table">
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
            <?php if ($result && $result->num_rows > 0): ?>
                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nis']) ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <?php if ($kelasFilter == ''): ?>
                            <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($row['total_hadir']) ?></td>
                        <td><?= htmlspecialchars($row['total_izin']) ?></td>
                        <td><?= htmlspecialchars($row['total_sakit']) ?></td>
                        <td><?= htmlspecialchars($row['total_alpa']) ?></td>
                        <td><?= htmlspecialchars($row['total_bolos']) ?></td>
                        <td><?= htmlspecialchars($row['total_terlambat']) ?></td>
                        <td>
                            <?= ($row['persentase_kehadiran'] == floor($row['persentase_kehadiran']))
                                ? number_format($row['persentase_kehadiran'], 0)
                                : number_format($row['persentase_kehadiran'], 2) ?>%
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= ($kelasFilter == '') ? 11 : 10 ?>">Tidak ada data yang ditemukan</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

// Inisialisasi Dompdf dan render HTML menjadi PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("rekap_presensi.pdf", ["Attachment" => true]);

$conn->close();

// Fungsi helper untuk mendapatkan nama kelas berdasarkan id
function getKelasName($conn, $idKelas) {
    $sql    = "SELECT nama_kelas FROM kelas WHERE id = '$idKelas'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nama_kelas'];
    }
    return '';
}
?>
