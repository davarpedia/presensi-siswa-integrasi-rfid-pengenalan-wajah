<?php
// Set timezone ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');
$tgl = date("Y-m-d H:i:s"); // Mendapatkan waktu saat ini dalam format Y-m-d H:i:s

// Koneksi ke database
require_once 'koneksi.php';

// Memastikan bahwa ada data POST dengan key "rfid"
if (isset($_POST["rfid"])) {
    $rfid = $_POST["rfid"]; // Ambil data RFID dari POST request

    // Query untuk memeriksa apakah ada data pada ID 1
    $sql_check = "SELECT * FROM tmp_rfid_tambah WHERE id = 1";
    $result = $conn->query($sql_check);

    // Jika data pada ID 1 sudah ada, lakukan UPDATE
    if ($result->num_rows > 0) {
        $sql_tmp = "UPDATE tmp_rfid_tambah SET no_rfid = '$rfid' WHERE id = 1";
    } else {
        // Jika data pada ID 1 belum ada, lakukan INSERT
        $sql_tmp = "INSERT INTO tmp_rfid_tambah (id, no_rfid) VALUES (1, '$rfid')";
    }

    // SQL query untuk menambahkan data ke tabel history_rfid dengan date_time
    $sql_history = "INSERT INTO history_rfid (no_rfid, waktu) VALUES ('$rfid', '$tgl')";

    // Eksekusi query untuk tabel tmp_rfid_tambah dan history_rfid
    if ($conn->query($sql_tmp) === TRUE && $conn->query($sql_history) === TRUE) {
        // Jika berhasil menyimpan kedua data
        $response = array("status" => "1", "pesan" => "Data RFID berhasil disimpan");
    } else {
        // Jika ada error pada salah satu query
        $response = array("status" => "0", "pesan" => "Error: " . $conn->error);
    }
} else {
    // Jika tidak ada data RFID yang dikirim melalui POST
    $response = array("status" => "0", "pesan" => "Data RFID tidak ditemukan dalam POST request");
}

// Mengirim respon dalam format JSON
header("Content-Type: application/json");
echo json_encode($response);

// Menutup koneksi database
$conn->close();
?>
