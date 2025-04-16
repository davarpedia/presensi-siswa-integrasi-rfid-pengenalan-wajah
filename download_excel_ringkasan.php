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
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tanggalFormat = $date->format('j');
    $bulan = $bulanIndo[(int)$date->format('n') - 1];
    $tahun = $date->format('Y');
    return $tanggalFormat . ' ' . $bulan . ' ' . $tahun;
}

// Ambil filter dari request
$kelasFilter = $_GET['kelas'] ?? '';
$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

// Ambil pengaturan dari database: jam_masuk dan hari_operasional
$jam_batas_default = "07:00";
$jam_batas = $jam_batas_default;
$operationalDays = array();
$queryPengaturan = "SELECT jam_masuk, hari_operasional FROM pengaturan WHERE id = 1";
$resultPengaturan = $conn->query($queryPengaturan);
if ($resultPengaturan && $resultPengaturan->num_rows > 0) {
    $rowPengaturan = $resultPengaturan->fetch_assoc();
    if (!empty($rowPengaturan['jam_masuk'])) {
        $jam_batas = $rowPengaturan['jam_masuk'];
    }
    if (!empty($rowPengaturan['hari_operasional'])) {
        $operationalDays = array_map('trim', explode(',', $rowPengaturan['hari_operasional']));
    }
}

// Hitung total hari, total hari libur (gabungan hari non-operasional dan libur tambahan),
// serta total hari masuk (efektif) untuk periode
$totalDays = 0;
$totalHolidayUnique = 0;
$totalEffective = 0;

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    // Hitung total hari dalam rentang
    $totalDays = (strtotime($tanggalAkhir) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;
    
    // Generate daftar tanggal non-operasional dari hari-hari yang tidak termasuk dalam operationalDays
    $nonOperationalDates = array();
    $startDate = new DateTime($tanggalMulai);
    $endDate   = new DateTime($tanggalAkhir);
    $endDate->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);
    foreach ($period as $dt) {
        $day_number = $dt->format('N'); // 1 (Senin) sampai 7 (Minggu)
        if (!in_array($day_number, $operationalDays)) {
            $nonOperationalDates[] = $dt->format('Y-m-d');
        }
    }
    
    // Ambil tanggal libur (misalnya libur nasional) dari tabel hari_libur
    // Query di-update untuk menyesuaikan range berdasarkan tanggal_mulai dan tanggal_selesai
    $holidayDates = array();
    $queryHolidayDates = "
        SELECT tanggal_mulai, tanggal_selesai 
        FROM hari_libur 
        WHERE tanggal_mulai <= '$tanggalAkhir' 
          AND tanggal_selesai >= '$tanggalMulai'
    ";
    $resultHolidayDates = $conn->query($queryHolidayDates);
    if ($resultHolidayDates) {
        while ($row = $resultHolidayDates->fetch_assoc()) {
            // Tentukan batas periode libur yang tumpang tindih dengan rentang input
            $liburMulai = max($tanggalMulai, $row['tanggal_mulai']);
            $liburSelesai = min($tanggalAkhir, $row['tanggal_selesai']);
            $periodHoliday = new DatePeriod(
                new DateTime($liburMulai),
                new DateInterval('P1D'),
                (new DateTime($liburSelesai))->modify('+1 day')
            );
            foreach ($periodHoliday as $dtHoliday) {
                $holidayDates[] = $dtHoliday->format('Y-m-d');
            }
        }
    }
    
    // Gabungkan tanggal non-operasional dan tanggal libur, buang duplikasi
    $allHolidayDates = array_unique(array_merge($nonOperationalDates, $holidayDates));
    $totalHolidayUnique = count($allHolidayDates);
    
    // Hitung hari efektif (total hari dikurangi hari non-operasional & libur)
    $totalEffective = $totalDays - $totalHolidayUnique;
}

// --- Perhitungan hari efektif untuk perhitungan Alpa (dibatasi sampai hari ini) ---
$totalEffectiveAlpa = 0;
$today = date('Y-m-d');

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    // Batasi tanggal akhir untuk Alpa hingga hari ini jika $tanggalAkhir lebih besar dari hari ini
    $tanggalAkhirAlpa = (strtotime($tanggalAkhir) > strtotime($today)) ? $today : $tanggalAkhir;
    
    if (strtotime($tanggalAkhirAlpa) >= strtotime($tanggalMulai)) {
        $totalDaysAlpa = (strtotime($tanggalAkhirAlpa) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;
        $allDatesAlpa = array();
        $startAlpa = new DateTime($tanggalMulai);
        $endAlpa   = new DateTime($tanggalAkhirAlpa);
        $endAlpa->modify('+1 day');
        $periodAlpa = new DatePeriod($startAlpa, $interval, $endAlpa);
        foreach ($periodAlpa as $dtAlpa) {
            $allDatesAlpa[] = $dtAlpa->format('Y-m-d');
        }
        
        // Hari non-operasional untuk periode Alpa
        $nonOpAlpa = array();
        foreach ($allDatesAlpa as $tglA) {
            $dayNumA = date('N', strtotime($tglA));
            if (!in_array($dayNumA, $operationalDays)) {
                $nonOpAlpa[] = $tglA;
            }
        }
        
        // Ambil tanggal libur (dengan range) untuk periode Alpa
        $holidayAlpa = array();
        $queryHolidayAlpa = "
            SELECT tanggal_mulai, tanggal_selesai 
            FROM hari_libur 
            WHERE tanggal_mulai <= '$tanggalAkhirAlpa'
              AND tanggal_selesai >= '$tanggalMulai'
        ";
        $resultHolidayAlpa = $conn->query($queryHolidayAlpa);
        if ($resultHolidayAlpa) {
            while ($rowAlpa = $resultHolidayAlpa->fetch_assoc()) {
                $liburMulaiAlpa = max($tanggalMulai, $rowAlpa['tanggal_mulai']);
                $liburSelesaiAlpa = min($tanggalAkhirAlpa, $rowAlpa['tanggal_selesai']);
                $periodHolidayAlpa = new DatePeriod(
                    new DateTime($liburMulaiAlpa),
                    new DateInterval('P1D'),
                    (new DateTime($liburSelesaiAlpa))->modify('+1 day')
                );
                foreach ($periodHolidayAlpa as $dtHolAlpa) {
                    $holidayAlpa[] = $dtHolAlpa->format('Y-m-d');
                }
            }
        }
        
        $allHolAlpa = array_unique(array_merge($nonOpAlpa, $holidayAlpa));
        $totalHolAlpa = count($allHolAlpa);
        $totalEffectiveAlpa = $totalDaysAlpa - $totalHolAlpa;
    }
}

// Query rekap presensi dengan perhitungan hari efektif (Alpa & Persentase)
$sql = "SELECT  
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
            (COUNT(DISTINCT CASE WHEN p.status = 'Hadir' AND p.tanggal <= CURDATE() THEN p.tanggal END) / 
              CASE WHEN $totalEffectiveAlpa > 0 THEN $totalEffectiveAlpa ELSE 1 END
            ) * 100 AS persentase_kehadiran
        FROM siswa s
        LEFT JOIN presensi p 
            ON s.no_rfid = p.no_rfid 
            AND (p.tanggal BETWEEN '$tanggalMulai' AND '$tanggalAkhir')
        LEFT JOIN kelas k 
            ON s.id_kelas = k.id
        WHERE 
            s.status = 'Aktif'
            AND ('$kelasFilter' = '' OR s.id_kelas = '$kelasFilter')
        GROUP BY 
            s.nis
        ORDER BY 
            s.nis ASC";

$result = $conn->query($sql);

// Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Tentukan jumlah kolom header berdasarkan apakah kolom "Kelas" ditampilkan
$columnsCount = ($kelasFilter == '') ? 11 : 10;  
$lastColLetter = Coordinate::stringFromColumnIndex($columnsCount);

// Set judul dokumen dan atur style judul
$sheet->setCellValue('A1', 'Rekap Presensi Siswa SD Negeri Gemawang');
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Baris 2 sebagai spasi kosong
$sheet->setCellValue('A2', '');

// Informasi Kelas dan Periode
function getKelasNameHelper($conn, $idKelas) {
    $sql = "SELECT nama_kelas FROM kelas WHERE id = '$idKelas'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nama_kelas'];
    }
    return '';
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

// Header kolom untuk tabel
$headerRow = $dataStartRow;
$colIndex = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'No');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'NIS');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Nama');
if ($kelasFilter == '') {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Kelas');
}
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Hadir');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Izin');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Sakit');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Alpa');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Bolos');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Terlambat');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Persentase');

// Set style header tabel
$lastColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getFont()->setBold(true);
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getFill()->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('D3D3D3');
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getRowDimension($headerRow)->setRowHeight(20);
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Isi data mulai dari baris setelah header tabel
$rowIndex = $dataStartRow + 1;
$nomor = 1;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $colIndex = 1;
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $nomor);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['nis']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['nama']);
        if ($kelasFilter == '') {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['nama_kelas']);
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['total_hadir']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['total_izin']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['total_sakit']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['total_alpa']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['total_bolos']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex, $row['total_terlambat']);
        $sheet->setCellValue(
            Coordinate::stringFromColumnIndex($colIndex++) . $rowIndex,
            ($row['persentase_kehadiran'] == floor($row['persentase_kehadiran'])) 
                ? number_format($row['persentase_kehadiran'], 0) . '%' 
                : number_format($row['persentase_kehadiran'], 2) . '%'
        );        
        $rowIndex++;
        $nomor++;
    }
} else {
    $sheet->setCellValue("A{$rowIndex}", 'Tidak ada data yang ditemukan');
    $sheet->mergeCells("A{$rowIndex}:{$lastColLetter}{$rowIndex}");
}

// Set border dan alignment untuk data
$sheet->getStyle("A" . ($dataStartRow + 1) . ":{$lastColLetter}" . ($rowIndex - 1))
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
foreach (range('A', $lastColLetter) as $colID) {
    $sheet->getStyle($colID . ($dataStartRow + 1) . ":{$colID}" . ($rowIndex - 1))
          ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
          ->setVertical(Alignment::VERTICAL_CENTER);
}

// Atur lebar kolom secara otomatis
for ($col = 1; $col <= $colIndex - 1; $col++) {
    $colLetter = Coordinate::stringFromColumnIndex($col);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Header untuk mengunduh file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="rekap_presensi.xlsx"');
header('Cache-Control: max-age=0');

// Tulis file Excel ke output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
?>
