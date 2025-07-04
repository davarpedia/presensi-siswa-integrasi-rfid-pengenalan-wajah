<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kelas = trim($conn->real_escape_string($_POST['nama_kelas']));
    $guru_id    = trim($conn->real_escape_string($_POST['guru_id']));

    if ($nama_kelas === '' || $guru_id === '') {
        $response['message'] = "Semua field harus diisi!";
    } else {
        // Cek duplikat nama_kelas
        $cek = $conn->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ?");
        $cek->bind_param("s", $nama_kelas);
        $cek->execute();
        $cek->bind_result($count);
        $cek->fetch();
        $cek->close();

        if ($count > 0) {
            $response['message'] = "Nama kelas “{$nama_kelas}” sudah ada. Silakan gunakan nama lain!";
        } else {
            // Insert
            $ins = $conn->prepare("INSERT INTO kelas (nama_kelas, guru_id) VALUES (?, ?)");
            $ins->bind_param("si", $nama_kelas, $guru_id);
            if ($ins->execute()) {
                $response['success'] = true;
                $response['message'] = "Kelas berhasil ditambahkan.";
            } else {
                $response['message'] = "Gagal menambahkan kelas: " . $ins->error;
            }
            $ins->close();
        }
    }
}

$conn->close();
echo json_encode($response);
