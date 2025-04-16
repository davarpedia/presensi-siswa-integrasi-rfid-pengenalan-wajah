<?php
require_once 'koneksi.php';

header('Content-Type: application/json');

$query = "SELECT id, tanggal_mulai, tanggal_selesai, keterangan FROM hari_libur";
$result = $conn->query($query);
$events = array();

while($row = $result->fetch_assoc()){
    // FullCalendar menganggap nilai 'end' sebagai tanggal eksklusif,
    // Tambahkan satu hari ke tanggal_selesai untuk menampilkan libur sampai hari tersebut.
    $endDate = date("Y-m-d", strtotime($row['tanggal_selesai'] . " +1 day"));
    
    $events[] = array(
        'id'      => $row['id'],
        'title'   => $row['keterangan'],
        'start'   => $row['tanggal_mulai'],
        'end'     => $endDate,
        'allDay'  => true
    );
}

echo json_encode($events);
?>
