<?php 
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data input dari form
    $id     = trim($_POST['id']);
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $level  = trim($_POST['level']);
    $status = trim($_POST['status']);

    // Validasi field wajib
    if (empty($id) || empty($nama) || empty($email) || empty($level) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
        exit;
    }
    
    // Validasi format email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
        exit;
    }
    
    // Cek apakah email sudah digunakan oleh pengguna lain (selain pengguna yang sedang diedit)
    $stmt = $conn->prepare("SELECT id FROM pengguna WHERE email = ? AND id != ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $email, $id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar oleh pengguna lain, silakan gunakan email lain']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Ambil foto profil saat ini (jika tidak ada file baru yang diupload, tetap gunakan foto lama)
    $stmtCurrent = $conn->prepare("SELECT foto_profil FROM pengguna WHERE id = ?");
    if (!$stmtCurrent) {
        echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]);
        exit;
    }
    $stmtCurrent->bind_param("i", $id);
    $stmtCurrent->execute();
    $stmtCurrent->bind_result($current_foto);
    $stmtCurrent->fetch();
    $stmtCurrent->close();
    $foto_profil = $current_foto; // Default foto profil lama

    // Proses upload foto jika ada file yang diupload
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        // Ambil ekstensi file dalam huruf kecil
        $imageFileType = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        // Buat nama file baru, misalnya: nama_waktu.ext (hapus spasi dari nama)
        $foto_profil = preg_replace('/\s+/', '_', $nama) . "_" . date('YmdHis') . "." . $imageFileType;
        $targetDir = "data/foto/foto_profil_pengguna/"; // Folder tujuan
        
        // Jika folder belum ada, buat folder tersebut
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetFile = $targetDir . $foto_profil;
        
        // Cek apakah file yang diupload benar merupakan gambar
        $check = getimagesize($_FILES['foto']['tmp_name']);
        if ($check === false) {
            echo json_encode(['success' => false, 'message' => 'File yang diupload bukan gambar']);
            exit;
        }
        
        // Cek ukuran file maksimal (misal: 5MB)
        if ($_FILES['foto']['size'] > 5000000) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar! Maksimal 5MB']);
            exit;
        }
        
        // Upload file ke folder tujuan
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengupload foto profil']);
            exit;
        }
    }
    
    // Update data pengguna, termasuk foto profil dan status (baik foto lama atau foto baru jika diupload)
    $stmt = $conn->prepare("UPDATE pengguna SET nama = ?, email = ?, level = ?, status = ?, foto_profil = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]);
        exit;
    }
    // Urutan parameter: nama, email, level, status, foto_profil, id
    $stmt->bind_param("sssssi", $nama, $email, $level, $status, $foto_profil, $id);
    
    if ($stmt->execute()) {

        // Jika pengguna yang sedang login adalah yang sedang diupdate, perbarui session
        if (isset($_SESSION['session_id']) && $_SESSION['session_id'] == $id) {
            // Jika status diubah menjadi Nonaktif, hapus session (logout)
            if (strtolower($status) == "nonaktif") {
                session_destroy();
                echo json_encode(['success' => true, 'message' => 'Akun telah dinonaktifkan. Anda akan otomatis logout.']);
                exit;
            } else {
                // Update session jika akun masih aktif
                $_SESSION['session_email']  = $email;
                $_SESSION['session_nama']   = $nama;
                $_SESSION['session_level']  = ucfirst($level);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Pengguna berhasil diperbarui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate pengguna: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
