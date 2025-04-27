<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

// Pastikan ada parameter id dan file
if (isset($_GET['id']) && isset($_POST['file'])) {
    $id_siswa = $_GET['id'];
    $file = $_POST['file'];

    // Cek apakah ID siswa valid
    $sql = "SELECT * FROM `siswa` WHERE `id` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_siswa);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $siswa = $result->fetch_assoc();
        $dataset_folder = "data/dataset/{$siswa['dataset_wajah']}";
        $file_path = "$dataset_folder/$file";

        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                $_SESSION['alert'] = [
                    'title' => 'Berhasil',
                    'text'  => 'Foto dataset berhasil dihapus.',
                    'icon'  => 'success'
                ];
            } else {
                $_SESSION['alert'] = [
                    'title' => 'Penghapusan Gagal!',
                    'text'  => 'Terjadi kesalahan saat menghapus foto. Silahkan coba lagi.',
                    'icon'  => 'error'
                ];
            }
        } else {
            $_SESSION['alert'] = [
                'title' => 'File Tidak Ditemukan!',
                'text'  => 'File tidak ditemukan di folder dataset.',
                'icon'  => 'warning'
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'title' => 'Siswa Tidak Ditemukan!',
            'text'  => 'Data siswa dengan ID tersebut tidak tersedia.',
            'icon'  => 'error'
        ];
    }
} else {
    $_SESSION['alert'] = [
        'title' => 'Permintaan Tidak Lengkap!',
        'text'  => 'Parameter ID dan nama file wajib disertakan.',
        'icon'  => 'error'
    ];
}

// Redirect kembali ke halaman pengelolaan dataset
header('Location: kelola_dataset_wajah.php?id=' . $_GET['id']);
exit();
?>
