<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_kelas = $conn->real_escape_string($_POST['id_kelas']);
    $nama_kelas = $conn->real_escape_string($_POST['nama_kelas']);
    $id_guru = $conn->real_escape_string($_POST['id_guru']);

    if($id_kelas != '' && $nama_kelas != '' && $id_guru != ''){
        $sql = "UPDATE kelas SET nama_kelas='$nama_kelas', id_guru='$id_guru' WHERE id='$id_kelas'";
        if($conn->query($sql)){
            $response['success'] = true;
            $response['message'] = "Data kelas berhasil diperbarui.";
        } else {
            $response['message'] = "Gagal memperbarui data kelas: " . $conn->error;
        }
    } else {
        $response['message'] = "Semua field harus diisi.";
    }
}
$conn->close();
echo json_encode($response);
?>
