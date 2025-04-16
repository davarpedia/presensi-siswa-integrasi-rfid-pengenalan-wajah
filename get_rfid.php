<?php
require_once 'koneksi.php';

// Menetapkan header untuk output JSON
header("Content-Type: application/json");

// Menjalankan query untuk mengambil data RFID terbaru dari tabel 'tmp_rfid_tambah'
$sql = "SELECT * FROM tmp_rfid_tambah ORDER BY id DESC LIMIT 1";
$result = $conn->query($sql);

// Mengecek apakah ada data yang ditemukan
if ($result && $result->num_rows > 0) {
    // Mengambil baris data terbaru
    $row = $result->fetch_assoc();
    
    // Menyimpan data RFID ke dalam array
    $data = array(
        'id' => $row['no_rfid']
    );
} else {
    // Jika tidak ada data, kirimkan data kosong
    $data = array(
        'id' => ""
    );
}

// Mengirimkan data dalam format JSON
echo json_encode($data);

// Menutup koneksi database
$conn->close();
?>
