<?php
require_once 'koneksi.php';
require_once 'autentikasi.php';

// Variabel file default
$foto_default = 'img/default_image.jpg';

$id_user = $_SESSION['session_id'];
// Ambil data dari tabel pengguna
$queryUser = mysqli_query($conn, "SELECT * FROM pengguna WHERE id = '$id_user'");
$dataUser = mysqli_fetch_assoc($queryUser);

// Jika level Guru, ambil data tambahan dari tabel guru
$dataGuru = [];
if ($dataUser['level'] === 'Guru') {
    $queryGuru = mysqli_query($conn, "SELECT * FROM guru WHERE id_pengguna = '$id_user'");
    $dataGuru = mysqli_fetch_assoc($queryGuru);
}

$peringatan = [];
$kesalahan   = false;
$alert       = null;

// Proses penyimpanan data jika form disubmit (mode edit)
if (isset($_GET['edit']) && isset($_POST['simpan'])) {
    $nama  = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $peringatan[] = "Format email tidak valid!";
    }
    // Cek email yang sama
    $cekEmail = mysqli_query($conn, "SELECT * FROM pengguna WHERE email = '$email' AND id != '$id_user'");
    if (mysqli_num_rows($cekEmail) > 0) {
        $peringatan[] = "Email sudah digunakan oleh pengguna lain!";
    }

    // Jika level Guru, ambil field tambahan dari form guru
    if ($dataUser['level'] === 'Guru') {
        $nip           = mysqli_real_escape_string($conn, $_POST['nip']);
        $jenis_kelamin = mysqli_real_escape_string($conn, $_POST['jenis_kelamin']);
        $telepon       = mysqli_real_escape_string($conn, $_POST['telepon']);
        $alamat        = mysqli_real_escape_string($conn, $_POST['alamat']);

        // Tambahkan pengecekan duplikat NIP (kecuali data dirinya sendiri)
        $cekNip = mysqli_query($conn, "SELECT * FROM guru WHERE nip = '$nip' AND id_pengguna != '$id_user'");
        if (mysqli_num_rows($cekNip) > 0) {
            $peringatan[] = "NIP sudah terdaftar!";
        }
    }

    if (empty($peringatan)) {
        $folder = "data/foto/foto_profil_pengguna/";
        $foto_sql = "";
        
        // Proses upload foto jika ada file yang diupload (mode edit)
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['name'] != '') {
            $fotoBaru = $_FILES['foto_profil']['name'];
            $tmpFile  = $_FILES['foto_profil']['tmp_name'];
            
            // Validasi file gambar
            if (!getimagesize($tmpFile)) {
                $peringatan[] = "File yang diupload bukan gambar yang valid!";
            } else {
                $timestamp    = date("YmdHis");
                $ext          = strtolower(pathinfo($fotoBaru, PATHINFO_EXTENSION));
                $namaFileBaru = str_replace(' ', '_', $nama) . '_' . $timestamp . '.' . $ext;
                
                if (move_uploaded_file($tmpFile, $folder . $namaFileBaru)) {
                    // Jika sebelumnya ada foto yang tersimpan, hapus file tersebut
                    if (!empty($dataUser['foto_profil']) && file_exists($folder . $dataUser['foto_profil'])) {
                        unlink($folder . $dataUser['foto_profil']);
                    }
                    $foto_sql = ", foto_profil = '$namaFileBaru'";
                } else {
                    $peringatan[] = "Gagal mengupload file foto!";
                }
            }
        }

        // Update tabel pengguna
        if (empty($peringatan)) {
            $updateUserQuery = "UPDATE pengguna SET nama='$nama', email='$email' $foto_sql WHERE id='$id_user'";
            if (mysqli_query($conn, $updateUserQuery)) {
                // Jika pengguna adalah Guru, update juga tabel guru (tanpa mengubah status)
                if ($dataUser['level'] === 'Guru') {
                    $updateGuruQuery = "UPDATE guru SET nip='$nip', jenis_kelamin='$jenis_kelamin', telepon='$telepon', alamat='$alamat'
                                        WHERE id_pengguna='$id_user'";
                    mysqli_query($conn, $updateGuruQuery);
                }
                
                $_SESSION['session_nama']  = $nama;
                $_SESSION['session_email'] = $email;
                
                $_SESSION['alert'] = [
                    'title' => 'Berhasil',
                    'text'  => 'Profil berhasil diperbarui!',
                    'icon'  => 'success'
                ];
                header('Location: profil_saya.php');
                exit;
            } else {
                $kesalahan = true;
            }
        }
    }

    if (!empty($peringatan) || $kesalahan) {
        if ($kesalahan) {
            $alert = [
                'title' => 'Kesalahan',
                'text'  => 'Terjadi kesalahan! Silahkan coba lagi.',
                'icon'  => 'error'
            ];
        } else {
            $alert = [
                'title' => 'Peringatan',
                'text'  => implode("\n", $peringatan),
                'icon'  => 'warning'
            ];
        }
    }
}

// Include header, sidebar, topbar
$pageTitle = "Profil Saya";
include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<div class="container-fluid">
    
    <?php if (!isset($_GET['edit'])): ?>
    <!-- TAMPILAN VIEW PROFILE -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Profil Saya</h1>
        <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
    </div>

    <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Profil Saya</li>
        </ol>
    </nav>
  </div>

    <div class="card mb-4">
        <div class="card-body">
            <!-- Tampilan Foto Profil -->
            <div class="text-center mb-4">
                <div class="profile-pic-container">
                    <?php 
                    $fotoProfilPath = "data/foto/foto_profil_pengguna/" . $dataUser['foto_profil'];
                    if (!empty($dataUser['foto_profil']) && file_exists($fotoProfilPath)):
                    ?>
                        <img src="<?= $fotoProfilPath; ?>" alt="Foto Profil" class="profile-pic">
                    <?php else: ?>
                        <img src="<?= $foto_default; ?>" alt="Foto Profil Default" class="profile-pic">
                    <?php endif; ?>
                </div>
            </div>
            <!-- Tampilkan Data Profil -->
            <table id="profilTable" class="table table-bordered profil-table">
                <tr>
                    <th style="width: 150px;">Nama</th>
                    <td><?= htmlspecialchars($dataUser['nama']); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($dataUser['email']); ?></td>
                </tr>
                <tr>
                    <th>Level</th>
                    <td>
                        <?php 
                        echo ($dataUser['level'] === 'Admin') 
                            ? '<span class="badge badge-primary">Admin</span>' 
                            : '<span class="badge badge-secondary">Guru</span>';
                        ?>
                    </td>
                </tr>
                <?php if ($dataUser['level'] === 'Guru'): ?>
                <tr>
                    <th>NIP</th>
                    <td><?= htmlspecialchars($dataGuru['nip']); ?></td>
                </tr>
                <tr>
                    <th>Jenis Kelamin</th>
                    <td><?= ($dataGuru['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan'); ?></td>
                </tr>
                <tr>
                    <th>Telepon</th>
                    <td><?= htmlspecialchars($dataGuru['telepon']); ?></td>
                </tr>
                <tr>
                    <th>Alamat</th>
                    <td><?= htmlspecialchars($dataGuru['alamat']); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <?php 
                        echo (strtolower($dataGuru['status']) === 'aktif') 
                            ? '<span class="badge badge-success">Aktif</span>' 
                            : '<span class="badge badge-danger">Nonaktif</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Kelas yang Diampu</th>
                    <td>
                        <?php
                        $kelasDiampu = [];
                        $id_guru = $dataGuru['id']; // pastikan field id benar
                        $queryKelas = mysqli_query($conn, "SELECT * FROM kelas WHERE id_guru = '$id_guru'");
                        while ($row = mysqli_fetch_assoc($queryKelas)) {
                            $kelasDiampu[] = $row['nama_kelas'];
                        }
                        echo empty($kelasDiampu) ? '-' : htmlspecialchars(implode(', ', $kelasDiampu));
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <!-- Tombol Edit Profil -->
            <div class="mt-4">
                <a href="profil_saya.php?edit=1" class="btn btn-primary">Edit Profil</a>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- TAMPILAN EDIT PROFILE -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Profil</h1>
        <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
    </div>
    
    <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="profil_saya.php">Profil Saya</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Profil</li>
        </ol>
    </nav>
  </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <!-- Tampilan Foto Profil dengan Edit Overlay -->
            <div class="text-center mb-3">
                <div class="profile-pic-container">
                    <?php 
                    $fotoProfilPath = "data/foto/foto_profil_pengguna/" . $dataUser['foto_profil'];
                    if (!empty($dataUser['foto_profil']) && file_exists($fotoProfilPath)):
                    ?>
                        <img src="<?= $fotoProfilPath; ?>" alt="Foto Profil" class="profile-pic">
                    <?php else: ?>
                        <img src="<?= $foto_default; ?>" alt="Foto Profil Default" class="profile-pic">
                    <?php endif; ?>
                    <!-- Edit overlay -->
                    <label for="foto_profil" class="edit-overlay">
                        <i class="fas fa-pen"></i>
                    </label>
                </div>
            </div>
            
            <!-- Form Edit Profil -->
            <form action="profil_saya.php?edit=1" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama</label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($dataUser['nama']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($dataUser['email']); ?>" required>
                </div>
                <?php if ($dataUser['level'] === 'Guru'): ?>
                    <div class="form-group">
                        <label>NIP</label>
                        <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($dataGuru['nip']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label><br>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input class="custom-control-input" type="radio" name="jenis_kelamin" id="jkL" value="L" <?= ($dataGuru['jenis_kelamin'] === 'L' ? 'checked' : ''); ?>>
                            <label class="custom-control-label" for="jkL">Laki-laki</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input class="custom-control-input" type="radio" name="jenis_kelamin" id="jkP" value="P" <?= ($dataGuru['jenis_kelamin'] === 'P' ? 'checked' : ''); ?>>
                            <label class="custom-control-label" for="jkP">Perempuan</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Telepon</label>
                        <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($dataGuru['telepon']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="alamat" class="form-control" required><?= htmlspecialchars($dataGuru['alamat']); ?></textarea>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <!-- Input file disembunyikan -->
                    <input type="file" name="foto_profil" id="foto_profil" class="d-none" accept="image/*" onchange="document.querySelector('.profile-pic').src = window.URL.createObjectURL(this.files[0])">
                </div>
                <div class="form-group">
                    <button type="submit" name="simpan" class="btn btn-primary mr-1">Simpan Perubahan</button>
                    <a href="profil_saya.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

</div>

<?php include 'footer.php'; ?>

<script>
<?php if(isset($alert) && !empty($alert)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: "<?= $alert['title']; ?>",
            text: "<?= $alert['text']; ?>",
            icon: "<?= $alert['icon']; ?>",
            timer: 3000,
            showConfirmButton: false
        });
    });
<?php endif; ?>
</script>
