<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kelas_id = $conn->real_escape_string($_POST['kelas_id']);

    if($kelas_id != ''){
        $sql = "DELETE FROM kelas WHERE id='$kelas_id'";
        if($conn->query($sql)){
            $response['success'] = true;
            $response['message'] = "Kelas berhasil dihapus.";
        } else {
            $response['message'] = "Gagal menghapus kelas: " . $conn->error;
        }
    } else {
        $response['message'] = "ID kelas tidak valid.";
    }
}
$conn->close();
echo json_encode($response);
?>
