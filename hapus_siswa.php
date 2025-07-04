<?php
session_start();
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Ambil data siswa berdasarkan ID
    $sql = "SELECT foto_siswa, dataset_wajah, nis FROM siswa WHERE id = '$id'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $foto_siswa   = $row['foto_siswa'];
        $dataset_wajah = $row['dataset_wajah'];
        $nis          = $row['nis'];

        // Hapus file foto siswa jika ada
        $foto_path = "data/foto/foto_siswa/" . $foto_siswa;
        if (!empty($foto_siswa) && file_exists($foto_path)) {
            unlink($foto_path);
        }

        // Hapus folder dataset wajah siswa jika ada
        if (!empty($dataset_wajah)) {
            $dataset_folder = "data/dataset/" . $dataset_wajah;
            if (is_dir($dataset_folder)) {
                $files = array_diff(scandir($dataset_folder), array('.', '..'));
                foreach ($files as $file) {
                    $file_path = $dataset_folder . "/" . $file;
                    if (is_file($file_path)) {
                        unlink($file_path);
                    }
                }
                rmdir($dataset_folder);
            }
        }

        // Hapus data siswa dari database
        $delete_sql = "DELETE FROM siswa WHERE id = '$id'";
        if ($conn->query($delete_sql) === TRUE) {
            // Panggil endpoint Flask untuk menghapus data wajah (opsional)
            $data = json_encode(["nis" => $nis]);
            $ch = curl_init("http://192.168.121.177:5000/delete_face_data");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            curl_close($ch);

            $_SESSION['alert'] = [
                'title' => 'Berhasil!',
                'text' => 'Data siswa berhasil dihapus.',
                'icon' => 'success'
            ];
        } else {
            $_SESSION['alert'] = [
                'title' => 'Gagal!',
                'text' => 'Terjadi kesalahan saat menghapus data siswa.',
                'icon' => 'error'
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'title' => 'Error!',
            'text' => 'Data siswa tidak ditemukan.',
            'icon' => 'warning'
        ];
    }
    $conn->close();
    header("Location: siswa.php");
    exit();
} else {
    $_SESSION['alert'] = [
        'title' => 'Error!',
        'text' => 'ID siswa tidak ditemukan.',
        'icon' => 'warning'
    ];
    header("Location: siswa.php");
    exit();
}
?>
