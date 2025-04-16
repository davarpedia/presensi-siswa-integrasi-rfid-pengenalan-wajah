<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

    // Validasi input
    if(empty($id) || empty($new_password) || empty($confirm_new_password)){
        echo json_encode(['success' => false, 'message' => 'Semua field harus diisi.']);
        exit;
    }
    if($new_password !== $confirm_new_password){
        echo json_encode(['success' => false, 'message' => 'Password baru dan konfirmasi tidak cocok!']);
        exit;
    }

    // Hash password baru menggunakan PASSWORD_DEFAULT
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password di database
    $stmt = $conn->prepare("UPDATE pengguna SET password = ? WHERE id = ?");
    if(!$stmt){
        echo json_encode(['success' => false, 'message' => 'Prepare statement error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $new_hashed_password, $id);
    if($stmt->execute()){
        echo json_encode(['success' => true, 'message' => 'Password berhasil diubah.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate password.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>
