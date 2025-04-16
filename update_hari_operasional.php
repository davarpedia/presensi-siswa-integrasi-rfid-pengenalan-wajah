<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Terima data hari_operasional sebagai string, misalnya "1,3,5"
    $hari_operasional = isset($_POST['hari_operasional']) ? $_POST['hari_operasional'] : '';
    
    $sql = "UPDATE `pengaturan` SET `hari_operasional` = '$hari_operasional' WHERE `id` = 1";
    
    if ($conn->query($sql) === TRUE) {
        $response = ['status' => 'success', 'message' => 'Hari operasional berhasil diubah!'];
    } else {
        $response = ['status' => 'error', 'message' => 'Gagal mengubah hari operasional!'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>
