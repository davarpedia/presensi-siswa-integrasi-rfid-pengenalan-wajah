<?php
require_once 'koneksi.php';

header('Content-Type: application/json');

$query  = "SELECT id, tanggal_mulai, tanggal_selesai, keterangan FROM hari_libur";
$result = $conn->query($query);
$events = [];

while($row = $result->fetch_assoc()){
    // FullCalendar menganggap 'end' eksklusif → +1 day
    $endDate = date("Y-m-d", strtotime($row['tanggal_selesai'] . " +1 day"));
    
    $events[] = [
        'id'                     => $row['id'],
        'title'                  => $row['keterangan'],
        'start'                  => $row['tanggal_mulai'],
        'end'                    => $endDate,
        'allDay'                 => true,
        // Properti tambahan untuk isi form nanti
        'tanggal_mulai_orig'     => $row['tanggal_mulai'],
        'tanggal_selesai_orig'   => $row['tanggal_selesai'],
    ];
}

echo json_encode($events);
?>
