<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kelas_id    = trim($conn->real_escape_string($_POST['kelas_id']));
    $nama_kelas  = trim($conn->real_escape_string($_POST['nama_kelas']));
    $guru_id     = trim($conn->real_escape_string($_POST['guru_id']));

    if ($kelas_id === '' || $nama_kelas === '' || $guru_id === '') {
        $response['message'] = "Semua field harus diisi!";
    } else {
        // 1) Cek duplikat nama_kelas (kecuali record ini)
        $cek = $conn->prepare(
            "SELECT COUNT(*) FROM kelas 
             WHERE nama_kelas = ? AND id <> ?"
        );
        $cek->bind_param("si", $nama_kelas, $kelas_id);
        $cek->execute();
        $cek->bind_result($count);
        $cek->fetch();
        $cek->close();

        if ($count > 0) {
            $response['message'] = "Nama kelas “{$nama_kelas}” sudah ada. Silakan gunakan nama lain!";
        } else {
            // 2) Lakukan update
            $upd = $conn->prepare(
                "UPDATE kelas 
                    SET nama_kelas = ?, guru_id = ?
                  WHERE id = ?"
            );
            $upd->bind_param("sii", $nama_kelas, $guru_id, $kelas_id);
            if ($upd->execute()) {
                $response['success'] = true;
                $response['message'] = "Data kelas berhasil diperbarui.";
            } else {
                $response['message'] = "Gagal memperbarui data kelas: " . $upd->error;
            }
            $upd->close();
        }
    }
}

$conn->close();
echo json_encode($response);
?>
