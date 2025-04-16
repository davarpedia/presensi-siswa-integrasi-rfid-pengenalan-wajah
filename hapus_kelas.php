<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kelas = $conn->real_escape_string($_POST['id_kelas']);

    if($id_kelas != ''){
        $sql = "DELETE FROM kelas WHERE id='$id_kelas'";
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
