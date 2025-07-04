<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid']);
    exit;
}

$pengguna_id   = trim($_POST['pengguna_id']   ?? '');
$nip           = trim($_POST['nip']           ?? '');
$jenis_kelamin = trim($_POST['jenis_kelamin'] ?? '');
$telepon       = trim($_POST['telepon']       ?? '');
$alamat        = trim($_POST['alamat']        ?? '');

// Wajib isi semua
if (empty($pengguna_id) || empty($nip) || empty($jenis_kelamin) || empty($telepon) || empty($alamat)) {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
    exit;
}

// Cek pengguna_id harus ada & level guru
$stmt = $conn->prepare("SELECT 1 FROM pengguna WHERE id = ? AND level = 'Guru' LIMIT 1");
$stmt->bind_param("i", $pengguna_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan atau bukan guru!']);
    exit;
}
$stmt->close();

// Cek pengguna_id udah dipake belum
$stmt = $conn->prepare("SELECT 1 FROM guru WHERE pengguna_id = ? LIMIT 1");
$stmt->bind_param("i", $pengguna_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Pengguna sudah terdaftar sebagai guru!']);
    exit;
}
$stmt->close();

// Cek NIP udah dipake belum
$stmt = $conn->prepare("SELECT 1 FROM guru WHERE nip = ? LIMIT 1");
$stmt->bind_param("s", $nip);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'NIP sudah terdaftar pada guru lain!']);
    exit;
}
$stmt->close();

// Insert data guru
$stmt = $conn->prepare("
    INSERT INTO guru (pengguna_id, nip, jenis_kelamin, telepon, alamat)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("issss", $pengguna_id, $nip, $jenis_kelamin, $telepon, $alamat);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Guru berhasil ditambahkan.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menambahkan guru: '.$stmt->error]);
}

$stmt->close();
$conn->close();
?>
