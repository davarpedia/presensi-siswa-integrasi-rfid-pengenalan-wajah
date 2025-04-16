<?php
session_start();
require_once 'koneksi.php';

$err = "";
$email = "";
$ingat_saya = 0;

// Cek jika pengguna sudah login
if (isset($_SESSION['session_email'])) {
    // Jika user guru tapi data guru belum lengkap, redirect ke lengkapi_data_guru.php
    if ($_SESSION['session_level'] === 'guru' && empty($_SESSION['guru_id'])) {
        header("Location: lengkapi_data_guru.php");
        exit();
    }
    // Jika sudah lengkap atau bukan guru, langsung ke dashboard
    header("Location: index.php");
    exit();
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);
    $ingat_saya = isset($_POST['ingat']) ? 1 : 0;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = "Format email tidak valid!";
    } else {
        $stmt = $conn->prepare("SELECT id, email, password, nama, level, status FROM pengguna WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id, $user_email, $hashed_password, $user_nama, $user_level, $user_status);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                if (strtolower($user_status) !== 'aktif') {
                    $err = "Akun Anda saat ini nonaktif! Silahkan hubungi administrator untuk mengaktifkannya kembali.";
                } else {
                    // Simpan informasi ke dalam session
                    $_SESSION['session_id']    = $user_id;
                    $_SESSION['session_email'] = $user_email;
                    $_SESSION['session_nama']  = $user_nama;
                    // Simpan dalam lowercase agar konsisten
                    $_SESSION['session_level'] = strtolower($user_level);

                    // Default redirect ke dashboard
                    $redirect = "index.php";

                    // Jika user adalah Guru, cek data di tabel guru
                    if ($_SESSION['session_level'] === 'guru') {
                        $stmtGuru = $conn->prepare("SELECT id FROM guru WHERE id_pengguna = ?");
                        $stmtGuru->bind_param("i", $user_id);
                        $stmtGuru->execute();
                        $stmtGuru->store_result();

                        if ($stmtGuru->num_rows > 0) {
                            $stmtGuru->bind_result($guru_id);
                            $stmtGuru->fetch();
                            // Data guru sudah ada → simpan ke session
                            $_SESSION['guru_id'] = $guru_id;
                        } else {
                            // Data guru belum ada → redirect ke halaman lengkapi data guru
                            $redirect = "lengkapi_data_guru.php";
                        }
                        $stmtGuru->close();
                    }

                    // Jika "Ingat Saya" dicentang, buat cookie
                    if ($ingat_saya) {
                        setcookie("cookie_email", $user_email, time() + (86400 * 30), "/", "", false, true);
                        setcookie("cookie_token", password_hash($hashed_password, PASSWORD_DEFAULT), time() + (86400 * 30), "/", "", false, true);
                    }

                    header("Location: $redirect");
                    exit();
                }
            } else {
                $err = "Email atau password salah!";
            }
        } else {
            $err = "Email tidak terdaftar!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <style>
    html, body {
        height: 100%;
        margin: 0;
        background-color: #f8f9fc;
    }
    .login-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100%;
    }
    .card { width: 100%; max-width: 800px; }
    .card.shadow-lg { box-shadow: 0 4px 6px rgba(0,0,0,0.1) !important; }
    .banner {
        background: url('img/banner_login.jpeg') center/cover no-repeat;
        min-height: 550px; width: 100%;
    }
    @media (max-width: 800px) {
        .banner { display: none; }
        .card.shadow-lg { box-shadow: none !important; }
        html, body { background-color: #fff; }
    }
    .alert-container {
        min-height: 50px;
    }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="card o-hidden border-0 shadow-lg">
            <div class="card-body p-0">
                <div class="row no-gutters">
                    <div class="col-lg-6 d-none d-lg-block banner"></div>
                    <div class="col-lg-6 d-flex align-items-center">
                        <div class="w-100 p-5">
                            <div class="text-center">
                                <h1 class="h5 text-gray-900 mb-4">
                                  LOGIN<br>SISTEM PRESENSI SISWA<br>SD N GEMAWANG
                                </h1>
                            </div>
                            <div class="alert-container mb-4 <?= $err ? '' : 'd-none' ?>">
                                <?php if ($err): ?>
                                  <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
                                <?php endif; ?>
                            </div>
                            <form class="user" method="POST">
                                <div class="form-group">
                                    <input type="email" name="email"
                                      class="form-control form-control-user"
                                      placeholder="Email" required>
                                </div>
                                <div class="form-group">
                                    <input type="password" name="password"
                                      class="form-control form-control-user"
                                      placeholder="Password" required>
                                </div>
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox small">
                                        <input type="checkbox" class="custom-control-input"
                                          id="customCheck" name="ingat">
                                        <label class="custom-control-label"
                                          for="customCheck">Ingat saya</label>
                                    </div>
                                </div>
                                <button type="submit" name="login"
                                  class="btn btn-primary btn-user btn-block">
                                  Login
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.login-wrapper -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
</body>
</html>
