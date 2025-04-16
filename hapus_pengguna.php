<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = trim($_POST['id']);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
        exit;
    }
    
    // Ambil nama file foto sebelum dihapus dari database
    $stmt = $conn->prepare("SELECT foto_profil FROM pengguna WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($foto_profil);
    $stmt->fetch();
    $stmt->close();

    // Jika ada foto, hapus file tersebut dari penyimpanan
    if (!empty($foto_profil)) {
        $targetFile = "data/foto/foto_profil_pengguna/" . $foto_profil;
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }
    }
    
    // Hapus data pengguna dari database
    $stmt = $conn->prepare("DELETE FROM pengguna WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        // Cek apakah pengguna yang sedang login menghapus akunnya sendiri
        if (isset($_SESSION['session_id']) && $_SESSION['session_id'] == $id) {
            // Hapus seluruh data session
            session_destroy();
        }
        echo json_encode(['success' => true, 'message' => 'Pengguna berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengguna: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
