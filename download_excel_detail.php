<?php 
require 'vendor/autoload.php';
include 'koneksi.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Fungsi format tanggal Bahasa Indonesia (untuk header informasi)
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

// Fungsi helper untuk mendapatkan nama kelas berdasarkan id
function getKelasName($conn, $idKelas) {
    $sql = "SELECT nama_kelas FROM kelas WHERE id = '$idKelas'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['nama_kelas'];
    }
    return '';
}

// Ambil filter dari request
$kelasFilter  = $_GET['kelas'] ?? '';
$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalAkhir = $_GET['tanggal_akhir'] ?? '';

// Pengaturan default dan ambil pengaturan (jam masuk & hari operasional)
$jam_batas_default = "07:00";
$jam_batas         = $jam_batas_default;
$operationalDays   = [];
$queryPengaturan = "SELECT jam_masuk, hari_operasional FROM pengaturan WHERE id = 1";
$resultPengaturan = $conn->query($queryPengaturan);
if ($resultPengaturan && $resultPengaturan->num_rows > 0) {
    $rowPengaturan = $resultPengaturan->fetch_assoc();
    if (!empty($rowPengaturan['jam_masuk'])) {
        $jam_batas = $rowPengaturan['jam_masuk'];
    }
    if (!empty($rowPengaturan['hari_operasional'])) {
        // Misalnya "1,2,3,4,5" untuk Senin-Jumat
        $operationalDays = array_map('trim', explode(',', $rowPengaturan['hari_operasional']));
    }
}

// Perhitungan tanggal dan hari libur
$totalDays = 0;
$totalHolidayUnique = 0;
$totalEffective = 0;
$totalEffectiveAlpa = 0;
$today = date('Y-m-d');
$allHolidayDates = [];

if (!empty($tanggalMulai) && !empty($tanggalAkhir)) {
    // 1. Total hari (inklusif)
    $totalDays = (strtotime($tanggalAkhir) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;
    
    // 2. Kumpulkan seluruh tanggal dalam rentang
    $allDatesRaw = [];
    $startDate = new DateTime($tanggalMulai);
    $endDate   = new DateTime($tanggalAkhir);
    $endDate->modify('+1 day'); // agar inklusif
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($startDate, $interval, $endDate);
    foreach ($period as $dt) {
        $allDatesRaw[] = $dt->format('Y-m-d');
    }
    
    // 3. Tanggal non-operasional (hari libur mingguan)
    $nonOperationalDates = [];
    foreach ($allDatesRaw as $tgl) {
        $day_number = date('N', strtotime($tgl)); // 1=Senin, ... 7=Minggu
        if (!in_array($day_number, $operationalDays)) {
            $nonOperationalDates[] = $tgl;
        }
    }
    
    // 4. Ambil tanggal libur tambahan (misal libur nasional)
    // Query diperbarui untuk menggunakan tanggal_mulai dan tanggal_selesai, memilih record libur yang tumpang tindih dengan rentang tanggal
    $holidayDates = [];
    $queryHolidayDates = "
        SELECT tanggal_mulai, tanggal_selesai 
        FROM hari_libur 
        WHERE tanggal_mulai <= '$tanggalAkhir' 
        AND tanggal_selesai >= '$tanggalMulai'
    ";
    $resultHolidayDates = $conn->query($queryHolidayDates);
    if ($resultHolidayDates) {
        while ($row = $resultHolidayDates->fetch_assoc()) {
            // Sesuaikan batas libur agar tidak melebihi rentang yang diminta
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

    // 5. Gabungkan non-operasional dan libur tambahan
    $allHolidayDates = array_unique(array_merge($nonOperationalDates, $holidayDates));
    $totalHolidayUnique = count($allHolidayDates);

    // 6. Total hari masuk (efektif) untuk keseluruhan periode
    $totalEffective = $totalDays - $totalHolidayUnique;

    // 7. Hitung hari efektif Alpa (dibatasi sampai hari ini)
    $tanggalAkhirAlpa = (strtotime($tanggalAkhir) > strtotime($today)) ? $today : $tanggalAkhir;
    if (strtotime($tanggalAkhirAlpa) >= strtotime($tanggalMulai)) {
        $totalDaysAlpa = (strtotime($tanggalAkhirAlpa) - strtotime($tanggalMulai)) / (60 * 60 * 24) + 1;
        $allDatesAlpa = [];
        $startAlpa = new DateTime($tanggalMulai);
        $endAlpa   = new DateTime($tanggalAkhirAlpa);
        $endAlpa->modify('+1 day');
        $periodAlpa = new DatePeriod($startAlpa, $interval, $endAlpa);
        foreach ($periodAlpa as $dtAlpa) {
            $allDatesAlpa[] = $dtAlpa->format('Y-m-d');
        }
        
        // Hari non-operasional untuk periode Alpa
        $nonOpAlpa = [];
        foreach ($allDatesAlpa as $tglA) {
            $dayNumA = date('N', strtotime($tglA));
            if (!in_array($dayNumA, $operationalDays)) {
                $nonOpAlpa[] = $tglA;
            }
        }
        
        // Ambil tanggal libur (dengan range) untuk periode Alpa
        $holidayAlpa = [];
        $queryHolidayAlpa = "
            SELECT tanggal_mulai, tanggal_selesai 
            FROM hari_libur 
            WHERE tanggal_mulai <= '$tanggalAkhirAlpa'
            AND tanggal_selesai >= '$tanggalMulai'
        ";
        $resHolAlpa = $conn->query($queryHolidayAlpa);
        if ($resHolAlpa) {
            while ($rowH = $resHolAlpa->fetch_assoc()) {
                $liburMulaiAlpa = max($tanggalMulai, $rowH['tanggal_mulai']);
                $liburSelesaiAlpa = min($tanggalAkhirAlpa, $rowH['tanggal_selesai']);
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
        
        // Gabungkan tanggal non-operasional dan libur tambahan untuk periode Alpa
        $allHolAlpa = array_unique(array_merge($nonOpAlpa, $holidayAlpa));
        $totalHolAlpa = count($allHolAlpa);
        $totalEffectiveAlpa = $totalDaysAlpa - $totalHolAlpa;
    } else {
        $totalEffectiveAlpa = 0;
    }
}

// Siapkan array seluruh tanggal dalam rentang untuk header kolom detail
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

// Ambil data siswa sesuai filter
$sqlSiswa = "SELECT s.nis, s.nama, k.nama_kelas 
             FROM siswa s 
             LEFT JOIN kelas k ON s.id_kelas = k.id
             WHERE s.status = 'Aktif'
               AND ('$kelasFilter' = '' OR s.id_kelas = '$kelasFilter')
             ORDER BY s.nis ASC";
$resultSiswa = $conn->query($sqlSiswa);

// Tentukan jumlah kolom dasar:
// Jika $kelasFilter kosong, tampilkan kolom No, NIS, Nama, Kelas (4 kolom)
// Jika tidak, hanya tampilkan No, NIS, Nama (3 kolom)
$baseCols = ($kelasFilter == '') ? 4 : 3;
$colCount = $baseCols + count($allDates);
$lastColLetter = Coordinate::stringFromColumnIndex($colCount);

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

/*
    BAGIAN HEADER & INFORMASI:
    1) Baris 1: Judul
    2) Baris 2: Kosong
    3) Baris 3: Kelas
    4) Baris 4: Periode
    5) Baris 5: Total Hari
    6) Baris 6: Total Hari Libur
    7) Baris 7: Total Hari Masuk
    8) Baris 8: Total Hari Efektif
    9) Baris 9: Kosong
    10) Baris 10: Header Tabel
*/

// 1) Baris 1: Judul
$sheet->setCellValue('A1', 'Detail Presensi Harian Siswa SD Negeri Gemawang');
$sheet->mergeCells("A1:{$lastColLetter}1");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// 2) Baris 2: Kosong
$sheet->setCellValue('A2', '');

// 3) Baris 3: Kelas
$kelasLabel = ($kelasFilter != '') ? getKelasName($conn, $kelasFilter) : 'Semua Kelas';
$sheet->setCellValue('A3', 'Kelas: ' . $kelasLabel);
$sheet->mergeCells("A3:{$lastColLetter}3");

// 4) Baris 4: Periode
$sheet->setCellValue('A4', 'Periode: ' . formatTanggal($tanggalMulai) . ' - ' . formatTanggal($tanggalAkhir));
$sheet->mergeCells("A4:{$lastColLetter}4");

// 5) Baris 5: Total Hari
$sheet->setCellValue('A5', 'Total Hari: ' . $totalDays . ' hari');
$sheet->mergeCells("A5:{$lastColLetter}5");

// 6) Baris 6: Total Hari Libur
$sheet->setCellValue('A6', 'Total Hari Libur: ' . $totalHolidayUnique . ' hari');
$sheet->mergeCells("A6:{$lastColLetter}6");

// 7) Baris 7: Total Hari Masuk
$sheet->setCellValue('A7', 'Total Hari Masuk: ' . $totalEffective . ' hari');
$sheet->mergeCells("A7:{$lastColLetter}7");

// 8) Baris 8: Total Hari Efektif
$sheet->setCellValue('A8', 'Total Hari Efektif: ' . $totalEffectiveAlpa . ' hari');
$sheet->mergeCells("A8:{$lastColLetter}8");

// 9) Baris 9: Kosong
$sheet->setCellValue('A9', '');

// 10) Header tabel (baris 10)
$headerRow = 10;
$colIndex = 1;
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'No');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'NIS');
$sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Nama');
// Tampilkan kolom "Kelas" hanya jika $kelasFilter kosong
if ($kelasFilter == '') {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, 'Kelas');
}

// Header untuk setiap tanggal
foreach ($allDates as $tgl) {
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $headerRow, date('d/m', strtotime($tgl)));
}

// Style header
$lastColLetter = Coordinate::stringFromColumnIndex($colIndex - 1);
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getFont()->setBold(true);
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getFill()->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('D3D3D3');
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
      ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// ISI DATA (mulai baris 11)
$rowIndex = $headerRow + 1;
$nomor = 1;

if ($resultSiswa && $resultSiswa->num_rows > 0 && !empty($allDates)) {
    while ($rowS = $resultSiswa->fetch_assoc()) {
        $colPos = 1;
        // Kolom No, NIS, Nama
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colPos++) . $rowIndex, $nomor);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colPos++) . $rowIndex, $rowS['nis']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colPos++) . $rowIndex, $rowS['nama']);
        if ($kelasFilter == '') {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colPos++) . $rowIndex, $rowS['nama_kelas']);
        }
        
        // Pastikan kolom "No" dan "NIS" selalu rata tengah.
        $sheet->getStyle("A{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        if ($kelasFilter == '') {
            // Kolom "Kelas" ada di kolom D jika filter kosong
            $sheet->getStyle("D{$rowIndex}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // Periksa status presensi untuk setiap tanggal
        foreach ($allDates as $tgl) {
            if (in_array($tgl, $allHolidayDates)) {
                $status = 'L'; // Libur
            } else {
                $nis = $rowS['nis'];
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
                    WHERE s2.nis = '$nis' 
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
                } else {
                    $status = (strtotime($tgl) <= strtotime($today)) ? 'A' : '-';
                }
            }
            
            $colLetter = Coordinate::stringFromColumnIndex($colPos++);
            $sheet->setCellValue($colLetter . $rowIndex, $status);
            // Terapkan style per sel (rata tengah dan border)
            $sheet->getStyle($colLetter . $rowIndex)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($colLetter . $rowIndex)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Style untuk kolom awal (No, NIS, Nama, dan Kelas jika ada)
        $endColData = ($kelasFilter == '') ? 4 : 3;
        $sheet->getStyle("A{$rowIndex}:" . Coordinate::stringFromColumnIndex($endColData) . "{$rowIndex}")
              ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$rowIndex}:" . Coordinate::stringFromColumnIndex($endColData) . "{$rowIndex}")
              ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        
        $rowIndex++;
        $nomor++;
    }
} else {
    $sheet->setCellValue('A' . $rowIndex, 'Tidak ada data yang ditemukan');
    $sheet->mergeCells("A{$rowIndex}:{$lastColLetter}{$rowIndex}");
    $rowIndex++;
}

// Tambahkan KETERANGAN di baris-baris terpisah
$legendStart = $rowIndex + 1;
$sheet->setCellValue("A{$legendStart}", 'Keterangan:');
$sheet->mergeCells("A{$legendStart}:{$lastColLetter}{$legendStart}");
$sheet->getStyle("A{$legendStart}")->getFont()->setBold(true);
$sheet->setCellValue("A".($legendStart + 1), "H = Hadir, B = Bolos, I = Izin, S = Sakit, A = Alpa, - = Belum Ada, L = Libur");
$sheet->mergeCells("A".($legendStart + 1).":{$lastColLetter}".($legendStart + 1));

// Atur lebar kolom secara otomatis
for ($col = 1; $col < $colIndex; $col++) {
    $colLetter = Coordinate::stringFromColumnIndex($col);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Output Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="detail_presensi.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();
?>
