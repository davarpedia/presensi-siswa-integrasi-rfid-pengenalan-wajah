<?php
session_start();
require_once 'koneksi.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['session_email'])) {
    header("Location: login.php");
    exit();
}

// Pastikan level pengguna adalah guru
// dan guru_id belum ada di session (artinya belum punya data di tabel guru)
if ($_SESSION['session_level'] !== 'guru' || !empty($_SESSION['guru_id'])) {
    header("Location: index.php");
    exit();
}

$err = "";
$nip = "";
$jenis_kelamin = "";
$telepon = "";
$alamat = "";

// Proses ketika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    $nip = trim($_POST['nip']);
    $jenis_kelamin = isset($_POST['jenis_kelamin']) ? $_POST['jenis_kelamin'] : '';
    $telepon = trim($_POST['telepon']);
    $alamat = trim($_POST['alamat']);

    // Validasi input sederhana
    if (empty($nip)) {
        $err = "NIP tidak boleh kosong!";
    } elseif ($jenis_kelamin !== 'L' && $jenis_kelamin !== 'P') {
        $err = "Pilih jenis kelamin terlebih dahulu!";
    } else {
        // Cek apakah NIP sudah ada di tabel guru
        $sqlCekNIP = "SELECT id FROM guru WHERE nip = ?";
        $stmtCek = $conn->prepare($sqlCekNIP);
        $stmtCek->bind_param("s", $nip);
        $stmtCek->execute();
        $stmtCek->store_result();

        if ($stmtCek->num_rows > 0) {
            // NIP sudah ada
            $err = "NIP sudah terdaftar, Silahkan gunakan NIP lain!";
        } else {
            // NIP belum ada, lanjutkan simpan
            $stmtCek->close();

            $id_pengguna = $_SESSION['session_id'];
            $sqlInsert = "INSERT INTO guru (id_pengguna, nip, jenis_kelamin, telepon, alamat, status)
                          VALUES (?, ?, ?, ?, ?, 'aktif')";
            $stmtIns = $conn->prepare($sqlInsert);
            $stmtIns->bind_param("issss", $id_pengguna, $nip, $jenis_kelamin, $telepon, $alamat);

            if ($stmtIns->execute()) {
                // Berhasil insert, ambil id guru yang baru disimpan
                $new_guru_id = $stmtIns->insert_id;
                
                // Simpan ke session
                $_SESSION['guru_id'] = $new_guru_id;

                // Redirect ke index setelah berhasil lengkapi data
                header("Location: index.php");
                exit();
            } else {
                $err = "Terjadi kesalahan saat menyimpan data guru!";
            }
            $stmtIns->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lengkapi Data Guru</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <style>
    body {
        background-color: #f8f9fc;
    }
    .container {
        max-width: 600px;
        margin: 50px auto;
    }
    .card {
        border: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Lengkapi Data Guru</h4>
        </div>
        <div class="card-body">
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="nip">NIP</label>
                    <input type="text" name="nip" id="nip" 
                           class="form-control" 
                           value="<?= htmlspecialchars($nip) ?>"
                           required>
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" 
                               type="radio" name="jenis_kelamin" 
                               id="jeniskel_l" value="L"
                               <?= $jenis_kelamin === 'L' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="jeniskel_l">Laki-laki</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" 
                               type="radio" name="jenis_kelamin" 
                               id="jeniskel_p" value="P"
                               <?= $jenis_kelamin === 'P' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="jeniskel_p">Perempuan</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="telepon">Telepon</label>
                    <input type="text" name="telepon" id="telepon" 
                           class="form-control"
                           value="<?= htmlspecialchars($telepon) ?>">
                </div>
                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea name="alamat" id="alamat" 
                              class="form-control" rows="3"><?= htmlspecialchars($alamat) ?></textarea>
                </div>
                <button type="submit" name="simpan" class="btn btn-primary w-100">
                    Simpan
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
