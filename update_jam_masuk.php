<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jam_masuk = $_POST['jam_masuk'];
    $sql = "UPDATE `pengaturan` SET `jam_masuk` = '$jam_masuk' WHERE `id` = 1";

    if ($conn->query($sql) === TRUE) {
        $response = ['status' => 'success', 'message' => 'Jam masuk berhasil diubah!'];
    } else {
        $response = ['status' => 'error', 'message' => 'Gagal mengubah jam masuk!'];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
