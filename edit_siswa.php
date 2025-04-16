<?php 
$pageTitle = "Edit Data Siswa";
require_once 'koneksi.php';
require_once 'autentikasi.php';
hanyaAdmin();

// Hapus data pada tabel tmp_rfid_tambah setiap kali halaman di-refresh
mysqli_query($conn, "DELETE FROM tmp_rfid_tambah");

// Pastikan parameter id tersedia
if (!isset($_GET['id'])) {
    echo "ID tidak ditemukan.";
    exit;
}

$id = $_GET['id'];

// Ambil data siswa berdasarkan id
$sql = "SELECT * FROM `siswa` WHERE id = '$id'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
} else {
    echo "Data siswa tidak ditemukan.";
    exit;
}

// Simpan nilai lama untuk perbandingan
$old_nis         = $row['nis'];
$old_nama        = $row['nama'];
$old_no_rfid     = $row['no_rfid'];
$old_folder_name = $row['dataset_wajah']; // Format lama: nis_nama_rfid
$old_foto_name   = $row['foto_siswa'];      // Format lama: nis_nama_rfid.ext

// Proses update apabila form disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $no_rfid       = $_POST['no_rfid'];
    $nis           = $_POST['nis'];
    $nama          = $_POST['nama'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $kelas         = $_POST['id_kelas'];
    $alamat        = $_POST['alamat'];
    $token         = $_POST['token'];
    $id_chat       = $_POST['id_chat'];
    $status        = $_POST['status'];
    $foto_siswa    = $row['foto_siswa']; // Jika tidak ada perubahan file foto

    // Format nama folder dan file foto baru
    $new_folder_name = $nis . "_" . $nama . "_" . $no_rfid;
    $ext = pathinfo($old_foto_name, PATHINFO_EXTENSION);
    $new_foto_name = $nis . "_" . $nama . "_" . $no_rfid . "." . $ext;

    // Cek duplikasi nomor RFID dan NIS (kecuali data yang sedang diedit)
    $sqlCheckRfid = "SELECT id FROM `siswa` WHERE no_rfid = '$no_rfid' AND id != '$id'";
    $sqlCheckNis  = "SELECT id FROM `siswa` WHERE nis = '$nis' AND id != '$id'";
    $resultRfid   = $conn->query($sqlCheckRfid);
    $resultNis    = $conn->query($sqlCheckNis);

    if ($resultRfid->num_rows > 0 && $resultNis->num_rows > 0) {
        echo "<script>alert('Nomor RFID dan NIS sudah terdaftar! Silahkan gunakan yang berbeda!');</script>";
    } elseif ($resultRfid->num_rows > 0) {
        echo "<script>alert('Nomor RFID sudah terdaftar! Silahkan gunakan kartu RFID yang berbeda!');</script>";
    } elseif ($resultNis->num_rows > 0) {
        echo "<script>alert('NIS sudah terdaftar! Silahkan gunakan NIS yang berbeda!');</script>";
    } else {
        // Proses upload foto apabila ada file baru
        if (isset($_FILES['foto_siswa']) && $_FILES['foto_siswa']['error'] == 0) {
            // Ambil ekstensi dari file upload
            $ext = pathinfo($_FILES['foto_siswa']['name'], PATHINFO_EXTENSION);
            // Buat nama file baru dengan format: nis_nama_no_rfid.ext
            $new_foto_name = $nis . "_" . $nama . "_" . $no_rfid . "." . $ext;
            
            // Update variabel foto_siswa agar nanti diupdate ke database dengan nama baru
            $foto_siswa = $new_foto_name;

            $targetDir  = "data/foto/foto_siswa/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            // Tentukan target file menggunakan nama file baru
            $targetFile = $targetDir . $new_foto_name;
            $imageFileType = strtolower($ext);

            // Validasi apakah file yang diupload adalah gambar
            $check = getimagesize($_FILES['foto_siswa']['tmp_name']);
            if ($check === false) {
                echo "<script>alert('File yang diupload bukan gambar!');</script>";
                echo "<script>window.history.back();</script>";
                exit();
            }
            // Validasi ukuran file (maksimal 5MB)
            if ($_FILES['foto_siswa']['size'] > 5000000) {
                echo "<script>alert('Ukuran file terlalu besar! Maksimal 5MB.');</script>";
                echo "<script>window.history.back();</script>";
                exit();
            }
            // Pindahkan file yang diupload dengan nama baru ke folder target
            if (!move_uploaded_file($_FILES['foto_siswa']['tmp_name'], $targetFile)) {
                echo "<script>alert('Maaf, terjadi kesalahan saat mengupload file.');</script>";
                echo "<script>window.history.back();</script>";
                exit();
            }
        }

        // Jika terjadi perubahan pada nama folder atau file foto, lakukan rename
        if ($old_folder_name !== $new_folder_name || $old_foto_name !== $new_foto_name) {
            $old_folder_path = "data/dataset/" . $old_folder_name;
            $new_folder_path = "data/dataset/" . $new_folder_name;
            $foto_path = "data/foto/foto_siswa/";
            if (is_dir($old_folder_path)) {
                rename($old_folder_path, $new_folder_path);
            }
            $old_foto_path = $foto_path . $old_foto_name;
            $new_foto_path = $foto_path . $new_foto_name;
            if (file_exists($old_foto_path)) {
                rename($old_foto_path, $new_foto_path);
            }
        }

        // Update data siswa di database
        $sql_update = "UPDATE `siswa` SET nis='$nis', nama='$nama', jenis_kelamin='$jenis_kelamin', id_kelas='$kelas', 
                        alamat='$alamat', token='$token', id_chat='$id_chat', no_rfid='$no_rfid', status='$status',
                        dataset_wajah='$new_folder_name', foto_siswa='$new_foto_name' WHERE id='$id'";
        
        if ($conn->query($sql_update) === TRUE) {
            // Panggil endpoint re-embedding apabila ada perubahan pada NIS, nama, atau nomor RFID
            if ($old_nis !== $nis || $old_nama !== $nama || $old_no_rfid !== $no_rfid) {
                $ch = curl_init("http://localhost:5000/reembed_faces");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);         
                $response = curl_exec($ch);
                curl_close($ch);
            }
            header("Location: siswa.php");
            exit();
        } else {
            echo "Error: " . $conn->error;
        }        
    }
}

// Ambil data kelas untuk dropdown
$queryKelas  = "SELECT * FROM kelas";
$resultKelas = mysqli_query($conn, $queryKelas);
$kelasOptions = [];
while ($kelasRow = mysqli_fetch_assoc($resultKelas)) {
    $kelasOptions[] = $kelasRow;
}
$conn->close();

include 'header.php';
include 'sidebar.php';
include 'topbar.php';
?>

<!-- Begin Page Content -->
<div class="container-fluid">
  <!-- Page Heading -->
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Data Siswa</h1>
    <div id="dateTimeDisplay" class="text-muted text-left text-sm-right mt-2 mt-sm-0 small"></div>
  </div>

  <div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="siswa.php">Data Siswa</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Data Siswa</li>
        </ol>
    </nav>
  </div>

  <!-- Kotak Cek RFID -->
  <div class="card border-primary mb-4" style="background-color: rgba(0, 122, 204, 0.1);">
    <div class="card-body p-4">
        <div class="text-center">
        <label for="rfid" class="font-weight-bold">Cek RFID</label>
        <div class="row justify-content-center">
            <div class="col-12 col-md-6">
            <input id="rfid" class="form-control text-center" style="background-color: #f8f9fa;" value="Tempelkan Kartu" disabled>
            <input type="hidden" id="no_rfid_cek" name="no_rfid" value="">
            </div>
        </div>
        </div>
    </div>
  </div>

  <!-- Form Edit Data Siswa -->
  <div class="card mb-4">
    <div class="card-body">
      <form method="POST" action="" enctype="multipart/form-data">
          <div class="form-group">
              <label for="no_rfid">No. RFID</label>
              <input type="text" class="form-control" name="no_rfid" id="no_rfid" value="<?php echo isset($row['no_rfid']) ? $row['no_rfid'] : ''; ?>" required>
          </div>
          <div class="form-group">
              <label for="nis">NIS</label>
              <input type="text" class="form-control" name="nis" id="nis" value="<?php echo $row['nis']; ?>" required>
          </div>
          <div class="form-group">
              <label for="nama">Nama</label>
              <input type="text" class="form-control" name="nama" id="nama" value="<?php echo $row['nama']; ?>" required>
          </div>
          <div class="form-group">
            <label>Jenis Kelamin</label><br>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="jenis_kelamin_L" name="jenis_kelamin" value="L" class="custom-control-input" <?php if ($row['jenis_kelamin'] == 'L') echo 'checked'; ?>>
                <label class="custom-control-label" for="jenis_kelamin_L">Laki-laki</label>
            </div>
            <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="jenis_kelamin_P" name="jenis_kelamin" value="P" class="custom-control-input" <?php if ($row['jenis_kelamin'] == 'P') echo 'checked'; ?>>
                <label class="custom-control-label" for="jenis_kelamin_P">Perempuan</label>
            </div>
          </div>
          <div class="form-group">
              <label for="id_kelas">Kelas</label>
              <select id="id_kelas" name="id_kelas" class="custom-select" required>
                  <option value="">Pilih Kelas</option>
                  <?php foreach ($kelasOptions as $kelasRow): ?>
                      <option value="<?php echo $kelasRow['id']; ?>" <?php echo ($row['id_kelas'] == $kelasRow['id']) ? 'selected' : ''; ?>>
                          <?php echo $kelasRow['nama_kelas']; ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="form-group">
              <label for="alamat">Alamat</label>
              <textarea class="form-control" name="alamat" id="alamat" rows="4" required><?php echo $row['alamat']; ?></textarea>
          </div>
          <div class="form-group">
              <label for="token">Token</label>
              <input type="text" class="form-control" name="token" id="token" value="<?php echo $row['token']; ?>">
          </div>
          <div class="form-group">
              <label for="id_chat">ID Chat</label>
              <input type="text" class="form-control" name="id_chat" id="id_chat" value="<?php echo $row['id_chat']; ?>">
          </div>
          <div class="form-group">
            <label for="foto_siswa">Foto Siswa</label>
            <div class="custom-file">
                <input type="file" class="custom-file-input" name="foto_siswa" id="foto_siswa" accept="image/*">
                <label class="custom-file-label" for="foto_siswa">Pilih File</label>
            </div>
            <small class="form-text text-danger mt-1">* Maksimal ukuran foto 5MB.</small>
          </div>
          <div class="form-group">
            <label for="status">Status</label>
                <select id="status" name="status" class="custom-select mb-2" required>
                    <option value="Aktif" <?php echo ($row['status'] == 'Aktif') ? 'selected' : ''; ?>>Aktif</option>
                    <option value="Nonaktif" <?php echo ($row['status'] == 'Nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Simpan Perubahan</button>
      </form>
    </div>
  </div>
</div>
<!-- End of Container Fluid -->

<!-- jQuery (versi 3.5.1) untuk Bootstrap v4 -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script>
  $(document).ready(function() {
    $('.custom-file-input').on('change', function() {
      var fileName = $(this).val().split('\\').pop();
      $(this).next('.custom-file-label').html(fileName);
    });
  });
</script>

<!-- Fungsi AJAX untuk update RFID -->
<script>
$(document).ready(function () {
    fetchData();
});

function fetchData() {
    $.ajax({
        url: "./get_rfid.php",
        type: "GET",
        dataType: "json",
        success: function (data) {
            if (data.id && data.id !== "") {
                $("#rfid").val(data.id);
                $("#no_rfid_cek").val(data.id);
            } else {
                $("#rfid").val("Tempelkan Kartu");
                $("#no_rfid_cek").val("");
            }
            setTimeout(fetchData, 1000);
        },
        error: function (xhr, status, error) {
            console.log("Terjadi kesalahan: " + error);
            setTimeout(fetchData, 1000);
        }
    });
}
</script>

<?php
include 'footer.php';
?>
