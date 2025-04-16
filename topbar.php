<?php
require 'koneksi.php';

// Ambil data dari session
$user_id   = $_SESSION['session_id'];
$nama_user = $_SESSION['session_nama'];
$email_user = $_SESSION['session_email'];
$level_user = $_SESSION['session_level'];

// Ambil foto profil dari database berdasarkan user_id
$stmt = $conn->prepare("SELECT foto_profil FROM pengguna WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($foto_profil);
$stmt->fetch();
$stmt->close();

// Cek apakah foto profil ada atau tidak
if (!empty($foto_profil) && file_exists("data/foto/foto_profil_pengguna/" . $foto_profil)) {
    $foto_profil_path = "data/foto/foto_profil_pengguna/" . $foto_profil;
} else {
    $foto_profil_path = "img/default_image.jpg"; // Gambar default jika tidak ada
}
?>

<!-- Content Wrapper -->
<div id="content-wrapper" class="d-flex flex-column">
    <!-- Main Content -->
    <div id="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
            <!-- Sidebar Toggle (Topbar) -->
            <button id="sidebarToggleTop" class="btn btn-link rounded-circle mr-3">
                <i class="fa fa-bars"></i>
            </button>

            <!-- Topbar Navbar -->
            <ul class="navbar-nav ml-auto">
                <!-- Nav Item - User Information -->
                <li class="nav-item dropdown no-arrow">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($nama_user); ?></span>
                        <img class="img-profile rounded-circle" src="<?= htmlspecialchars($foto_profil_path); ?>" alt="Foto Profil">
                    </a>

                    <!-- Dropdown - User Information -->
                    <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown" style="min-width: 250px;">
                        <!-- Tampilan Profil -->
                        <div class="dropdown-item-text text-center p-2">
                            <img class="img-profile rounded-circle" src="<?= htmlspecialchars($foto_profil_path); ?>" alt="Foto Profil" width="75" height="75">
                            <h6 class="mt-3 mb-0"><?= htmlspecialchars($nama_user); ?></h6>
                            <span class="text-muted"><?= htmlspecialchars($email_user); ?></span>
                            <br>
                            <span class="badge badge-<?= $level_user === 'admin' ? 'primary' : 'secondary'; ?> mt-2 p-1">
                                <?= ucfirst(htmlspecialchars($level_user)); ?>
                            </span>
                        </div>
                        <div class="dropdown-divider"></div>

                        <!-- Link Profil Saya -->
                        <a class="dropdown-item" href="profil_saya.php">
                            <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                            Profil Saya
                        </a>

                        <!-- Link Ganti Password -->
                        <a class="dropdown-item" href="ganti_password.php">
                            <i class="fas fa-key fa-sm fa-fw mr-2 text-gray-400"></i>
                            Ganti Password
                        </a>

                        <div class="dropdown-divider"></div>

                        <!-- Link Logout -->
                        <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                            Keluar
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- End of Topbar -->

        <!-- Logout Modal -->
        <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Konfirmasi Keluar</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                    <div class="modal-body">Apakah Anda yakin ingin keluar dari akun ini?</div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                        <a class="btn btn-danger" href="logout.php">Ya, Keluar</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- End of Logout Modal -->