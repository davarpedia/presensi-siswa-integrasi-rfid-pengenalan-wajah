<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $guru_id = isset($_POST['guru_id']) ? trim($_POST['guru_id']) : '';

    if (empty($guru_id)) {
        echo json_encode(['success' => false, 'message' => 'ID guru tidak ditemukan']);
        exit;
    }

    // Hapus data guru dari tabel guru
    $stmt = $conn->prepare("DELETE FROM guru WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $guru_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Data guru berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus guru: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
