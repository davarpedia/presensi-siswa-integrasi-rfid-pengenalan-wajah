<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

// Aktifkan error reporting untuk debugging (nonaktifkan di produksi)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form dan lakukan trim untuk menghindari spasi yang tidak perlu
    $nama     = trim($_POST['nama']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $level    = trim($_POST['level']);

    // Validasi sederhana: pastikan semua field wajib terisi
    if (empty($nama) || empty($email) || empty($password) || empty($level)) {
        echo json_encode(['success' => false, 'message' => 'Semua field harus diisi']);
        exit;
    }
    
    // Validasi format email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
        exit;
    }
    
    // Cek apakah email sudah terdaftar
    $stmt = $conn->prepare("SELECT id FROM pengguna WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar, silakan gunakan email lain!']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Proses upload foto profil (opsional)
    $foto_profil = null; // default jika tidak diupload
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        // Ambil ekstensi file dan ubah ke huruf kecil
        $imageFileType = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        // Buat nama file baru, misalnya: nama_ditulis_tanpa_spasi_waktu.ext
        $foto_profil = preg_replace('/\s+/', '_', $nama) . "_" . date('YmdHis') . "." . $imageFileType;
        $targetDir = "data/foto/foto_profil_pengguna/"; // Folder penyimpanan foto
        
        // Jika folder tidak ada, buat folder tersebut
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetFile = $targetDir . $foto_profil;
        
        // Validasi: Pastikan file yang diupload benar-benar gambar
        $check = getimagesize($_FILES['foto']['tmp_name']);
        if ($check === false) {
            echo json_encode(['success' => false, 'message' => 'File yang diupload bukan gambar']);
            exit;
        }
        
        // Validasi: Ukuran file maksimal 5MB
        if ($_FILES['foto']['size'] > 5000000) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar, maksimal 5MB']);
            exit;
        }
        
        // Upload file ke server
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengupload file']);
            exit;
        }
    }
    
    // Hash password untuk keamanan menggunakan PASSWORD_DEFAULT
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert data ke tabel pengguna
    $stmt = $conn->prepare("INSERT INTO pengguna (nama, email, password, level, foto_profil) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssss", $nama, $email, $hashed_password, $level, $foto_profil);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pengguna berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan pengguna: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
