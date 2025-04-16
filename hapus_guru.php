<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_guru = isset($_POST['id_guru']) ? trim($_POST['id_guru']) : '';

    if (empty($id_guru)) {
        echo json_encode(['success' => false, 'message' => 'ID guru tidak ditemukan']);
        exit;
    }

    // Hapus data guru dari tabel guru
    $stmt = $conn->prepare("DELETE FROM guru WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $id_guru);
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
