<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data form
    $guru_id       = isset($_POST['guru_id']) ? trim($_POST['guru_id']) : '';
    $nama          = isset($_POST['nama']) ? trim($_POST['nama']) : '';
    $email         = isset($_POST['email']) ? trim($_POST['email']) : '';
    $nip           = isset($_POST['nip']) ? trim($_POST['nip']) : '';
    $jenis_kelamin = isset($_POST['jenis_kelamin']) ? trim($_POST['jenis_kelamin']) : '';
    $telepon       = isset($_POST['telepon']) ? trim($_POST['telepon']) : '';
    $alamat        = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
    $status        = isset($_POST['status']) ? trim($_POST['status']) : '';
    
    // Validasi wajib
    if (empty($guru_id) || empty($nama) || empty($email) || empty($nip) || empty($jenis_kelamin) || empty($telepon) || empty($alamat) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
        exit;
    }
    
    // Ambil pengguna_id milik guru tersebut
    $stmt = $conn->prepare("SELECT pengguna_id FROM guru WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error (select pengguna_id): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $stmt->bind_result($pengguna_id);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Data guru tidak ditemukan!']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Validasi: pastikan email belum terdaftar di tabel pengguna untuk pengguna lain
    $stmt = $conn->prepare("SELECT id FROM pengguna WHERE email = ? AND id <> ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error (validasi email): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $email, $pengguna_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar pada pengguna lain!']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Validasi: pastikan NIP belum terdaftar di tabel guru untuk guru lain
    $stmt = $conn->prepare("SELECT id FROM guru WHERE nip = ? AND id <> ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error (validasi NIP): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $nip, $guru_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'NIP sudah terdaftar pada guru lain!']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // Update data tambahan pada tabel guru (NIP, jenis_kelamin, telepon, alamat, status)
    $stmt = $conn->prepare("UPDATE guru SET nip = ?, jenis_kelamin = ?, telepon = ?, alamat = ?, status = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error (guru): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssssi", $nip, $jenis_kelamin, $telepon, $alamat, $status, $guru_id);
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui data guru: ' . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Jika ada file foto baru yang diupload, proses upload dan update foto_profil di tabel pengguna
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        // Ambil ekstensi file
        $imageFileType = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        
        // Pastikan file benar-benar gambar
        $check = getimagesize($_FILES['foto']['tmp_name']);
        if($check === false) {
            echo json_encode(['success' => false, 'message' => 'File yang diupload bukan gambar!']);
            exit;
        }
        // Validasi ukuran file (maksimal 5MB)
        if ($_FILES['foto']['size'] > 5000000) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar, maksimal 5MB!']);
            exit;
        }
        
        // Buat nama file baru dengan format custom: [NamaGuru]_YYYYMMDDHHMMSS.[ekstensi]
        $namaClean = str_replace(' ', '_', $nama);
        $timestamp = date("YmdHis");
        $foto_baru = $namaClean . "_" . $timestamp . "." . $imageFileType;
        
        // Tentukan direktori upload
        $targetDir = "data/foto/foto_profil_pengguna/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetFile = $targetDir . $foto_baru;
        
        // Pindahkan file ke direktori tujuan
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat mengupload file']);
            exit;
        }
        
        // Update kolom foto_profil di tabel pengguna dengan nama file baru
        $stmt = $conn->prepare("UPDATE pengguna SET foto_profil = ? WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Prepare statement error (update foto): ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("si", $foto_baru, $pengguna_id);
        if (!$stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate foto: ' . $stmt->error]);
            $stmt->close();
            exit;
        }
        $stmt->close();
    }
    
    // Update data di tabel pengguna (nama dan email)
    $stmt = $conn->prepare("UPDATE pengguna SET nama = ?, email = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error (pengguna): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssi", $nama, $email, $pengguna_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data guru berhasil diperbarui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate data pengguna: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
