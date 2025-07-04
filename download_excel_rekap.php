<?php 
require 'vendor/autoload.php';
require_once 'koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Fungsi untuk format tanggal dengan nama bulan Bahasa Indonesia
function formatTanggal($tanggal) {
    if (!$tanggal) return '';
    $date = new DateTime($tanggal);
    $bulanIndo = [
        'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    ];
    $d = $date->format('j');
    $b = $bulanIndo[(int)$date->format('n') - 1];
    $y = $date->format('Y');
    return "$d $b $y";
}

// Ambil filter dari request
$kelasFilter  = $_GET['kelas']         ?? '';
$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

// Ambil pengaturan dari database: jam masuk dan hari operasional
$jam_batas_default = "07:00";
$jam_batas         = $jam_batas_default;
$operationalDays   = [];

$queryPengaturan  = "SELECT jam_masuk, hari_operasional FROM pengaturan WHERE id = 1";
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
$totalDays          = 0;
$totalHolidayUnique = 0;
$totalEffective     = 0;
$totalEffectiveAlpa = 0;
$today              = date('Y-m-d');
$allHolidayDates    = [];
// Default untuk SQL exclude
$holidayIn = "''";

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    // --- Hitung total hari dalam rentang ---
    $totalDays = (strtotime($tanggalAkhir) - strtotime($tanggalMulai)) / 86400 + 1;

    // 1) Kumpulkan seluruh tanggal dalam rentang
    $allDatesRaw = [];
    $startDate   = new DateTime($tanggalMulai);
    $endDate     = new DateTime($tanggalAkhir);
    $endDate->modify('+1 day');
    $interval    = new DateInterval('P1D');
    $period      = new DatePeriod($startDate, $interval, $endDate);
    foreach ($period as $dt) {
        $allDatesRaw[] = $dt->format('Y-m-d');
    }

    // 2) Tandai hari non-operasional (weekend/dll)
    $nonOperationalDates = [];
    foreach ($allDatesRaw as $tgl) {
        $dayNum = date('N', strtotime($tgl)); // 1=Mon…7=Sun
        if (!in_array($dayNum, $operationalDays)) {
            $nonOperationalDates[] = $tgl;
        }
    }

    // 3) Ambil holidays dari tabel hari_libur
    $holidayDates = [];
    $qHol = "
        SELECT tanggal_mulai, tanggal_selesai
        FROM hari_libur
        WHERE tanggal_mulai <= '$tanggalAkhir'
          AND tanggal_selesai >= '$tanggalMulai'
    ";
    $rHol = $conn->query($qHol);
    if ($rHol) {
        while ($h = $rHol->fetch_assoc()) {
            $m = max($tanggalMulai,   $h['tanggal_mulai']);
            $s = min($tanggalAkhir,   $h['tanggal_selesai']);
            $p = new DatePeriod(
                new DateTime($m),
                new DateInterval('P1D'),
                (new DateTime($s))->modify('+1 day')
            );
            foreach ($p as $dtHol) {
                $holidayDates[] = $dtHol->format('Y-m-d');
            }
        }
    }

    // 4) Gabungkan dan unikkan
    $allHolidayDates    = array_unique(array_merge($nonOperationalDates, $holidayDates));
    $totalHolidayUnique = count($allHolidayDates);

    // 5) Bangun string untuk SQL exclude
    if ($totalHolidayUnique > 0) {
        $holidayIn = "'" . implode("','", $allHolidayDates) . "'";
    }

    // 6) Hitung hari efektif (operasional)
    $totalEffective = $totalDays - $totalHolidayUnique;

    // --- Hitung Alpa sampai hari ini ---
    $tanggalAkhirAlpa = (strtotime($tanggalAkhir) > strtotime($today)) ? $today : $tanggalAkhir;
    if (strtotime($tanggalAkhirAlpa) >= strtotime($tanggalMulai)) {
        $totalDaysAlpa = (strtotime($tanggalAkhirAlpa) - strtotime($tanggalMulai)) / 86400 + 1;

        // Hari non-op mingguan Alpa
        $nonOpAlpa = [];
        $pAlpa     = new DatePeriod(
            new DateTime($tanggalMulai),
            new DateInterval('P1D'),
            (new DateTime($tanggalAkhirAlpa))->modify('+1 day')
        );
        foreach ($pAlpa as $dtA) {
            $tglA = $dtA->format('Y-m-d');
            if (!in_array(date('N', strtotime($tglA)), $operationalDays)) {
                $nonOpAlpa[] = $tglA;
            }
        }

        // Libur Alpa
        $holidayAlpa = [];
        $qHolA = "
            SELECT tanggal_mulai, tanggal_selesai
            FROM hari_libur
            WHERE tanggal_mulai <= '$tanggalAkhirAlpa'
              AND tanggal_selesai >= '$tanggalMulai'
        ";
        $rHolA = $conn->query($qHolA);
        if ($rHolA) {
            while ($ha = $rHolA->fetch_assoc()) {
                $mA = max($tanggalMulai,   $ha['tanggal_mulai']);
                $sA = min($tanggalAkhirAlpa,$ha['tanggal_selesai']);
                $p2 = new DatePeriod(
                    new DateTime($mA),
                    new DateInterval('P1D'),
                    (new DateTime($sA))->modify('+1 day')
                );
                foreach ($p2 as $dt2) {
                    $holidayAlpa[] = $dt2->format('Y-m-d');
                }
            }
        }

        $allHolAlpa         = array_unique(array_merge($nonOpAlpa, $holidayAlpa));
        $totalEffectiveAlpa = $totalDaysAlpa - count($allHolAlpa);
    }
}

// Query rekap presensi dengan pengecualian holiday/non-op
$sql = "
    SELECT  
        s.nis,
        s.nama,
        s.kelas_id,
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
        (
            COUNT(DISTINCT CASE WHEN p.status='Hadir' AND p.tanggal<=CURDATE() THEN p.tanggal END)
            / CASE WHEN $totalEffectiveAlpa>0 THEN $totalEffectiveAlpa ELSE 1 END
        ) * 100 AS persentase_kehadiran
    FROM siswa s
    LEFT JOIN presensi p
      ON s.id=p.siswa_id
     AND (p.tanggal BETWEEN '$tanggalMulai' AND '$tanggalAkhir')
     AND p.tanggal NOT IN ($holidayIn)
    LEFT JOIN kelas k ON s.kelas_id=k.id
    WHERE s.status='Aktif'
      AND ('$kelasFilter' = '' OR s.kelas_id='$kelasFilter')
    GROUP BY s.nis, s.nama, s.kelas_id, k.nama_kelas
    ORDER BY s.nis ASC
";

$result = $conn->query($sql);

// Buat objek Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();

// Tentukan jumlah kolom header
$columnsCount  = ($kelasFilter == '') ? 11 : 10;
$lastColLetter = Coordinate::stringFromColumnIndex($columnsCount);

// Judul dokumen
$sheet->setCellValue('A1', 'Rekap Presensi Siswa SD Negeri Gemawang');
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER);

// Spasi
$sheet->setCellValue('A2', '');

// Info Kelas & Periode
function getKelasNameHelper($conn, $id) {
    $r = $conn->query("SELECT nama_kelas FROM kelas WHERE id='$id'");
    return ($r && $r->num_rows>0) ? $r->fetch_assoc()['nama_kelas'] : '';
}
$kelasLabel = $kelasFilter ? getKelasNameHelper($conn, $kelasFilter) : 'Semua Kelas';
$sheet->setCellValue('A3', 'Kelas: ' . $kelasLabel);
$sheet->mergeCells("A3:{$lastColLetter}3");
$sheet->setCellValue('A4', 'Periode: ' . formatTanggal($tanggalMulai) . ' - ' . formatTanggal($tanggalAkhir));
$sheet->mergeCells("A4:{$lastColLetter}4");

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    $sheet->setCellValue('A5', 'Total Hari: ' . $totalDays . ' hari');
    $sheet->mergeCells("A5:{$lastColLetter}5");
    $sheet->setCellValue('A6', 'Total Hari Libur: ' . $totalHolidayUnique . ' hari');
    $sheet->mergeCells("A6:{$lastColLetter}6");
    $sheet->setCellValue('A7', 'Total Hari Masuk: ' . $totalEffective . ' hari');
    $sheet->mergeCells("A7:{$lastColLetter}7");
    $sheet->setCellValue('A8', 'Total Hari Efektif: ' . $totalEffectiveAlpa . ' hari');
    $sheet->mergeCells("A8:{$lastColLetter}8");
    $dataStartRow = 10;
} else {
    $dataStartRow = 5;
}

// Header tabel
$headerRow = $dataStartRow;
$ci = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'No');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'NIS');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Nama');
if ($kelasFilter=='') {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Kelas');
}
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Hadir');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Izin');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Sakit');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Alpa');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Bolos');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Terlambat');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $headerRow, 'Persentase');

// Style header
$lastHeaderCol = Coordinate::stringFromColumnIndex($ci - 1);
$sheet->getStyle("A{$headerRow}:{$lastHeaderCol}{$headerRow}")
      ->getFont()->setBold(true);
$sheet->getStyle("A{$headerRow}:{$lastHeaderCol}{$headerRow}")
      ->getFill()->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('D3D3D3');
$sheet->getStyle("A{$headerRow}:{$lastHeaderCol}{$headerRow}")
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension($headerRow)->setRowHeight(20);
$sheet->getStyle("A{$headerRow}:{$lastHeaderCol}{$headerRow}")
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Isi data
$rowIndex = $dataStartRow + 1;
$nomor    = 1;
if ($result && $result->num_rows > 0) {
    while ($r = $result->fetch_assoc()) {
        $ci = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $nomor);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['nis']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['nama']);
        if ($kelasFilter=='') {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['nama_kelas']);
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['total_hadir']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['total_izin']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['total_sakit']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['total_alpa']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['total_bolos']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci++) . $rowIndex, $r['total_terlambat']);
        $sheet->setCellValue(
            Coordinate::stringFromColumnIndex($ci++) . $rowIndex,
            ($r['persentase_kehadiran'] == floor($r['persentase_kehadiran']))
                ? number_format($r['persentase_kehadiran'],0).'%'
                : number_format($r['persentase_kehadiran'],2).'%'
        );
        $rowIndex++;
        $nomor++;
    }
} else {
    $sheet->setCellValue("A{$rowIndex}", 'Tidak ada data yang ditemukan');
    $sheet->mergeCells("A{$rowIndex}:{$lastHeaderCol}{$rowIndex}");
}

// Border & alignment data
$sheet->getStyle("A" . ($dataStartRow+1) . ":{$lastHeaderCol}" . ($rowIndex-1))
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', $lastHeaderCol) as $colID) {
    $sheet->getStyle("{$colID}" . ($dataStartRow+1) . ":{$colID}" . ($rowIndex-1))
          ->getAlignment()
          ->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
}

// Auto size kolom
for ($col = 1; $col <= $ci-1; $col++) {
    $colLetter = Coordinate::stringFromColumnIndex($col);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Output header & file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="rekap_presensi.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
?>
