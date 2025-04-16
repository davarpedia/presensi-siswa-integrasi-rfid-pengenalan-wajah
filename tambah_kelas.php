<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

$response = array('success' => false, 'message' => '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_kelas = $conn->real_escape_string($_POST['nama_kelas']);
    $id_guru = $conn->real_escape_string($_POST['id_guru']);

    if($nama_kelas != '' && $id_guru != ''){
        $sql = "INSERT INTO kelas (nama_kelas, id_guru) VALUES ('$nama_kelas', '$id_guru')";
        if($conn->query($sql)){
            $response['success'] = true;
            $response['message'] = "Kelas berhasil ditambahkan.";
        } else {
            $response['message'] = "Gagal menambahkan kelas: " . $conn->error;
        }
    } else {
        $response['message'] = "Semua field harus diisi.";
    }
}
$conn->close();
echo json_encode($response);
?>
