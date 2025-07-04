<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';

$id_user = $_SESSION['session_id'];
$errors = [];

if (isset($_POST['ubah_password'])) {
    $password_lama = mysqli_real_escape_string($conn, $_POST['password_lama']);
    $password_baru = mysqli_real_escape_string($conn, $_POST['password_baru']);
    $konfirmasi_password = mysqli_real_escape_string($conn, $_POST['konfirmasi_password']);

    // Ambil password lama dari database
    $query = mysqli_query($conn, "SELECT password FROM pengguna WHERE id = '$id_user'");
    $data = mysqli_fetch_assoc($query);

    // Verifikasi password lama
    if (!password_verify($password_lama, $data['password'])) {
        $errors[] = "Password lama salah.";
    }

    // Validasi password baru
    if (strlen($password_baru) < 6) {
        $errors[] = "Password baru minimal harus terdiri dari 6 karakter.";
    }
    if ($password_baru !== $konfirmasi_password) {
        $errors[] = "Konfirmasi password tidak sesuai.";
    }
    if (password_verify($password_baru, $data['password'])) {
        $errors[] = "Password baru tidak boleh sama dengan password lama.";
    }

    // Jika tidak ada error, update password
    if (empty($errors)) {
        $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
        $update = mysqli_query($conn, "UPDATE pengguna SET password='$password_hash' WHERE id='$id_user'");

        if ($update) {
            // Set session alert untuk SweetAlert2
            $_SESSION['alert'] = [
                'title' => 'Berhasil',
                'text'  => 'Password berhasil diperbarui.',
                'icon'  => 'success'
            ];
            header('Location: ganti_password.php');
            exit;
        } else {
            // Jika gagal, bisa juga set session alert error atau menampilkan error biasa
            $_SESSION['alert'] = [
                'title' => 'Error',
                'text'  => 'Gagal mengubah password! Silakan coba lagi.',
                'icon'  => 'error'
            ];
            header('Location: ganti_password.php');
            exit;
        }        
    }
}

$pageTitle = "Ganti Password";
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Ganti Password</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Ganti Password</li>
        </ol>
    </nav>
  </div>

    <div class="card mb-4">
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger p-2">
                    <?php foreach ($errors as $err): ?>
                        <div><?= $err; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Password Saat Ini</label>
                    <input type="password" name="password_lama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="password_baru" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password Baru</label>
                    <input type="password" name="konfirmasi_password" class="form-control" required>
                </div>
                <button type="submit" name="ubah_password" class="btn btn-primary">Ubah Password</button>
            </form>
        </div>
    </div>
</div>

</div

<?php include 'footer.php'; ?>
